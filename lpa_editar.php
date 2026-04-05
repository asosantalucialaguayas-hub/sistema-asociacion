<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once 'config/conexion.php';
require __DIR__ . "/config/periodo_guard.php";
$periodo = require_periodo_abierto_json($pdo); // 🔒 candado de período

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id === 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                l.*,
                s.identificacion,
                s.nombre_completo,
                s.sexo as sexo_socio,
                s.telefono,
                s.fecha_nacimiento,
                s.fecha_ingreso
            FROM tabla_lpa l
            INNER JOIN socios s ON s.id_socio = l.id_socio
            WHERE l.id_lpa = :id
            LIMIT 1
        ");
        
        $stmt->execute([':id' => $id]);
        $lpa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lpa) {
            echo json_encode(['success' => false, 'message' => 'LPA no encontrada']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'lpa' => $lpa
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    // Meses
    $enero = $_POST['enero'] ?? 0;
    $febrero = $_POST['febrero'] ?? 0;
    $marzo = $_POST['marzo'] ?? 0;
    $abril = $_POST['abril'] ?? 0;
    $mayo = $_POST['mayo'] ?? 0;
    $junio = $_POST['junio'] ?? 0;
    $julio = $_POST['julio'] ?? 0;
    $agosto = $_POST['agosto'] ?? 0;
    $septiembre = $_POST['septiembre'] ?? 0;
    $octubre = $_POST['octubre'] ?? 0;
    $noviembre = $_POST['noviembre'] ?? 0;
    $diciembre = $_POST['diciembre'] ?? 0;
    
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
            volumen_entregado_org = ?,
            enero = ?,
            febrero = ?,
            marzo = ?,
            abril = ?,
            mayo = ?,
            junio = ?,
            julio = ?,
            agosto = ?,
            septiembre = ?,
            octubre = ?,
            noviembre = ?,
            diciembre = ?
        WHERE id_lpa = ?";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $zona, $comunidad_grupo, $en_acercamiento, $otra_org_fairtrade,
            $area_total_ha, $area_cacao_ha, $num_matas_ha, $certificacion_organica,
            $volumen_produccion_estimado, $volumen_entregado_org,
            $enero, $febrero, $marzo, $abril, $mayo, $junio,
            $julio, $agosto, $septiembre, $octubre, $noviembre, $diciembre,
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
}