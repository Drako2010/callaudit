<?php

/*
|--------------------------------------------------------------------------
| Prueba temporal de contraseña
|--------------------------------------------------------------------------
| Este archivo solamente comprueba si una contraseña introducida
| corresponde al hash almacenado en la base de datos.
|
| IMPORTANTE:
| No muestra ni modifica la contraseña almacenada.
*/

$hash = '$2y$...';

/*
|--------------------------------------------------------------------------
| Escribe aquí temporalmente la contraseña que estás intentando usar.
|--------------------------------------------------------------------------
*/

$password = '$2y$10$wvK8UTvcZtRFpNWSKEsvKOHJPyGHUJbtn7x.5xU3A7ccu5mvAtqqi';


/*
|--------------------------------------------------------------------------
| Verificación
|--------------------------------------------------------------------------
| password_verify() compara la contraseña en texto plano contra
| el hash sin necesidad de conocer el valor original.
*/

if (password_verify($password, $hash)) {

    echo 'CONTRASEÑA CORRECTA';

} else {

    echo 'CONTRASEÑA INCORRECTA';
}