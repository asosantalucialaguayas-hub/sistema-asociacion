<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once "config/conexion.php";

header('Content-Type: application/json; charset=utf-8');

try {
    $id_periodo = (int)($_POST['id_periodo'] ?? 0);
    $tipo = $_POST['tipo'] ?? 'CONTRATO';
    $titulo = trim($_POST['titulo'] ?? '');
    
    if (!$id_periodo || empty($titulo)) {
        throw new Exception('Datos incompletos');
    }
    
    // Validar archivo
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No se recibió el archivo correctamente');
    }
    
    $archivo = $_FILES['archivo'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    
    if ($extension !== 'pdf') {
        throw new Exception('Solo se permiten archivos PDF');
    }
    
    if ($archivo['size'] > 10 * 1024 * 1024) {
        throw new Exception('El archivo no debe superar 10MB');
    }
    
    // Crear directorio si no existe
    $directorio = __DIR__ . '/uploads/contratos/';
    if (!is_dir($directorio)) {
        mkdir($directorio, 0755, true);
    }
    
    // Guardar archivo
    $nombre_archivo = 'doc_' . $id_periodo . '_' . time() . '.pdf';
    $ruta_completa = $directorio . $nombre_archivo;
    
    if (!move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
        throw new Exception('Error al guardar el archivo');
    }
    
    // Registrar en base de datos
    $stmt = $pdo->prepare("
        INSERT INTO contrato_periodo_documento 
        (id_periodo, tipo, titulo, archivo_nombre, archivo_ruta, mime, tamano) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $id_periodo,
        $tipo,
        $titulo,
        $archivo['name'],
        'uploads/contratos/' . $nombre_archivo,
        $archivo['type'],
        $archivo['size']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Documento subido exitosamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}