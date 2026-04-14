<?php
// debug_directiva.php — BORRAR DESPUÉS DE USAR
ob_start();
require __DIR__ . "/layout/bootstrap.php";
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$resultado = [];

// 1. ¿Existe la tabla directiva_periodos?
try {
    $r = $pdo->query("SELECT COUNT(*) FROM directiva_periodos")->fetchColumn();
    $resultado['tabla_periodos'] = "OK — $r filas";
} catch(Exception $e) {
    $resultado['tabla_periodos'] = "ERROR: " . $e->getMessage();
}

// 2. ¿Qué períodos hay?
try {
    $rows = $pdo->query("SELECT id, nombre, estado FROM directiva_periodos")->fetchAll(PDO::FETCH_ASSOC);
    $resultado['periodos'] = $rows;
} catch(Exception $e) {
    $resultado['periodos'] = "ERROR: " . $e->getMessage();
}

// 3. ¿Existe tabla directiva_miembros?
try {
    $r = $pdo->query("SELECT COUNT(*) FROM directiva_miembros")->fetchColumn();
    $resultado['tabla_miembros'] = "OK — $r filas";
} catch(Exception $e) {
    $resultado['tabla_miembros'] = "ERROR: " . $e->getMessage();
}

// 4. Contar únicos del período 1
try {
    $r = $pdo->query("
        SELECT COUNT(DISTINCT COALESCE(s.identificacion, dm.cedula_manual))
        FROM directiva_miembros dm
        LEFT JOIN socios s ON s.identificacion = dm.cedula_manual AND s.estado = 'activo'
        WHERE dm.periodo_id = 1
    ")->fetchColumn();
    $resultado['unicos_periodo_1'] = (int)$r;
} catch(Exception $e) {
    $resultado['unicos_periodo_1'] = "ERROR: " . $e->getMessage();
}

// 5. Muestra los primeros 3 miembros
try {
    $rows = $pdo->query("SELECT id, periodo_id, cargo, nombre_manual, cedula_manual, socio_id FROM directiva_miembros LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    $resultado['muestra_miembros'] = $rows;
} catch(Exception $e) {
    $resultado['muestra_miembros'] = "ERROR: " . $e->getMessage();
}

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
