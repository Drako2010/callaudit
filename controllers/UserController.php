<?php

/*
 * ============================================================
 * CONTROLADOR DE USUARIOS
 * ============================================================
 *
 * Este controlador se encarga de coordinar:
 *
 * 1. Validaciones de los datos recibidos.
 * 2. Autorización del usuario que realiza la operación.
 * 3. Validación del ámbito (tenant/empresa).
 * 4. Validación del rol que se desea asignar.
 * 5. Creación del usuario.
 * 6. Asignación del rol.
 * 7. Transacción para garantizar integridad.
 *
 * Regla importante de CallAudit:
 *
 * El frontend nunca constituye el mecanismo de seguridad.
 * Aunque el formulario envíe un tenant_id o role_id,
 * el backend debe volver a comprobar que la operación
 * está permitida.
 *
 * ============================================================
 */


/*
 * ============================================================
 * CARGA DE DEPENDENCIAS
 * ============================================================
 *
 * User:
 *   Permite crear y consultar usuarios.
 *
 * Role:
 *   Permite validar y asignar roles.
 *
 * Tenant:
 *   Permite validar la empresa.
 *
 * Database:
 *   Permite compartir una misma conexión PDO entre los modelos
 *   involucrados en la transacción.
 *
 * SessionService:
 *   Permite conocer qué usuario está autenticado actualmente.
 *
 * Authorization:
 *   Permite comprobar los permisos efectivos del usuario.
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Role.php';
require_once __DIR__ . '/../models/Tenant.php';
require_once __DIR__ . '/../services/SessionService.php';
require_once __DIR__ . '/../services/Authorization.php';


class UserController
{
    /*
     * ========================================================
     * MODELOS Y SERVICIOS
     * ========================================================
     */

    private ?PDO $db;

    private User $user;

    private Role $role;

    private Tenant $tenant;

    private SessionService $session;

    private Authorization $authorization;


    /*
     * ========================================================
     * CONSTRUCTOR
     * ========================================================
     *
     * Creamos una conexión PDO compartida para User y Role.
     *
     * Esto es importante porque la creación del usuario y
     * la asignación del rol deben pertenecer a la MISMA
     * transacción.
     *
     * Si cada modelo utilizara una conexión diferente,
     * no podríamos garantizar correctamente:
     *
     * BEGIN
     *   crear usuario
     *   asignar rol
     * COMMIT
     *
     * ========================================================
     */

    public function __construct()
    {
        /*
         * Crear conexión principal.
         */
        $database = new Database();

        $this->db = $database->connect();


        /*
         * Los modelos User y Role reciben la misma conexión.
         *
         * De esta forma ambos trabajan sobre la misma
         * transacción cuando sea necesario.
         */
        $this->user = new User($this->db);

        $this->role = new Role($this->db);


        /*
         * Tenant actualmente utiliza su propia conexión,
         * siguiendo el patrón existente del proyecto.
         */
        $this->tenant = new Tenant();


        /*
         * Servicio de sesión.
         *
         * Nos permite identificar al usuario que está
         * realizando la operación.
         */
        $this->session = new SessionService();


        /*
         * Servicio de autorización.
         *
         * Se utilizará para comprobar el permiso:
         *
         * users.create
         */
        $this->authorization = new Authorization();
    }


    /*
     * ========================================================
     * LISTAR USUARIOS
     * ========================================================
     *
     * Mantiene el comportamiento actual del controlador.
     *
     * La protección de acceso al listado deberá mantenerse
     * también en la entrada correspondiente mediante
     * AuthMiddleware.
     *
     * ========================================================
     */

    public function index(): array
    {
        return $this->user->listar();
    }


    /*
     * ========================================================
     * CREAR USUARIO
     * ========================================================
     *
     * Parámetros:
     *
     * $tenantId:
     *   Empresa a la que pertenecerá el nuevo usuario.
     *
     * $name:
     *   Nombre del usuario.
     *
     * $email:
     *   Correo electrónico.
     *
     * $password:
     *   Contraseña inicial.
     *
     * $roleId:
     *   Rol que se asignará al nuevo usuario.
     *
     * ========================================================
     */

    public function store(
        int $tenantId,
        string $name,
        string $email,
        string $password,
        int $roleId
    ): array {

        /*
         * ====================================================
         * 1. NORMALIZAR DATOS
         * ====================================================
         *
         * Eliminamos espacios innecesarios antes de realizar
         * las validaciones.
         */

        $name = trim($name);

        $email = trim($email);

        $password = trim($password);


        /*
         * ====================================================
         * 2. VERIFICAR SESIÓN
         * ====================================================
         *
         * El controlador no debe permitir crear usuarios si
         * no existe una sesión autenticada.
         */

        if (!$this->session->estaAutenticado()) {

            return [
                'success' => false,
                'message' => 'Debe iniciar sesión para realizar esta operación.'
            ];
        }


        /*
         * Obtener los datos del usuario actualmente autenticado.
         */

        $usuarioActual = $this->session->obtenerUsuario();


        if ($usuarioActual === null) {

            return [
                'success' => false,
                'message' => 'No se pudo identificar al usuario autenticado.'
            ];
        }


        /*
         * Guardamos el ID del usuario que está realizando
         * la operación.
         */

        $usuarioActualId = (int) $usuarioActual['id'];


        /*
         * ====================================================
         * 3. COMPROBAR PERMISO users.create
         * ====================================================
         *
         * Tener acceso a la pantalla no es suficiente.
         *
         * El backend vuelve a comprobar el permiso efectivo.
         *
         * Authorization considera:
         *
         * - usuario activo
         * - DENY individual
         * - GRANT individual
         * - permisos heredados por rol
         * - ámbito del rol
         *
         * Además:
         *
         * DENY tiene prioridad sobre GRANT.
         */

        if (!$this->authorization->tienePermiso(
            $usuarioActualId,
            'users.create'
        )) {

            return [
                'success' => false,
                'message' => 'No tiene permisos para crear usuarios.'
            ];
        }


        /*
         * ====================================================
         * 4. VALIDAR EMPRESA RECIBIDA
         * ====================================================
         *
         * Nunca debemos confiar únicamente en el tenant_id
         * recibido desde POST.
         *
         * Por eso primero comprobamos que tenga un valor válido.
         */

        if ($tenantId <= 0) {

            return [
                'success' => false,
                'message' => 'La empresa es obligatoria.'
            ];
        }


        /*
         * ====================================================
         * 5. VALIDAR ÁMBITO DEL USUARIO ACTUAL
         * ====================================================
         *
         * Esta es una de las comprobaciones más importantes
         * para evitar que un usuario de una empresa cree
         * usuarios dentro de otra empresa.
         *
         * Caso A:
         *
         * tenant_id del usuario actual = NULL
         *
         * Significa que trabaja en ámbito global.
         * Un usuario global con users.create puede operar
         * sobre cualquier empresa.
         *
         * Caso B:
         *
         * tenant_id del usuario actual != NULL
         *
         * Solo puede operar sobre su propia empresa.
         */

        $tenantActual = $usuarioActual['tenant_id'] !== null
            ? (int) $usuarioActual['tenant_id']
            : null;


        /*
         * Si el usuario pertenece a una empresa concreta,
         * el tenant solicitado debe ser exactamente el mismo.
         */

        if (
            $tenantActual !== null
            && $tenantActual !== $tenantId
        ) {

            return [
                'success' => false,
                'message' => 'No puede crear usuarios en otra empresa.'
            ];
        }


        /*
         * ====================================================
         * 6. VALIDAR EMPRESA EXISTENTE
         * ====================================================
         */

        $tenant = $this->tenant->obtenerPorId($tenantId);


        if ($tenant === null) {

            return [
                'success' => false,
                'message' => 'La empresa seleccionada no existe.'
            ];
        }


        /*
         * Una empresa inactiva no debe recibir nuevos usuarios.
         */

        if ($tenant['status'] !== 'ACTIVE') {

            return [
                'success' => false,
                'message' => 'La empresa seleccionada está inactiva.'
            ];
        }


        /*
         * ====================================================
         * 7. VALIDAR NOMBRE
         * ====================================================
         */

        if ($name === '') {

            return [
                'success' => false,
                'message' => 'El nombre del usuario es obligatorio.'
            ];
        }


        /*
         * ====================================================
         * 8. VALIDAR CORREO
         * ====================================================
         */

        if ($email === '') {

            return [
                'success' => false,
                'message' => 'El correo electrónico es obligatorio.'
            ];
        }


        /*
         * Comprobar formato válido de correo.
         */

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            return [
                'success' => false,
                'message' => 'El correo electrónico no es válido.'
            ];
        }


        /*
         * ====================================================
         * 9. VALIDAR CONTRASEÑA
         * ====================================================
         */

        if ($password === '') {

            return [
                'success' => false,
                'message' => 'La contraseña es obligatoria.'
            ];
        }


        /*
         * Regla mínima actual:
         *
         * 8 caracteres.
         */

        if (strlen($password) < 8) {

            return [
                'success' => false,
                'message' => 'La contraseña debe tener al menos 8 caracteres.'
            ];
        }


        /*
         * ====================================================
         * 10. VALIDAR ROLE ID
         * ====================================================
         */

        if ($roleId <= 0) {

            return [
                'success' => false,
                'message' => 'El rol es obligatorio.'
            ];
        }


        /*
         * ====================================================
         * 11. COMPROBAR CORREO DUPLICADO
         * ====================================================
         *
         * La regla actual de CallAudit permite que el mismo
         * correo exista en diferentes empresas.
         *
         * Pero no puede repetirse dentro de la misma empresa.
         */

        if ($this->user->existeEmail($tenantId, $email)) {

            return [
                'success' => false,
                'message' => 'El correo ya está registrado en esta empresa.'
            ];
        }


        /*
         * ====================================================
         * 12. OBTENER Y VALIDAR EL ROL
         * ====================================================
         *
         * Primero obtenemos el rol solicitado.
         */

        $role = $this->role->obtenerPorId($roleId);


        if ($role === null) {

            return [
                'success' => false,
                'message' => 'El rol seleccionado no existe.'
            ];
        }


        /*
         * Un rol inactivo no puede asignarse a un usuario nuevo.
         */

        if ($role['status'] !== 'ACTIVE') {

            return [
                'success' => false,
                'message' => 'El rol seleccionado está inactivo.'
            ];
        }


        /*
         * ====================================================
         * 13. VALIDAR ÁMBITO DEL ROL
         * ====================================================
         *
         * Un usuario de una empresa debe recibir un rol de
         * esa misma empresa.
         *
         * Un rol global (tenant_id NULL) solamente puede
         * asignarse a un usuario global.
         *
         * En esta operación estamos creando usuarios de
         * empresa, por lo que un rol global no es válido.
         */

        if ($role['tenant_id'] === null) {

            return [
                'success' => false,
                'message' => 'El rol seleccionado es global y no puede asignarse a un usuario de empresa.'
            ];
        }


        /*
         * El rol debe pertenecer exactamente a la empresa
         * donde estamos creando el usuario.
         */

        if ((int) $role['tenant_id'] !== $tenantId) {

            return [
                'success' => false,
                'message' => 'El rol seleccionado no pertenece a la empresa indicada.'
            ];
        }


        /*
         * ====================================================
         * 14. COMENZAR TRANSACCIÓN
         * ====================================================
         *
         * Desde este punto:
         *
         * - crear usuario
         * - asignar rol
         *
         * forman una sola operación.
         *
         * Si cualquiera falla:
         *
         * ROLLBACK
         *
         * Si todo funciona:
         *
         * COMMIT
         */

        if ($this->db === null) {

            return [
                'success' => false,
                'message' => 'No se pudo establecer la conexión con la base de datos.'
            ];
        }


        try {

            /*
             * Iniciar transacción.
             */
            $this->db->beginTransaction();


            /*
             * =================================================
             * 15. CREAR USUARIO
             * =================================================
             *
             * crearConId() devuelve el ID generado.
             *
             * Necesitamos ese ID para poder insertar después
             * la relación en user_roles.
             */

            $userId = $this->user->crearConId(
                $tenantId,
                $name,
                $email,
                $password
            );


            /*
             * Si no se pudo crear el usuario,
             * cancelamos toda la operación.
             */

            if ($userId === null) {

                $this->db->rollBack();

                return [
                    'success' => false,
                    'message' => 'No se pudo crear el usuario.'
                ];
            }


            /*
             * =================================================
             * 16. VALIDAR NUEVAMENTE COMPATIBILIDAD
             * =================================================
             *
             * Role::puedeAsignarseAUsuario() vuelve a verificar
             * directamente en la base de datos:
             *
             * - usuario existente
             * - usuario activo
             * - rol existente
             * - rol activo
             * - ámbito del usuario
             * - ámbito del rol
             *
             * Esto agrega una segunda barrera de seguridad.
             */

            if (!$this->role->puedeAsignarseAUsuario(
                $userId,
                $roleId
            )) {

                $this->db->rollBack();

                return [
                    'success' => false,
                    'message' => 'El rol seleccionado no puede asignarse al usuario.'
                ];
            }


            /*
             * =================================================
             * 17. ASIGNAR ROL
             * =================================================
             */

            if (!$this->role->asignarAUsuario(
                $userId,
                $roleId
            )) {

                /*
                 * Si falla la asignación del rol,
                 * también se elimina la creación del usuario.
                 */
                $this->db->rollBack();

                return [
                    'success' => false,
                    'message' => 'No se pudo asignar el rol al usuario.'
                ];
            }


            /*
             * =================================================
             * 18. CONFIRMAR TRANSACCIÓN
             * =================================================
             *
             * Llegados aquí:
             *
             * - usuario creado
             * - rol asignado
             *
             * Por lo tanto podemos confirmar definitivamente
             * ambas operaciones.
             */

            $this->db->commit();


            /*
             * =================================================
             * 19. RESPUESTA EXITOSA
             * =================================================
             */

            return [
                'success' => true,
                'message' => 'Usuario creado correctamente.',
                'user_id' => $userId
            ];

        } catch (Throwable $e) {

            /*
             * =================================================
             * 20. MANEJO DE ERROR
             * =================================================
             *
             * Si ocurre cualquier excepción durante la
             * transacción, comprobamos si sigue activa y
             * hacemos ROLLBACK.
             */

            if ($this->db->inTransaction()) {

                $this->db->rollBack();
            }


            /*
             * No mostramos el mensaje técnico de la excepción
             * al usuario final.
             *
             * Los detalles técnicos deben manejarse mediante
             * logging posteriormente.
             */

            return [
                'success' => false,
                'message' => 'No se pudo completar la creación del usuario.'
            ];
        }
    }
}