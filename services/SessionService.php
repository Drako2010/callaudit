<?php

class SessionService
{
    /**
     * Inicia la sesión si todavía no está iniciada.
     *
     * secure=false permite trabajar con HTTP en el entorno local.
     * En producción con HTTPS se debe cambiar a true.
     */
    public function iniciar(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'secure' => false,
                'samesite' => 'Lax'
            ]);

            session_start();
        }
    }

    /**
     * Registra una sesión autenticada.
     *
     * Si tenant_id es null, la sesión corresponde al ámbito global.
     * Si tiene un tenant_id, corresponde al ámbito de una empresa.
     */
    public function iniciarSesion(array $user): void
    {
        $this->iniciar();

        // Cambiar el identificador para reducir el riesgo de fijación de sesión.
        session_regenerate_id(true);

        // Eliminar cualquier selección pendiente anterior.
        unset($_SESSION['login_pendiente']);

        $_SESSION['user_id'] = (int) $user['id'];

        $_SESSION['tenant_id'] = isset($user['tenant_id'])
            ? (int) $user['tenant_id']
            : null;

        $_SESSION['name'] = $user['name'] ?? '';
        $_SESSION['email'] = $user['email'] ?? '';
        $_SESSION['status'] = $user['status'] ?? '';

        // Separar el acceso global del acceso a una empresa.
        $_SESSION['scope'] = $_SESSION['tenant_id'] === null
            ? 'GLOBAL'
            : 'TENANT';

        $_SESSION['authenticated'] = true;
    }

    /**
     * Guarda temporalmente las cuentas de empresa disponibles.
     *
     * Los datos se guardan en el servidor, no en campos ocultos
     * del formulario del navegador.
     */
    public function guardarLoginPendiente(array $empresas): void
    {
        $this->iniciar();

        $candidatas = [];

        foreach ($empresas as $empresa) {
            // Ignorar registros sin identificadores válidos.
            if (
                !isset($empresa['id'], $empresa['tenant_id'])
                || !is_numeric($empresa['id'])
                || !is_numeric($empresa['tenant_id'])
            ) {
                continue;
            }

            $candidatas[] = [
                'id' => (int) $empresa['id'],
                'tenant_id' => (int) $empresa['tenant_id'],
                'name' => (string) ($empresa['name'] ?? ''),
                'email' => (string) ($empresa['email'] ?? ''),
                'tenant_name' => (string) (
                    $empresa['tenant_name'] ?? 'Empresa'
                )
            ];
        }

        // Evitar guardar una selección ambigua o vacía.
        if (count($candidatas) < 2) {
            unset($_SESSION['login_pendiente']);
            return;
        }

        // Renovar el identificador de sesión antes de guardar el estado.
        session_regenerate_id(true);

        $_SESSION['login_pendiente'] = [
            'empresas' => $candidatas,
            'creado_en' => time()
        ];
    }

    /**
     * Recupera la selección pendiente si todavía es válida.
     *
     * La selección caduca después de cinco minutos.
     */
    public function obtenerLoginPendiente(): ?array
    {
        $this->iniciar();

        if (!isset($_SESSION['login_pendiente'])) {
            return null;
        }

        $pendiente = $_SESSION['login_pendiente'];

        if (
            !isset($pendiente['empresas'], $pendiente['creado_en'])
            || !is_array($pendiente['empresas'])
            || !is_numeric($pendiente['creado_en'])
        ) {
            $this->limpiarLoginPendiente();
            return null;
        }

        // La selección no debe mantenerse indefinidamente.
        if (time() - (int) $pendiente['creado_en'] > 300) {
            $this->limpiarLoginPendiente();
            return null;
        }

        if (count($pendiente['empresas']) < 2) {
            $this->limpiarLoginPendiente();
            return null;
        }

        return $pendiente['empresas'];
    }

    /**
     * Elimina la selección pendiente de empresa.
     */
    public function limpiarLoginPendiente(): void
    {
        $this->iniciar();

        unset($_SESSION['login_pendiente']);
    }

    /**
     * Comprueba si existe una sesión autenticada.
     */
    public function estaAutenticado(): bool
    {
        $this->iniciar();

        return isset($_SESSION['authenticated'])
            && $_SESSION['authenticated'] === true
            && isset($_SESSION['user_id']);
    }

    /**
     * Devuelve los datos básicos del usuario autenticado.
     */
    public function obtenerUsuario(): ?array
    {
        if (!$this->estaAutenticado()) {
            return null;
        }

        return [
            'id' => (int) $_SESSION['user_id'],
            'tenant_id' => $_SESSION['tenant_id'] ?? null,
            'name' => $_SESSION['name'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'status' => $_SESSION['status'] ?? '',
            'scope' => $_SESSION['scope'] ?? 'TENANT'
        ];
    }

    /**
     * Cierra la sesión y elimina sus datos y cookie.
     */
    public function cerrarSesion(): void
    {
        $this->iniciar();

        // Eliminar todos los datos almacenados en la sesión.
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}
