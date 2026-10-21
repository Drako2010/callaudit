<?php
/**
 * ============================================================================
 * ARCHIVO: models/AuditForm.php
 * PROYECTO: CallAudit
 * ============================================================================
 *
 * RESPONSABILIDAD
 * --------------
 * Contiene las operaciones de base de datos del módulo de formularios:
 *
 * - Listar formularios pertenecientes a una empresa.
 * - Crear un formulario y su primera versión en estado DRAFT.
 *
 * TABLAS UTILIZADAS
 * -----------------
 * - tenants
 * - users
 * - audit_forms
 * - audit_form_versions
 *
 * REGLAS DE DISEÑO
 * ----------------
 * 1. Todos los formularios pertenecen a una empresa (tenant).
 * 2. El formulario se crea con una versión inicial en borrador.
 * 3. Las preguntas se administrarán posteriormente en esa versión.
 * 4. Los datos recibidos se validan antes de guardarlos.
 * 5. Las operaciones relacionadas se ejecutan en una transacción.
 * 6. Los errores técnicos se registran en el log del servidor.
 *
 * SEGURIDAD
 * ---------
 * Este modelo no sustituye al middleware de autorización.
 * El controlador debe verificar el permiso del usuario y el tenant
 * antes de llamar a los métodos de este archivo.
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';

class AuditForm
{
    /**
     * Conexión PDO.
     *
     * Puede ser null si no se pudo establecer la conexión.
     */
    private ?PDO $db;

    /**
     * Constructor.
     *
     * Obtiene la conexión utilizando la clase Database existente
     * en CallAudit. No se crea una conexión independiente.
     */
    public function __construct()
    {
        $database = new Database();
        $this->db = $database->connect();
    }

    /**
     * ------------------------------------------------------------------------
     * MÉTODO: listar()
     * ------------------------------------------------------------------------
     * Obtiene los formularios de una empresa y algunos datos de sus versiones.
     *
     * PARÁMETRO:
     * @param int $tenantId ID de la empresa autorizada.
     *
     * RETORNO:
     * @return array Lista de formularios. Si ocurre un error, devuelve [].
     *
     * NOTAS:
     * - La consulta siempre filtra por tenant_id.
     * - Se cuentan las versiones existentes de cada formulario.
     * - Se muestra el número de la versión más reciente.
     * - Se usa una subconsulta para evitar duplicar filas del formulario.
     */
    public function listar(int $tenantId): array
    {
        // Sin conexión o con un identificador inválido no se consulta.
        if ($this->db === null || $tenantId <= 0) {
            return [];
        }

        $sql = "
            SELECT
                f.id,
                f.tenant_id,
                f.name,
                f.description,
                f.status,
                f.created_by,
                f.created_at,
                f.updated_at,

                (
                    SELECT COUNT(*)
                    FROM audit_form_versions v
                    WHERE v.form_id = f.id
                      AND v.tenant_id = f.tenant_id
                ) AS total_versions,

                (
                    SELECT MAX(v.version_number)
                    FROM audit_form_versions v
                    WHERE v.form_id = f.id
                      AND v.tenant_id = f.tenant_id
                ) AS latest_version

            FROM audit_forms f
            WHERE f.tenant_id = :tenant_id
            ORDER BY f.id DESC
        ";

        try {
            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                ':tenant_id' => $tenantId
            ]);

            return $stmt->fetchAll();
        } catch (Throwable $e) {
            // Registrar el detalle técnico sin mostrarlo al usuario.
            error_log(
                '[CallAudit][AuditForm::listar] '
                . get_class($e) . ': ' . $e->getMessage()
            );

            return [];
        }
    }

    /**
     * ------------------------------------------------------------------------
     * MÉTODO: crear()
     * ------------------------------------------------------------------------
     * Crea un formulario y su versión inicial.
     *
     * PARÁMETROS:
     * @param int         $tenantId    Empresa propietaria.
     * @param string      $name        Nombre del formulario.
     * @param string|null $description Descripción opcional.
     * @param int         $createdBy   Usuario que realiza la creación.
     *
     * RETORNO:
     * @return array Resultado con las claves:
     *               success: true o false.
     *               message: mensaje para mostrar en pantalla.
     *               id: identificador del formulario cuando se crea.
     *
     * TRANSACCIÓN:
     * Se insertan el formulario y su versión inicial en una sola operación.
     * Si cualquiera de las inserciones falla, se revierten ambas.
     */
    public function crear(
        int $tenantId,
        string $name,
        ?string $description,
        int $createdBy
    ): array {
        // Validaciones iniciales de los parámetros.
        $name = trim($name);

        $description = $description !== null
            ? trim($description)
            : null;

        if ($description === '') {
            $description = null;
        }

        if ($this->db === null) {
            return [
                'success' => false,
                'message' => 'No se pudo conectar con la base de datos.'
            ];
        }

        if ($tenantId <= 0) {
            return [
                'success' => false,
                'message' => 'La empresa seleccionada no es válida.'
            ];
        }

        if ($createdBy <= 0) {
            return [
                'success' => false,
                'message' => 'El usuario que realiza la operación no es válido.'
            ];
        }

        if ($name === '') {
            return [
                'success' => false,
                'message' => 'El nombre del formulario es obligatorio.'
            ];
        }

        // El nombre debe respetar el tamaño de audit_forms.name.
        if (mb_strlen($name, 'UTF-8') > 150) {
            return [
                'success' => false,
                'message' => 'El nombre no puede superar los 150 caracteres.'
            ];
        }

        try {
            /*
             * INICIO DE TRANSACCIÓN
             *
             * Evita dejar un formulario creado sin su versión inicial.
             */
            $this->db->beginTransaction();

            /*
             * VALIDAR EMPRESA
             *
             * No basta con recibir un tenant_id desde el controlador.
             * Se verifica que la empresa exista y se encuentre activa.
             */
            $sqlTenant = "
                SELECT id
                FROM tenants
                WHERE id = :tenant_id
                  AND status = 'ACTIVE'
                LIMIT 1
            ";

            $stmtTenant = $this->db->prepare($sqlTenant);
            $stmtTenant->execute([
                ':tenant_id' => $tenantId
            ]);

            if ($stmtTenant->fetchColumn() === false) {
                $this->db->rollBack();

                return [
                    'success' => false,
                    'message' => 'La empresa no existe o no está activa.'
                ];
            }

            /*
             * VALIDAR USUARIO
             *
             * Se permite un usuario global (tenant_id NULL) o un usuario
             * perteneciente a la misma empresa.
             *
             * La autorización real para crear el formulario se valida
             * también en el controlador mediante audits.create.
             */
            $sqlUser = "
                SELECT id
                FROM users
                WHERE id = :user_id
                  AND status = 'ACTIVE'
                  AND (tenant_id = :tenant_id OR tenant_id IS NULL)
                LIMIT 1
            ";

            $stmtUser = $this->db->prepare($sqlUser);
            $stmtUser->execute([
                ':user_id' => $createdBy,
                ':tenant_id' => $tenantId
            ]);

            if ($stmtUser->fetchColumn() === false) {
                $this->db->rollBack();

                return [
                    'success' => false,
                    'message' => 'El usuario no está activo o no pertenece al ámbito autorizado.'
                ];
            }

            /*
             * INSERTAR FORMULARIO
             *
             * El formulario se crea como ACTIVE.
             * La versión inicial, en cambio, se crea como DRAFT.
             */
            $sqlForm = "
                INSERT INTO audit_forms (
                    tenant_id,
                    name,
                    description,
                    status,
                    created_by
                ) VALUES (
                    :tenant_id,
                    :name,
                    :description,
                    'ACTIVE',
                    :created_by
                )
            ";

            $stmtForm = $this->db->prepare($sqlForm);
            $stmtForm->execute([
                ':tenant_id' => $tenantId,
                ':name' => $name,
                ':description' => $description,
                ':created_by' => $createdBy
            ]);

            // Recuperar el ID generado por MariaDB.
            $formId = (int) $this->db->lastInsertId();

            if ($formId <= 0) {
                throw new RuntimeException(
                    'No se obtuvo el identificador del formulario creado.'
                );
            }

            /*
             * INSERTAR VERSIÓN INICIAL
             *
             * version_number = 1:
             *     Primera versión del formulario.
             *
             * status = DRAFT:
             *     Se puede configurar antes de publicarla.
             *
             * na_policy = EXCLUDE:
             *     Por defecto, las respuestas "No aplica" se excluyen
             *     del cálculo de la puntuación.
             */
            $sqlVersion = "
                INSERT INTO audit_form_versions (
                    tenant_id,
                    form_id,
                    version_number,
                    status,
                    na_policy,
                    created_by
                ) VALUES (
                    :tenant_id,
                    :form_id,
                    1,
                    'DRAFT',
                    'EXCLUDE',
                    :created_by
                )
            ";

            $stmtVersion = $this->db->prepare($sqlVersion);
            $stmtVersion->execute([
                ':tenant_id' => $tenantId,
                ':form_id' => $formId,
                ':created_by' => $createdBy
            ]);

            /*
             * CONFIRMAR TRANSACCIÓN
             *
             * Solo llegamos aquí si se guardaron correctamente
             * el formulario y su primera versión.
             */
            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Formulario creado correctamente en estado borrador.',
                'id' => $formId
            ];
        } catch (Throwable $e) {
            /*
             * REVERSIÓN SEGURA
             *
             * Si la transacción sigue abierta, se revierten los cambios.
             */
            if ($this->db->inTransaction()) {
                try {
                    $this->db->rollBack();
                } catch (Throwable $rollbackError) {
                    error_log(
                        '[CallAudit][AuditForm::crear][ROLLBACK] '
                        . get_class($rollbackError) . ': '
                        . $rollbackError->getMessage()
                    );
                }
            }

            // Guardar el error técnico para facilitar la depuración.
            error_log(
                '[CallAudit][AuditForm::crear] '
                . get_class($e) . ': ' . $e->getMessage()
                . ' en ' . $e->getFile()
                . ':' . $e->getLine()
            );

            // No revelar SQL, rutas ni detalles internos al usuario.
            return [
                'success' => false,
                'message' => 'No se pudo crear el formulario. Revisa el registro de errores del servidor.'
            ];
        }
    }
}
