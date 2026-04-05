<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}
require __DIR__ . "/config/conexion.php";

try {
    $id_socio = intval($_POST['id_socio'] ?? 0);
    if (!$id_socio) {
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        exit;
    }

    if (empty($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No se recibió imagen']);
        exit;
    }

    $file     = $_FILES['foto'];
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed  = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Solo se permiten imágenes JPG, PNG o WEBP']);
        exit;
    }

    if ($file['size'] > 3 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'La imagen no puede superar 3 MB']);
        exit;
    }

    // Crear carpeta si no existe
    $carpeta = __DIR__ . '/uploads/fotos_socios/';
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0755, true);
    }

    // Borrar foto anterior si existe
    $stOld = $pdo->prepare("SELECT foto_ruta FROM socios WHERE id_socio = ?");
    $stOld->execute([$id_socio]);
    $fotoAnterior = $stOld->fetchColumn();
    if ($fotoAnterior && file_exists(__DIR__ . '/' . ltrim($fotoAnterior, '/'))) {
        @unlink(__DIR__ . '/' . ltrim($fotoAnterior, '/'));
    }

    // Guardar nueva foto con nombre único
    $nombreArchivo = 'socio_' . $id_socio . '_' . time() . '.' . $ext;
    $rutaServidor  = $carpeta . $nombreArchivo;
    $rutaBD        = 'uploads/fotos_socios/' . $nombreArchivo;

    if (!move_uploaded_file($file['tmp_name'], $rutaServidor)) {
        echo json_encode(['success' => false, 'message' => 'Error al guardar la imagen en el servidor']);
        exit;
    }

    // Actualizar BD
    $pdo->prepare("UPDATE socios SET foto_ruta = ? WHERE id_socio = ?")
        ->execute([$rutaBD, $id_socio]);

    echo json_encode([
        'success'   => true,
        'message'   => 'Foto actualizada correctamente',
        'foto_ruta' => $rutaBD . '?v=' . time()
    ]);

} catch (PDOException $e) {
    error_log("consulta_general_foto: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
}
?>
