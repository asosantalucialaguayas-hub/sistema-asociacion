<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}

require "config/conexion.php";

function calcular_tarifa_por_has(float $hasTotal): float {
    if ($hasTotal >= 1 && $hasTotal <= 5) return 200.00;
    if ($hasTotal >= 6 && $hasTotal <= 10) return 250.00;
    if ($hasTotal >= 11 && $hasTotal <= 20) return 300.00;
    if ($hasTotal >= 21 && $hasTotal <= 30) return 350.00;
    return 0.00;
}
function money($n): string { return '$ ' . number_format((float)$n, 2); }

$id_pago = isset($_GET['id_pago']) ? (int)$_GET['id_pago'] : 0;
if ($id_pago <= 0) { header("Location: pago_inscripcion.php"); exit; }

$stmt = $pdo->prepare("
    SELECT p.id_pago, p.id_acuerdo, p.id_socio, p.has_nacional, p.has_ccn51,
           p.has_total, p.total_debe_usd, p.estado AS estado_pago, p.fecha_creado,
           a.numero_acuerdo, a.cedula, a.nombres_completos, a.provincia, a.canton,
           a.parroquia, a.sector, a.fecha_firma
    FROM pago_inscripcion p
    INNER JOIN acuerdo_productor a ON a.id_acuerdo = p.id_acuerdo
    WHERE p.id_pago = ? LIMIT 1
");
$stmt->execute([$id_pago]);
$head = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$head) { header("Location: pago_inscripcion.php"); exit; }

