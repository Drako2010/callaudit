<?php
// Modelos
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Tenant.php';

class UserController
{
    // Propiedades de los modelos
    private User $user;
    private Tenant $tenant;

    public function __construct()
    {
        $this->user = new User();
        $this->tenant = new Tenant();
    }

    public function index(): array
    {
        return $this->user->listar();
    }

    public function store(
        int $tenantId,
        string $name,
        string $email,
        string $password
    ): array {
        $name = trim($name);
        $email = trim($email);
        $password = trim($password);

        if ($tenantId <= 0) {
            return [
                'success' => false,
                'message' => 'La empresa es obligatoria.'
            ];
        }

        $tenant = $this->tenant->obtenerPorId($tenantId);

        if ($tenant === null) {
            return [
                'success' => false,
                'message' => 'La empresa seleccionada no existe.'
            ];
        }

        if ($tenant['status'] !== 'ACTIVE') {
            return [
                'success' => false,
                'message' => 'La empresa seleccionada está inactiva.'
            ];
        }

        if ($name === '') {
            return [
                'success' => false,
                'message' => 'El nombre del usuario es obligatorio.'
            ];
        }

        if ($email === '') { // comprueba que se haya enviado un correo.
            return [
                'success' => false,
                'message' => 'El correo electrónico es obligatorio.'
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // comprueba que tenga formato de correo válido.
            return [
                'success' => false,
                'message' => 'El correo electrónico no es válido.'
            ];
        }

        if ($password === '') {
            return [
                'success' => false,
                'message' => 'La contraseña es obligatoria.'
            ];
        }

        if (strlen($password) < 8) { // establece nuestra primera regla de seguridad para contraseñas.
            return [
                'success' => false,
                'message' => 'La contraseña debe tener al menos 8 caracteres.'
            ];
        }

        if ($this->user->existeEmail($tenantId, $email)) { // comprueba que no exista otro usuario con el mismo correo dentro de esa empresa.
            return [
                'success' => false,
                'message' => 'El correo ya está registrado en esta empresa.'
            ];
        }

        $creado = $this->user->crear(
            $tenantId,
            $name,
            $email,
            $password
        );

        if (!$creado) {
            return [
                'success' => false,
                'message' => 'No se pudo crear el usuario.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Usuario creado correctamente.'
        ];
    }
}