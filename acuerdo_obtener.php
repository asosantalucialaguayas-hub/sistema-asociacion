<?php
// acuerdo_obtener.php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require "config/conexion.php";

try {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        exit;
    }

    $sql = "
        SELECT
            id_acuerdo,
            numero_acuerdo,
            cedula,
            nombres_completos,
            provincia,
            canton,
            parroquia,
            sector,
            fecha_firma,
            posee_riego,
            periodo_de_fertilizacion,
            cacao_nacional_has,
            estimado_produccion_nacional,
            cacao_ccn51_has,
            estimado_produccion_ccn51
        FROM acuerdo_productor
        WHERE id_acuerdo = :id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $acuerdo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$acuerdo) {
        echo json_encode(['success' => false, 'message' => 'Acuerdo no encontrado']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'acuerdo' => $acuerdo
    ]);

} catch (PDOException $e) {
    error_log("acuerdo_obtener.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error BD']);
}
?>