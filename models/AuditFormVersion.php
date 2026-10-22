<?php
/**
 * ============================================================================
 * ARCHIVO: models/AuditFormVersion.php
 * PROYECTO: CallAudit
 * RESPONSABILIDAD:
 *   - Listar versiones de formularios de una empresa.
 *   - Crear una nueva versión copiando preguntas de otra versión.
 *   - Publicar borradores después de validar su contenido.
 *
 * SEGURIDAD:
 *   El controlador verifica permisos y ámbito de empresa.
 *   Este modelo vuelve a validar tenant_id en cada operación.
 *
 * INMUTABILIDAD:
 *   Una versión publicada o retirada no se modifica desde este módulo.
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';

class AuditFormVersion
{
    private ?PDO $db;

    public function __construct()
    {
        // CallAudit utiliza Database como clase no estática.
        $database = new Database();
        $this->db = $database->connect();
    }

    /**
     * Lista las versiones del tenant autorizado.
     */
    public function listar(int $tenantId): array
    {
        if (!$this->db || $tenantId <= 0) {
            return [];
        }

        try {
            $sql = "
                SELECT
                    v.id,
                    v.tenant_id,
                    v.form_id,
                    v.version_number,
                    v.status,
                    v.passing_score,
                    v.na_policy,
                    v.published_by,
                    v.published_at,
                    v.created_at,
                    f.name AS form_name,
                    COUNT(
                        CASE WHEN q.active = 1 THEN 1 END
                    ) AS active_questions,
                    COUNT(q.id) AS total_questions
                FROM audit_form_versions v
                INNER JOIN audit_forms f
                    ON f.id = v.form_id
                   AND f.tenant_id = v.tenant_id
                LEFT JOIN audit_form_questions q
                    ON q.form_version_id = v.id
                   AND q.tenant_id = v.tenant_id
                WHERE v.tenant_id = :tenant_id
                GROUP BY
                    v.id, v.tenant_id, v.form_id,
                    v.version_number, v.status,
                    v.passing_score, v.na_policy,
                    v.published_by, v.published_at,
                    v.created_at, f.name
                ORDER BY f.name ASC, v.version_number DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['tenant_id' => $tenantId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log(
                '[CallAudit][AuditFormVersion::listar] '
                . $e->getMessage()
            );
            return [];
        }
    }

    /**
     * Crea un borrador nuevo a partir de una versión existente.
     *
     * Copia todas las preguntas, incluidas las desactivadas, para
     * conservar su configuración. Los IDs de las preguntas son nuevos.
     */
    public function crearBorrador(
        int $tenantId,
        int $versionOrigenId,
        int $usuarioId
    ): array {
        if (!$this->db || $tenantId <= 0 ||
            $versionOrigenId <= 0 || $usuarioId <= 0) {
            return [
                'success' => false,
                'message' => 'Los datos recibidos no son válidos.'
            ];
        }

        try {
            $this->db->beginTransaction();

            // Bloquear la versión de origen y confirmar su empresa.
            $sqlOrigen = "
                SELECT
                    v.id,
                    v.form_id,
                    v.na_policy,
                    v.passing_score,
                    f.status AS form_status
                FROM audit_form_versions v
                INNER JOIN audit_forms f
                    ON f.id = v.form_id
                   AND f.tenant_id = v.tenant_id
                WHERE v.id = :version_id
                  AND v.tenant_id = :tenant_id
                FOR UPDATE
            ";

            $stmt = $this->db->prepare($sqlOrigen);
            $stmt->execute([
                'version_id' => $versionOrigenId,
                'tenant_id' => $tenantId
            ]);

            $origen = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$origen || $origen['form_status'] !== 'ACTIVE') {
                $this->db->rollBack();

                return [
                    'success' => false,
                    'message' => 'El formulario no existe o está inactivo.'
                ];
            }

            // Verificar que el usuario esté activo y autorizado para
            // registrar la operación dentro de esta empresa.
            $sqlUsuario = "
                SELECT id
                FROM users
                WHERE id = :usuario_id
                  AND status = 'ACTIVE'
                  AND (tenant_id = :tenant_id OR tenant_id IS NULL)
                LIMIT 1
            ";

            $stmt = $this->db->prepare($sqlUsuario);
            $stmt->execute([
                'usuario_id' => $usuarioId,
                'tenant_id' => $tenantId
            ]);

            if (!$stmt->fetchColumn()) {
                $this->db->rollBack();

                return [
                    'success' => false,
                    'message' => 'El usuario no está activo o no pertenece al ámbito autorizado.'
                ];
            }

            // Calcular la siguiente versión del mismo formulario.
            $sqlNumero = "
                SELECT COALESCE(MAX(version_number), 0) + 1
                FROM audit_form_versions
                WHERE tenant_id = :tenant_id
                  AND form_id = :form_id
            ";

            $stmt = $this->db->prepare($sqlNumero);
            $stmt->execute([
                'tenant_id' => $tenantId,
                'form_id' => (int) $origen['form_id']
            ]);

            $numero = (int) $stmt->fetchColumn();

            // Crear el borrador sin alterar la versión de origen.
            $sqlNueva = "
                INSERT INTO audit_form_versions (
                    tenant_id,
                    form_id,
                    version_number,
                    status,
                    passing_score,
                    na_policy,
                    created_by
                ) VALUES (
                    :tenant_id,
                    :form_id,
                    :version_number,
                    'DRAFT',
                    :passing_score,
                    :na_policy,
                    :created_by
                )
            ";

            $stmt = $this->db->prepare($sqlNueva);
            $stmt->execute([
                'tenant_id' => $tenantId,
                'form_id' => (int) $origen['form_id'],
                'version_number' => $numero,
                'passing_score' => $origen['passing_score'],
                'na_policy' => $origen['na_policy'],
                'created_by' => $usuarioId
            ]);

            $nuevaId = (int) $this->db->lastInsertId();

            // Copiar la configuración completa de las preguntas.
            // No se copian los IDs ni las fechas originales.
            $sqlCopiar = "
                INSERT INTO audit_form_questions (
                    tenant_id,
                    form_version_id,
                    position,
                    question_text,
                    help_text,
                    answer_type,
                    options_json,
                    weight,
                    is_critical,
                    required_answer,
                    allow_na,
                    na_score,
                    active
                )
                SELECT
                    tenant_id,
                    :nueva_version_id,
                    position,
                    question_text,
                    help_text,
                    answer_type,
                    options_json,
                    weight,
                    is_critical,
                    required_answer,
                    allow_na,
                    na_score,
                    active
                FROM audit_form_questions
                WHERE tenant_id = :tenant_id
                  AND form_version_id = :origen_id
            ";

            $stmt = $this->db->prepare($sqlCopiar);
            $stmt->execute([
                'nueva_version_id' => $nuevaId,
                'tenant_id' => $tenantId,
                'origen_id' => $versionOrigenId
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => "Se creó la versión {$numero} como borrador.",
                'id' => $nuevaId
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log(
                '[CallAudit][AuditFormVersion::crearBorrador] '
                . $e->getMessage()
            );

            return [
                'success' => false,
                'message' => 'No se pudo crear la versión. Revisa el log de PHP.'
            ];
        }
    }

    /**
     * Publica un borrador.
     *
     * Reglas:
     * - Solo se publica una versión DRAFT.
     * - Debe existir al menos una pregunta activa.
     * - El umbral de aprobación, si se informa, debe estar entre 0 y 100.
     * - La política N/A debe coincidir con los valores permitidos por SQL.
     *
     * Las versiones publicadas anteriormente no se sobrescriben ni retiran.
     */
    public function publicar(
        int $tenantId,
        int $versionId,
        int $usuarioId,
        ?float $passingScore,
        string $naPolicy
    ): array {
        if (!$this->db || $tenantId <= 0 ||
            $versionId <= 0 || $usuarioId <= 0) {
            return [
                'success' => false,
                'message' => 'Los datos recibidos no son válidos.'
            ];
        }

        if (
            $passingScore !== null &&
            ($passingScore < 0 || $passingScore > 100)
        ) {
            return [
                'success' => false,
                'message' => 'El puntaje de aprobación debe estar entre 0 y 100.'
            ];
        }

        if (!in_array($naPolicy, ['EXCLUDE', 'ZERO', 'FULL'], true)) {
            return [
                'success' => false,
                'message' => 'La política para respuestas N/A no es válida.'
            ];
        }

        try {
            $this->db->beginTransaction();

            // Confirmar que la versión sea de esta empresa y siga en borrador.
            $sqlVersion = "
                SELECT v.id, f.status AS form_status
                FROM audit_form_versions v
                INNER JOIN audit_forms f
                    ON f.id = v.form_id
                   AND f.tenant_id = v.tenant_id
                WHERE v.id = :version_id
                  AND v.tenant_id = :tenant_id
                  AND v.status = 'DRAFT'
                FOR UPDATE
            ";

            $stmt = $this->db->prepare($sqlVersion);
            $stmt->execute([
                'version_id' => $versionId,
                'tenant_id' => $tenantId
            ]);

            $version = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$version || $version['form_status'] !== 'ACTIVE') {
                $this->db->rollBack();

                return [
                    'success' => false,
                    'message' => 'La versión no existe, no es borrador o el formulario está inactivo.'
                ];
            }

            // Validar que haya preguntas activas y comprobar pesos.
            $sqlPreguntas = "
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN weight < 0 THEN 1 ELSE 0 END)
                        AS pesos_invalidos
                FROM audit_form_questions
                WHERE tenant_id = :tenant_id
                  AND form_version_id = :version_id
                  AND active = 1
            ";

            $stmt = $this->db->prepare($sqlPreguntas);
            $stmt->execute([
                'tenant_id' => $tenantId,
                'version_id' => $versionId
            ]);

            $validacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if ((int) $validacion['total'] < 1) {
                $this->db->rollBack();

                return [
                    'success' => false,
                    'message' => 'No se puede publicar: agrega al menos una pregunta activa.'
                ];
            }

            if ((int) $validacion['pesos_invalidos'] > 0) {
                $this->db->rollBack();

                return [
                    'success' => false,
                    'message' => 'No se puede publicar: existen pesos negativos.'
                ];
            }

            // Registrar publicación y configuración final de la versión.
            $sqlPublicar = "
                UPDATE audit_form_versions
                SET status = 'PUBLISHED',
                    passing_score = :passing_score,
                    na_policy = :na_policy,
                    published_by = :usuario_id,
                    published_at = NOW()
                WHERE id = :version_id
                  AND tenant_id = :tenant_id
                  AND status = 'DRAFT'
            ";

            $stmt = $this->db->prepare($sqlPublicar);
            $stmt->execute([
                'passing_score' => $passingScore,
                'na_policy' => $naPolicy,
                'usuario_id' => $usuarioId,
                'version_id' => $versionId,
                'tenant_id' => $tenantId
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'No se pudo confirmar la publicación del borrador.'
                );
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Versión publicada correctamente. Ya no debe editarse.'
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log(
                '[CallAudit][AuditFormVersion::publicar] '
                . $e->getMessage()
            );

            return [
                'success' => false,
                'message' => 'No se pudo publicar la versión. Revisa el log de PHP.'
            ];
        }
    }
}
