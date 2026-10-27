<?php
header('Content-Type: text/plain; charset=utf-8');

$ffmpeg = 'D:\\tools\\ffmpeg\\bin\\ffmpeg.exe';

echo "PHP: " . PHP_VERSION . PHP_EOL;
echo "proc_open: ";
var_dump(function_exists('proc_open'));

echo "FFmpeg existe: ";
var_dump(is_file($ffmpeg));

if (!function_exists('proc_open') || !is_file($ffmpeg)) {
    exit("ERROR: revisar proc_open o la ruta de FFmpeg.");
}

$process = proc_open(
    '"' . $ffmpeg . '" -version',
    [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ],
    $pipes
);

if (!is_resource($process)) {
    exit("ERROR: PHP no pudo iniciar FFmpeg.");
}

fclose($pipes[0]);
$output = stream_get_contents($pipes[1]);
$error = stream_get_contents($pipes[2]);

fclose($pipes[1]);
fclose($pipes[2]);

$code = proc_close($process);

echo PHP_EOL . "SALIDA:" . PHP_EOL . $output;
echo PHP_EOL . "ERRORES:" . PHP_EOL . $error;
echo PHP_EOL . "CODIGO: " . $code;