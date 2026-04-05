<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require __DIR__ . "/config/conexion.php";

try {
    $pagina    = max(1, intval($_GET['pagina'] ?? 1));
    $porPagina = 15;
    $offset    = ($pagina - 1) * $porPagina;

    // Total de socios
    $stmtCount = $pdo->query("SELECT COUNT(*) FROM socios");
    $total     = (int)$stmtCount->fetchColumn();
    $totalPaginas = (int)ceil($total / $porPagina);

    // Socios paginados
    $stmt = $pdo->prepare("
        SELECT 
            id_socio,
            identificacion,
            COALESCE(nombre_completo, CONCAT(nombres, ' ', apellidos)) AS nombre_completo,
            telefono,
            sexo,
            estado,
            foto_ruta
        FROM socios
        ORDER BY COALESCE(nombre_completo, CONCAT(nombres, ' ', apellidos)) ASC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit',  $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $socios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success'      => true,
        'socios'       => $socios,
        'pagina'       => $pagina,
        'totalPaginas' => $totalPaginas,
        'total'        => $total
    ]);

} catch (PDOException $e) {
    error_log("Error listar: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>