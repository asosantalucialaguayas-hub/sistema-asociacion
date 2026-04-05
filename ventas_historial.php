<?php
require "layout/bootstrap.php";

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require __DIR__ . "/config/conexion.php";

if (!isset($pdo) || $pdo === null) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}

try {
    $id_lpa = $_GET['id_lpa'] ?? null;

    if (!$id_lpa) {
        echo json_encode(['success' => false, 'message' => 'ID LPA requerido']);
        exit;
    }

    $sql = "
        SELECT
            v.id_venta,
            v.fecha_venta,
            v.cantidad_vende,
            v.cantidad_kg,
            v.cantidad_qq,
            v.precio_kg,
            v.total,
            v.destino,
            v.floid,
            v.sucursal,
            v.observacion,
            v.factura,
            v.descripcion,
            v.numero_doc,
            v.fecha_registro
        FROM tabla_ventas v
        WHERE v.id_lpa = :id_lpa
        ORDER BY v.fecha_venta DESC, v.fecha_registro DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id_lpa', $id_lpa, PDO::PARAM_INT);
    $stmt->execute();

    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'ventas'  => $ventas
    ]);

} catch (PDOException $e) {
    error_log("Error en ventas_historial.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener historial']);
}
?>