<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'No autorizado']);
  exit;
}

require "config/conexion.php";
require "helpers/periodo.php";

$id = (int)($_POST['id_periodo'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'ID inválido']);
  exit;
}

$periodo = get_periodo_by_id($pdo, $id);
if (!$periodo) {
  http_response_code(404);
  echo json_encode(['success' => false, 'message' => 'Período no existe']);
  exit;
}

// ✅ Guardar selección global
$_SESSION['periodo_activo'] = (int)$periodo['id_periodo'];

echo json_encode([
  'success' => true,
  'message' => 'Período cambiado',
  'periodo'  => [
    'id'     => (int)$periodo['id_periodo'],
    'nombre' => $periodo['nombre'],
    'estado' => $periodo['estado'],
  ]
]);
