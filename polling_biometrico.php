<?php
// ============================================================
// polling_biometrico.php
// Recibe marcaciones del Bridge local y las guarda en BD
// ✅ Autenticación por token — NO requiere sesión PHP
// ============================================================

// ── Token secreto (debe coincidir con el bridge HTML) ────────
define('BIO_TOKEN', 'SantaLucia2026_Bio#Token');

// ── Capturar output de bootstrap para que no rompa el JSON ───
ob_start();
require __DIR__ . "/layout/bootstrap.php";
ob_clean();

header('Content-Type: application/json; charset=utf-8');

function jsonSalir(array $d): void { ob_clean(); echo json_encode($d); exit; }

// ── Validar token (viene en header X-Bio-Token o en el body) ─
$tokenHeader = $_SERVER['HTTP_X_BIO_TOKEN'] ?? '';
$raw         = file_get_contents('php://input');
$data        = json_decode($raw, true) ?? [];
$tokenBody   = $data['token'] ?? '';

if ($tokenHeader !== BIO_TOKEN && $tokenBody !== BIO_TOKEN) {
    jsonSalir(['ok' => false, 'msg' => 'Token inválido']);
}

// ── Log dir ───────────────────────────────────────────────────
$log_dir = __DIR__ . '/logs/';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);

$conv_id = intval($_GET['conv_id'] ?? $data['conv_id'] ?? 0);

// Log para debug
$log = date('Y-m-d H:i:s') . " | conv_id=$conv_id | " . substr($raw, 0, 300) . "\n";
file_put_contents($log_dir . 'polling_' . date('Y-m-d') . '.log', $log, FILE_APPEND);

if (!$conv_id) jsonSalir(['ok' => false, 'msg' => 'Falta conv_id']);

$marcaciones = $data['marcaciones'] ?? [];
if (empty($marcaciones)) {
    jsonSalir(['ok' => true, 'msg' => 'Sin marcaciones', 'registrados' => 0, 'ya_existian' => 0]);
}

// ── Verificar que la convocatoria existe ─────────────────────
try {
    $stConv = $pdo->prepare("SELECT id, titulo FROM convocatorias WHERE id = ?");
    $stConv->execute([$conv_id]);
    $conv = $stConv->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    jsonSalir(['ok' => false, 'msg' => 'Error DB: ' . $e->getMessage()]);
}

if (!$conv) jsonSalir(['ok' => false, 'msg' => "Convocatoria #$conv_id no encontrada"]);

$registrados = 0;
$ya_existian = 0;
$errores     = [];

