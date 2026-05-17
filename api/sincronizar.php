<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['aplicaciones'])) {
    echo json_encode(['ok' => false, 'msg' => 'Datos inválidos o vacíos']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO visitas_fichas
            (id_socio, id_ficha, datos_json, firma_inspector,
             firma_socio, fecha_visita, sincronizado_en)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $ids_sincronizados = [];

    foreach ($input['aplicaciones'] as $ap) {
        // Validar campos mínimos
        if (empty($ap['id_socio']) || empty($ap['id_ficha'])) continue;

        $stmt->execute([
            (int)$ap['id_socio'],
            (int)$ap['id_ficha'],
            $ap['datos_json']      ?? '{}',
            $ap['firma_inspector'] ?? null,
            $ap['firma_socio']     ?? null,
            $ap['fecha_visita']    ?? date('Y-m-d H:i:s'),
        ]);

        $ids_sincronizados[] = $ap['id_local']; // ID local de SQLite para marcarlo
    }

    echo json_encode([
        'ok'  => true,
        'ids' => $ids_sincronizados,
        'msg' => count($ids_sincronizados) . ' ficha(s) sincronizada(s)'
    ]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
