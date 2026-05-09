<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['ok' => false, 'msg' => 'Datos inválidos']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO visitas_fichas 
            (id_socio, id_ficha, datos_json, firma_inspector, 
             firma_socio, fecha_visita, sincronizado_en)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $input['id_socio'],
        $input['id_ficha'],
        json_encode($input['datos']),
        $input['firma_inspector'] ?? null,
        $input['firma_socio']     ?? null,
        $input['fecha_visita']    ?? date('Y-m-d H:i:s'),
    ]);

    echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}