<?php

require_once __DIR__ . '/../models/AuditSelectionPolicy.php';

/*
 * GENERATE_AUDIT_TASKS
 * entity_id: ID de audit_selection_policies.
 * payload.assigned_by: usuario solicitante.
 * payload.selected_call_id: llamada concreta para método MANUAL (opcional para otros métodos).
 * El tenant siempre procede del registro de la cola.
 */

return [
    'GENERATE_AUDIT_TASKS' => function (array $job): bool {
        $tenantId = (int) ($job['tenant_id'] ?? 0);
        $policyId = (int) ($job['entity_id'] ?? 0);
        $payload = $job['payload'] ?? [];
        $userId = (int) ($payload['assigned_by'] ?? 0);

        if ($tenantId <= 0 || $policyId <= 0 || $userId <= 0) {
            throw new RuntimeException('El trabajo no contiene tenant, política o usuario válidos.');
        }

        $selectedCallId = null;
        if (
            array_key_exists('selected_call_id', $payload) &&
            $payload['selected_call_id'] !== null &&
            $payload['selected_call_id'] !== ''
        ) {
            $validated = filter_var($payload['selected_call_id'], FILTER_VALIDATE_INT);
            if ($validated === false || $validated <= 0) {
                throw new RuntimeException('El ID de la llamada seleccionada no es válido.');
            }
            $selectedCallId = (int) $validated;
        }

        $model = new AuditSelectionPolicy();
        $result = $model->generarTareas(
            $tenantId,
            $policyId,
            $userId,
            $selectedCallId
        );

        if (empty($result['success'])) {
            throw new RuntimeException((string) ($result['message'] ?? 'No se pudieron generar las tareas.'));
        }

        error_log(sprintf(
            '[CallAudit] GENERATE_AUDIT_TASKS job=%d tenant=%d policy=%d selected_call=%d selected=%d created=%d skipped=%d',
            (int) ($job['id'] ?? 0),
            $tenantId,
            $policyId,
            $selectedCallId ?? 0,
            (int) ($result['selected'] ?? 0),
            (int) ($result['created'] ?? 0),
            (int) ($result['skipped'] ?? 0)
        ));

        return true;
    }
];
