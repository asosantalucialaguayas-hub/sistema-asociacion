<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require "config/conexion.php";

$id_solicitud = $_POST['id_solicitud'] ?? null;
$tipo_documento = $_POST['tipo_documento'] ?? null;
$nombre_documento = $_POST['nombre_documento'] ?? null;

if (!$id_solicitud || !$tipo_documento || !isset($_FILES['archivo_pdf'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Faltan datos']);
    exit;
}

$file = $_FILES['archivo_pdf'];

// Validar que sea PDF
if ($file['type'] !== 'application/pdf') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Solo se aceptan archivos PDF']);
    exit;
}

// Crear carpeta si no existe
$upload_dir = __DIR__ . '/documentos/pdf/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generar nombre único
$nombre_archivo = date('Y-m-d-H-i-s') . '_' . $id_solicitud . '_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $nombre_documento) . '.pdf';
$ruta_archivo = $upload_dir . $nombre_archivo;

// Guardar archivo
if (move_uploaded_file($file['tmp_name'], $ruta_archivo)) {
    // Guardar en BD (tabla documentos_socios)
    $ruta_relativa = '/documentos/pdf/' . $nombre_archivo;
    
    $sql = "INSERT INTO documentos_socios (id_solicitud, tipo_documento, nombre, ruta_archivo, fecha_carga) 
            VALUES (?, ?, ?, ?, NOW())";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_solicitud, $tipo_documento, $nombre_documento, $ruta_relativa]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Documento subido exitosamente']);
    } catch (PDOException $e) {
        unlink($ruta_archivo); // Eliminar archivo si falla BD
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error al guardar en BD: ' . $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error al subir archivo']);
}
exit;
