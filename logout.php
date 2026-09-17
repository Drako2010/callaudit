<?php

require_once __DIR__ . '/services/SessionService.php';

$session = new SessionService();

$session->cerrarSesion();

header('Location: login.php');
exit;