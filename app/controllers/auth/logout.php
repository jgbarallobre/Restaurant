<?php
require_once __DIR__ . '/../../public/bootstrap.php';

if (isLoggedIn()) {
    logger("Usuario " . $_SESSION['usuario'] . " cerró sesión");
}

session_destroy();
redireccionar('/restaurant-pos/public/index.php');
