<?php

require_once __DIR__ . '/models/Tenant.php';

$tenant = new Tenant();

$tenants = $tenant->listar();

echo '<pre>';

print_r($tenants);

echo '</pre>';