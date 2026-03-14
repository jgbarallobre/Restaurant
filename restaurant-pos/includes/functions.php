<?php
/**
 * Funciones Globales de Moneda
 * Sistema POS Restaurante
 */

require_once __DIR__ . '/config/tasa.php';

/**
 * Convierte dólares a bolívares
 */
function usdToBs($amount, $rate = null) {
    if ($rate === null) {
        $rate = obtenerTasaActual();
    }
    return round($amount * $rate, 2);
}

/**
 * Convierte bolívares a dólares
 */
function bsToUsd($amount, $rate = null) {
    if ($rate === null) {
        $rate = obtenerTasaActual();
    }
    if ($rate == 0) return 0;
    return round($amount / $rate, 2);
}

/**
 * Formatea precio en Bs con símbolo
 */
function formatoBs($monto) {
    return number_format($monto, 2, ',', '.') . ' Bs';
}

/**
 * Formatea precio en USD con símbolo
 */
function formatoUsd($monto) {
    return '$' . number_format($monto, 2, '.', ',');
}

/**
 * Formatea precio mostrando ambos valores
 */
function formatoPrecio($precioUsd, $rate = null) {
    $bs = usdToBs($precioUsd, $rate);
    return formatoBs($bs) . ' (' . formatoUsd($precioUsd) . ')';
}

/**
 * Formatea precio solo Bs a partir de USD
 */
function formatoPrecioBs($precioUsd, $rate = null) {
    $bs = usdToBs($precioUsd, $rate);
    return formatoBs($bs);
}

/**
 * Redondea monto para evitar decimales extra
 */
function redondearMonto($monto, $decimals = 2) {
    return round($monto, $decimals);
}