$stmt = $pdo->prepare("
    SELECT id_abono, fecha_abono, monto_usd, metodo_pago, referencia,
           comprobante_pdf, observacion, estado
    FROM pago_inscripcion_abono
    WHERE id_pago = ?
    ORDER BY fecha_abono DESC
");
$stmt->execute([$id_pago]);
$abonos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPagado = 0.0;
foreach ($abonos as $ab) {
    if (($ab['estado'] ?? '') === 'REGISTRADO') $totalPagado += (float)$ab['monto_usd'];
}
$debe  = (float)$head['total_debe_usd'];
$saldo = max(0, $debe - $totalPagado);
$pct   = $debe > 0 ? min(100, ($totalPagado / $debe) * 100) : 0;

$badge = ['class'=>'badge-off','text'=>'SIN REGISTRO'];
if (($head['estado_pago'] ?? '') === 'PAGADO')        $badge = ['class'=>'badge-ok', 'text'=>'PAGADO'];
elseif (($head['estado_pago'] ?? '') === 'PARCIAL')   $badge = ['class'=>'badge-mid','text'=>'PARCIAL'];
elseif (($head['estado_pago'] ?? '') === 'PENDIENTE') $badge = ['class'=>'badge-no', 'text'=>'PENDIENTE'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Detalle pago de inscripción</title>
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/modal-message.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.btn-actions{ display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
.btn-actions a{ display:inline-flex; align-items:center; gap:8px; padding:12px 20px; background:#2563eb; color:#fff; border-radius:8px; text-decoration:none; font-weight:800; font-size:14px; }
.btn-actions a:hover{ background:#1d4ed8; }
.btn-actions .btn-gray{ background:#6b7280; }
.btn-actions .btn-gray:hover{ background:#4b5563; }
.form-grid{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
@media(max-width:1000px){ .form-grid{ grid-template-columns:1fr 1fr; } }
.input{ width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:10px; outline:none; font-size:14px; background:#fff; }
.small-muted{ color:#6b7280; font-size:12px; }
.mono{ font-variant-numeric:tabular-nums; }
.nowrap{ white-space:nowrap; }
.kpi-row{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-top:12px; }
@media(max-width:1000px){ .kpi-row{ grid-template-columns:1fr 1fr; } }
.kpi{ background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:14px; }
.kpi .label{ color:#6b7280; font-size:12px; }
.kpi .value{ font-size:16px; font-weight:900; color:#111827; margin-top:4px; }
.progress-bar{ width:100%; height:10px; background:#e5e7eb; border-radius:999px; overflow:hidden; margin-top:8px; }
.progress-fill{ height:100%; width:0%; border-radius:999px; background:#2563eb; }
.badge{ display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-weight:900; font-size:11px; letter-spacing:.2px; white-space:nowrap; }
.badge-ok { background:#dcfce7; color:#166534; }
.badge-mid{ background:#fef9c3; color:#854d0e; }
.badge-no { background:#fee2e2; color:#991b1b; }
.badge-off{ background:#f3f4f6; color:#4b5563; }
.table-responsive{ overflow-x:auto; }
.data-table{ width:100%; border-collapse:collapse; font-size:13px; }
.data-table thead{ background:#1f3a5f !important; }
.data-table th{ text-align:left; padding:14px; font-weight:600; background:#1f3a5f !important; color:#fff !important; position:sticky; top:0; z-index:2; white-space:nowrap; }
.data-table td{ padding:12px 14px; border-bottom:1px solid #e5e7eb; color:#374151 !important; vertical-align:middle; }
.data-table tbody tr:hover{ background:#f9fafb !important; }
.btn-icon{ display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:14px; text-decoration:none; }
.btn-icon:hover{ background:#1d4ed8; }
.btn-red{ background:#dc2626; }
.btn-red:hover{ background:#b91c1c; }
</style>
</head>

<body>
<div class="app">

<!-- ✅ CLAVE: modal-message.js se carga PRIMERO, antes del sidebar y de jQuery -->
<script src="/layout/modal-message.js"></script>

<?php include __DIR__."/layout/sidebar.php"; ?>

<main class="content">
<header class="topbar">
    <span>Bienvenido, <?= htmlspecialchars($_SESSION['usuario']) ?></span>
</header>

<section class="page">
<h1>Detalle pago de inscripción</h1>

<div class="btn-actions">
    <a href="pago_inscripcion.php" class="btn-gray">
        <i class="fa fa-arrow-left"></i> Volver al listado
    </a>
    <a href="pago_inscripcion_form.php?buscar=<?= urlencode($head['cedula']) ?>">
        <i class="fa fa-money-bill-wave"></i> Registrar abono
    </a>
</div>

<div class="form-card">
    <div class="form-grid">
        <div class="form-group">
            <label>N° Acuerdo</label>
            <input class="input" value="<?= htmlspecialchars($head['numero_acuerdo']) ?>" readonly>
        </div>
        <div class="form-group">
            <label>Cédula</label>
            <input class="input" value="<?= htmlspecialchars($head['cedula']) ?>" readonly>
        </div>
        <div class="form-group">
            <label>Nombres</label>
            <input class="input" value="<?= htmlspecialchars($head['nombres_completos']) ?>" readonly>
        </div>
        <div class="form-group">
            <label>Ubicación</label>
            <input class="input" value="<?= htmlspecialchars($head['provincia'].' / '.$head['canton']) ?>" readonly>
        </div>
    </div>

    <div class="kpi-row">
        <div class="kpi">
            <div class="label">Has Nacional</div>
            <div class="value mono"><?= number_format((float)$head['has_nacional'], 2) ?></div>
        </div>
        <div class="kpi">
            <div class="label">Has CCN51</div>
            <div class="value mono"><?= number_format((float)$head['has_ccn51'], 2) ?></div>
        </div>
        <div class="kpi">
            <div class="label">Debe (Inscripción)</div>
            <div class="value mono"><?= money($debe) ?></div>
        </div>
        <div class="kpi">
            <div class="label">Estado</div>
            <div class="value"><span class="badge <?= $badge['class'] ?>"><?= $badge['text'] ?></span></div>
        </div>
    </div>

    <div style="margin-top:14px;">
        <div class="small-muted">
            Pagado: <strong class="mono"><?= money($totalPagado) ?></strong>
            &nbsp;|&nbsp;
            Saldo: <strong class="mono"><?= money($saldo) ?></strong>
            &nbsp;|&nbsp;
            Progreso: <strong class="mono"><?= number_format($pct,0) ?>%</strong>
        </div>
        <div class="progress-bar" title="<?= number_format($pct,0) ?>%">
            <div class="progress-fill" style="width:<?= number_format($pct,2) ?>%;"></div>
        </div>
    </div>
</div>

<div class="form-card">
    <h3 style="margin:0 0 12px 0;color:#1f3a5f;">Historial de abonos</h3>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th class="nowrap">Fecha</th>
                    <th class="nowrap">Método</th>
                    <th class="nowrap">Monto</th>
                    <th>Referencia</th>
                    <th>PDF</th>
                    <th>Observación</th>
                    <th class="nowrap">Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($abonos)): ?>
                <tr><td colspan="9" class="small-muted" style="padding:20px;text-align:center;">No hay abonos registrados.</td></tr>
            <?php else: ?>
                <?php foreach ($abonos as $i => $ab): ?>
                    <?php
                        $estadoAb    = ($ab['estado'] ?? '') === 'ANULADO' ? 'ANULADO' : 'REGISTRADO';
                        $estadoClass = $estadoAb === 'ANULADO' ? 'badge-no' : 'badge-ok';
                    ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="nowrap"><?= date('d/m/Y H:i', strtotime($ab['fecha_abono'])) ?></td>
                        <td class="nowrap"><strong><?= htmlspecialchars($ab['metodo_pago']) ?></strong></td>
                        <td class="nowrap mono"><?= money($ab['monto_usd']) ?></td>
                        <td><?= htmlspecialchars($ab['referencia'] ?? '') ?></td>
                        <td>
                            <?php if (!empty($ab['comprobante_pdf'])): ?>
                                <a href="<?= htmlspecialchars($ab['comprobante_pdf']) ?>" target="_blank"
                                   class="badge badge-off" style="text-decoration:none;">
                                    <i class="fa fa-file-pdf"></i> Ver
                                </a>
                            <?php else: ?>
                                <span class="small-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($ab['observacion'] ?? '') ?></td>
                        <td><span class="badge <?= $estadoClass ?>"><?= $estadoAb ?></span></td>
                        <td>
                            <?php if ($estadoAb === 'REGISTRADO'): ?>
                                <!-- ✅ data-id correcto: usa id_abono -->
                                <button
                                    class="btn-icon btn-red abono-eliminar-btn"
                                    title="Eliminar abono"
                                    data-id="<?= (int)$ab['id_abono'] ?>">
                                    <i class="fa fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</section>
</main>
</div>

<!-- jQuery al final, DESPUÉS de modal-message.js -->
<!-- Pega esto justo antes de </body> -->
<!-- jQuery primero, luego modal-message.js, luego tu script -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="/layout/modal-message.js"></script>
<script>
// ─── helper de mensaje (no pisa window.mostrarMensaje) ───────────────────────
function uiMsg(titulo, mensaje, tipo, ms, cb) {
    tipo = tipo || 'success';
    ms   = (ms === undefined || ms === null) ? 2500 : ms;
    window.mostrarMensaje(titulo, mensaje, tipo, ms, cb);
}

// ─── confirmación PROPIA (modal-message.js no la tiene) ──────────────────────
function uiConfirmar(titulo, mensaje, onConfirmar) {
    // Reutiliza el CSS que ya tienes de modal-message pero con dos botones
    var overlay = document.createElement('div');
    overlay.className = 'modal-message-overlay';
    overlay.style.zIndex = '999999';
    overlay.innerHTML =
        '<div class="modal-message-box modal-info" role="dialog" aria-modal="true">' +
            '<div class="modal-message-icon">⚠️</div>' +
            '<h2>' + titulo + '</h2>' +
            '<p>' + mensaje + '</p>' +
            '<div style="display:flex;gap:10px;justify-content:center;margin-top:10px">' +
                '<button id="uiConfCancelar" type="button" ' +
                    'style="padding:10px 22px;border-radius:8px;border:1px solid #d1d5db;' +
                           'background:#f3f4f6;color:#374151;font-weight:700;cursor:pointer;font-size:14px">' +
                    'Cancelar' +
                '</button>' +
                '<button id="uiConfAceptar" type="button" ' +
                    'style="padding:10px 22px;border-radius:8px;border:none;' +
                           'background:#dc2626;color:#fff;font-weight:700;cursor:pointer;font-size:14px">' +
                    'Confirmar' +
                '</button>' +
            '</div>' +
        '</div>';

    document.body.appendChild(overlay);

    function cerrar() { overlay.remove(); }

    overlay.querySelector('#uiConfAceptar').addEventListener('click', function () {
        cerrar();
        if (typeof onConfirmar === 'function') onConfirmar();
    });

    overlay.querySelector('#uiConfCancelar').addEventListener('click', cerrar);

    // Click fuera cierra
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) cerrar();
    });

    // ESC cierra
    document.addEventListener('keydown', function esc(e) {
        if (e.key === 'Escape') { cerrar(); document.removeEventListener('keydown', esc); }
    });
}

// ─── click botón eliminar ─────────────────────────────────────────────────────
$(document).on('click', '.abono-eliminar-btn', function () {
    var idAbono = $(this).data('id');
    if (!idAbono) { console.error('data-id vacío'); return; }

    uiConfirmar(
        'Eliminar abono',
        '¿Está seguro de eliminar este abono? Esta acción no se puede deshacer.',
        function () { hacerEliminar(idAbono); }
    );
});

// ─── AJAX eliminar ────────────────────────────────────────────────────────────
function hacerEliminar(idAbono) {
    $.post('eliminar_abono.php', { id_abono: idAbono }, function (resp) {
        if (resp && resp.success) {
            uiMsg('Eliminado', resp.message || 'Abono eliminado correctamente', 'success', 1800,
                function () { location.reload(); });
        } else {
            uiMsg('Error',
                (resp && resp.message) ? resp.message : 'No se pudo eliminar el abono',
                'error');
        }
    }, 'json').fail(function () {
        uiMsg('Error', 'Error de conexión con el servidor', 'error');
    });
}
</script>

</body>
</html>