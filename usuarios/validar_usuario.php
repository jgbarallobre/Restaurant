<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/config/session.php';

header('Content-Type: application/json');

$usuario = $_GET['usuario'] ?? '';

if (empty($usuario)) {
    echo json_encode(['existe' => false]);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT id FROM usuarios WHERE usuario = ?");
$stmt->execute([sanitizar($usuario)]);
$existe = $stmt->fetch() !== false;

echo json_encode(['existe' => $existe]);
