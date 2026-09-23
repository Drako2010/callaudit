<?php

// Carga el modelo Campaign
require_once __DIR__ . '/../models/Campaign.php';

class CampaignController
{
    private Campaign $campaign;

    public function __construct()
    {
        $this->campaign = new Campaign();
    }

    /*
    El Controller dice:

    Necesito la lista de campañas de una empresa.

    Y delega el trabajo al modelo:
    $this->campaign->listar($tenantId);
    */
    public function index(int $tenantId): array
    {
        return $this->campaign->listar($tenantId);
    }

    // Esta es la parte encargada de crear campañas.
    public function store(
        int $tenantId,
        string $name,
        string $slug,
        ?string $description,
        ?string $startDate,
        ?string $endDate,
        ?int $createdBy
    ): array {

        // Elimina espacios innecesarios.
        $name = trim($name);
        $slug = trim($slug);

        if ($name === '') {
            return [
                'success' => false,
                'message' => 'El nombre de la campaña es obligatorio.'
            ];
        }

        if ($slug === '') {
            return [
                'success' => false,
                'message' => 'El slug de la campaña es obligatorio.'
            ];
        }

        if ($this->campaign->existeSlug($tenantId, $slug)) {
            return [
                'success' => false,
                'message' => 'El slug ya está registrado en esta empresa.'
            ];
        }

        // Validar fecha de inicio y fecha de fin.
        if ($startDate !== null && $startDate !== '') {
            $startDate = trim($startDate);
        } else {
            $startDate = null;
        }

        if ($endDate !== null && $endDate !== '') {
            $endDate = trim($endDate);
        } else {
            $endDate = null;
        }

        // Si ambas fechas existen, comprobar que sean coherentes.
        if ($startDate !== null && $endDate !== null) {

            if ($endDate < $startDate) {
                return [
                    'success' => false,
                    'message' => 'La fecha de fin no puede ser anterior a la fecha de inicio.'
                ];
            }
        }

        // Si está vacío, convertir descripción a NULL.
        if ($description !== null) {
            $description = trim($description);

            if ($description === '') {
                $description = null;
            }
        }

        // Si todo está correcto: el modelo realiza el INSERT.
        $creado = $this->campaign->crear(
            $tenantId,
            $name,
            $slug,
            $description,
            $startDate,
            $endDate,
            $createdBy
        );

        // Si hubo errores.
        if (!$creado) {
            return [
                'success' => false,
                'message' => 'No se pudo crear la campaña.'
            ];
        }

        // Después del INSERT.
        return [
            'success' => true,
            'message' => 'Campaña creada correctamente.'
        ];
    }

    // Esta es la parte encargada de actualizar una campaña.
    public function update(
        int $tenantId,
        int $id,
        string $name,
        string $slug,
        ?string $description,
        ?string $startDate,
        ?string $endDate
    ): array {

        // Elimina espacios innecesarios.
        $name = trim($name);
        $slug = trim($slug);

        if ($name === '') {
            return [
                'success' => false,
                'message' => 'El nombre de la campaña es obligatorio.'
            ];
        }

        if ($slug === '') {
            return [
                'success' => false,
                'message' => 'El slug de la campaña es obligatorio.'
            ];
        }

        // Comprobar que la campaña exista dentro de la empresa.
        $campaign = $this->campaign->obtenerPorId(
            $tenantId,
            $id
        );

        if ($campaign === null) {
            return [
                'success' => false,
                'message' => 'La campaña no existe.'
            ];
        }

        // Comprobar que el slug no pertenezca a otra campaña.
        if (
            $this->campaign->existeSlugExceptoId(
                $tenantId,
                $slug,
                $id
            )
        ) {
            return [
                'success' => false,
                'message' => 'El slug ya está registrado en esta empresa.'
            ];
        }

        // Validar fecha de inicio.
        if ($startDate !== null && $startDate !== '') {
            $startDate = trim($startDate);
        } else {
            $startDate = null;
        }

        // Validar fecha de fin.
        if ($endDate !== null && $endDate !== '') {
            $endDate = trim($endDate);
        } else {
            $endDate = null;
        }

        // Comprobar coherencia de fechas.
        if ($startDate !== null && $endDate !== null) {

            if ($endDate < $startDate) {
                return [
                    'success' => false,
                    'message' => 'La fecha de fin no puede ser anterior a la fecha de inicio.'
                ];
            }
        }

        // Si la descripción está vacía, guardar NULL.
        if ($description !== null) {

            $description = trim($description);

            if ($description === '') {
                $description = null;
            }
        }

        $actualizado = $this->campaign->actualizar(
            $tenantId,
            $id,
            $name,
            $slug,
            $description,
            $startDate,
            $endDate
        );

        if (!$actualizado) {
            return [
                'success' => false,
                'message' => 'No se pudo actualizar la campaña.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Campaña actualizada correctamente.'
        ];
    }

    // Cambia el estado de una campaña.    
    public function cambiarEstado(
            int $tenantId,
            int $id,
            string $status
        ): array {

            // Solo permitimos estos dos estados.
            if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {

                return [
                    'success' => false,
                    'message' => 'Estado de campaña no válido.'
                ];
            }

            // Comprobar que la campaña pertenece al tenant.
            $campaign = $this->campaign->obtenerPorId(
                $tenantId,
                $id
            );

            if ($campaign === null) {

                return [
                    'success' => false,
                    'message' => 'La campaña no existe.'
                ];
            }

            // Evitar realizar nuevamente el mismo cambio.
            if ($campaign['status'] === $status) {

                return [
                    'success' => false,
                    'message' => 'La campaña ya tiene ese estado.'
                ];
            }

            $actualizado = $this->campaign->cambiarEstado(
                $tenantId,
                $id,
                $status
            );

            if (!$actualizado) {

                return [
                    'success' => false,
                    'message' => 'No se pudo cambiar el estado de la campaña.'
                ];
            }

            if ($status === 'ACTIVE') {

                $mensaje = 'Campaña activada correctamente.';

            } else {

                $mensaje = 'Campaña desactivada correctamente.';
            }

            return [
                'success' => true,
                'message' => $mensaje
            ];
    }    

    // Este método permite obtener una campaña específica.
    public function show(int $tenantId, int $id): ?array
    {
        return $this->campaign->obtenerPorId($tenantId, $id);
    }
}