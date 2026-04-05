<?php
// config/periodo_guard.php
// Uso: require __DIR__."/config/periodo_guard.php";  (después de conexion.php)

function periodo_activo(PDO $pdo): ?array {
    $stmt = $pdo->query("SELECT * FROM periodo_comercializacion WHERE estado='ABIERTO' ORDER BY id_periodo DESC LIMIT 1");
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    return $p ?: null;
}

/**
 * Bloquea si NO hay periodo abierto (para páginas HTML que redirigen)
 */
function require_periodo_abierto_redirect(PDO $pdo, string $redirect = 'periodos.php', string $msg = 'No hay período ABIERTO. Abra uno en Herramientas > Períodos.'): array {
    $p = periodo_activo($pdo);
    if (!$p) {
        header("Location: {$redirect}?msg=" . urlencode($msg) . "&type=error");
        exit;
    }
    return $p;
}

/**
 * Bloquea si NO hay periodo abierto (para endpoints que devuelven JSON)
 */
function require_periodo_abierto_json(PDO $pdo, string $msg = 'No hay período ABIERTO. No se permite esta acción.'): array {
    $p = periodo_activo($pdo);
    if (!$p) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
    return $p;
}

/**
 * Permite solo si hay periodo abierto, pero para UI: devuelve boolean
 */
function is_periodo_abierto(PDO $pdo): bool {
    return (bool) periodo_activo($pdo);
}