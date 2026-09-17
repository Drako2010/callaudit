<?php

require_once __DIR__ . '/controllers/UserController.php';

$controller = new UserController();

$users = $controller->index(); // Llama al objeto index del controlador UserController.php quien enviara la informacion de los usuarios obtenida desde el modelo

// y luego envía esa información a: require_once __DIR__ . '/views/users/index.php';
require_once __DIR__ . '/views/users/index.php';

// Por eso en la vista podemos utilizar: $users sin hacer ninguna consulta SQL desde la vista.

/*

El recorrido:  EN LA VISTA  :  require_once __DIR__ . '/views/users/index.php';

foreach ($users as $user)

genera una fila por cada usuario.

Y algo importante:

htmlspecialchars($user['name'])

protege la salida HTML frente a contenido que pudiera interpretarse como código HTML/JavaScript.

*/