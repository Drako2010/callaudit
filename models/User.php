<?php

// Cargamos la clase Database para poder establecer la conexión
// con la base de datos MariaDB.
require_once __DIR__ . '/../config/database.php';


/**
 * Clase User
 *
 * Se encarga de las operaciones relacionadas con los usuarios:
 *
 * - Listar usuarios.
 * - Validar si un correo ya existe dentro de una empresa.
 * - Crear usuarios.
 * - Crear usuarios y obtener su ID.
 *
 * La clase permite recibir una conexión PDO externa para poder
 * participar posteriormente en transacciones junto con otras clases,
 * por ejemplo Role.
 */
class User
{
    /**
     * Conexión PDO utilizada para acceder a MariaDB.
     *
     * Puede ser:
     * - Una conexión creada internamente por esta clase.
     * - Una conexión recibida desde otra clase, por ejemplo el Controller.
     *
     * El valor puede ser null si la conexión no pudo establecerse.
     */
    private ?PDO $db;


    /**
     * Constructor de la clase User.
     *
     * Si recibimos una conexión PDO desde fuera, utilizamos esa conexión.
     * Esto permitirá que User y Role trabajen dentro de la misma
     * transacción de base de datos.
     *
     * Si no recibimos una conexión, mantenemos el comportamiento anterior
     * y User crea su propia conexión.
     *
     * @param PDO|null $db Conexión PDO opcional.
     */
    public function __construct(?PDO $db = null)
    {
        // Si el Controller nos proporciona una conexión,
        // utilizamos esa misma conexión.
        if ($db !== null) {
            $this->db = $db;
            return;
        }

        // Si no recibimos una conexión externa,
        // creamos una nueva instancia de Database.
        $database = new Database();

        // Obtenemos la conexión PDO a MariaDB.
        $this->db = $database->connect();
    }


    /**
     * Lista todos los usuarios que pertenecen a empresas.
     *
     * Actualmente utiliza INNER JOIN con tenants, por lo que
     * los usuarios globales con tenant_id NULL no aparecen en este listado.
     *
     * @return array Lista de usuarios.
     */
    public function listar(): array
    {
        // Si no existe conexión con la base de datos,
        // devolvemos un arreglo vacío.
        if ($this->db === null) {
            return [];
        }

        // Consulta los datos principales del usuario y el nombre
        // de la empresa a la que pertenece.
        $sql = "SELECT
                    u.id,
                    u.tenant_id,
                    t.name AS tenant_name,
                    u.name,
                    u.email,
                    u.status,
                    u.created_at,
                    u.updated_at
                FROM users u
                INNER JOIN tenants t
                    ON t.id = u.tenant_id
                ORDER BY u.id DESC";

        // Ejecutamos la consulta.
        $stmt = $this->db->query($sql);

        // Devolvemos todos los usuarios encontrados.
        return $stmt->fetchAll();
    }


    /**
     * Verifica si un correo electrónico ya está registrado
     * dentro de una empresa determinada.
     *
     * La validación se realiza considerando tenant_id + email.
     *
     * Esto respeta la regla de CallAudit:
     *
     * Un mismo correo puede existir en diferentes empresas,
     * pero no puede repetirse dentro de la misma empresa.
     *
     * @param int $tenantId ID de la empresa.
     * @param string $email Correo electrónico a verificar.
     *
     * @return bool true si existe, false si no existe.
     */
    public function existeEmail(int $tenantId, string $email): bool
    {
        // Si no existe conexión con la base de datos,
        // no podemos realizar la validación.
        if ($this->db === null) {
            return false;
        }

        // Buscamos un usuario que pertenezca a la empresa indicada
        // y tenga exactamente el mismo correo.
        $sql = "SELECT id
                FROM users
                WHERE tenant_id = :tenant_id
                AND email = :email
                LIMIT 1";

        // Preparamos la consulta para utilizar parámetros.
        $stmt = $this->db->prepare($sql);

        // Ejecutamos la consulta enviando los valores correspondientes.
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':email' => $email
        ]);

        // Si encontramos un registro, el correo ya existe.
        // Si no encontramos ninguno, el correo está disponible.
        return $stmt->fetch() !== false;
    }


    /**
     * Crea un usuario nuevo.
     *
     * Este método mantiene la compatibilidad con el código existente,
     * ya que devuelve únicamente true o false.
     *
     * Internamente utiliza crearConId(), que realiza la inserción
     * y devuelve el ID generado.
     *
     * @param int $tenantId ID de la empresa.
     * @param string $name Nombre del usuario.
     * @param string $email Correo electrónico.
     * @param string $password Contraseña sin cifrar.
     *
     * @return bool true si el usuario fue creado correctamente.
     */
    public function crear(
        int $tenantId,
        string $name,
        string $email,
        string $password
    ): bool {
        // Llamamos al método que realiza la creación y obtiene el ID.
        //
        // Si devuelve un ID válido, significa que la creación fue exitosa.
        // Si devuelve null, significa que ocurrió un error.
        return $this->crearConId(
            $tenantId,
            $name,
            $email,
            $password
        ) !== null;
    }


    /**
     * Crea un usuario y devuelve el ID generado por MariaDB.
     *
     * Este método será utilizado posteriormente por UserController
     * durante la creación transaccional de usuarios.
     *
     * El flujo será:
     *
     * 1. Crear usuario.
     * 2. Obtener su ID.
     * 3. Asignarle un rol.
     * 4. Si todo funciona -> COMMIT.
     * 5. Si algo falla -> ROLLBACK.
     *
     * @param int $tenantId ID de la empresa.
     * @param string $name Nombre del usuario.
     * @param string $email Correo electrónico.
     * @param string $password Contraseña sin cifrar.
     *
     * @return int|null ID del usuario creado o null si ocurrió un error.
     */
    public function crearConId(
        int $tenantId,
        string $name,
        string $email,
        string $password
    ): ?int {
        // Si no tenemos conexión con la base de datos,
        // no podemos crear el usuario.
        if ($this->db === null) {
            return null;
        }

        // Generamos el hash seguro de la contraseña.
        //
        // La contraseña original nunca se guarda directamente
        // en la base de datos.
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Si PHP no pudo generar el hash,
        // cancelamos la creación.
        if ($passwordHash === false) {
            return null;
        }

        // Sentencia SQL para insertar el nuevo usuario.
        //
        // El estado inicial será ACTIVE.
        $sql = "INSERT INTO users
                    (tenant_id, name, email, password, status)
                VALUES
                    (:tenant_id, :name, :email, :password, 'ACTIVE')";

        try {

            // Preparamos la consulta SQL.
            $stmt = $this->db->prepare($sql);

            // Ejecutamos el INSERT utilizando parámetros.
            $ejecutado = $stmt->execute([
                ':tenant_id' => $tenantId,
                ':name' => $name,
                ':email' => $email,
                ':password' => $passwordHash
            ]);

            // Si el INSERT no se ejecutó correctamente,
            // informamos el fallo devolviendo null.
            if (!$ejecutado) {
                return null;
            }

            // Obtenemos el ID generado automáticamente por MariaDB.
            //
            // Este ID será necesario para poder asignarle el rol
            // al usuario recién creado.
            return (int)$this->db->lastInsertId();

        } catch (PDOException $e) {

            // Si MariaDB genera una excepción, devolvemos null.
            //
            // El Controller será responsable posteriormente de realizar
            // el ROLLBACK cuando esta operación forme parte de una
            // transacción.
            return null;
        }
    }
}