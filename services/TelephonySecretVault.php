<?php
/**
 * Almacén cifrado de secretos para fuentes telefónicas.
 * Requiere OpenSSL. Clave de 32 bytes en base64 por variable de entorno
 * CALLAUDIT_SECRET_KEY o en un archivo externo a htdocs:
 * D:\\Xampp\\private\\callaudit.key (Windows XAMPP).
 */
class TelephonySecretVault
{
    private const CIPHER = 'aes-256-gcm';
    private const KEY_VERSION = 'v1';

    private function key(): string
    {
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('La extensión OpenSSL de PHP no está habilitada.');
        }

        $encoded = getenv('CALLAUDIT_SECRET_KEY');
        if (!is_string($encoded) || trim($encoded) === '') {
            $keyFile = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'private'
                . DIRECTORY_SEPARATOR . 'callaudit.key';
            if (is_file($keyFile) && is_readable($keyFile)) {
                $encoded = trim((string) file_get_contents($keyFile));
            }
        }

        if (!is_string($encoded) || trim($encoded) === '') {
            throw new RuntimeException(
                'No está configurada CALLAUDIT_SECRET_KEY ni existe el archivo de clave externo.'
            );
        }

        $key = base64_decode(trim($encoded), true);
        if (!is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException('La clave de CallAudit debe ser base64 válido de exactamente 32 bytes.');
        }
        return $key;
    }

    public function cifrar(array $secret): array
    {
        $plain = json_encode($secret, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($plain)) {
            throw new RuntimeException('No se pudieron serializar las credenciales.');
        }
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plain, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag, 'callaudit:telephony:v1', 16);
        if (!is_string($ciphertext) || strlen($tag) !== 16) {
            throw new RuntimeException('No se pudieron cifrar las credenciales.');
        }
        $envelope = json_encode([
            'v' => self::KEY_VERSION,
            'alg' => self::CIPHER,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($ciphertext),
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($envelope)) {
            throw new RuntimeException('No se pudo crear el contenedor cifrado.');
        }
        return ['blob' => $envelope, 'key_version' => self::KEY_VERSION];
    }

    public function descifrar(string $envelope): array
    {
        $box = json_decode($envelope, true);
        if (!is_array($box) || ($box['v'] ?? null) !== self::KEY_VERSION || ($box['alg'] ?? null) !== self::CIPHER) {
            throw new RuntimeException('El secreto guardado tiene una versión o formato no compatible.');
        }
        foreach (['iv', 'tag', 'data'] as $field) {
            if (!isset($box[$field]) || !is_string($box[$field])) {
                throw new RuntimeException('El secreto guardado está incompleto.');
            }
        }
        $iv = base64_decode($box['iv'], true);
        $tag = base64_decode($box['tag'], true);
        $ciphertext = base64_decode($box['data'], true);
        if (!is_string($iv) || strlen($iv) !== 12 || !is_string($tag) || strlen($tag) !== 16 || !is_string($ciphertext)) {
            throw new RuntimeException('El secreto guardado está dañado.');
        }
        $plain = openssl_decrypt($ciphertext, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag, 'callaudit:telephony:v1');
        if (!is_string($plain)) {
            throw new RuntimeException('No se pudieron descifrar las credenciales; verifica la clave configurada.');
        }
        $secret = json_decode($plain, true);
        if (!is_array($secret)) {
            throw new RuntimeException('Las credenciales descifradas no tienen un formato válido.');
        }
        return $secret;
    }
}
