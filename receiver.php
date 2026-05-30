<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['employeeNo'])) {
    echo json_encode(["ok" => false, "msg" => "Datos incompletos"]);
    exit;
}

$employeeNo      = trim($input['employeeNo']);
$tiempo          = $input['time'] ?? date('Y-m-d H:i:s');
$convocatoria_id = $input['convocatoria_id'] ?? 1;
$empLimpio       = ltrim($employeeNo, '0') ?: '0';

$stmtMap = $pdo->prepare("
    SELECT s.id_socio, s.nombre_completo, s.identificacion
    FROM biometrico_socios b
    JOIN socios s ON s.id_socio = b.id_socio
    WHERE b.empleado_id = :emp
    LIMIT 1
");
$stmtMap->execute([':emp' => $empLimpio]);
$socio = $stmtMap->fetch(PDO::FETCH_ASSOC);

if (!$socio) {
    $stmt = $pdo->prepare("
        SELECT id_socio, nombre_completo, identificacion
        FROM socios
        WHERE identificacion = :emp
        LIMIT 1
    ");
    $stmt->execute([':emp' => $empLimpio]);
    $socio = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$socio) {
    echo json_encode([
        "ok"        => false,
        "msg"       => "Socio no encontrado",
        "buscado"   => $empLimpio,
        "original"  => $employeeNo
    ]);
    exit;
}

$stmtDup = $pdo->prepare("
    SELECT id FROM conv_asistencia
    WHERE id_socio = :sid AND convocatoria_id = :cid
    LIMIT 1
");
$stmtDup->execute([':sid' => $socio['id_socio'], ':cid' => $convocatoria_id]);

if ($stmtDup->fetch()) {
    echo json_encode(["ok" => true, "msg" => "Ya registrado", "nombre" => $socio['nombre_completo'], "duplicado" => true]);
    exit;
}

$stmtIns = $pdo->prepare("
    INSERT INTO conv_asistencia (convocatoria_id, id_socio, hora_registro, metodo)
    VALUES (:cid, :sid, :hora, 'biometrico')
");
$stmtIns->execute([':cid' => $convocatoria_id, ':sid' => $socio['id_socio'], ':hora' => $tiempo]);

echo json_encode([
    "ok"       => true,
    "msg"      => "Asistencia registrada",
    "nombre"   => $socio['nombre_completo'],
    "id_socio" => $socio['id_socio'],
    "hora"     => $tiempo
]);