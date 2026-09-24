<?php

// Cargamos la clase Database para poder establecer la conexión
// con la base de datos MariaDB cuando no recibamos una conexión externa.
require_once __DIR__ . '/../config/database.php';


/**
 * Clase Role
 *
 * Se encarga de las operaciones relacionadas con los roles:
 *
 * - Listar roles por empresa o roles globales.
 * - Obtener un rol por ID.
 * - Listar los roles asignados a un usuario.
 * - Validar si un rol puede asignarse a un usuario.
 * - Asignar un rol a un usuario.
 *
 * La clase permite recibir una conexión PDO externa.
 *
 * Esto es importante porque posteriormente UserController podrá
 * utilizar la misma conexión PDO para realizar operaciones como:
 *
 * 1. Crear usuario.
 * 2. Asignar rol.
 * 3. COMMIT si todo funciona.
 * 4. ROLLBACK si algo falla.
 */
class Role
{
    /**
     * Conexión PDO utilizada para acceder a MariaDB.
     *
     * Puede ser:
     *
     * - Una conexión creada internamente por esta clase.
     * - Una conexión recibida desde UserController.
     *
     * Permitir una conexión externa hace posible compartir
     * la misma transacción entre User y Role.
     */
    private ?PDO $db;


    /**
     * Constructor de la clase Role.
     *
     * Si recibimos una conexión PDO desde fuera, utilizamos
     * esa conexión.
     *
     * Si no recibimos ninguna conexión, mantenemos el comportamiento
     * original y Role crea su propia conexión mediante Database.
     *
     * @param PDO|null $db Conexión PDO opcional.
     */
    public function __construct(?PDO $db = null)
    {
        // Si recibimos una conexión desde otra clase,
        // utilizamos exactamente esa conexión.
        if ($db !== null) {
            $this->db = $db;
            return;
        }

        // Si no recibimos una conexión externa,
        // creamos nuestra propia conexión.
        $database = new Database();

        // Obtenemos la conexión PDO a MariaDB.
        $this->db = $database->connect();
    }


    /**
     * Lista los roles correspondientes a un ámbito determinado.
     *
     * Cuando $tenantId es NULL:
     *
     *     Se solicitan únicamente roles globales.
     *
     * Cuando $tenantId contiene un ID:
     *
     *     Se solicitan únicamente roles pertenecientes
     *     a esa empresa.
     *
     * IMPORTANTE:
     *
     * Este método solamente consulta los roles.
     * La autorización de quién puede visualizar o utilizar
     * esos roles corresponde a las capas superiores.
     *
     * @param int|null $tenantId ID de empresa o NULL para roles globales.
     *
     * @return array Lista de roles.
     */
    public function listarPorTenant(?int $tenantId): array
    {
        // Si no existe conexión con la base de datos,
        // devolvemos un arreglo vacío.
        if ($this->db === null) {
            return [];
        }


        /**
         * CASO 1:
         *
         * $tenantId === NULL
         *
         * En este caso solamente debemos devolver
         * los roles globales.
         */
        if ($tenantId === null) {

            // Consulta de roles globales.
            //
            // Un rol global tiene tenant_id = NULL.
            $sql = "SELECT
                        id,
                        tenant_id,
                        name,
                        slug,
                        description,
                        status,
                        created_at,
                        updated_at
                    FROM roles
                    WHERE tenant_id IS NULL
                    ORDER BY id ASC";

            // Ejecutamos la consulta.
            $stmt = $this->db->query($sql);

            // Devolvemos los roles encontrados.
            return $stmt->fetchAll();
        }


        /**
         * CASO 2:
         *
         * Tenemos un tenant específico.
         *
         * Solo debemos devolver los roles pertenecientes
         * exactamente a esa empresa.
         */
        $sql = "SELECT
                    id,
                    tenant_id,
                    name,
                    slug,
                    description,
                    status,
                    created_at,
                    updated_at
                FROM roles
                WHERE tenant_id = :tenant_id
                ORDER BY id ASC";

        // Preparamos la consulta.
        $stmt = $this->db->prepare($sql);

        // Enviamos el ID de la empresa.
        $stmt->execute([
            ':tenant_id' => $tenantId
        ]);

        // Devolvemos los roles encontrados.
        return $stmt->fetchAll();
    }


