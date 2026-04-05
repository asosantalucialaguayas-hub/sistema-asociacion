<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}

require "config/conexion.php";

/* =========================
   HELPERS
========================= */
function calcular_tarifa_por_has(float $hasTotal): float {
    if ($hasTotal >= 1 && $hasTotal <= 5) return 200.00;
    if ($hasTotal >= 6 && $hasTotal <= 10) return 250.00;
    if ($hasTotal >= 11 && $hasTotal <= 20) return 300.00;
    if ($hasTotal >= 21 && $hasTotal <= 30) return 350.00;
    return 0.00;
}
function money($n): string { return '$ ' . number_format((float)$n, 2); }

/* =========================
   BUSCAR ACUERDO POR CÉDULA
   (Trae datos desde acuerdo_productor)
========================= */
$acuerdo = null;
$mensaje = null;

if (isset($_GET['buscar']) && trim($_GET['buscar']) !== '') {
    $cedula = trim($_GET['buscar']);

    $stmt = $pdo->prepare("
        SELECT
            a.id_acuerdo,
            a.id_socio,
            a.numero_acuerdo,
            a.cedula,
            a.fecha_nacimiento,
            a.nombres_completos,
            a.provincia,
            a.canton,
            a.parroquia,
            a.sector,
            a.posee_riego,
            a.periodo_de_fertilizacion,
            COALESCE(a.cacao_nacional_has,0) AS cacao_nacional_has,
            COALESCE(a.cacao_ccn51_has,0) AS cacao_ccn51_has,
            a.fecha_firma
        FROM acuerdo_productor a
        WHERE a.cedula = ?
        ORDER BY a.id_acuerdo DESC
        LIMIT 1
    ");
    $stmt->execute([$cedula]);
    $acuerdo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$acuerdo) {
        $mensaje = "No se encontró un acuerdo para esa cédula.";
    }
}

/* =========================
   CARGAR ESTADO DE PAGO (si existe)
========================= */
$pago = null;
$abonos = [];

