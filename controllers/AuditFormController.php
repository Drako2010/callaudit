<?php
/**
 * ============================================================================
 * ARCHIVO: controllers/AuditFormController.php
 * PROYECTO: CallAudit
 * ============================================================================
 *
 * RESPONSABILIDAD
 * --------------
 * Coordinar las operaciones del módulo de formularios de auditoría:
 *
 * - Comprobar permisos.
 * - Validar el ámbito de la empresa.
 * - Solicitar al modelo la lista de formularios.
 * - Solicitar al modelo la creación de un formulario.
 *
 * PERMISOS EXISTENTES UTILIZADOS
 * ------------------------------
 * audits.view   : consultar formularios.
 * audits.create : crear formularios.
 *
 * No se crean permisos nuevos en este archivo.
 *
 * SEGURIDAD MULTIEMPRESA
 * ----------------------
 * - Los usuarios empresariales solo trabajan en su tenant.
 * - Los usuarios globales deben tener un tenant seleccionado en sesión.
 * - El tenant debe proceder del contexto resuelto por la página.
 * - La autorización se valida en el servidor.
 * ============================================================================
 */

require_once __DIR__ . '/../models/AuditForm.php';
require_once __DIR__ . '/../services/AuthMiddleware.php';

class AuditFormController
{
    /** @var AuditForm Modelo que consulta y modifica la base de datos. */
    private AuditForm $model;

    /** @var AuthMiddleware Servicio existente de autenticación y permisos. */
    private AuthMiddleware $auth;

    /**
     * Constructor.
     *
     * Inicializa las dependencias del controlador.
     */
    public function __construct()
    {
        $this->model = new AuditForm();
        $this->auth = new AuthMiddleware();
    }

    /**
     * ------------------------------------------------------------------------
     * MÉTODO PRIVADO: validarAcceso()
     * ------------------------------------------------------------------------
     * Comprueba que el usuario tenga el permiso solicitado y que el tenant
     * indicado coincida con su ámbito autorizado.
     *
     * IMPORTANTE:
     * CampaignTenantContext debe haber resuelto previamente el tenant
     * seleccionado para un SUPERADMIN global.
     *
     * @param int    $tenantId Empresa sobre la que se realizará la operación.
     * @param string $permiso  Slug de permiso ya existente.
     *
     * @return array Usuario autenticado.
     *
     * @throws No lanza una excepción controlada al usuario; el middleware
     *         responde directamente cuando el acceso es denegado.
     */
    private function validarAcceso(int $tenantId, string $permiso): array
    {
        // Primera barrera: sesión autenticada y permiso efectivo.
        $this->auth->requierePermiso($permiso);

        // Obtener los datos del usuario autenticado desde el servicio.
        $usuario = $this->auth->usuario();

        if ($usuario === null) {
            http_response_code(401);
            exit('Usuario no autenticado.');
        }

        // Rechazar IDs vacíos, negativos o inválidos.
        if ($tenantId <= 0) {
            http_response_code(400);
            exit('La empresa seleccionada no es válida.');
        }

        /*
         * USUARIO EMPRESARIAL
         *
         * Un usuario con tenant_id asignado solo puede trabajar en esa
         * empresa. No se acepta cambiar de tenant mediante GET o POST.
         */
        if ($usuario['tenant_id'] !== null) {
            if ((int) $usuario['tenant_id'] !== $tenantId) {
                http_response_code(403);
                exit('No tiene autorización para acceder a esta empresa.');
            }

            return $usuario;
        }

        /*
         * USUARIO GLOBAL
         *
         * Para una cuenta sin tenant_id, comprobar que el tenant recibido
         * coincida con la empresa que el usuario global seleccionó
         * previamente mediante CampaignTenantContext.
         *
         * Esta comprobación evita que una llamada directa al controlador
         * utilice un tenant diferente del guardado en la sesión.
         */
        $tenantSeleccionado = $_SESSION['campaign_tenant_id'] ?? null;

        if (
            (!is_int($tenantSeleccionado)
                && !(is_string($tenantSeleccionado)
                    && ctype_digit($tenantSeleccionado)))
            || (int) $tenantSeleccionado !== $tenantId
        ) {
            http_response_code(403);
            exit('El contexto de empresa no es válido o no está seleccionado.');
        }

        return $usuario;
    }

    /**
     * ------------------------------------------------------------------------
     * MÉTODO: index()
     * ------------------------------------------------------------------------
     * Devuelve los formularios de una empresa.
     *
     * Permiso requerido: audits.view
     *
     * @param int $tenantId Empresa autorizada.
     * @return array Formularios encontrados.
     */
    public function index(int $tenantId): array
    {
        // Verificar permiso y empresa antes de consultar la base de datos.
        $this->validarAcceso($tenantId, 'audits.view');

        // Delegar la consulta al modelo.
        return $this->model->listar($tenantId);
    }

    /**
     * ------------------------------------------------------------------------
     * MÉTODO: store()
     * ------------------------------------------------------------------------
     * Crea un formulario y su primera versión DRAFT.
     *
     * Permiso requerido: audits.create
     *
     * @param int         $tenantId    Empresa autorizada.
     * @param string      $name        Nombre del formulario.
     * @param string|null $description Descripción opcional.
     *
     * @return array Resultado de la operación.
     */
    public function store(
        int $tenantId,
        string $name,
        ?string $description
    ): array {
        /*
         * Validar acceso antes de ejecutar cualquier modificación.
         * El ID del usuario creador se obtiene de la sesión, no del formulario.
         */
        $usuario = $this->validarAcceso($tenantId, 'audits.create');

        $userId = (int) ($usuario['id'] ?? 0);

        if ($userId <= 0) {
            http_response_code(401);
            exit('No se pudo identificar al usuario autenticado.');
        }

        // Normalizar los valores recibidos.
        $name = trim($name);

        if ($description !== null) {
            $description = trim($description);

            if ($description === '') {
                $description = null;
            }
        }

        // Validación básica antes de delegar la operación al modelo.
        if ($name === '') {
            return [
                'success' => false,
                'message' => 'El nombre del formulario es obligatorio.'
            ];
        }

        if (mb_strlen($name, 'UTF-8') > 150) {
            return [
                'success' => false,
                'message' => 'El nombre no puede superar los 150 caracteres.'
            ];
        }

        /*
         * El modelo realiza las validaciones de base de datos y ejecuta
         * la transacción para insertar el formulario y la versión inicial.
         */
        return $this->model->crear(
            $tenantId,
            $name,
            $description,
            $userId
        );
    }
}
