<?php
ob_start();
header('Content-Type: application/json');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Método no permitido']); exit;
    }
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['usuario'])) {
        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Sesión no válida']); exit;
    }
    require_once 'config/conexion.php';

    $id_socio      = !empty($_POST['id_socio'])      ? (int)$_POST['id_socio']        : null;
    $area_cacao_ha = isset($_POST['area_cacao_ha'])   ? (float)$_POST['area_cacao_ha'] : null;

    if (!$id_socio || $area_cacao_ha === null) {
        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Datos incompletos']); exit;
    }

    // Actualiza SOLO el registro LPA más reciente del socio
    $stmt = $pdo->prepare(
        "UPDATE tabla_lpa SET area_cacao_ha = :ha 
         WHERE id_socio = :id 
         ORDER BY id_lpa DESC 
         LIMIT 1"
    );
    $stmt->execute([':ha' => $area_cacao_ha, ':id' => $id_socio]);

    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Área actualizada correctamente']);

} catch (Throwable $e) {
    $out = ob_get_clean();
    echo json_encode(['success'=>false,'message'=>$e->getMessage(),'debug'=>$out]);
}
