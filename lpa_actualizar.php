<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once 'config/conexion.php';

$id_lpa = $_POST['id_lpa'] ?? null;
$zona = $_POST['zona'] ?? '';
$comunidad_grupo = $_POST['comunidad_grupo'] ?? '';
$en_acercamiento = $_POST['en_acercamiento'] ?? '';
$otra_org_fairtrade = $_POST['otra_org_fairtrade'] ?? '';
$area_total_ha = $_POST['area_total_ha'] ?? 0;
$area_cacao_ha = $_POST['area_cacao_ha'] ?? 0;
$num_matas_ha = $_POST['num_matas_ha'] ?? 0;
$certificacion_organica = $_POST['certificacion_organica'] ?? '';
$volumen_produccion_estimado = $_POST['volumen_produccion_estimado'] ?? 0;
$volumen_entregado_org = $_POST['volumen_entregado_org'] ?? 0;

if (!$id_lpa) {
    echo json_encode(['success' => false, 'message' => 'ID LPA requerido']);
    exit;
}

try {
    $sql = "UPDATE tabla_lpa SET 
        zona = ?,
        comunidad_grupo = ?,
        en_acercamiento = ?,
        otra_org_fairtrade = ?,
        area_total_ha = ?,
        area_cacao_ha = ?,
        num_matas_ha = ?,
        certificacion_organica = ?,
        volumen_produccion_estimado = ?,
        volumen_entregado_org = ?
    WHERE id_lpa = ?";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $zona,
        $comunidad_grupo,
        $en_acercamiento,
        $otra_org_fairtrade,
        $area_total_ha,
        $area_cacao_ha,
        $num_matas_ha,
        $certificacion_organica,
        $volumen_produccion_estimado,
        $volumen_entregado_org,
        $id_lpa
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'LPA actualizada correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo actualizar']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}