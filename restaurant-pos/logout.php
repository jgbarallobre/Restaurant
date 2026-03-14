<?php
require_once __DIR__ . '/app/config/session.php';
require_once __DIR__ . '/includes/auth.php';

if (!verificarSesion()) {
    header('Location: login.php');
    exit;
}

procesarLogout();