    /**
     * Obtiene un rol mediante su ID.
     *
     * @param int $id ID del rol.
     *
     * @return array|null Datos del rol o null si no existe.
     */
    public function obtenerPorId(int $id): ?array
    {
        // Si no existe conexión, no podemos consultar.
        if ($this->db === null) {
            return null;
        }

        // Buscamos el rol mediante su ID.
        $sql = "SELECT
                    id,
                    tenant_id,
                    name,
                    slug,
                    description,
                    status
                FROM roles
                WHERE id = :id
                LIMIT 1";

        // Preparamos la consulta.
        $stmt = $this->db->prepare($sql);

        // Ejecutamos enviando el ID.
        $stmt->execute([
            ':id' => $id
        ]);

        // Obtenemos el registro.
        $role = $stmt->fetch();

        // Si no existe, devolvemos null.
        if ($role === false) {
            return null;
        }

        // Devolvemos los datos del rol.
        return $role;
    }


    /**
     * Lista los roles activos asignados a un usuario.
     *
     * La relación entre usuarios y roles se encuentra
     * almacenada en la tabla user_roles.
     *
     * @param int $userId ID del usuario.
     *
     * @return array Roles asignados al usuario.
     */
    public function listarPorUsuario(int $userId): array
    {
        // Si no existe conexión con la base de datos,
        // devolvemos un arreglo vacío.
        if ($this->db === null) {
            return [];
        }

        // Obtenemos los roles relacionados con el usuario.
        //
        // Además filtramos solamente roles ACTIVE.
        $sql = "SELECT
                    r.id,
                    r.tenant_id,
                    r.name,
                    r.slug,
                    r.description,
                    r.status
                FROM roles r
                INNER JOIN user_roles ur
                    ON ur.role_id = r.id
                WHERE ur.user_id = :user_id
                AND r.status = 'ACTIVE'
                ORDER BY r.id ASC";

        // Preparamos la consulta.
        $stmt = $this->db->prepare($sql);

        // Ejecutamos enviando el ID del usuario.
        $stmt->execute([
            ':user_id' => $userId
        ]);

        // Devolvemos los roles encontrados.
        return $stmt->fetchAll();
    }


