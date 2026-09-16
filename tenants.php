<?php
// Este archivo se encarga de mostrar el listado
//Cargamos el controlador
require_once __DIR__ . '/controllers/TenantController.php';

$controller = new TenantController();

// Obtiene las empresas
$tenants = $controller->index();

// Finalmente manda los datos a la vista.
require_once __DIR__ . '/views/tenants/index.php';