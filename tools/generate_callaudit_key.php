<?php
/** Ejecutar por CLI desde cualquier carpeta. No ejecutar por navegador. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo CLI.\n"); }
$dir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'private';
$file = $dir . DIRECTORY_SEPARATOR . 'callaudit.key';
if (is_file($file)) { fwrite(STDERR, "La clave ya existe; no se sobrescribió: $file\nGuárdala en un lugar seguro antes de cualquier cambio.\n"); exit(1); }
if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) { fwrite(STDERR, "No se pudo crear el directorio externo: $dir\n"); exit(1); }
$key = base64_encode(random_bytes(32)) . PHP_EOL;
if (file_put_contents($file, $key, LOCK_EX) === false) { fwrite(STDERR, "No se pudo escribir el archivo de clave.\n"); exit(1); }
@chmod($file, 0600);
echo "Clave creada fuera del proyecto: $file\nCopia de seguridad segura obligatoria. No subas este archivo a Git.\n";
