<?php

/*
 * Cada clave debe coincidir exactamente con audit_jobs.job_type.
 *
 * Registrar aquí únicamente procesos cuya lógica real ya esté implementada.
 * No registrar un tipo solo para que el trabajador lo marque como completado.
 */

return [
    // Ejemplo de registro:
    //
    // 'TIPO_REAL_DEL_TRABAJO' => function (array $job): bool {
    //     $tenantId = (int) $job['tenant_id'];
    //     $entityId = (int) $job['entity_id'];
    //     $payload = $job['payload'] ?? [];
    //
    //     // Ejecutar aquí la lógica real y validar el resultado.
    //     // Lanzar RuntimeException si no se pudo completar.
    //
    //     return true;
    // },
];
