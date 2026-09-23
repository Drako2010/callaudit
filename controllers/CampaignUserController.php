<?php

require_once __DIR__ . '/../models/CampaignUser.php';
require_once __DIR__ . '/../models/Campaign.php';

class CampaignUserController
{
    private CampaignUser $campaignUser;

    public function __construct()
    {
        $this->campaignUser = new CampaignUser();
    }

    /**
     * Listar agentes asignados a una campaña.
     */
    public function index(
        int $tenantId,
        int $campaignId
    ): array {
        return $this->campaignUser->listarPorCampania(
            $tenantId,
            $campaignId
        );
    }

    /**
     * Listar agentes disponibles para asignar.
     */
    public function agentesDisponibles(
        int $tenantId,
        int $campaignId
    ): array {
        return $this->campaignUser->listarAgentesDisponibles(
            $tenantId,
            $campaignId
        );
    }

    /**
     * Asignar un agente a una campaña.
     */
    public function store(
        int $tenantId,
        int $campaignId,
        int $userId
    ): array {

        /*
         * Verificar que la campaña pertenezca
         * al tenant autenticado.
         */
        $campaign = new Campaign();

        $datosCampania = $campaign->obtenerPorId(
            $tenantId,
            $campaignId
        );

        if ($datosCampania === null) {
            return [
                'success' => false,
                'message' => 'La campaña no pertenece a esta empresa.'
            ];
        }

        /*
         * Verificar que el usuario sea:
         * - del mismo tenant
         * - activo
         * - AGENTE
         * - no asignado previamente
         */
        if (
            !$this->campaignUser->esAgenteDisponible(
                $tenantId,
                $campaignId,
                $userId
            )
        ) {
            return [
                'success' => false,
                'message' => 'El usuario no es un agente disponible para esta campaña.'
            ];
        }

        /*
         * Verificar nuevamente que no exista
         * una asignación duplicada.
         */
        if (
            $this->campaignUser->existeAsignacion(
                $tenantId,
                $campaignId,
                $userId
            )
        ) {
            return [
                'success' => false,
                'message' => 'El usuario ya está asignado a esta campaña.'
            ];
        }

        /*
         * Crear asignación.
         */
        $resultado = $this->campaignUser->asignar(
            $tenantId,
            $campaignId,
            $userId
        );

        if (!$resultado) {
            return [
                'success' => false,
                'message' => 'No se pudo asignar el usuario a la campaña.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Usuario asignado correctamente.'
        ];
    }

    /**
     * Cambiar estado de una asignación.
     */
    public function cambiarEstado(
        int $tenantId,
        int $campaignUserId,
        string $status
    ): array {

        if (!in_array(
            $status,
            ['ACTIVE', 'INACTIVE'],
            true
        )) {
            return [
                'success' => false,
                'message' => 'Estado no válido.'
            ];
        }

        $resultado = $this->campaignUser->cambiarEstado(
            $tenantId,
            $campaignUserId,
            $status
        );

        if (!$resultado) {
            return [
                'success' => false,
                'message' => 'No se encontró la asignación.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Estado actualizado correctamente.'
        ];
    }
}