<?php

// Carga el modelo Tenant
require_once __DIR__ . '/../models/Tenant.php';

class TenantController
{
    private Tenant $tenant;

    public function __construct()
    {
        $this->tenant = new Tenant();
    }

/*
El Controller dice:

Necesito la lista de empresas.

Y delega el trabajo al modelo:   $this->tenant->listar();
*/
    public function index(): array
    {
        return $this->tenant->listar();
    }

// Esta es la parte encargada de crear empresas.
    public function store(string $name, string $slug): array
    {
        // elimina espacios innecesarios.
        $name = trim($name);
        $slug = trim($slug);

        if ($name === '') { // comprueba que haya nombre.
            return [
                'success' => false,
                'message' => 'El nombre de la empresa es obligatorio.'
            ];
        }

        if ($slug === '') { // comprueba que haya slug.
            return [
                'success' => false,
                'message' => 'El slug de la empresa es obligatorio.'
            ];
        }

        if ($this->tenant->existeSlug($slug)) { // Aquí pregunta al modelo: ¿Ya existe este slug? 
            // si ya existe retorna
            return [
                'success' => false,
                'message' => 'El slug ya está registrado.'
            ];
        }

// Si todo está correcto: el modelo realiza el INSERT.
        $creado = $this->tenant->crear($name, $slug);

// Si esta vacio: con errores
        if (!$creado) {
            return [
                'success' => false,
                'message' => 'No se pudo crear la empresa.'
            ];
        }

// despues del insert
        return [
            'success' => true,
            'message' => 'Empresa creada correctamente.'
        ];
    }
}