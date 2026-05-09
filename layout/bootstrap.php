<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
  header("Location: /auth/login.php");
  exit;
}

require __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../helpers/periodo.php";
require_once __DIR__ . "/../auditoria_helper.php"; // ← AGREGA ESTA LÍNEA

// ✅ Resolver período activo global
global $id_periodo_actual;
$periodoSeleccionado = null;
if (!empty($_SESSION['periodo_activo'])) {
  $periodoSeleccionado = get_periodo_by_id($pdo, (int)$_SESSION['periodo_activo']);
}
if (!$periodoSeleccionado) {
  $periodoSeleccionado = get_periodo_abierto($pdo);
}
if (!$periodoSeleccionado) {
  $todos = get_all_periodos($pdo);
  $periodoSeleccionado = $todos[0] ?? null;
}
$id_periodo_actual = $periodoSeleccionado ? (int)$periodoSeleccionado['id_periodo'] : null;
if ($periodoSeleccionado) {
  $_SESSION['periodo_activo'] = $periodoSeleccionado['id_periodo'];
}