<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/auth.php';

if (!verificarSesion()) {
    header('Location: login.php');
    exit;
}

procesarLogout();
