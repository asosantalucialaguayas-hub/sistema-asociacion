<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require __DIR__ . "/config/conexion.php";
require __DIR__ . "/vendor/autoload.php"; // Para PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($pdo) || $pdo === null) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit;
}

try {
    $mes = $_POST['mes_informe'] ?? null;
    $anio = $_POST['anio_informe'] ?? null;
    
    if (!$mes || !$anio) {
        echo json_encode(['success' => false, 'message' => 'Mes y año requeridos']);
        exit;
    }
    
    if (!isset($_FILES['archivo_acopio']) || $_FILES['archivo_acopio']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Archivo requerido']);
        exit;
    }
    
    $archivo = $_FILES['archivo_acopio']['tmp_name'];
    
    // Cargar Excel
    $spreadsheet = IOFactory::load($archivo);
    $sheet = $spreadsheet->getActiveSheet();
    $data = $sheet->toArray();
    
    // Buscar fila de encabezados
    $headerRow = -1;
    $colProductor = -1;
    $colPesoKg = -1;
    $colPesoQQ = -1;
    
    foreach ($data as $idx => $row) {
        foreach ($row as $colIdx => $cell) {
            $cellUpper = strtoupper(trim($cell ?? ''));
            if (strpos($cellUpper, 'PRODUCTOR') !== false && $colProductor === -1) {
                $headerRow = $idx;
                $colProductor = $colIdx;
            }
            if (strpos($cellUpper, 'PESO NETO KG') !== false || strpos($cellUpper, 'PESO KG') !== false) {
                $colPesoKg = $colIdx;
            }
            if (strpos($cellUpper, 'PESO NETO QQ') !== false || strpos($cellUpper, 'PESO QQ') !== false) {
                $colPesoQQ = $colIdx;
            }
        }
        if ($headerRow !== -1 && $colProductor !== -1 && $colPesoKg !== -1) {
            break;
        }
    }
    
    if ($headerRow === -1 || $colProductor === -1 || $colPesoKg === -1) {
        echo json_encode(['success' => false, 'message' => 'No se encontraron las columnas requeridas']);
        exit;
    }
    
    $registrosImportados = 0;
    
    // Procesar datos
    for ($i = $headerRow + 1; $i < count($data); $i++) {
        $row = $data[$i];
        
        $nombreProductor = trim($row[$colProductor] ?? '');
        $pesoKg = floatval(str_replace([',', ' '], ['', ''], $row[$colPesoKg] ?? '0'));
        $pesoQQ = $colPesoQQ !== -1 ? floatval(str_replace([',', ' '], ['', ''], $row[$colPesoQQ] ?? '0')) : 0;
        
        if (empty($nombreProductor) || $pesoKg == 0) {
            continue;
        }
        
        // Buscar socio por nombre
        $sqlBuscar = "SELECT id_socio FROM socios WHERE UPPER(nombre_completo) = UPPER(:nombre) LIMIT 1";
        $stmtBuscar = $pdo->prepare($sqlBuscar);
        $stmtBuscar->bindValue(':nombre', $nombreProductor, PDO::PARAM_STR);
        $stmtBuscar->execute();
        $socio = $stmtBuscar->fetch(PDO::FETCH_ASSOC);
        
        if (!$socio) {
            continue; // Saltar si no encuentra el socio
        }
        
        $idSocio = $socio['id_socio'];
        
        // Insertar o actualizar en tabla_consolidado_acopio
        $sqlUpsert = "
            INSERT INTO tabla_consolidado_acopio 
                (id_socio, mes, anio, peso_kg, peso_qq, fecha_importacion)
            VALUES 
                (:id_socio, :mes, :anio, :peso_kg, :peso_qq, NOW())
            ON DUPLICATE KEY UPDATE
                peso_kg = :peso_kg2,
                peso_qq = :peso_qq2,
                fecha_importacion = NOW()
        ";
        
        $stmtUpsert = $pdo->prepare($sqlUpsert);
        $stmtUpsert->bindValue(':id_socio', $idSocio, PDO::PARAM_INT);
        $stmtUpsert->bindValue(':mes', $mes, PDO::PARAM_STR);
        $stmtUpsert->bindValue(':anio', $anio, PDO::PARAM_STR);
        $stmtUpsert->bindValue(':peso_kg', $pesoKg, PDO::PARAM_STR);
        $stmtUpsert->bindValue(':peso_qq', $pesoQQ, PDO::PARAM_STR);
        $stmtUpsert->bindValue(':peso_kg2', $pesoKg, PDO::PARAM_STR);
        $stmtUpsert->bindValue(':peso_qq2', $pesoQQ, PDO::PARAM_STR);
        $stmtUpsert->execute();
        
        $registrosImportados++;
    }
    
    echo json_encode([
        'success' => true,
        'registros' => $registrosImportados,
        'message' => "Se importaron $registrosImportados registros exitosamente"
    ]);
    
} catch(Exception $e) {
    error_log("Error en consolidado_importar.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al importar: ' . $e->getMessage()]);
}
?>