    /**
     * Determina si un rol puede asignarse a un usuario.
     *
     * Esta validación es MUY importante para el aislamiento
     * de empresas de CallAudit.
     *
     * Reglas:
     *
     * 1. El usuario debe existir.
     * 2. El usuario debe estar ACTIVE.
     * 3. El rol debe existir.
     * 4. El rol debe estar ACTIVE.
     *
     * 5. Si el rol es GLOBAL:
     *      solamente puede asignarse a un usuario GLOBAL.
     *
     * 6. Si el rol pertenece a una empresa:
     *      solamente puede asignarse a un usuario
     *      de esa misma empresa.
     *
     * Ejemplo:
     *
     * Usuario empresa 6 + Rol empresa 6 = permitido.
     *
     * Usuario empresa 6 + Rol empresa 5 = DENEGADO.
     *
     * Usuario global + Rol global = permitido.
     *
     * Usuario global + Rol empresa 6 = DENEGADO.
     *
     * Esta validación debe mantenerse independientemente
     * de lo que envíe el formulario HTML.
     *
     * @param int $userId ID del usuario.
     * @param int $roleId ID del rol.
     *
     * @return bool true si el rol es compatible con el usuario.
     */
    public function puedeAsignarseAUsuario(
        int $userId,
        int $roleId
    ): bool {

        // Si no existe conexión con la base de datos,
        // no podemos realizar la validación.
        if ($this->db === null) {
            return false;
        }


        /**
         * Primero obtenemos la información del usuario.
         */
        $sqlUser = "SELECT
                        id,
                        tenant_id,
                        status
                    FROM users
                    WHERE id = :user_id
                    LIMIT 1";

        // Preparamos la consulta del usuario.
        $stmtUser = $this->db->prepare($sqlUser);

        // Ejecutamos buscando el usuario indicado.
        $stmtUser->execute([
            ':user_id' => $userId
        ]);

        // Obtenemos el usuario.
        $user = $stmtUser->fetch();

        // Si el usuario no existe, el rol no puede asignarse.
        if ($user === false) {
            return false;
        }

        // Un rol no puede asignarse a un usuario INACTIVE.
        if ($user['status'] !== 'ACTIVE') {
            return false;
        }


        /**
         * Ahora obtenemos la información del rol.
         */
        $sqlRole = "SELECT
                        id,
                        tenant_id,
                        slug,
                        status
                    FROM roles
                    WHERE id = :role_id
                    LIMIT 1";

        // Preparamos la consulta del rol.
        $stmtRole = $this->db->prepare($sqlRole);

        // Ejecutamos buscando el rol indicado.
        $stmtRole->execute([
            ':role_id' => $roleId
        ]);

        // Obtenemos el rol.
        $role = $stmtRole->fetch();

        // Si el rol no existe, no puede asignarse.
        if ($role === false) {
            return false;
        }

        // Un rol INACTIVE no puede asignarse.
        if ($role['status'] !== 'ACTIVE') {
            return false;
        }


        /**
         * VALIDACIÓN DE ÁMBITO
         *
         * Aquí se aplica una de las reglas fundamentales
         * de CallAudit:
         *
         * El rol y el usuario deben pertenecer al mismo ámbito.
         *
         * GLOBAL:
         *     tenant_id = NULL
         *
         * EMPRESA:
         *     tenant_id contiene el ID de la empresa.
         */


        /**
         * Si el rol es GLOBAL:
         *
         * tenant_id = NULL
         *
         * Solo puede asignarse a un usuario GLOBAL.
         */
        if ($role['tenant_id'] === null) {

            // Si el usuario también es global,
            // la combinación es válida.
            if ($user['tenant_id'] === null) {
                return true;
            }

            // Usuario de empresa + rol global = DENEGADO.
            return false;
        }


        /**
         * Si llegamos aquí significa que el rol pertenece
         * a una empresa.
         *
         * Por lo tanto, un usuario GLOBAL no puede recibir
         * directamente un rol de empresa.
         */
        if ($user['tenant_id'] === null) {
            return false;
        }


        /**
         * Finalmente comprobamos que ambos pertenezcan
         * exactamente a la misma empresa.
         *
         * Ejemplo:
         *
         * role.tenant_id = 6
         * user.tenant_id = 6
         *
         * Resultado: TRUE
         *
         * role.tenant_id = 5
         * user.tenant_id = 6
         *
         * Resultado: FALSE
         */
        return (int) $role['tenant_id'] === (int) $user['tenant_id'];
    }


    /**
     * Asigna un rol a un usuario.
     *
     * Antes de realizar el INSERT se ejecuta
     * puedeAsignarseAUsuario().
     *
     * Esto evita que el código que llama a este método
     * pueda asignar accidentalmente un rol de otra empresa.
     *
     * IMPORTANTE:
     *
     * Este método NO inicia ni confirma una transacción.
     *
     * La transacción debe ser controlada por el Controller
     * cuando necesitemos combinar:
     *
     *     crear usuario
     *          +
     *     asignar rol
     *
     * dentro de una sola operación atómica.
     *
     * @param int $userId ID del usuario.
     * @param int $roleId ID del rol.
     *
     * @return bool true si la asignación fue correcta.
     */
    public function asignarAUsuario(
        int $userId,
        int $roleId
    ): bool {

        // Si no existe conexión, no podemos realizar
        // la asignación.
        if ($this->db === null) {
            return false;
        }


        /**
         * Validamos nuevamente que el rol sea compatible
         * con el usuario.
         *
         * No debemos confiar únicamente en la validación
         * realizada previamente por el formulario o Controller.
         */
        if (!$this->puedeAsignarseAUsuario($userId, $roleId)) {
            return false;
        }


        /**
         * Insertamos la relación usuario + rol.
         *
         * La tabla user_roles utiliza:
         *
         *     user_id
         *     role_id
         *
         * como clave primaria compuesta.
         */
        $sql = "INSERT INTO user_roles
                    (user_id, role_id)
                VALUES
                    (:user_id, :role_id)";

        try {

            // Preparamos el INSERT.
            $stmt = $this->db->prepare($sql);

            // Ejecutamos la asignación.
            return $stmt->execute([
                ':user_id' => $userId,
                ':role_id' => $roleId
            ]);

        } catch (PDOException $e) {

            /**
             * Si ocurre un error de MariaDB, devolvemos false.
             *
             * Si este método se está ejecutando dentro de una
             * transacción iniciada por UserController,
             * será el Controller quien realizará el ROLLBACK.
             */
            return false;
        }
    }
}