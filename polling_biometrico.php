<?php
// ============================================================
// polling_biometrico.php
// Puente entre el biométrico Hikvision DS-K1T321 y tu BD
// La página de asistencia llama este PHP cada 10 segundos
// Este PHP llama al biométrico por su IP local y guarda los registros
//
// IMPORTANTE: Este script debe ejecutarse desde la misma red
// donde está el biométrico, O desde un servidor local.
// Si el servidor está en Hostinger (internet) y el biométrico
// está en la red local Starlink, este puente NO puede alcanzar
// la IP 192.0.0.64 directamente.
//
// Solución: usar el script local_bridge.php en una PC de la red
// ============================================================
if (session_status()===PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
    exit;
}

require __DIR__ . "/layout/bootstrap.php";
header('Content-Type: application/json; charset=utf-8');

$conv_id = intval($_GET['conv_id'] ?? $_POST['conv_id'] ?? 0);
if (!$conv_id) {
    echo json_encode(['ok'=>false,'msg'=>'conv_id requerido']);
    exit;
}

// ── Verificar convocatoria activa ────────────────────────────
$stC = $pdo->prepare("SELECT id,titulo,estado FROM convocatorias WHERE id=? AND estado='activa'");
$stC->execute([$conv_id]);
$conv = $stC->fetch();
if (!$conv) {
    echo json_encode(['ok'=>false,'msg'=>'Convocatoria no activa']);
    exit;
}

// ── Leer marcaciones enviadas por el bridge local ────────────
// El bridge local (local_bridge.php corriendo en la PC de la red)
// hace POST a este endpoint con los datos del biométrico
$data = json_decode(file_get_contents('php://input'), true) ?? [];

if (!empty($data['marcaciones'])) {
    $registrados  = 0;
    $ya_existian  = 0;
    $errores      = [];

    $stSocio = $pdo->prepare("
        SELECT id_socio, nombre_completo
        FROM socios
        WHERE identificacion = ? AND estado = 'activo'
    ");
    $stIns = $pdo->prepare("
        INSERT INTO conv_asistencia (convocatoria_id, id_socio, hora_registro, metodo, registrado_por)
        VALUES (?, ?, ?, 'biometrico', NULL)
        ON DUPLICATE KEY UPDATE hora_registro = hora_registro
    ");

    foreach ($data['marcaciones'] as $m) {
        $cedula = preg_replace('/\D/', '', trim($m['employeeNo'] ?? $m['cardNo'] ?? ''));
        $hora   = $m['time'] ?? $m['dateTime'] ?? date('Y-m-d H:i:s');

        // Normalizar formato de fecha
        try {
            $dt = new DateTime($hora);
            $hora_bd = $dt->format('Y-m-d H:i:s');
        } catch(Exception $e) {
            $hora_bd = date('Y-m-d H:i:s');
        }

        if (strlen($cedula) < 6) continue;

        $stSocio->execute([$cedula]);
        $socio = $stSocio->fetch();

        if (!$socio) {
            $errores[] = "Cédula $cedula no encontrada en socios";
            continue;
        }

        $stIns->execute([$conv_id, $socio['id_socio'], $hora_bd]);
        if ($stIns->rowCount() > 0) {
            $registrados++;
        } else {
            $ya_existian++;
        }
    }

    // Devolver estadísticas actualizadas
    $stStats = $pdo->prepare("SELECT COUNT(*) FROM conv_asistencia WHERE convocatoria_id=?");
    $stStats->execute([$conv_id]);
    $presentes = (int)$stStats->fetchColumn();
    $total     = (int)$pdo->query("SELECT COUNT(*) FROM socios WHERE estado='activo'")->fetchColumn();
    $pct       = $total > 0 ? round(($presentes/$total)*100,1) : 0;

    echo json_encode([
        'ok'          => true,
        'registrados' => $registrados,
        'ya_existian' => $ya_existian,
        'errores'     => $errores,
        'presentes'   => $presentes,
        'total'       => $total,
        'porcentaje'  => $pct,
    ]);
    exit;
}

// Si no hay body, devolver estadísticas actuales
$stStats = $pdo->prepare("SELECT COUNT(*) FROM conv_asistencia WHERE convocatoria_id=?");
$stStats->execute([$conv_id]);
$presentes = (int)$stStats->fetchColumn();
$total     = (int)$pdo->query("SELECT COUNT(*) FROM socios WHERE estado='activo'")->fetchColumn();
$pct       = $total > 0 ? round(($presentes/$total)*100,1) : 0;

echo json_encode([
    'ok'        => true,
    'presentes' => $presentes,
    'total'     => $total,
    'porcentaje'=> $pct,
]);
