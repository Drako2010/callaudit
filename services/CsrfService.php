<?php

/*
|--------------------------------------------------------------------------
| Servicio CSRF
|--------------------------------------------------------------------------
| Este servicio protege las operaciones que modifican información
| mediante solicitudes POST.
|
| Utilizamos el patrón Synchronizer Token:
|
|     SESIÓN
|       ↓
|   CSRF TOKEN
|       ↓
|   FORMULARIO
|       ↓
|      POST
|       ↓
| VALIDACIÓN BACKEND
|
| El token se almacena en la sesión del usuario y nunca se obtiene
| desde datos enviados por el cliente.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/SessionService.php';

class CsrfService
{
    private SessionService $session;

    public function __construct()
    {
        $this->session = new SessionService();
    }

    /*
    |--------------------------------------------------------------------------
    | Obtener token CSRF
    |--------------------------------------------------------------------------
    | Generamos el token una sola vez por sesión.
    |
    | random_bytes() utiliza un generador criptográficamente seguro.
    */

    public function token(): string
    {
        $this->session->iniciar();

        if (
            !isset($_SESSION['csrf_token'])
            || !is_string($_SESSION['csrf_token'])
            || $_SESSION['csrf_token'] === ''
        ) {

            $_SESSION['csrf_token'] = bin2hex(
                random_bytes(32)
            );
        }

        return $_SESSION['csrf_token'];
    }

    /*
    |--------------------------------------------------------------------------
    | Validar token
    |--------------------------------------------------------------------------
    | El token recibido debe existir y coincidir exactamente con el
    | token almacenado en la sesión.
    |
    | hash_equals() evita comparaciones susceptibles a ataques de
    | timing.
    */

    public function validar(?string $token): bool
    {
        $this->session->iniciar();

        if (
            $token === null
            || $token === ''
            || !isset($_SESSION['csrf_token'])
            || !is_string($_SESSION['csrf_token'])
        ) {
            return false;
        }

        return hash_equals(
            $_SESSION['csrf_token'],
            $token
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validar solicitud POST
    |--------------------------------------------------------------------------
    | Este método simplifica la protección de formularios.
    |
    | Si el token falta o es incorrecto, la operación se detiene.
    */

    public function validarRequest(): void
    {
        $token = $_POST['csrf_token'] ?? null;

        if (!$this->validar($token)) {

            http_response_code(403);

            echo 'Solicitud no válida. Token CSRF incorrecto o ausente.';
            exit;
        }
    }
}