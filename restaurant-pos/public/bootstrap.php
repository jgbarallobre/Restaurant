<?php
/**
 * Autoload y Bootstrap del Sistema
 * Sistema POS Restaurante
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';

spl_autoload_register(function ($class) {
    $prefixes = [
        'App\\Controllers\\' => __DIR__ . '/../controllers/',
        'App\\Models\\' => __DIR__ . '/../models/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

function vista($vista, $datos = []) {
    extract($datos);
    $rutaVista = __DIR__ . '/../views/' . $vista . '.php';
    
    if (file_exists($rutaVista)) {
        require $rutaVista;
    } else {
        echo "Vista no encontrada: {$vista}";
    }
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirect($ruta) {
    header("Location: " . $ruta);
    exit;
}
