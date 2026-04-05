<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}
require "config/conexion.php";

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: periodos.php?msg=" . urlencode("ID de contrato inválido") . "&type=error");
    exit;
}

// contrato + periodo
$stmt = $pdo->prepare("
    SELECT c.*, p.nombre AS periodo_nombre, p.estado AS periodo_estado, p.fecha_apertura, p.fecha_cierre, p.id_periodo
    FROM contrato_periodo c
    JOIN periodo_comercializacion p ON p.id_periodo = c.id_periodo
    WHERE c.id_contrato=?
    LIMIT 1
");
$stmt->execute([$id]);
$contrato = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contrato) {
    header("Location: periodos.php?msg=" . urlencode("Contrato no encontrado") . "&type=error");
    exit;
}

// documentos
$docsStmt = $pdo->prepare("SELECT * FROM contrato_periodo_documento WHERE id_contrato=? ORDER BY subido_en DESC, id_doc DESC");
$docsStmt->execute([$id]);
$documentos = $docsStmt->fetchAll(PDO::FETCH_ASSOC);

// Mensajes
$msg = $_GET['msg'] ?? '';
$type = $_GET['type'] ?? 'info';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Contrato del Período</title>

<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/modal-message.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<?php include 'layout/modals.php'; ?>

<style>
.btn-actions{display:flex;gap:10px;margin-bottom:15px;flex-wrap:wrap}
.btn-primary{background:#1f3a5f;color:#fff;padding:10px 18px;border-radius:8px;border:none;font-weight:600;cursor:pointer}
.btn-secondary{background:#1f3a5f;color:#fff;padding:10px 18px;border-radius:8px;border:none;font-weight:600;cursor:pointer}
.btn-danger{background:#ef4444;color:#fff;padding:10px 18px;border-radius:8px;border:none;font-weight:600;cursor:pointer}
.btn-primary:hover,.btn-secondary:hover{background:#16304d}
.btn-danger:hover{background:#dc2626}

.data-table{width:100%;border-collapse:collapse;font-size:13px}
.data-table thead th{background:#1f3a5f;color:#fff;padding:12px;text-align:left}
.data-table td{padding:10px;border-bottom:1px solid #e5e7eb}
.data-table tbody tr:hover{background:#f9fafb}

.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px;align-items:end}
.form-group label{display:block;font-size:13px;font-weight:700;color:#1f3a5f;margin-bottom:6px}
.form-group input,.form-group select{width:100%;padding:10px 12px;border-radius:8px;border:1px solid #d1d5db;font-size:14px;box-sizing:border-box}

.badge{background:#e5e7eb;padding:4px 10px;border-radius:999px;font-size:12px;color:#374151;font-weight:700}
.estado{padding:4px 12px;border-radius:999px;font-size:12px;font-weight:800;color:#fff;display:inline-block}
.estado.abierto{background:#10b981}
.estado.cerrado{background:#ef4444}
.estado.borrador{background:#6b7280}
.estado.vigente{background:#2563eb}

.mini{font-size:13px;color:#374151}
</style>
</head>

<body>
<script src="layout/modal-message.js"></script>

<div class="app">
<?php include __DIR__."/layout/sidebar.php"; ?>

<main class="content">
<header class="topbar">
    <span>Bienvenido, <?= $_SESSION['usuario'] ?></span>
</header>

<section class="page">

<h1><i class="fa-solid fa-file-signature"></i> Contrato del Período</h1>

<div class="btn-actions">
    <a href="periodos.php" style="text-decoration:none">
        <button class="btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver</button>
    </a>

    <?php if ($contrato['periodo_estado'] !== 'ABIERTO'): ?>
        <button class="btn-danger" type="button" onclick="mostrarMensaje('Info','El período está CERRADO. No se permiten nuevas cargas.','info')">
            <i class="fa-solid fa-lock"></i> Período Cerrado
        </button>
    <?php endif; ?>
</div>

<?php if ($msg): ?>
<script>
window.addEventListener('DOMContentLoaded', () => {
    mostrarMensaje('Mensaje', <?= json_encode($msg) ?>, <?= json_encode($type) ?>);
});
</script>
<?php endif; ?>

<div class="form-card">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:8px;">
        <span class="badge"><?= htmlspecialchars($contrato['periodo_nombre']) ?></span>
        <span class="mini">Apertura: <strong><?= htmlspecialchars($contrato['fecha_apertura']) ?></strong></span>
        <span class="mini">Estado período:
            <?php if ($contrato['periodo_estado'] === 'ABIERTO'): ?>
                <span class="estado abierto">ABIERTO</span>
            <?php else: ?>
                <span class="estado cerrado">CERRADO</span>
            <?php endif; ?>
        </span>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <span class="mini"><strong>Título:</strong> <?= htmlspecialchars($contrato['titulo']) ?></span>
        <span class="mini"><strong>Número:</strong> <?= $contrato['numero'] ? htmlspecialchars($contrato['numero']) : '-' ?></span>
        <span class="mini"><strong>Fecha firma:</strong> <?= $contrato['fecha_firma'] ? htmlspecialchars($contrato['fecha_firma']) : '-' ?></span>
        <span class="mini"><strong>Estado contrato:</strong>
            <?php
            $estC = strtolower($contrato['estado']);
            ?>
            <span class="estado <?= $estC ?>"><?= htmlspecialchars($contrato['estado']) ?></span>
        </span>
    </div>
</div>

<!-- SUBIR CONTRATO -->
<div class="form-card">
    <h2 style="margin:0 0 12px 0;"><i class="fa-solid fa-upload"></i> Subir PDF del Contrato</h2>

    <form method="POST" action="contrato_subir_pdf.php" enctype="multipart/form-data" class="form-grid">
        <input type="hidden" name="id_contrato" value="<?= (int)$contrato['id_contrato'] ?>">
        <input type="hidden" name="tipo" value="CONTRATO">

        <div class="form-group">
            <label>Título del archivo</label>
            <input type="text" name="titulo" placeholder="Contrato firmado" required>
        </div>

        <div class="form-group">
            <label>Archivo (PDF)</label>
            <input type="file" name="pdf" accept="application/pdf" required>
        </div>

        <div class="form-group" style="grid-column:1/-1">
            <button type="submit" class="btn-primary" <?= ($contrato['periodo_estado'] !== 'ABIERTO' ? 'disabled' : '') ?>>
                <i class="fa-solid fa-file-arrow-up"></i> Subir Contrato
            </button>
        </div>
    </form>
</div>

<!-- SUBIR ADENDA -->
<div class="form-card">
    <h2 style="margin:0 0 12px 0;"><i class="fa-solid fa-file-circle-plus"></i> Subir Adenda (Addendum)</h2>

    <form method="POST" action="contrato_subir_pdf.php" enctype="multipart/form-data" class="form-grid">
        <input type="hidden" name="id_contrato" value="<?= (int)$contrato['id_contrato'] ?>">
        <input type="hidden" name="tipo" value="ADENDA">

        <div class="form-group">
            <label>Número de Adenda</label>
            <input type="number" name="numero_adenda" placeholder="Ej: 1" min="1" required>
        </div>

        <div class="form-group">
            <label>Título de Adenda</label>
            <input type="text" name="titulo" placeholder="Adenda #1 - Ajuste de cupos" required>
        </div>

        <div class="form-group" style="grid-column:1/-1">
            <label>Archivo (PDF)</label>
            <input type="file" name="pdf" accept="application/pdf" required>
        </div>

        <div class="form-group" style="grid-column:1/-1">
            <button type="submit" class="btn-primary" <?= ($contrato['periodo_estado'] !== 'ABIERTO' ? 'disabled' : '') ?>>
                <i class="fa-solid fa-file-arrow-up"></i> Subir Adenda
            </button>
        </div>
    </form>
</div>

<!-- LISTADO DOCUMENTOS -->
<div class="form-card">
    <h2 style="margin:0 0 12px 0;"><i class="fa-solid fa-folder-open"></i> Documentos del Contrato</h2>

    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Tipo</th>
                <th>N° Adenda</th>
                <th>Título</th>
                <th>Archivo</th>
                <th>Subido</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$documentos): ?>
            <tr><td colspan="6">No hay documentos subidos.</td></tr>
        <?php else: ?>
            <?php foreach ($documentos as $i => $d): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($d['tipo']) ?></td>
                <td><?= $d['tipo'] === 'ADENDA' ? (int)$d['numero_adenda'] : '-' ?></td>
                <td><?= htmlspecialchars($d['titulo']) ?></td>
                <td>
                    <a href="<?= htmlspecialchars($d['archivo_ruta']) ?>" target="_blank" style="color:#1f3a5f;font-weight:700">
                        <i class="fa-solid fa-file-pdf"></i> Ver PDF
                    </a>
                    <div class="mini"><?= htmlspecialchars($d['archivo_nombre']) ?></div>
                </td>
                <td><?= htmlspecialchars($d['subido_en']) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</section>
</main>
</div>

</body>
</html>