foreach ($marcaciones as $m) {
    $employeeNo = trim($m['employeeNo'] ?? '');
    $nombre_bio = trim($m['nombre']     ?? '');

    if (empty($employeeNo)) continue;

    $emp_limpio = ltrim($employeeNo, '0') ?: '0';
    $socio = null;

    // 1. Por identificacion exacta (cédula sin ceros)
    if (!$socio && is_numeric($emp_limpio)) {
        $st = $pdo->prepare("SELECT id_socio, nombre_completo, identificacion FROM socios WHERE identificacion = ? AND estado = 'activo' LIMIT 1");
        $st->execute([$emp_limpio]);
        $socio = $st->fetch(PDO::FETCH_ASSOC);
    }

    // 2. Por employeeNo tal como viene
    if (!$socio) {
        $st = $pdo->prepare("SELECT id_socio, nombre_completo, identificacion FROM socios WHERE identificacion = ? AND estado = 'activo' LIMIT 1");
        $st->execute([$employeeNo]);
        $socio = $st->fetch(PDO::FETCH_ASSOC);
    }

    // 3. Por biometrico_id
    if (!$socio) {
        try {
            $st = $pdo->prepare("SELECT id_socio, nombre_completo, identificacion FROM socios WHERE biometrico_id = ? AND estado = 'activo' LIMIT 1");
            $st->execute([$employeeNo]);
            $socio = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    }

    // 4. Por numero_socio
    if (!$socio && strlen($emp_limpio) >= 1) {
        try {
            $st = $pdo->prepare("SELECT id_socio, nombre_completo, identificacion FROM socios WHERE numero_socio = ? AND estado = 'activo' LIMIT 1");
            $st->execute([$emp_limpio]);
            $socio = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    }

    // 5. Por nombre del biométrico
    if (!$socio && !empty($nombre_bio) && strlen($nombre_bio) > 3) {
        $partes  = explode(' ', strtoupper(trim($nombre_bio)));
        $primer  = $partes[0] ?? '';
        $segundo = $partes[1] ?? '';
        if ($primer && $segundo) {
            $st = $pdo->prepare("SELECT id_socio, nombre_completo, identificacion FROM socios WHERE UPPER(nombre_completo) LIKE ? AND UPPER(nombre_completo) LIKE ? AND estado = 'activo' LIMIT 1");
            $st->execute(["%$primer%", "%$segundo%"]);
            $socio = $st->fetch(PDO::FETCH_ASSOC);
        } elseif ($primer) {
            $st = $pdo->prepare("SELECT id_socio, nombre_completo, identificacion FROM socios WHERE UPPER(nombre_completo) LIKE ? AND estado = 'activo' LIMIT 1");
            $st->execute(["%$primer%"]);
            $socio = $st->fetch(PDO::FETCH_ASSOC);
        }
    }

    // 6. Por id_socio secuencial
    if (!$socio && is_numeric($emp_limpio)) {
        $st = $pdo->prepare("SELECT id_socio, nombre_completo, identificacion FROM socios WHERE id_socio = ? AND estado = 'activo' LIMIT 1");
        $st->execute([(int)$emp_limpio]);
        $socio = $st->fetch(PDO::FETCH_ASSOC);
    }

    if (!$socio) {
        $errores[] = "No encontrado: employeeNo=$employeeNo nombre='$nombre_bio'";
        file_put_contents($log_dir . 'polling_' . date('Y-m-d') . '.log',
            date('Y-m-d H:i:s') . " | NO ENCONTRADO: emp=$employeeNo nombre=$nombre_bio\n", FILE_APPEND);
        continue;
    }

    // ── Registrar asistencia ─────────────────────────────────
    try {
        $ins = $pdo->prepare("
            INSERT INTO conv_asistencia (convocatoria_id, id_socio, hora_registro, metodo, registrado_por)
            VALUES (?, ?, NOW(), 'biometrico', NULL)
            ON DUPLICATE KEY UPDATE hora_registro = hora_registro
        ");
        $ins->execute([$conv_id, $socio['id_socio']]);

        if ($ins->rowCount() > 0) {
            $registrados++;
            file_put_contents($log_dir . 'polling_' . date('Y-m-d') . '.log',
                date('Y-m-d H:i:s') . " | REGISTRADO: {$socio['nombre_completo']} ({$socio['identificacion']}) → Conv #{$conv_id}\n", FILE_APPEND);
        } else {
            $ya_existian++;
            file_put_contents($log_dir . 'polling_' . date('Y-m-d') . '.log',
                date('Y-m-d H:i:s') . " | YA EXISTE: {$socio['nombre_completo']} → Conv #{$conv_id}\n", FILE_APPEND);
        }
    } catch (PDOException $e) {
        $errores[] = "BD error para {$socio['nombre_completo']}: " . $e->getMessage();
    }
}

// ── Totales actuales ─────────────────────────────────────────
$stC = $pdo->prepare("SELECT COUNT(*) FROM conv_asistencia WHERE convocatoria_id = ?");
$stC->execute([$conv_id]);
$presentes = (int)$stC->fetchColumn();
$total     = (int)$pdo->query("SELECT COUNT(*) FROM socios WHERE estado='activo'")->fetchColumn();
$pct       = $total > 0 ? round(($presentes / $total) * 100, 1) : 0;

jsonSalir([
    'ok'          => true,
    'registrados' => $registrados,
    'ya_existian' => $ya_existian,
    'presentes'   => $presentes,
    'total'       => $total,
    'porcentaje'  => $pct,
    'errores'     => $errores,
    'conv'        => $conv['titulo'],
]);