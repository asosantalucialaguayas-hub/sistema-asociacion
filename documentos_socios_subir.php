<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require __DIR__ . "/config/conexion.php";

if (!isset($pdo)) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit;
}

try {
    $id_socio       = $_POST['id_socio']       ?? null;
    $tipo_documento = $_POST['tipo_documento'] ?? null;
    $id_periodo     = $_POST['id_periodo']     ?? null;
    $observacion    = $_POST['observacion']    ?? '';

    if (!$id_socio || !$tipo_documento || !$id_periodo) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
        exit;
    }

    // Validar archivo
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Error al subir archivo']);
        exit;
    }

    $file = $_FILES['archivo'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];

    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Solo se permiten: PDF, JPG, PNG']);
        exit;
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Archivo muy grande (máx 10MB)']);
        exit;
    }

    // Crear directorio
    $uploadDir = __DIR__ . '/uploads/documentos_productores';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Nombre único
    $nombreArchivo = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
    $rutaCompleta  = $uploadDir . '/' . $nombreArchivo;
    $rutaRelativa  = 'uploads/documentos_productores/' . $nombreArchivo;

    if (!move_uploaded_file($file['tmp_name'], $rutaCompleta)) {
        echo json_encode(['success' => false, 'message' => 'Error al mover archivo']);
        exit;
    }

    // Insertar en BD usando documentos_productores
    $stmt = $pdo->prepare("
        INSERT INTO documentos_productores 
        (id_socio, tipo_documento, nombre_archivo, ruta_archivo, tamano_archivo, id_periodo, observacion, fecha_carga)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $id_socio,
        $tipo_documento,
        $file['name'],
        $rutaRelativa,
        $file['size'],
        $id_periodo,
        $observacion
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Documento subido correctamente',
        'id'      => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    error_log("Error al subir documento: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al guardar en BD: ' . $e->getMessage()]);
}
?>