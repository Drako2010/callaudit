<?php

require_once __DIR__ . '/config/database.php';

$database = new Database();

$connection = $database->connect();

if ($connection !== null) {
    echo "CONEXION EXITOSA";
} else {
    echo "ERROR DE CONEXION";
}