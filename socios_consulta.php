<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}

require "config/conexion.php";
require_once "helpers/periodo.php";

// ── Selector de período ──────────────────────────────────────────
$todosPeriodos = $pdo->query("SELECT * FROM periodo_comercializacion ORDER BY fecha_apertura DESC")->fetchAll(PDO::FETCH_ASSOC);
$periodo_id = isset($_GET['periodo']) ? (int)$_GET['periodo'] : null;
if (!$periodo_id && !empty($todosPeriodos)) {
    $periodoAbierto = get_periodo_abierto($pdo);
    $periodo_id = $periodoAbierto ? $periodoAbierto['id_periodo'] : $todosPeriodos[0]['id_periodo'];
}

// ── Búsqueda ─────────────────────────────────────────────────────
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// ── Query principal filtrada por período ─────────────────────────
$sql = "
SELECT
    s.id_solicitud,
    s.identificacion,
    s.nombres_completos,
    s.estado_solicitud,
    s.correo,
    s.celular,
    s.fecha_nacimiento,
    a.id_acuerdo,
    a.numero_acuerdo,
    a.fecha_firma,
    a.archivo_pdf,
    a.provincia,
    a.canton,
    a.parroquia,
    a.sector,
    a.posee_riego,
    a.periodo_de_fertilizacion,
    a.cacao_nacional_has,
    a.estimado_produccion_nacional,
    a.cacao_ccn51_has,
    a.estimado_produccion_ccn51
FROM solicitud_ingreso s
LEFT JOIN acuerdo_productor a
    ON a.cedula = s.identificacion
   AND a.id_periodo = ?
WHERE s.id_periodo = ?
";

// Parámetros posicionales — evita HY093 con named params duplicados
$params = [$periodo_id, $periodo_id];

if ($search !== '') {
    $sql .= " AND (s.identificacion LIKE ? OR s.nombres_completos LIKE ? OR s.celular LIKE ?) ";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

// ✅ Orden alfabético por nombre
$sql .= " ORDER BY s.nombres_completos ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar documentos relacionados (optimizado en bloque)
$ids = array_column($lista, 'id_solicitud');
$docsBySolicitud = [];
if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmtDocs = $pdo->prepare("SELECT * FROM documentos_socios WHERE id_solicitud IN ($placeholders)");
    $stmtDocs->execute($ids);
    $docs = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);
    foreach ($docs as $d) {
        $docsBySolicitud[$d['id_solicitud']][] = $d;
    }
}

