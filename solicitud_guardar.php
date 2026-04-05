<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require "config/conexion.php";
require_once "helpers/periodo.php";

header('Content-Type: application/json');

// ── Obtener período abierto automáticamente ──────────────────────
$periodoAbierto = get_periodo_abierto($pdo);
if (!$periodoAbierto) {
    echo json_encode(['success' => false, 'message' => 'No hay un período abierto. Por favor abra un período antes de registrar solicitudes.']);
    exit;
}
$periodo_id = $periodoAbierto['id_periodo'];

// ── Validar campos requeridos ────────────────────────────────────
$identificacion    = trim($_POST['identificacion']    ?? '');
$nombres_completos = trim($_POST['nombres_completos'] ?? '');

if ($identificacion === '' || $nombres_completos === '') {
    echo json_encode(['success' => false, 'message' => 'La identificación y nombres son obligatorios.']);
    exit;
}

// ── Verificar duplicado en el mismo período ──────────────────────
$check = $pdo->prepare("SELECT id_solicitud FROM solicitud_ingreso WHERE identificacion = ? AND id_periodo = ?");
$check->execute([$identificacion, $periodo_id]);
if ($check->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Ya existe una solicitud con esa cédula en el período actual.']);
    exit;
}

// ── INSERT con id_periodo ────────────────────────────────────────
try {
    $sql = "INSERT INTO solicitud_ingreso (
                id_periodo,
                identificacion,
                nombres_completos,
                correo,
                celular,
                fecha_nacimiento,
                direccion,
                observaciones,
                estado_solicitud,
                fecha_solicitud
            ) VALUES (
                :id_periodo,
                :identificacion,
                :nombres_completos,
                :correo,
                :celular,
                :fecha_nacimiento,
                :direccion,
                :observaciones,
                'PENDIENTE',
                NOW()
            )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_periodo'        => $periodo_id,
        ':identificacion'    => $identificacion,
        ':nombres_completos' => $nombres_completos,
        ':correo'            => trim($_POST['correo']           ?? ''),
        ':celular'           => trim($_POST['celular']          ?? ''),
        ':fecha_nacimiento'  => $_POST['fecha_nacimiento']      ?: null,
        ':direccion'         => trim($_POST['direccion']        ?? ''),
        ':observaciones'     => trim($_POST['observaciones']    ?? ''),
    ]);

    $id = $pdo->lastInsertId();

    echo json_encode([
        'success'    => true,
        'message'    => 'Solicitud registrada correctamente en el período: ' . $periodoAbierto['nombre'],
        'id_solicitud' => $id,
        'periodo'    => $periodoAbierto['nombre'],
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
}