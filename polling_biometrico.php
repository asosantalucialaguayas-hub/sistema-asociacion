<?php
// ============================================================
// polling_biometrico.php
// Recibe marcaciones del Bridge local y las guarda en BD
// ============================================================
require __DIR__ . "/layout/bootstrap.php";
header('Content-Type: application/json; charset=utf-8');

// Log dir
$log_dir = __DIR__ . '/logs/';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);

$raw     = file_get_contents('php://input');
$conv_id = intval($_GET['conv_id'] ?? $_POST['conv_id'] ?? 0);
$data    = json_decode($raw, true);

// Log para debug
$log = date('Y-m-d H:i:s') . " | conv_id=$conv_id | " . substr($raw, 0, 300) . "\n";
file_put_contents($log_dir . 'polling_' . date('Y-m-d') . '.log', $log, FILE_APPEND);

if (!$conv_id) {
    echo json_encode(['ok' => false, 'msg' => 'Falta conv_id']);
    exit;
}

$marcaciones = $data['marcaciones'] ?? [];
if (empty($marcaciones)) {
    echo json_encode(['ok' => true, 'msg' => 'Sin marcaciones', 'registrados' => 0, 'ya_existian' => 0]);
    exit;
}

// ── Verificar que la convocatoria existe ─────────────────────
$stConv = $pdo->prepare("SELECT id, titulo FROM convocatorias WHERE id = ?");
$stConv->execute([$conv_id]);
$conv = $stConv->fetch(PDO::FETCH_ASSOC);
if (!$conv) {
    echo json_encode(['ok' => false, 'msg' => "Convocatoria #$conv_id no encontrada"]);
    exit;
}

$registrados = 0;
$ya_existian = 0;
$errores     = [];

foreach ($marcaciones as $m) {
    $employeeNo = trim($m['employeeNo'] ?? '');
    $nombre_bio = trim($m['nombre'] ?? '');
    $time       = $m['time'] ?? date('Y-m-d H:i:s');

    if (empty($employeeNo)) continue;

    // Limpiar ceros a la izquierda para comparar como cédula
    $emp_limpio = ltrim($employeeNo, '0') ?: '0';

    $socio = null;

    // ── Estrategia de búsqueda (en orden de prioridad) ───────

    // 1. Buscar por identificacion exacta (cédula)
    if (!$socio && is_numeric($emp_limpio)) {
        $st = $pdo->prepare("SELECT id_socio, nombre_completo, identificacion FROM socios WHERE identificacion = ? AND estado = 'activo' LIMIT 1");
        $st->execute([$emp_limpio]);
        $socio = $st->fetch(PDO::FETCH_ASSOC);
    }

    // 2. Buscar por el employeeNo tal como viene (con ceros)
    if (!$socio) {
        $st = $pdo->prepare("SELECT id_socio, nombre_completo, identificacion FROM socios WHERE identificacion = ? AND estado = 'activo' LIMIT 1");
        $st->execute([$employeeNo]);
        $socio = $st->fetch(PDO::FETCH_ASSOC);
    }

    // 3. Buscar por campo biometrico_id si existe
    if (!$socio) {
        try {
            $st = $pdo->prepare("SELECT id_socio, nombre_completo, identificacion FROM socios WHERE biometrico_id = ? AND estado = 'activo' LIMIT 1");
            $st->execute([$employeeNo]);
            $socio = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) { /* columna puede no existir */ }
    }

    // 4. Buscar por número de empleado (los primeros dígitos significativos)
    if (!$socio && strlen($emp_limpio) >= 1) {
        try {
            $st = $pdo->prepare("SELECT id_socio, nombre_completo, identificacion FROM socios WHERE numero_socio = ? AND estado = 'activo' LIMIT 1");
            $st->execute([$emp_limpio]);
            $socio = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) { /* columna puede no existir */ }
    }

    // 5. Buscar por nombre del biométrico (si el biométrico devuelve nombre)
    if (!$socio && !empty($nombre_bio) && strlen($nombre_bio) > 3) {
        // Búsqueda flexible por nombre completo
        $partes = explode(' ', strtoupper(trim($nombre_bio)));
        $primer = $partes[0] ?? '';
        $segundo = $partes[1] ?? '';

        if ($primer && $segundo) {
            $st = $pdo->prepare("
                SELECT id_socio, nombre_completo, identificacion
                FROM socios
                WHERE UPPER(nombre_completo) LIKE ? AND UPPER(nombre_completo) LIKE ?
                AND estado = 'activo'
                LIMIT 1
            ");
            $st->execute(["%$primer%", "%$segundo%"]);
            $socio = $st->fetch(PDO::FETCH_ASSOC);
        } elseif ($primer) {
            $st = $pdo->prepare("SELECT id_socio, nombre_completo, identificacion FROM socios WHERE UPPER(nombre_completo) LIKE ? AND estado = 'activo' LIMIT 1");
            $st->execute(["%$primer%"]);
            $socio = $st->fetch(PDO::FETCH_ASSOC);
        }
    }

    // 6. Último recurso: buscar por el número de empleado secuencial
    //    Si employeeNo = "0000000000000001" → buscar socio con id_socio = 1
    if (!$socio && is_numeric($emp_limpio)) {
        $st = $pdo->prepare("SELECT id_socio, nombre_completo, identificacion FROM socios WHERE id_socio = ? AND estado = 'activo' LIMIT 1");
        $st->execute([(int)$emp_limpio]);
        $socio = $st->fetch(PDO::FETCH_ASSOC);
    }

    if (!$socio) {
        $errores[] = "No encontrado: employeeNo=$employeeNo nombre='$nombre_bio'";
        $log2 = date('Y-m-d H:i:s') . " | NO ENCONTRADO: emp=$employeeNo nombre=$nombre_bio\n";
        file_put_contents($log_dir . 'polling_' . date('Y-m-d') . '.log', $log2, FILE_APPEND);
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
            $log2 = date('Y-m-d H:i:s') . " | REGISTRADO: {$socio['nombre_completo']} ({$socio['identificacion']}) → Conv #{$conv_id}\n";
        } else {
            $ya_existian++;
            $log2 = date('Y-m-d H:i:s') . " | YA EXISTE: {$socio['nombre_completo']} → Conv #{$conv_id}\n";
        }
        file_put_contents($log_dir . 'polling_' . date('Y-m-d') . '.log', $log2, FILE_APPEND);

    } catch (PDOException $e) {
        $errores[] = "BD error para {$socio['nombre_completo']}: " . $e->getMessage();
    }
}

// Contar totales actuales
$stC = $pdo->prepare("SELECT COUNT(*) FROM conv_asistencia WHERE convocatoria_id = ?");
$stC->execute([$conv_id]);
$presentes = (int)$stC->fetchColumn();

$total = (int)$pdo->query("SELECT COUNT(*) FROM socios WHERE estado='activo'")->fetchColumn();
$pct   = $total > 0 ? round(($presentes / $total) * 100, 1) : 0;

echo json_encode([
    'ok'          => true,
    'registrados' => $registrados,
    'ya_existian' => $ya_existian,
    'presentes'   => $presentes,
    'total'       => $total,
    'porcentaje'  => $pct,
    'errores'     => $errores,
    'conv'        => $conv['titulo'],
]);