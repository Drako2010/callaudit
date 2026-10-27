
<?php

/*
 * Ejecutar desde consola:
 * php workers/audit_worker.php
 *
 * En Windows puede programarse con el Programador de tareas.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este proceso solo puede ejecutarse por CLI.\n");
}

require_once __DIR__ . '/../models/AuditJobQueue.php';

$handlersFile = __DIR__ . '/audit_job_handlers.php';

if (!is_file($handlersFile)) {
    fwrite(STDERR, "Falta workers/audit_job_handlers.php. No se procesaron trabajos.\n");
    exit(1);
}

$handlers = require $handlersFile;

if (!is_array($handlers) || !$handlers) {
    fwrite(STDERR, "No hay procesadores registrados. No se procesaron trabajos.\n");
    exit(1);
}

foreach ($handlers as $tipo => $handler) {
    if (!is_string($tipo) || !is_callable($handler)) {
        fwrite(STDERR, "La configuración de procesadores no es válida.\n");
        exit(1);
    }
}

$queue = new AuditJobQueue();

$hostname = gethostname() ?: 'callaudit-worker';
$workerId = substr($hostname . '-' . getmypid(), 0, 150);

$recuperados = $queue->recuperarBloqueados(15);

if ($recuperados > 0) {
    echo date('Y-m-d H:i:s') . " - Trabajos recuperados: $recuperados\n";
}

$maxTrabajosPorEjecucion = 25;
$procesados = 0;
$tiposRegistrados = array_keys($handlers);

while ($procesados < $maxTrabajosPorEjecucion) {
    $job = $queue->reclamar($tiposRegistrados, $workerId);

    if ($job === null) {
        break;
    }

    $procesados++;
    $jobId = (int) $job['id'];
    $tenantId = (int) $job['tenant_id'];
    $tipo = (string) $job['job_type'];

    echo date('Y-m-d H:i:s') .
        " - Procesando trabajo #$jobId, tipo=$tipo, empresa=$tenantId\n";

    try {
        /*
         * El procesador debe lanzar una excepción si el trabajo falla.
         * Solo se marca COMPLETED cuando termina correctamente.
         */
        $resultado = $handlers[$tipo]($job);

        if ($resultado === false) {
            throw new RuntimeException('El procesador indicó que el trabajo falló.');
        }

        if (!$queue->completar($tenantId, $jobId, $workerId)) {
            throw new RuntimeException(
                'No se pudo confirmar el trabajo; puede haber perdido su bloqueo.'
            );
        }

        echo "Trabajo #$jobId completado.\n";
    } catch (Throwable $e) {
        error_log(
            "[CallAudit Worker] job_id=$jobId tenant_id=$tenantId tipo=$tipo " .
            get_class($e) . ': ' . $e->getMessage()
        );

        $queue->fallar(
            $tenantId,
            $jobId,
            $workerId,
            'PROCESSING_ERROR',
            $e->getMessage()
        );

        echo "Trabajo #$jobId falló; se aplicó la política de reintentos.\n";
    }
}

echo date('Y-m-d H:i:s') . " - Fin. Trabajos reclamados: $procesados\n";
