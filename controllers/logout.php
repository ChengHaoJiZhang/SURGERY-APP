<?php
// grupd/controllers/logout.php

// 1. Cargamos el controlador (que está en la misma carpeta)
require_once __DIR__ . '/LoginController.php';

// 2. Iniciamos el controlador
$controller = new LoginController();

// 3. Ejecutamos la función logout()
// Esto destruirá la sesión, guardará el log y redirigirá al Login automáticamente
$controller->logout();
?>