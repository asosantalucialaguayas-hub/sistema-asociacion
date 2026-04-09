<?php
// ============================================================
// isup_receiver.php
// Receptor de eventos ISUP del biométrico Hikvision
// Subir en: /asosantalu/isup_receiver.php
// ============================================================

require __DIR__ . "/layout/bootstrap.php";
header('Content-Type: application/json; charset=utf-8');

// Log todo para depuración (desactivar en producción)
$log_dir = __DIR__ . '/logs/';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);

$raw    = file_get_contents('php://input');
$method = $_SERVER['REQUEST_METHOD'];
$uri    = $_SERVER['REQUEST_URI'] ?? '';

// Guardar log del evento recibido
$log_entry = date('Y-m-d H:i:s') . " | $method | " . json_encode($_GET) . " | BODY: $raw\n";
file_put_contents($log_dir . 'isup_' . date('Y-m-d') . '.log', $log_entry, FILE_APPEND);

// ── Hikvision ISUP envía XML o JSON según el evento ──────────
// Eventos típicos:
//   AccessControllerEvent → entrada/salida de persona
//   AttendanceRecord       → registro de asistencia
// ─────────────────────────────────────────────────────────────

$employee_no = null;
$timestamp   = null;
$event_type  = 'unknown';

// Intentar parsear como JSON primero
$json_data = json_decode($raw, true);
if ($json_data) {
    // Formato JSON (algunos modelos)
    $employee_no = $json_data['employeeNo']
        ?? $json_data['employee_no']
        ?? $json_data['cardNo']
        ?? $json_data['CardNo']
        ?? null;
    $timestamp  = $json_data['time'] ?? $json_data['dateTime'] ?? date('Y-m-d H:i:s');
    $event_type = $json_data['eventType'] ?? $json_data['major'] ?? 'json_event';
}

// Intentar parsear como XML si no hay JSON
if (!$employee_no && !empty($raw) && str_contains($raw, '<')) {
    // Suprimir errores de XML malformado
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($raw);
    if ($xml) {
        // AccessControllerEvent
        $employee_no = (string)($xml->AccessControllerEvent->employeeNoString
            ?? $xml->AccessControllerEvent->employeeNo
            ?? $xml->employeeNoString
            ?? $xml->employeeNo
            ?? '');
        $timestamp   = (string)($xml->AccessControllerEvent->time
            ?? $xml->dateTime
            ?? date('Y-m-d H:i:s'));
        $event_type  = (string)($xml->AccessControllerEvent->eventType
            ?? 'xml_event');
    }
}

// Parámetros GET (algunos modelos los envían en la URL)
if (!$employee_no) {
    $employee_no = $_GET['employeeNo']
        ?? $_GET['employee_no']
        ?? $_GET['cardno']
        ?? $_GET['id']
        ?? null;
}

// Si no hay employee_no, solo registrar y responder OK (keepalive del dispositivo)
if (empty($employee_no)) {
    echo json_encode(['ok' => true, 'msg' => 'keepalive recibido']);
    exit;
}

// Limpiar: solo números
$employee_no = preg_replace('/\D/', '', trim($employee_no));

if (strlen($employee_no) < 1) {
    echo json_encode(['ok' => false, 'msg' => 'employee_no inválido']);
    exit;
}

// ── Buscar socio por identificacion (cédula) ─────────────────
// El número de empleado en el biométrico DEBE ser la cédula del socio
$stS = $pdo->prepare("
    SELECT id_socio, nombre_completo, identificacion
    FROM socios
    WHERE identificacion = ? AND estado = 'activo'
    LIMIT 1
");
$stS->execute([$employee_no]);
$socio = $stS->fetch(PDO::FETCH_ASSOC);

if (!$socio) {
    $log = date('Y-m-d H:i:s') . " | NO ENCONTRADO: $employee_no\n";
    file_put_contents($log_dir . 'isup_' . date('Y-m-d') . '.log', $log, FILE_APPEND);
    echo json_encode(['ok' => false, 'msg' => "Socio con ID $employee_no no encontrado"]);
    exit;
}

// ── Buscar convocatoria activa HOY ───────────────────────────
$conv = $pdo->query("
    SELECT id, titulo FROM convocatorias
    WHERE estado = 'activa' AND fecha = CURDATE()
    ORDER BY hora DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$conv) {
    $log = date('Y-m-d H:i:s') . " | SIN SESION ACTIVA para socio: {$socio['nombre_completo']}\n";
    file_put_contents($log_dir . 'isup_' . date('Y-m-d') . '.log', $log, FILE_APPEND);
    // Respondemos OK igual para que el dispositivo no reintente indefinidamente
    echo json_encode(['ok' => false, 'msg' => 'No hay sesión activa hoy', 'socio' => $socio['nombre_completo']]);
    exit;
}

// ── Registrar asistencia ─────────────────────────────────────
try {
    $ins = $pdo->prepare("
        INSERT INTO conv_asistencia
            (convocatoria_id, id_socio, hora_registro, metodo, registrado_por)
        VALUES (?, ?, NOW(), 'biometrico', NULL)
        ON DUPLICATE KEY UPDATE hora_registro = hora_registro
    ");
    $ins->execute([$conv['id'], $socio['id_socio']]);

    $nuevo = $ins->rowCount() > 0;

    // Contar asistencia actual
    $stC = $pdo->prepare("SELECT COUNT(*) FROM conv_asistencia WHERE convocatoria_id = ?");
    $stC->execute([$conv['id']]);
    $presentes = (int)$stC->fetchColumn();

    $total    = (int)$pdo->query("SELECT COUNT(*) FROM socios WHERE estado='activo'")->fetchColumn();
    $pct      = $total > 0 ? round(($presentes / $total) * 100, 1) : 0;

    $log = date('Y-m-d H:i:s') . " | " . ($nuevo ? 'REGISTRADO' : 'YA EXISTIA') . ": {$socio['nombre_completo']} ({$socio['identificacion']}) → Conv #{$conv['id']}\n";
    file_put_contents($log_dir . 'isup_' . date('Y-m-d') . '.log', $log, FILE_APPEND);

    // Respuesta que el dispositivo Hikvision espera
    http_response_code(200);
    echo json_encode([
        'ok'         => true,
        'nuevo'      => $nuevo,
        'msg'        => $nuevo
            ? 'Bienvenido/a, ' . $socio['nombre_completo']
            : $socio['nombre_completo'] . ' ya fue registrado/a',
        'socio'      => $socio['nombre_completo'],
        'identificacion' => $socio['identificacion'],
        'presentes'  => $presentes,
        'total'      => $total,
        'porcentaje' => $pct,
        'convocatoria' => $conv['titulo'],
    ]);

} catch (PDOException $e) {
    $log = date('Y-m-d H:i:s') . " | ERROR DB: " . $e->getMessage() . "\n";
    file_put_contents($log_dir . 'isup_' . date('Y-m-d') . '.log', $log, FILE_APPEND);
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error BD: ' . $e->getMessage()]);
}
