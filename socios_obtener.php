<?php
header('Content-Type: application/json');

require __DIR__ . "/config/conexion.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de socio inválido'
    ]);
    exit;
}

$id = (int) $_GET['id'];

try {
    $sql = "SELECT 
                id_socio,
                identificacion,
                apellidos,
                nombres,
                sexo,
                fecha_nacimiento,
                direccion,
                telefono,
                correo,
                fecha_ingreso,
                estado
            FROM socios
            WHERE id_socio = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $socio = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$socio) {
        echo json_encode([
            'success' => false,
            'message' => 'Socio no encontrado'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => $socio
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener socio',
        'error' => $e->getMessage()
    ]);
}