// Preparar datos para JS (combinar docs en cada fila)
$lista_js = $lista;
foreach ($lista_js as $k => $r) {
    $lista_js[$k]['documents'] = $docsBySolicitud[$r['id_solicitud']] ?? [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Consulta de socios</title>

<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/modal-message.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<?php include __DIR__."/layout/modals.php"; ?>

<style>
/* ── Toolbar ── */
.toolbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.toolbar-left  { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
.toolbar-right { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }

/* Selector de período destacado */
.periodo-wrap {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 8px 12px;
    background: #f0f4ff;
    border: 1px solid #c7d7f7;
    border-radius: 10px;
}
.periodo-wrap label { font-size: 12px; color: #1f3a5f; font-weight: 700; }
.periodo-wrap select {
    height: 36px;
    border: 1px solid #93c5fd;
    border-radius: 8px;
    padding: 0 10px;
    font-size: 13px;
    min-width: 230px;
    background: #fff;
    outline: none;
}

.search-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    height: 36px;
    box-sizing: border-box;
}
.search-wrap i { color: #6b7280; }
.search-wrap input {
    border: none;
    outline: none;
    font-size: 13px;
    min-width: 200px;
    background: transparent;
}

.btn-toolbar {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 36px;
    padding: 0 14px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    border: 1px solid #e5e7eb;
    text-decoration: none;
}
.btn-toolbar.primary { background: #1f3a5f; color: #fff; border-color: #1f3a5f; }
.btn-toolbar.primary:hover { background: #16304d; }
.btn-toolbar.ghost   { background: #f3f4f6; color: #111827; }
.btn-toolbar.ghost:hover { background: #e5e7eb; }

/* ── MODAL CONFIRMACIÓN / ÉXITO ── */
.modal-confirm { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:10000; align-items:center; justify-content:center; }
.modal-confirm.active { display:flex; }
.modal-confirm-box { background:#fff; width:380px; border-radius:16px; padding:28px 24px; text-align:center; box-shadow:0 30px 80px rgba(0,0,0,.25); animation:popIn .25s ease-out; }
@keyframes popIn { from{transform:scale(.9);opacity:0} to{transform:scale(1);opacity:1} }
.modal-confirm-icon { width:70px; height:70px; margin:0 auto 16px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:36px; }
.modal-confirm-icon.success { background:#dcfce7; color:#16a34a; }
.modal-confirm-icon.warning { background:#fee2e2; color:#dc2626; }
.modal-confirm h3 { margin:10px 0 6px; font-size:20px; color:#1f3a5f; }
.modal-confirm p  { font-size:14px; color:#4b5563; margin-bottom:18px; }
.modal-confirm strong { color:#111827; }
.modal-confirm-actions { display:flex; gap:12px; justify-content:center; }
.modal-confirm-actions button { border:none; padding:10px 18px; border-radius:8px; font-weight:600; cursor:pointer; font-size:14px; }
.btn-cancel  { background:#e5e7eb; color:#374151; }
.btn-cancel:hover { background:#d1d5db; }
.btn-danger  { background:#dc2626; color:#fff; }
.btn-danger:hover { background:#b91c1c; }
.btn-primary { background:#1f3a5f; color:#fff; }
.btn-primary:hover { background:#16304d; }

/* ── Tabla ── */
.table-responsive { overflow-x:auto; }
.data-table { width:100%; border-collapse:collapse; font-size:13px; }
.data-table thead th { background:#1f3a5f !important; color:#fff !important; text-align:left; padding:14px; font-weight:600; position:sticky; top:0; z-index:2; }
.data-table td { padding:12px 14px; border-bottom:1px solid #e5e7eb; color:#374151; }
.data-table tbody tr:hover td { background:#f9fafb; }

/* ── Botones ── */
.btn-icon-small {
    display:inline-flex; align-items:center; justify-content:center;
    width:32px; height:32px; background:#1f3a5f; color:#fff;
    border:none; border-radius:6px; cursor:pointer; font-size:14px;
    margin-right:6px; transition:background 0.2s;
}
.btn-icon-small:hover     { background:#16304d; }
.btn-icon-small.upload    { background:#10b981; }
.btn-icon-small.upload:hover { background:#059669; }
.btn-icon-small.solicitud { background:#0ea5e9; }
.btn-icon-small.solicitud:hover { background:#0284c7; }
.btn-icon-small.acuerdo   { background:#ef4444; }
.btn-icon-small.acuerdo:hover { background:#dc2626; }

/* ── MODAL SUBIDA ── */
.modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; }
.modal-overlay.active { display:flex; }
.modal-box { background:#fff; padding:30px; border-radius:12px; box-shadow:0 20px 50px rgba(0,0,0,.2); max-width:500px; width:95%; animation:slideUp 0.3s ease-out; position:relative; }
@keyframes slideUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
.modal-box h2 { color:#1f3a5f; margin-bottom:20px; }
.modal-box .close-btn { position:absolute; top:14px; right:14px; background:#fff; border:2px solid rgba(0,0,0,0.08); width:36px; height:36px; border-radius:50%; font-size:18px; cursor:pointer; color:#374151; display:inline-flex; align-items:center; justify-content:center; box-shadow:0 6px 18px rgba(0,0,0,.12); z-index:10001; }
.modal-box input, .modal-box select { width:100%; padding:10px 12px; margin-bottom:15px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; box-sizing:border-box; }
.modal-box button[type="submit"] { width:100%; padding:12px; background:#1f3a5f; color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:600; }
.modal-box button[type="submit"]:hover { background:#16304d; }
.modal-box .form-group { margin-bottom:15px; }
.modal-box label { display:block; margin-bottom:6px; font-weight:600; color:#374151; font-size:13px; }

/* Vacío */
.empty-state { text-align:center; padding:40px; color:#6b7280; }
.empty-state i { font-size:28px; display:block; margin-bottom:10px; }
</style>
</head>

<body>
<script src="layout/modal-message.js"></script>
<div class="app">
<?php include __DIR__."/layout/sidebar.php"; ?>

<main class="content">
<header class="topbar">
    <span>Bienvenido, <?= htmlspecialchars($_SESSION['usuario']) ?></span>
</header>

<section class="page">
<h1>Consulta de socios</h1>

<!-- ── TOOLBAR CON PERÍODO Y BUSCADOR ── -->
<form class="toolbar" method="GET" action="socios_consulta.php">

    <div class="toolbar-left">

        <!-- ✅ FIX 1: Input hidden para que la búsqueda conserve el período seleccionado -->
        <input type="hidden" name="periodo" value="<?= $periodo_id ?>">

        <!-- Selector de período -->
        <div class="periodo-wrap">
            <label for="periodo_visual"><i class="fa fa-calendar-alt"></i> Período</label>
            <select id="periodo_visual" onchange="document.querySelector('input[name=periodo]').value=this.value; this.form.submit()">
                <?php foreach ($todosPeriodos as $p): ?>
                    <option value="<?= $p['id_periodo'] ?>"
                        <?= $periodo_id == $p['id_periodo'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['nombre']) ?>
                        (<?= htmlspecialchars($p['estado']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Buscador -->
        <div class="search-wrap">
            <i class="fa fa-magnifying-glass"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Buscar por cédula, nombres o celular...">
        </div>

        <button type="submit" class="btn-toolbar primary">
            <i class="fa fa-filter"></i> Filtrar
        </button>
        <a href="socios_consulta.php" class="btn-toolbar ghost">
            <i class="fa fa-rotate-left"></i> Limpiar
        </a>

        <button type="button" class="btn-toolbar" style="background:#1d6f42;color:#fff;border-color:#1d6f42;" onclick="exportarExcel()">
            <i class="fa fa-file-excel"></i> Exportar Excel
        </button>

    </div>

    <!-- Total de registros -->
    <div class="toolbar-right">
        <span style="font-size:13px; color:#6b7280; align-self:center;">
            <?= count($lista) ?> registro(s) encontrado(s)
        </span>
    </div>

</form>

<div class="form-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Cédula</th>
                    <th>Nombres</th>
                    <th>Estado</th>
                    <th>Acuerdo</th>
                    <th>PDFs</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lista)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="fa fa-users-slash"></i>
                                No hay socios registrados para este período.
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($lista as $i => $row): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($row['identificacion']) ?></td>
                    <td><?= htmlspecialchars($row['nombres_completos']) ?></td>
                    <td>
                        <?php
                        $est = $row['estado_solicitud'] ?? 'PENDIENTE';
                        $bgEst = $est === 'APROBADO'  ? '#bbf7d0' : ($est === 'RECHAZADO' ? '#fecaca' : '#fde68a');
                        $clEst = $est === 'APROBADO'  ? '#065f46' : ($est === 'RECHAZADO' ? '#991b1b' : '#92400e');
                        ?>
                        <span style="background:<?= $bgEst ?>; color:<?= $clEst ?>; padding:4px 8px; border-radius:6px; font-size:12px; font-weight:600;">
                            <?= htmlspecialchars($est) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($row['numero_acuerdo']): ?>
                            <span style="background:#dbeafe; color:#1e40af; padding:4px 8px; border-radius:6px; font-size:12px;">
                                <?= htmlspecialchars($row['numero_acuerdo']) ?>
                            </span>
                        <?php else: ?>
                            <span style="background:#fee2e2; color:#991b1b; padding:4px 8px; border-radius:6px; font-size:12px;">
                                Sin acuerdo
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $docs       = $docsBySolicitud[$row['id_solicitud']] ?? [];
                        $solicitudPdf = null;
                        $acuerdoPdf   = null;

                        foreach ($docs as $d) {
                            if (($d['tipo_documento'] ?? '') === 'solicitud') $solicitudPdf = $d;
                            if (($d['tipo_documento'] ?? '') === 'acuerdo')   $acuerdoPdf   = $d;
                        }
                        if (empty($acuerdoPdf) && !empty($row['archivo_pdf'])) {
                            $acuerdoPdf = ['ruta_archivo' => $row['archivo_pdf'], 'nombre' => 'Acuerdo generado'];
                        }

                        if ($solicitudPdf) {
                            $t = htmlspecialchars($solicitudPdf['nombre'], ENT_QUOTES);
                            $r = htmlspecialchars($solicitudPdf['ruta_archivo'], ENT_QUOTES);
                            echo "<button class=\"btn-icon-small solicitud\" title=\"Ver solicitud\" onclick=\"abrirPdfOrUpload({$row['id_solicitud']}, 'solicitud', '{$r}', '{$t}')\"><i class=\"fa fa-file-pdf\"></i></button>";
                        } else {
                            echo "<button class=\"btn-icon-small solicitud\" title=\"Subir solicitud\" onclick=\"abrirPdfOrUpload({$row['id_solicitud']}, 'solicitud')\" style=\"opacity:0.6\"><i class=\"fa fa-file-pdf\"></i></button>";
                        }

                        if ($acuerdoPdf) {
                            $t = htmlspecialchars($acuerdoPdf['nombre'] ?? 'Acuerdo', ENT_QUOTES);
                            $r = htmlspecialchars($acuerdoPdf['ruta_archivo'], ENT_QUOTES);
                            echo "<button class=\"btn-icon-small acuerdo\" title=\"Ver acuerdo\" onclick=\"abrirPdfOrUpload({$row['id_solicitud']}, 'acuerdo', '{$r}', '{$t}')\"><i class=\"fa fa-file-pdf\"></i></button>";
                        } else {
                            echo "<button class=\"btn-icon-small acuerdo\" title=\"Subir acuerdo\" onclick=\"abrirPdfOrUpload({$row['id_solicitud']}, 'acuerdo')\" style=\"opacity:0.6\"><i class=\"fa fa-file-pdf\"></i></button>";
                        }
                        ?>
                    </td>
                    <td>
                        <button class="btn-icon-small upload"
                                onclick="abrirModalSubida(<?= $row['id_solicitud'] ?>, '<?= htmlspecialchars($row['nombres_completos'], ENT_QUOTES) ?>')"
                                title="Subir PDF">
                            <i class="fa fa-upload"></i>
                        </button>
                        <button class="btn-icon-small" style="background:#2563eb"
                                onclick="abrirInfoModal(<?= $row['id_solicitud'] ?>)"
                                title="Ver info">
                            <i class="fa fa-eye"></i>
                        </button>
                        <button class="btn-icon-small" style="background:#dc2626"
                                title="Eliminar"
                                onclick="eliminarAspirante(<?= $row['id_solicitud'] ?>)">
                            <i class="fa fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</section>
</main>
</div>

<!-- MODAL PARA SUBIR PDF -->
<div id="modalUpload" class="modal-overlay">
    <div class="modal-box">
        <button class="close-btn" onclick="cerrarModalSubida()">×</button>
        <h2>Subir documento PDF</h2>
        <form id="formUpload" enctype="multipart/form-data">
            <input type="hidden" name="id_solicitud" id="id_solicitud_upload">
            <div class="form-group">
                <label>Socio:</label>
                <input type="text" id="nombre_socio" readonly>
            </div>
            <div class="form-group">
                <label>Tipo de documento:</label>
                <select name="tipo_documento" required>
                    <option value="">-- Seleccionar --</option>
                    <option value="acuerdo">Acuerdo de productor</option>
                    <option value="solicitud">Solicitud de ingreso</option>
                    <option value="otro">Otro documento</option>
                </select>
            </div>
            <div class="form-group">
                <label>Nombre del documento:</label>
                <input type="text" name="nombre_documento" placeholder="Ej: Acuerdo 2025" required>
            </div>
            <div class="form-group">
                <label>Seleccionar archivo PDF:</label>
                <input type="file" name="archivo_pdf" accept=".pdf" required>
            </div>
            <button type="submit">
                <i class="fa fa-upload"></i> Subir documento
            </button>
        </form>
    </div>
</div>

<!-- MODAL VER INFORMACIÓN COMPLETA -->
<div id="modalInfo" class="modal-overlay">
    <div class="modal-box" style="max-width:1100px; width:92%; height:82vh; overflow:auto;">
        <button class="close-btn" onclick="cerrarModalInfo()">×</button>
        <h2 id="infoTitle" style="background:#1f3a5f;color:#fff;padding:10px;border-radius:6px;margin:-30px -30px 15px -30px">Detalle del socio</h2>
        <div id="infoContent"></div>
    </div>
</div>

<!-- MODAL VER PDF -->
<div id="modalPdf" class="modal-overlay">
    <div class="modal-box" style="max-width:900px; width:95%;">
        <button class="close-btn" onclick="cerrarModalPdf()">×</button>
        <h2 id="pdfTitle" style="background:#1f3a5f;color:#fff;padding:10px;border-radius:6px;margin:-30px -30px 15px -30px">Documento PDF</h2>
        <div style="height:75vh;">
            <iframe id="pdfViewer" src="" style="width:100%;height:100%;border:0"></iframe>
        </div>
    </div>
</div>

<script>
var sociosData = <?= json_encode($lista_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function recargarPagina() {
    // FIX: setTimeout garantiza que el modal cierre antes de recargar
    setTimeout(function() {
        window.location.href = window.location.href.split('#')[0];
    }, 400);
}

function abrirModalSubida(idSolicitud, nombreSocio, tipoPre = '') {
    document.getElementById('id_solicitud_upload').value = idSolicitud;
    document.getElementById('nombre_socio').value = nombreSocio;
    if (tipoPre) {
        const sel = document.querySelector('#formUpload select[name="tipo_documento"]');
        if (sel) sel.value = tipoPre;
    }
    document.getElementById('modalUpload').classList.add('active');
}

function abrirPdfOrUpload(idSolicitud, tipo, src = null, title = '') {
    if (src && src.trim() !== '') {
        abrirPdfModal(src, title || (tipo === 'acuerdo' ? 'Acuerdo' : 'Solicitud'));
        return;
    }
    const row = sociosData.find(r => Number(r.id_solicitud) === Number(idSolicitud));
    if (row && row.documents && row.documents.length) {
        const doc = row.documents.find(d => d.tipo_documento === tipo);
        if (doc && doc.ruta_archivo) {
            abrirPdfModal(doc.ruta_archivo, doc.nombre || (tipo === 'acuerdo' ? 'Acuerdo' : 'Solicitud'));
            return;
        }
    }
    abrirModalSubida(idSolicitud, row ? row.nombres_completos : '', tipo);
}

function cerrarModalSubida() {
    document.getElementById('modalUpload').classList.remove('active');
    document.getElementById('formUpload').reset();
}

document.getElementById('formUpload').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    try {
        const response = await fetch('pdf_upload.php', { method: 'POST', body: formData });
        const result   = await response.json();
        if (result.success) {
            mostrarMensaje('Éxito', 'Documento subido exitosamente', 'success', recargarPagina);
            cerrarModalSubida();
        } else {
            mostrarMensaje('Error', 'Error: ' + result.message, 'error');
        }
    } catch (error) {
        mostrarMensaje('Error', 'Error en la solicitud: ' + error, 'error');
    }
});

function abrirInfoModal(id) {
    const row = sociosData.find(r => Number(r.id_solicitud) === Number(id));
    if (!row) return mostrarMensaje('Error', 'Datos no encontrados', 'error');
    const content = document.getElementById('infoContent');

    let html = `
    <div style="display:flex;gap:18px;align-items:flex-start;">
        <div style="flex:1;background:#fff;border-radius:6px;padding:10px;border:1px solid #e6edf3">
            <h4 style="margin:0 0 10px 0;color:#1f3a5f">Solicitud de ingreso</h4>
            <table style="width:100%;font-size:13px">
                <tr><td style="width:40%;font-weight:600;color:#374151;padding:6px">Nombre</td><td style="padding:6px">${row.nombres_completos||'-'}</td></tr>
                <tr style="background:#fbfdff"><td style="font-weight:600;color:#374151;padding:6px">Cédula</td><td style="padding:6px">${row.identificacion||'-'}</td></tr>
                <tr><td style="font-weight:600;color:#374151;padding:6px">Correo</td><td style="padding:6px">${row.correo||'-'}</td></tr>
                <tr style="background:#fbfdff"><td style="font-weight:600;color:#374151;padding:6px">Teléfono</td><td style="padding:6px">${row.celular||'-'}</td></tr>
                <tr><td style="font-weight:600;color:#374151;padding:6px">Fecha nacimiento</td><td style="padding:6px">${row.fecha_nacimiento||'-'}</td></tr>
                <tr style="background:#fbfdff"><td style="font-weight:600;color:#374151;padding:6px">Estado</td><td style="padding:6px">
                    <select id="estadoSelect_${id}" style="padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px" onchange="cambiarEstado(${id}, this.value)">
                        <option value="PENDIENTE" ${row.estado_solicitud==='PENDIENTE'?'selected':''}>PENDIENTE</option>
                        <option value="APROBADO"  ${row.estado_solicitud==='APROBADO' ?'selected':''}>APROBADO</option>
                        <option value="RECHAZADO" ${row.estado_solicitud==='RECHAZADO'?'selected':''}>RECHAZADO</option>
                    </select>
                </td></tr>
            </table>
        </div>

        <div style="width:420px;background:#fff;border-radius:6px;padding:10px;border:1px solid #e6edf3">
            <h4 style="margin:0 0 10px 0;color:#1f3a5f">Acuerdo de productor</h4>
            <table style="width:100%;font-size:13px">
                <tr><td style="width:45%;font-weight:600;color:#374151;padding:6px">Número Acuerdo</td><td style="padding:6px">${row.numero_acuerdo||'-'}</td></tr>
                <tr style="background:#fbfdff"><td style="font-weight:600;color:#374151;padding:6px">Fecha firma</td><td style="padding:6px">${row.fecha_firma||'-'}</td></tr>
                <tr><td style="font-weight:600;color:#374151;padding:6px">Provincia</td><td style="padding:6px">${row.provincia||'-'}</td></tr>
                <tr style="background:#fbfdff"><td style="font-weight:600;color:#374151;padding:6px">Cantón</td><td style="padding:6px">${row.canton||'-'}</td></tr>
                <tr><td style="font-weight:600;color:#374151;padding:6px">Parroquia</td><td style="padding:6px">${row.parroquia||'-'}</td></tr>
                <tr style="background:#fbfdff"><td style="font-weight:600;color:#374151;padding:6px">Sector</td><td style="padding:6px">${row.sector||'-'}</td></tr>
                <tr><td style="font-weight:600;color:#374151;padding:6px">Posee riego</td><td style="padding:6px">${row.posee_riego||'-'}</td></tr>
                <tr style="background:#fbfdff"><td style="font-weight:600;color:#374151;padding:6px">Periodo fertilización</td><td style="padding:6px">${row.periodo_de_fertilizacion||'-'}</td></tr>
                <tr><td style="font-weight:600;color:#374151;padding:6px">Cacao nacional (has)</td><td style="padding:6px">${row.cacao_nacional_has||'-'}</td></tr>
                <tr style="background:#fbfdff"><td style="font-weight:600;color:#374151;padding:6px">Est. producción nacional</td><td style="padding:6px">${row.estimado_produccion_nacional||'-'}</td></tr>
                <tr><td style="font-weight:600;color:#374151;padding:6px">Cacao CCN51 (has)</td><td style="padding:6px">${row.cacao_ccn51_has||'-'}</td></tr>
                <tr style="background:#fbfdff"><td style="font-weight:600;color:#374151;padding:6px">Est. producción CCN51</td><td style="padding:6px">${row.estimado_produccion_ccn51||'-'}</td></tr>
            </table>
        </div>
    </div>

    <div style="margin-top:14px;border-top:2px solid #e6edf3;padding-top:12px">
        <h4 style="margin:0 0 8px 0;color:#1f3a5f">Documentos</h4>
        <div style="max-height:220px;overflow:auto">`;

    if (row.documents && row.documents.length) {
        row.documents.forEach(d => {
            const safeName = (d.nombre||'Documento').replace(/'/g,"\\'");
            html += `
            <div style="background:#fff;padding:10px;border-radius:8px;margin-bottom:10px;border-left:4px solid #1f3a5f;display:flex;align-items:center;gap:12px">
                <div style="flex:1">
                    <div style="font-weight:700;color:#1f3a5f;text-transform:capitalize;font-size:13px">${d.tipo_documento}</div>
                    <div style="font-size:12px;color:#6b7280;margin-top:4px">${d.nombre}</div>
                </div>
                <div style="display:flex;gap:8px">
                    <button style="flex:1;background:#1f3a5f;color:#fff;padding:10px;border-radius:8px;border:none;cursor:pointer;font-weight:700;font-size:13px"
                            onclick="abrirPdfModal('${d.ruta_archivo.replace(/'/g,"\\'")}','${safeName}')">
                        <i class="fa fa-file-pdf" style="margin-right:8px"></i> Ver
                    </button>
                    <button style="width:40px;height:40px;background:#ef4444;color:#fff;padding:0;border-radius:8px;border:none;cursor:pointer;font-weight:700"
                            onclick="eliminarDocumento(${d.id_documento||0}, ${row.id_solicitud})" title="Eliminar documento">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </div>`;
        });
    } else {
        html += '<p style="color:#9ca3af;font-size:13px;text-align:center">No hay documentos</p>';
    }

    html += `</div></div>`;

    if (row.id_acuerdo && !row.archivo_pdf) {
        html += `
        <div style="display:flex;gap:10px;margin-top:14px;padding-top:12px;align-items:center">
            <input id="cupo_nacional_${row.id_acuerdo}" placeholder="Cupo Nacional (QQ)"
                   style="width:45%;padding:10px;border:1px solid #d1d5db;border-radius:8px;font-size:14px">
            <input id="cupo_ccn51_${row.id_acuerdo}" placeholder="Cupo CCN51 (QQ)"
                   style="width:45%;padding:10px;border:1px solid #d1d5db;border-radius:8px;font-size:14px">
            <button style="padding:10px 14px;background:#059669;color:#fff;border-radius:8px;border:none;cursor:pointer;font-weight:700"
                    onclick="asignarCupo(${row.id_acuerdo},
                        document.getElementById('cupo_nacional_${row.id_acuerdo}').value,
                        document.getElementById('cupo_ccn51_${row.id_acuerdo}').value)">
                <i class="fa fa-file-pdf" style="margin-right:8px"></i> Asignar cupo
            </button>
        </div>`;
    }

    content.innerHTML = html;
    document.getElementById('modalInfo').classList.add('active');
}

function cambiarEstado(id, nuevoEstado) {
    fetch('actualizar_estado.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'id_solicitud=' + id + '&estado=' + encodeURIComponent(nuevoEstado)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            mostrarMensaje('Éxito', 'Estado actualizado correctamente', 'success', recargarPagina);
        } else {
            mostrarMensaje('Error', data.message || 'Error al actualizar', 'error');
        }
    })
    .catch(e => mostrarMensaje('Error', 'Error: ' + e, 'error'));
}

function cerrarModalInfo() {
    document.getElementById('modalInfo').classList.remove('active');
}

function abrirPdfModal(src, title) {
    document.getElementById('pdfTitle').textContent = title || 'Documento PDF';
    document.getElementById('pdfViewer').src = 'documentos/pdf/' + src.split('/').pop();
    document.getElementById('modalPdf').classList.add('active');
}

function cerrarModalPdf() {
    document.getElementById('modalPdf').classList.remove('active');
    document.getElementById('pdfViewer').src = '';
}

function eliminarDocumento(idDocumento, idSolicitud) {
    mostrarConfirmacion('Eliminar documento', '¿Eliminar este documento? Esta acción no se puede deshacer.', function() {
        var fd = new FormData();
        fd.append('id_documento', idDocumento);
        fd.append('id_solicitud', idSolicitud);
        fetch('eliminar_documento.php', { method: 'POST', body: fd })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    mostrarMensaje('Éxito', data.message || 'Documento eliminado', 'success', recargarPagina);
                } else {
                    mostrarMensaje('Error', data.message || 'Error al eliminar', 'error');
                }
            })
            .catch(function(e) {
                mostrarMensaje('Error', 'Error en la petición: ' + e, 'error');
            });
    });
}

function eliminarAspirante(id) {
    mostrarConfirmacion('Eliminar aspirante', '¿Eliminar este aspirante y todos sus documentos y acuerdos? Esta acción no se puede deshacer.', function() {
        var fd = new FormData();
        fd.append('id_solicitud', id);
        fetch('eliminar_aspirante.php', { method: 'POST', body: fd })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    mostrarMensaje('Éxito', data.message || 'Aspirante eliminado', 'success', recargarPagina);
                } else {
                    mostrarMensaje('Error', data.message || 'Error al eliminar', 'error');
                }
            })
            .catch(function(e) {
                mostrarMensaje('Error', 'Error en la petición: ' + e, 'error');
            });
    });
}

function exportarExcel() {
    // Filtrar solo socios que tienen acuerdo
    const conAcuerdo = sociosData.filter(r => r.numero_acuerdo);

    if (!conAcuerdo.length) {
        mostrarMensaje('Sin datos', 'No hay socios con acuerdo en este período.', 'error');
        return;
    }

    // Cabeceras
    const headers = ['N°', 'Zona', 'Comunidad o Grupo', 'Cédula del Productor', 'Apellidos y Nombres Productor/a'];

    // Filas
    const rows = conAcuerdo.map((r, i) => [
        i + 1,
        r.sector   || '',
        r.parroquia|| '',
        r.identificacion      || '',
        r.nombres_completos   || ''
    ]);

    // Construir tabla HTML para Excel
    let html = '<table border="1">';
    html += '<tr>' + headers.map(h => `<th style="background:#1f3a5f;color:#fff;font-weight:bold;padding:6px">${h}</th>`).join('') + '</tr>';
    rows.forEach((row, i) => {
        const bg = i % 2 === 0 ? '#ffffff' : '#f0f4ff';
        html += '<tr>' + row.map(c => `<td style="background:${bg};padding:5px">${c}</td>`).join('') + '</tr>';
    });
    html += '</table>';

    // Descargar como .xls
    const blob = new Blob(["\uFEFF" + html], { type: 'application/vnd.ms-excel;charset=utf-8' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'socios_acuerdo.xls';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function asignarCupo(id_acuerdo, cupoNacional, cupoCcn51) {
    var fd = new FormData();
    fd.append('id_acuerdo',    id_acuerdo);
    fd.append('cupo_nacional', cupoNacional || '');
    fd.append('cupo_ccn51',    cupoCcn51    || '');
    fetch('asignar_cupo.php', { method: 'POST', body: fd })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                mostrarMensaje('Éxito', data.message || 'Cupo asignado', 'success', recargarPagina);
            } else {
                mostrarMensaje('Error', data.message || 'Error al asignar', 'error');
            }
        })
        .catch(function(e) {
            mostrarMensaje('Error', 'Error en la petición: ' + e, 'error');
        });
}
</script>

</body>
</html>