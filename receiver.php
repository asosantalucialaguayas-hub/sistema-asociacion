<?php
// receiver.php — Recibe marcaciones del bridge y las guarda en BD
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Conexión BD ──────────────────────────────────────────────
$host = "localhost";
$db   = "u241263046_asociacion2";
$user = "u241263046_pedro";
$pass = "40242745aA";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["ok" => false, "msg" => "Error BD: " . $e->getMessage()]);
    exit;
}

// ── Leer datos del bridge ─────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['employeeNo'])) {
    echo json_encode(["ok" => false, "msg" => "Datos incompletos"]);
    exit;
}

$employeeNo  = trim($input['employeeNo']);   // ID del biométrico
$tiempo      = $input['time'] ?? date('Y-m-d H:i:s');
$convocatoria_id = $input['convocatoria_id'] ?? 1;

// ── Buscar socio ──────────────────────────────────────────────
// Primero busca por cédula, luego por id_socio (para los que tienen ID simple)
$stmt = $pdo->prepare("
    SELECT id_socio, nombre_completo, identificacion 
    FROM socios 
    WHERE identificacion = :emp 
       OR id_socio = :emp2
    LIMIT 1
");

// Convertir "0000000000000002" → "2" para buscar por id_socio
$empLimpio = ltrim($employeeNo, '0') ?: '0';

$stmt->execute([':emp' => $empLimpio, ':emp2' => $empLimpio]);
$socio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$socio) {
    echo json_encode(["ok" => false, "msg" => "Socio no encontrado: $employeeNo"]);
    exit;
}

// ── Evitar duplicados (misma persona, misma convocatoria, mismo día) ──
$stmtDup = $pdo->prepare("
    SELECT id FROM asistencia 
    WHERE socio_id = :sid 
      AND convocatoria_id = :cid 
      AND DATE(hora_registro) = DATE(:hora)
    LIMIT 1
");
$stmtDup->execute([
    ':sid'  => $socio['id_socio'],
    ':cid'  => $convocatoria_id,
    ':hora' => $tiempo
]);

if ($stmtDup->fetch()) {
    echo json_encode([
        "ok"     => true,
        "msg"    => "Ya registrado hoy",
        "nombre" => $socio['nombre_completo'],
        "duplicado" => true
    ]);
    exit;
}

// ── Insertar asistencia ───────────────────────────────────────
$stmtIns = $pdo->prepare("
    INSERT INTO asistencia (socio_id, convocatoria_id, hora_registro, metodo)
    VALUES (:sid, :cid, :hora, 'biometrico')
");

$stmtIns->execute([
    ':sid'  => $socio['id_socio'],
    ':cid'  => $convocatoria_id,
    ':hora' => $tiempo
]);

echo json_encode([
    "ok"     => true,
    "msg"    => "Asistencia registrada",
    "nombre" => $socio['nombre_completo'],
    "id_socio" => $socio['id_socio'],
    "hora"   => $tiempo
]);
