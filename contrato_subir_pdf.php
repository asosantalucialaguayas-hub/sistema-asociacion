<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}
require "config/conexion.php";

$id_contrato = (int)($_POST['id_contrato'] ?? 0);
$tipo = trim($_POST['tipo'] ?? 'CONTRATO'); // CONTRATO | ADENDA
$titulo = trim($_POST['titulo'] ?? '');
$numero_adenda = isset($_POST['numero_adenda']) ? (int)$_POST['numero_adenda'] : null;

if ($id_contrato <= 0 || $titulo === '' || !in_array($tipo, ['CONTRATO','ADENDA'], true)) {
    header("Location: contrato_detalle.php?id={$id_contrato}&msg=" . urlencode("Datos incompletos") . "&type=error");
    exit;
}
if ($tipo === 'ADENDA' && (!$numero_adenda || $numero_adenda < 1)) {
    header("Location: contrato_detalle.php?id={$id_contrato}&msg=" . urlencode("Número de adenda inválido") . "&type=error");
    exit;
}

try {
    // Traer contrato + periodo
    $stmt = $pdo->prepare("
        SELECT c.id_contrato, c.id_periodo, p.estado AS periodo_estado
        FROM contrato_periodo c
        JOIN periodo_comercializacion p ON p.id_periodo = c.id_periodo
        WHERE c.id_contrato=?
        LIMIT 1
    ");
    $stmt->execute([$id_contrato]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$c) {
        header("Location: periodos.php?msg=" . urlencode("Contrato no existe") . "&type=error");
        exit;
    }
    if ($c['periodo_estado'] !== 'ABIERTO') {
        header("Location: contrato_detalle.php?id={$id_contrato}&msg=" . urlencode("Período CERRADO. No se permite subir archivos.") . "&type=info");
        exit;
    }

    if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        header("Location: contrato_detalle.php?id={$id_contrato}&msg=" . urlencode("Error al subir el archivo") . "&type=error");
        exit;
    }

    $f = $_FILES['pdf'];
    $maxBytes = 10 * 1024 * 1024; // 10MB
    if ((int)$f['size'] > $maxBytes) {
        header("Location: contrato_detalle.php?id={$id_contrato}&msg=" . urlencode("El PDF supera 10MB") . "&type=error");
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($f['tmp_name']);
    if ($mime !== 'application/pdf') {
        header("Location: contrato_detalle.php?id={$id_contrato}&msg=" . urlencode("Solo se permite PDF") . "&type=error");
        exit;
    }

    // Carpeta por periodo
    $id_periodo = (int)$c['id_periodo'];
    $baseDir = __DIR__ . "/uploads/contratos/" . $id_periodo;
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0755, true);
    }

    $rand = bin2hex(random_bytes(16));
    $safeName = $tipo . "_" . $id_contrato . "_" . $rand . ".pdf";
    $dest = $baseDir . "/" . $safeName;

    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        header("Location: contrato_detalle.php?id={$id_contrato}&msg=" . urlencode("No se pudo guardar el archivo") . "&type=error");
        exit;
    }

    $rutaRel = "uploads/contratos/" . $id_periodo . "/" . $safeName;

    // Guardar registro
    $ins = $pdo->prepare("
        INSERT INTO contrato_periodo_documento
        (id_contrato, tipo, numero_adenda, titulo, archivo_nombre, archivo_ruta, mime, tamano)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->execute([
        $id_contrato,
        $tipo,
        ($tipo === 'ADENDA' ? $numero_adenda : null),
        $titulo,
        $f['name'],
        $rutaRel,
        $mime,
        (int)$f['size']
    ]);

    header("Location: contrato_detalle.php?id={$id_contrato}&msg=" . urlencode("PDF subido correctamente") . "&type=success");
    exit;

} catch (Exception $e) {
    header("Location: contrato_detalle.php?id={$id_contrato}&msg=" . urlencode("Error: " . $e->getMessage()) . "&type=error");
    exit;
}