if ($acuerdo) {
    $stmt = $pdo->prepare("
        SELECT id_pago, total_debe_usd, estado, has_total, has_nacional, has_ccn51
        FROM pago_inscripcion
        WHERE id_acuerdo = ?
        LIMIT 1
    ");
    $stmt->execute([$acuerdo['id_acuerdo']]);
    $pago = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pago) {
        $stmt = $pdo->prepare("
            SELECT id_abono, fecha_abono, monto_usd, metodo_pago, referencia, comprobante_pdf, observacion
            FROM pago_inscripcion_abono
            WHERE id_pago = ? AND estado='REGISTRADO'
            ORDER BY fecha_abono DESC
        ");
        $stmt->execute([$pago['id_pago']]);
        $abonos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/* =========================
   GUARDAR / REGISTRAR ABONO
   - Si no existe cabecera: la crea (snapshot has + total_debe)
   - Inserta abono
   - Actualiza estado (PENDIENTE/PARCIAL/PAGADO)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_acuerdo = (int)($_POST['id_acuerdo'] ?? 0);
    $monto      = (float)($_POST['monto_usd'] ?? 0);
    $metodo     = trim($_POST['metodo_pago'] ?? '');
    $referencia = trim($_POST['referencia'] ?? '');
    $obs        = trim($_POST['observacion'] ?? '');

    // archivo opcional
    $rutaPdf = null;
    if (!empty($_FILES['comprobante_pdf']['name'])) {
        $dir = __DIR__ . "/uploads/comprobantes_inscripcion";
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES['comprobante_pdf']['name'], PATHINFO_EXTENSION));
        $permitidos = ['pdf'];
        if (!in_array($ext, $permitidos, true)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Solo se permite PDF.']);
            exit;
        }

        $nombre = "comp_insc_" . time() . "_" . rand(1000,9999) . ".pdf";
        $destino = $dir . "/" . $nombre;
        if (!move_uploaded_file($_FILES['comprobante_pdf']['tmp_name'], $destino)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No se pudo subir el PDF.']);
            exit;
        }

        // ruta web relativa
        $rutaPdf = "uploads/comprobantes_inscripcion/" . $nombre;
    }

    if ($id_acuerdo <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Acuerdo inválido.']);
        exit;
    }
    if ($monto <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'El monto debe ser mayor a 0.']);
        exit;
    }
    if ($metodo === '') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Seleccione un método de pago.']);
        exit;
    }

    // Traer acuerdo para snapshot
    $stmt = $pdo->prepare("
        SELECT id_acuerdo, id_socio, COALESCE(cacao_nacional_has,0) nac, COALESCE(cacao_ccn51_has,0) ccn
        FROM acuerdo_productor
        WHERE id_acuerdo = ?
        LIMIT 1
    ");
    $stmt->execute([$id_acuerdo]);
    $a = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$a) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No existe el acuerdo.']);
        exit;
    }

    $hasNac   = (float)$a['nac'];
    $hasCcn   = (float)$a['ccn'];
    $hasTotal = $hasNac + $hasCcn;
    $debe     = calcular_tarifa_por_has($hasTotal);

    if ($debe <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'El total de hectáreas está fuera de la tabla de tarifas.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1) Buscar/crear cabecera
        $stmt = $pdo->prepare("SELECT id_pago FROM pago_inscripcion WHERE id_acuerdo = ? LIMIT 1");
        $stmt->execute([$id_acuerdo]);
        $id_pago = (int)$stmt->fetchColumn();

        if ($id_pago <= 0) {
            $stmt = $pdo->prepare("
                INSERT INTO pago_inscripcion (
                    id_acuerdo, id_socio,
                    has_nacional, has_ccn51, has_total,
                    total_debe_usd, estado
                ) VALUES (?,?,?,?,?,?, 'PENDIENTE')
            ");
            $stmt->execute([
                $id_acuerdo,
                (int)$a['id_socio'],
                $hasNac,
                $hasCcn,
                $hasTotal,
                $debe
            ]);
            $id_pago = (int)$pdo->lastInsertId();
        }

        // 2) Insertar abono
        $stmt = $pdo->prepare("
            INSERT INTO pago_inscripcion_abono
                (id_pago, monto_usd, metodo_pago, referencia, comprobante_pdf, observacion)
            VALUES (?,?,?,?,?,?)
        ");
        $stmt->execute([$id_pago, $monto, $metodo, $referencia ?: null, $rutaPdf, $obs ?: null]);

        // 3) Recalcular total pagado y actualizar estado
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(monto_usd),0)
            FROM pago_inscripcion_abono
            WHERE id_pago = ? AND estado='REGISTRADO'
        ");
        $stmt->execute([$id_pago]);
        $totalPagado = (float)$stmt->fetchColumn();

        $nuevoEstado = 'PENDIENTE';
        if ($totalPagado > 0 && $totalPagado < $debe) $nuevoEstado = 'PARCIAL';
        if ($totalPagado >= $debe) $nuevoEstado = 'PAGADO';

        $stmt = $pdo->prepare("UPDATE pago_inscripcion SET estado=? WHERE id_pago=?");
        $stmt->execute([$nuevoEstado, $id_pago]);

        $pdo->commit();

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Abono registrado correctamente.',
            'estado'  => $nuevoEstado,
            'totalPagado' => $totalPagado,
            'debe' => $debe
        ]);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error al guardar: '.$e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Registro de pago de inscripción</title>

<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.search-row { display:flex; gap:12px; align-items:center; }
.search-row input { flex:1; padding:12px 14px; font-size:15px; }

.btn-search{
    background:#1f3a5f; color:#fff; border:none; padding:12px 22px; border-radius:8px;
    cursor:pointer; font-size:14px;
}
.btn-search:hover{ background:#16304d; }

.kpi-row{
    display:grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap:12px;
    margin-top: 12px;
}
.kpi{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:12px;
    padding:14px;
}
.kpi .label{ color:#6b7280; font-size:12px; }
.kpi .value{ font-size:16px; font-weight:800; color:#111827; margin-top:4px; }
.kpi .sub{ color:#6b7280; font-size:11px; margin-top:4px; }

@media (max-width: 1000px){
    .kpi-row{ grid-template-columns: 1fr 1fr; }
}

.progress-bar{ width:100%; height:10px; background:#e5e7eb; border-radius:999px; overflow:hidden; margin-top:8px;}
.progress-fill{ height:100%; width:0%; border-radius:999px; background:#2563eb; }

.btn-actions{
    display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap;
}
.btn-actions a{
    display:inline-flex; align-items:center; gap:8px;
    padding:12px 20px; background:#2563eb; color:#fff;
    border-radius:8px; text-decoration:none; font-weight:700; font-size:14px;
}
.btn-actions a:hover{ background:#1d4ed8; }

.table-responsive{ overflow-x:auto; }
.data-table{ width:100%; border-collapse:collapse; font-size:13px; }
.data-table thead{ background:#1f3a5f !important; }
.data-table th{
    text-align:left; padding:14px; font-weight:600; background:#1f3a5f !important; color:#fff !important;
    position:sticky; top:0; z-index:2; white-space:nowrap;
}
.data-table td{ padding:12px 14px; border-bottom:1px solid #e5e7eb; color:#374151 !important; }
.data-table tbody tr:hover{ background:#f9fafb !important; }

.badge{
    display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px;
    font-weight:800; font-size:11px; letter-spacing:.2px; white-space:nowrap;
}
.badge-ok { background:#dcfce7; color:#166534; }
.badge-mid { background:#fef9c3; color:#854d0e; }
.badge-no { background:#fee2e2; color:#991b1b; }
.badge-off { background:#f3f4f6; color:#4b5563; }

.form-actions button{
    background:#1f3a5f; color:#fff; border:none;
    padding:12px 22px; border-radius:8px; cursor:pointer; font-weight:800;
}
.form-actions button:hover{ background:#16304d; }

.input, select, textarea{
    width:100%;
    padding:10px 12px;
    border:1px solid #e5e7eb;
    border-radius:10px;
    outline:none;
    font-size:14px;
}
.input:focus, select:focus, textarea:focus{
    border-color:#93c5fd;
    box-shadow:0 0 0 3px rgba(37,99,235,.15);
}
.small-muted{ color:#6b7280; font-size:12px; }
.mono{ font-variant-numeric: tabular-nums; }
.nowrap{ white-space:nowrap; }
</style>
<?php include 'layout/modals.php'; ?>
</head>

<body>
<div class="app">
<?php include __DIR__."/layout/sidebar.php"; ?>

<main class="content">
<header class="topbar">
    <span>Bienvenido, <?= htmlspecialchars($_SESSION['usuario']) ?></span>
</header>

<section class="page">
<h1>Registro de pago de inscripción</h1>

<div class="btn-actions">
    <a href="pago_inscripcion.php">
        <i class="fa fa-arrow-left"></i> Volver al listado
    </a>
</div>

<!-- BUSCADOR -->
<div class="form-card">
<form method="GET" action="pago_inscripcion_form.php">
    <label>Buscar por cédula (acuerdo de productor)</label>
    <div class="search-row">
        <input type="text" name="buscar" placeholder="Ingrese cédula" value="<?= isset($_GET['buscar']) ? htmlspecialchars($_GET['buscar']) : '' ?>" required>
        <button class="btn-search" type="submit">
            <i class="fa fa-search"></i> Buscar
        </button>
    </div>
    <?php if ($mensaje): ?>
        <div class="small-muted" style="margin-top:10px; color:#991b1b; font-weight:700;"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
</form>
</div>

<?php if ($acuerdo): ?>
<?php
    $hasTotal = (float)$acuerdo['cacao_nacional_has'] + (float)$acuerdo['cacao_ccn51_has'];
    $debeCalc = calcular_tarifa_por_has($hasTotal);

    $debe = $pago && $pago['total_debe_usd'] !== null ? (float)$pago['total_debe_usd'] : $debeCalc;

    $totalPagado = 0.0;
    foreach ($abonos as $ab) $totalPagado += (float)$ab['monto_usd'];

    $saldo = max(0, $debe - $totalPagado);
    $pct = $debe > 0 ? min(100, ($totalPagado / $debe) * 100) : 0;

    $badge = ['class'=>'badge-off','text'=>'SIN REGISTRO'];
    if ($pago) {
        if ($totalPagado <= 0) $badge = ['class'=>'badge-no','text'=>'PENDIENTE'];
        elseif ($totalPagado < $debe) $badge = ['class'=>'badge-mid','text'=>'PARCIAL'];
        else $badge = ['class'=>'badge-ok','text'=>'PAGADO'];
    }
?>
<!-- RESUMEN -->
<div class="form-card">
    <div class="form-grid">
        <div class="form-group">
            <label>N° Acuerdo</label>
            <input class="input" value="<?= htmlspecialchars($acuerdo['numero_acuerdo']) ?>" readonly>
        </div>
        <div class="form-group">
            <label>Cédula</label>
            <input class="input" value="<?= htmlspecialchars($acuerdo['cedula']) ?>" readonly>
        </div>
        <div class="form-group">
            <label>Nombres</label>
            <input class="input" value="<?= htmlspecialchars($acuerdo['nombres_completos']) ?>" readonly>
        </div>
        <div class="form-group">
            <label>Ubicación</label>
            <input class="input" value="<?= htmlspecialchars($acuerdo['provincia'].' / '.$acuerdo['canton']) ?>" readonly>
        </div>
    </div>

    <div class="kpi-row">
        <div class="kpi">
            <div class="label">Has Nacional</div>
            <div class="value mono"><?= number_format((float)$acuerdo['cacao_nacional_has'], 2) ?></div>
        </div>
        <div class="kpi">
            <div class="label">Has CCN51</div>
            <div class="value mono"><?= number_format((float)$acuerdo['cacao_ccn51_has'], 2) ?></div>
        </div>
        <div class="kpi">
            <div class="label">Debe (Inscripción)</div>
            <div class="value mono"><?= money($debe) ?></div>
            <?php if ($debeCalc <= 0): ?>
                <div class="sub" style="color:#991b1b; font-weight:700;">Fuera de tabla de tarifas</div>
            <?php endif; ?>
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
            <div class="progress-fill" style="width: <?= number_format($pct,2) ?>%;"></div>
        </div>
    </div>
</div>

<!-- FORMULARIO ABONO -->
<div class="form-card">
<form method="POST" enctype="multipart/form-data" id="formAbono">
    <input type="hidden" name="id_acuerdo" value="<?= (int)$acuerdo['id_acuerdo'] ?>">

    <div class="form-grid">
        <div class="form-group">
            <label>Monto a abonar (USD)</label>
            <input class="input" type="number" step="0.01" min="0.01" name="monto_usd" required>
        </div>

        <div class="form-group">
            <label>Método de pago</label>
            <select name="metodo_pago" required>
                <option value="">Seleccione</option>
                <option value="EFECTIVO">EFECTIVO</option>
                <option value="TRANSFERENCIA">TRANSFERENCIA</option>
                <option value="DEPOSITO">DEPÓSITO</option>
                <option value="OTRO">OTRO</option>
            </select>
        </div>

        <div class="form-group">
            <label>Referencia (opcional)</label>
            <input class="input" type="text" name="referencia" placeholder="# comprobante / transacción">
        </div>

        <div class="form-group">
            <label>Comprobante PDF (opcional)</label>
            <input class="input" type="file" name="comprobante_pdf" accept="application/pdf">
            <div class="small-muted" style="margin-top:6px;">Solo PDF.</div>
        </div>

        <div class="form-group" style="grid-column: 1 / -1;">
            <label>Observación (opcional)</label>
            <textarea name="observacion" rows="2" placeholder="Ej: Abono parcial del socio..."></textarea>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit">
            <i class="fa fa-save"></i> Guardar abono
        </button>
    </div>
</form>
</div>

<!-- HISTORIAL ABONOS -->
<div class="form-card">
    <h3 style="margin:0 0 12px 0; color:#1f3a5f;">Historial de abonos</h3>

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
                </tr>
            </thead>
            <tbody>
            <?php if (empty($abonos)): ?>
                <tr><td colspan="7" class="small-muted">Aún no hay abonos registrados.</td></tr>
            <?php else: ?>
                <?php foreach ($abonos as $i => $ab): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td class="nowrap"><?= date('d/m/Y H:i', strtotime($ab['fecha_abono'])) ?></td>
                        <td class="nowrap"><strong><?= htmlspecialchars($ab['metodo_pago']) ?></strong></td>
                        <td class="nowrap mono"><?= money($ab['monto_usd']) ?></td>
                        <td><?= htmlspecialchars($ab['referencia'] ?? '') ?></td>
                        <td>
                            <?php if (!empty($ab['comprobante_pdf'])): ?>
                                <a href="<?= htmlspecialchars($ab['comprobante_pdf']) ?>" target="_blank" class="badge badge-off" style="text-decoration:none;">
                                    <i class="fa fa-file-pdf"></i> Ver
                                </a>
                            <?php else: ?>
                                <span class="small-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($ab['observacion'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

</section>
</main>
</div>

<!-- MODAL FLOTANTE -->
<div id="modalSuccess" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="modal-box" style="background:#fff; padding:30px; border-radius:12px; box-shadow:0 20px 50px rgba(0,0,0,.2); text-align:center; max-width:420px;">
        <div class="icon-success" style="font-size:48px; color:#10b981; margin-bottom:15px;">
            <i class="fa fa-check-circle"></i>
        </div>
        <h2 style="color:#1f3a5f; margin-bottom:10px; font-size:20px;">¡Éxito!</h2>
        <p id="modalMessage">Abono guardado correctamente</p>
        <button onclick="cerrarModal()" style="background:#1f3a5f; color:#fff; border:none; padding:10px 24px; border-radius:8px; cursor:pointer; font-size:14px; font-weight:700;">
            Aceptar
        </button>
    </div>
</div>

<script>
const form = document.getElementById('formAbono');
if (form) {
  form.addEventListener('submit', function(e){
    e.preventDefault();

    const fd = new FormData(this);

    fetch('pago_inscripcion_form.php', { // mismo archivo, responde JSON
      method: 'POST',
      body: fd
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        document.getElementById('modalMessage').textContent = data.message || 'Guardado';
        document.getElementById('modalSuccess').style.display = 'flex';
        // recarga para refrescar resumen + historial
        setTimeout(() => location.reload(), 650);
      } else {
        mostrarMensaje('Error', 'Error: ' + (data.message || 'No se pudo guardar'), 'error');
      }
    })
    .catch(err => mostrarMensaje('Error', 'Error en la solicitud: ' + err, 'error'));
  });
}

function cerrarModal(){
  document.getElementById('modalSuccess').style.display = 'none';
}
</script>

</body>
</html>
