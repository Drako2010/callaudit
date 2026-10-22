<?php
/*
|--------------------------------------------------------------------------
| Archivo: models/AuditFormQuestion.php
|--------------------------------------------------------------------------
| Responsabilidad:
|   Gestionar las preguntas de las versiones de formularios de auditoría.
|
| Funcionalidades:
|   - Listar versiones de formularios de una empresa.
|   - Listar preguntas de una versión concreta.
|   - Crear preguntas con ponderación y reglas de respuesta.
|   - Activar o desactivar preguntas de una versión en borrador.
|
| Seguridad:
|   - Todas las operaciones reciben el tenant_id autorizado.
|   - No se aceptan preguntas para versiones de otra empresa.
|   - Las versiones PUBLISHED y RETIRED no se pueden modificar.
|   - Los errores técnicos se registran en el log, sin exponer detalles
|     internos de la base de datos al usuario.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';

class AuditFormQuestion
{
    /** Conexión PDO compartida por las operaciones del modelo. */
    private ?PDO $db;

    /**
     * Constructor del modelo.
     *
     * Crea una instancia de Database y obtiene la conexión PDO,
     * siguiendo el patrón utilizado por los modelos existentes de CallAudit.
     */
    public function __construct()
    {
        $database = new Database();
        $this->db = $database->connect();
    }

    /**
     * Devuelve las versiones de formularios pertenecientes a una empresa.
     *
     * Se muestran también las versiones publicadas y retiradas para que
     * el usuario pueda consultarlas; las operaciones de escritura solo
     * se permiten sobre versiones DRAFT.
     */
    public function listarVersiones(int $tenantId): array
    {
        if (!$this->db || $tenantId <= 0) {
            return [];
        }

        try {
            $sql = "
                SELECT
                    v.id AS version_id,
                    v.form_id,
                    v.version_number,
                    v.status AS version_status,
                    v.na_policy,
                    f.name AS form_name,
                    COUNT(q.id) AS total_preguntas
                FROM audit_form_versions v
                INNER JOIN audit_forms f
                    ON f.id = v.form_id
                   AND f.tenant_id = v.tenant_id
                LEFT JOIN audit_form_questions q
                    ON q.form_version_id = v.id
                   AND q.tenant_id = v.tenant_id
                WHERE v.tenant_id = :tenant_id
                GROUP BY
                    v.id,
                    v.form_id,
                    v.version_number,
                    v.status,
                    v.na_policy,
                    f.name
                ORDER BY f.name ASC, v.version_number DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['tenant_id' => $tenantId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('AuditFormQuestion::listarVersiones: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Lista las preguntas de una versión específica.
     *
     * La combinación de tenant_id y form_version_id evita que una
     * petición manipulada consulte preguntas de otra empresa.
     */
    public function listarPreguntas(
        int $tenantId,
        int $versionId
    ): array {
        if (!$this->db || $tenantId <= 0 || $versionId <= 0) {
            return [];
        }

        try {
            $sql = "
                SELECT
                    id,
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
                    active,
                    created_at
                FROM audit_form_questions
                WHERE tenant_id = :tenant_id
                  AND form_version_id = :version_id
                ORDER BY position ASC, id ASC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'tenant_id' => $tenantId,
                'version_id' => $versionId
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('AuditFormQuestion::listarPreguntas: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Crea una pregunta en una versión que todavía está en borrador.
     *
     * $datos admite:
     *   question_text, help_text, answer_type, options,
     *   weight, is_critical, required_answer, allow_na, na_score.
     *
     * Las opciones se reciben como un arreglo de textos y se guardan
     * serializadas en options_json para preguntas de selección.
     */
    public function crearPregunta(
        int $tenantId,
        int $versionId,
        array $datos
    ): array {
        if (!$this->db || $tenantId <= 0 || $versionId <= 0) {
            return ['ok' => false, 'mensaje' => 'No se pudo conectar con la base de datos.'];
        }

        // Validar el texto antes de abrir la transacción.
        $texto = trim((string) ($datos['question_text'] ?? ''));

        if ($texto === '' || mb_strlen($texto) > 1000) {
            return ['ok' => false, 'mensaje' => 'La pregunta es obligatoria y no debe superar 1000 caracteres.'];
        }

        // Solo se admiten los tipos definidos en el esquema SQL.
        $tiposPermitidos = [
            'YES_NO',
            'SCALE',
            'SINGLE_CHOICE',
            'MULTIPLE_CHOICE',
            'TEXT',
            'NUMBER'
        ];

        $tipo = (string) ($datos['answer_type'] ?? 'YES_NO');

        if (!in_array($tipo, $tiposPermitidos, true)) {
            return ['ok' => false, 'mensaje' => 'El tipo de respuesta seleccionado no es válido.'];
        }

        // La ponderación debe ser numérica, finita y mayor que cero.
        $peso = filter_var($datos['weight'] ?? 1, FILTER_VALIDATE_FLOAT);

        if ($peso === false || !is_finite((float) $peso) || $peso <= 0 || $peso > 999999.99) {
            return ['ok' => false, 'mensaje' => 'La ponderación debe ser un número mayor que cero.'];
        }

        // Las preguntas de selección requieren al menos dos opciones.
        $opciones = $datos['options'] ?? [];
        if (!is_array($opciones)) {
            $opciones = [];
        }

        $opcionesLimpias = [];

        foreach ($opciones as $opcion) {
            if (!is_string($opcion)) {
                continue;
            }

            $opcion = trim($opcion);

            if ($opcion !== '') {
                $opcionesLimpias[] = $opcion;
            }
        }

        $opcionesLimpias = array_values(array_unique($opcionesLimpias));

        if (
            in_array($tipo, ['SINGLE_CHOICE', 'MULTIPLE_CHOICE'], true)
            && count($opcionesLimpias) < 2
        ) {
            return [
                'ok' => false,
                'mensaje' => 'Las preguntas de selección requieren al menos dos opciones diferentes.'
            ];
        }

        $opcionesJson = null;

        if (in_array($tipo, ['SINGLE_CHOICE', 'MULTIPLE_CHOICE'], true)) {
            $opcionesJson = json_encode(
                $opcionesLimpias,
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        }

        $permiteNa = !empty($datos['allow_na']) ? 1 : 0;
        $esCritica = !empty($datos['is_critical']) ? 1 : 0;
        $obligatoria = !empty($datos['required_answer']) ? 1 : 0;

        // na_score solo puede utilizarse si se permite "No aplica".
        $notaNa = null;

        if ($permiteNa && isset($datos['na_score']) && $datos['na_score'] !== '') {
            $notaNa = filter_var($datos['na_score'], FILTER_VALIDATE_FLOAT);

            if (
                $notaNa === false
                || !is_finite((float) $notaNa)
                || $notaNa < 0
                || $notaNa > 999999.99
            ) {
                return ['ok' => false, 'mensaje' => 'La puntuación para No aplica no es válida.'];
            }
        }

        $ayuda = trim((string) ($datos['help_text'] ?? ''));
        $ayuda = $ayuda === '' ? null : $ayuda;

        try {
            $this->db->beginTransaction();

            /*
             * Bloquear y comprobar la versión durante la operación.
             * La consulta comprueba simultáneamente empresa y estado.
             */
            $stmtVersion = $this->db->prepare("
                SELECT id, status
                FROM audit_form_versions
                WHERE id = :version_id
                  AND tenant_id = :tenant_id
                FOR UPDATE
            ");

            $stmtVersion->execute([
                'version_id' => $versionId,
                'tenant_id' => $tenantId
            ]);

            $version = $stmtVersion->fetch(PDO::FETCH_ASSOC);

            if (!$version) {
                $this->db->rollBack();
                return ['ok' => false, 'mensaje' => 'La versión seleccionada no existe en esta empresa.'];
            }

            if ($version['status'] !== 'DRAFT') {
                $this->db->rollBack();
                return ['ok' => false, 'mensaje' => 'Solo se pueden modificar versiones en borrador.'];
            }

            // Calcular una posición al final de la lista de preguntas.
            $stmtPosicion = $this->db->prepare("
                SELECT COALESCE(MAX(position), 0) + 1
                FROM audit_form_questions
                WHERE tenant_id = :tenant_id
                  AND form_version_id = :version_id
            ");

            $stmtPosicion->execute([
                'tenant_id' => $tenantId,
                'version_id' => $versionId
            ]);

            $posicion = (int) $stmtPosicion->fetchColumn();

            // Insertar la pregunta con los valores validados.
            $sql = "
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
                ) VALUES (
                    :tenant_id,
                    :version_id,
                    :position,
                    :question_text,
                    :help_text,
                    :answer_type,
                    :options_json,
                    :weight,
                    :is_critical,
                    :required_answer,
                    :allow_na,
                    :na_score,
                    1
                )
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'tenant_id' => $tenantId,
                'version_id' => $versionId,
                'position' => $posicion,
                'question_text' => $texto,
                'help_text' => $ayuda,
                'answer_type' => $tipo,
                'options_json' => $opcionesJson,
                'weight' => $peso,
                'is_critical' => $esCritica,
                'required_answer' => $obligatoria,
                'allow_na' => $permiteNa,
                'na_score' => $notaNa
            ]);

            $this->db->commit();

            return ['ok' => true, 'mensaje' => 'Pregunta creada correctamente.'];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('AuditFormQuestion::crearPregunta: ' . $e->getMessage());

            return ['ok' => false, 'mensaje' => 'No se pudo crear la pregunta. Revisa el registro de errores del servidor.'];
        }
    }

    /**
     * Activa o desactiva una pregunta sin eliminar su registro.
     *
     * Se usa desactivación lógica para conservar trazabilidad y evitar
     * borrar accidentalmente preguntas que podrían tener resultados.
     */
    public function cambiarEstado(
        int $tenantId,
        int $versionId,
        int $preguntaId,
        bool $activo
    ): array {
        if (!$this->db || $tenantId <= 0 || $versionId <= 0 || $preguntaId <= 0) {
            return ['ok' => false, 'mensaje' => 'Los datos recibidos no son válidos.'];
        }

        try {
            $this->db->beginTransaction();

            // Verificar que la versión sea propiedad de esta empresa.
            $stmtVersion = $this->db->prepare("
                SELECT status
                FROM audit_form_versions
                WHERE id = :version_id
                  AND tenant_id = :tenant_id
                FOR UPDATE
            ");

            $stmtVersion->execute([
                'version_id' => $versionId,
                'tenant_id' => $tenantId
            ]);

            $estadoVersion = $stmtVersion->fetchColumn();

            if ($estadoVersion === false) {
                $this->db->rollBack();
                return ['ok' => false, 'mensaje' => 'La versión seleccionada no existe.'];
            }

            if ($estadoVersion !== 'DRAFT') {
                $this->db->rollBack();
                return ['ok' => false, 'mensaje' => 'No se puede modificar una versión publicada o retirada.'];
            }

            // La pregunta debe pertenecer tanto a la empresa como a la versión.
            $stmt = $this->db->prepare("
                UPDATE audit_form_questions
                SET active = :active
                WHERE id = :question_id
                  AND tenant_id = :tenant_id
                  AND form_version_id = :version_id
            ");

            $stmt->execute([
                'active' => $activo ? 1 : 0,
                'question_id' => $preguntaId,
                'tenant_id' => $tenantId,
                'version_id' => $versionId
            ]);

            if ($stmt->rowCount() < 1) {
                $this->db->rollBack();
                return ['ok' => false, 'mensaje' => 'No se encontró la pregunta o no era necesario cambiar su estado.'];
            }

            $this->db->commit();

            return [
                'ok' => true,
                'mensaje' => $activo
                    ? 'Pregunta activada correctamente.'
                    : 'Pregunta desactivada correctamente.'
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('AuditFormQuestion::cambiarEstado: ' . $e->getMessage());

            return ['ok' => false, 'mensaje' => 'No se pudo cambiar el estado de la pregunta.'];
        }
    }
}