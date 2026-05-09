<?php
require_once __DIR__ . '/config.php';

try {
    $q = trim($_GET['q'] ?? '');
    
    if ($q) {
        $stmt = $pdo->prepare("
            SELECT id_socio, identificacion, nombre_completo, 
                   telefono, direccion, estado
            FROM socios 
            WHERE estado = 'activo'
              AND (nombre_completo LIKE ? OR identificacion LIKE ?)
            ORDER BY nombre_completo
            LIMIT 50
        ");
        $like = "%$q%";
        $stmt->execute([$like, $like]);
    } else {
        $stmt = $pdo->query("
            SELECT id_socio, identificacion, nombre_completo,
                   telefono, direccion, estado
            FROM socios 
            WHERE estado = 'activo'
            ORDER BY nombre_completo
        ");
    }

    $socios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'data' => $socios]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}