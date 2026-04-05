<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}

require "config/conexion.php";

// ── Selector de período ──────────────────────────────────────────
$todosPeriodos = $pdo->query("SELECT * FROM periodo_comercializacion ORDER BY fecha_apertura DESC")->fetchAll(PDO::FETCH_ASSOC);
$periodo_id = isset($_GET['periodo']) ? (int)$_GET['periodo'] : null;
if (!$periodo_id && !empty($todosPeriodos)) {
    $stPA = $pdo->query("SELECT * FROM periodo_comercializacion WHERE estado = 'ABIERTO' LIMIT 1");
    $periodoAbierto = $stPA->fetch(PDO::FETCH_ASSOC);
    $periodo_id = $periodoAbierto ? $periodoAbierto['id_periodo'] : $todosPeriodos[0]['id_periodo'];
}

// ── Filtros ──────────────────────────────────────────────────────
$q     = isset($_GET['q'])     ? trim($_GET['q'])     : '';
$desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';

$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$usarFechas = ($desde !== '' || $hasta !== '');
if ($usarFechas) {
    if ($desde === '') $desde = '2000-01-01';
    if ($hasta === '') $hasta = date('Y-m-d');
}

// ── Construcción del WHERE ────────────────────────────────────────
// FIX: usar parámetros únicos para evitar error PDO con múltiples columnas LIKE
$where  = ["a.id_periodo = :periodo_id"];
$params = [':periodo_id' => $periodo_id];

if ($q !== '') {
    // FIX: parámetros nombrados únicos por cada columna
    $where[] = "(
        a.numero_acuerdo    LIKE :q1 OR
        a.cedula            LIKE :q2 OR
        a.nombres_completos LIKE :q3 OR
        a.provincia         LIKE :q4 OR
        a.canton            LIKE :q5 OR
        a.parroquia         LIKE :q6 OR
        a.sector            LIKE :q7
    )";
    $qLike = "%{$q}%";
    $params[':q1'] = $qLike;
    $params[':q2'] = $qLike;
    $params[':q3'] = $qLike;
    $params[':q4'] = $qLike;
    $params[':q5'] = $qLike;
    $params[':q6'] = $qLike;
    $params[':q7'] = $qLike;
}

if ($usarFechas) {
    $where[]          = "(a.fecha_firma IS NULL OR DATE(a.fecha_firma) BETWEEN :desde AND :hasta)";
    $params[':desde'] = $desde;
    $params[':hasta'] = $hasta;
}

$whereSql = "WHERE " . implode(" AND ", $where);

// ── COUNT ─────────────────────────────────────────────────────────
$sqlCount = "SELECT COUNT(*) FROM acuerdo_productor a $whereSql";
$stmtC    = $pdo->prepare($sqlCount);
$stmtC->execute($params);
$total      = (int)$stmtC->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// ── DATA ──────────────────────────────────────────────────────────
$sql = "
SELECT
    a.id_acuerdo,
    a.numero_acuerdo,
    a.cedula,
    a.nombres_completos,
    a.fecha_firma,
    a.provincia,
    a.canton,
    a.parroquia,
    a.sector
FROM acuerdo_productor a
$whereSql
ORDER BY a.id_acuerdo DESC
LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

function build_qs(array $extra = []): string {
    $base = $_GET;
    foreach ($extra as $k => $v) $base[$k] = $v;
    return http_build_query($base);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Listado de Acuerdos</title>
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.toolbar { display:flex; align-items:flex-end; gap:10px; flex-wrap:wrap; margin:12px 0 18px; }
.periodo-wrap { display:flex; flex-direction:column; gap:6px; padding:8px 12px; background:#f0f4ff; border:1px solid #c7d7f7; border-radius:10px; }
.periodo-wrap label { font-size:12px; color:#1f3a5f; font-weight:700; }
.periodo-wrap select { height:38px; border:1px solid #93c5fd; border-radius:8px; padding:0 10px; font-size:13px; min-width:230px; background:#fff; outline:none; }
.filters-group { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; }
.field { display:grid; gap:6px; }
.field label { font-size:12px; opacity:.8; }
.field input { height:38px; padding:8px 10px; border:1px solid #e6e6e6; border-radius:10px; outline:none; font-size:13px; min-width:200px; }
.field input:focus { border-color:#93c5fd; }
.btn { height:38px; padding:0 14px; border-radius:10px; border:1px solid #e6e6e6; background:#fff; cursor:pointer; display:inline-flex; align-items:center; gap:8px; text-decoration:none; font-size:13px; font-weight:600; }
.btn.primary { background:#1f3a5f; color:#fff; border-color:#1f3a5f; }
.btn.primary:hover { background:#16304d; }
.btn.ghost:hover { background:#f3f4f6; }
.pill { font-size:12px; padding:4px 10px; border-radius:999px; border:1px solid #e6e6e6; background:#fff; text-decoration:none; display:inline-flex; gap:6px; align-items:center; }
.table-responsive { overflow-x:auto; }
.data-table { width:100%; border-collapse:collapse; font-size:13px; }
.data-table thead { background:#1f3a5f !important; }
.data-table th { padding:14px; font-weight:600; background:#1f3a5f !important; color:#fff !important; position:sticky; top:0; z-index:2; text-align:left; }
.data-table td { padding:12px 14px; border-bottom:1px solid #e5e7eb; color:#374151 !important; }
.data-table tbody tr:hover { background:#f9fafb !important; }
.data-table strong { color:#1f3a5f !important; }
.iconbtn { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:8px; border:1px solid #e6e6e6; background:#fff; cursor:pointer; text-decoration:none; font-size:14px; margin-right:4px; }
.iconbtn:hover { background:#f3f4f6; }
.iconbtn.blue { background:#2563eb; color:#fff; border-color:#2563eb; }
.iconbtn.blue:hover { background:#1d4ed8; }
.iconbtn.green { background:#10b981; color:#fff; border-color:#10b981; }
.iconbtn.green:hover { background:#059669; }
.iconbtn.red { background:#ef4444; color:#fff; border-color:#ef4444; }
.iconbtn.red:hover { background:#dc2626; }
.pager { display:flex; justify-content:space-between; align-items:center; margin-top:14px; gap:10px; flex-wrap:wrap; padding:0 4px; }
.pager .pages { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.pager .meta { font-size:13px; opacity:.85; }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.55); display:none; align-items:center; justify-content:center; padding:18px; z-index:9999; }
.modal { width:min(680px,100%); background:#fff; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,.25); overflow:hidden; max-height:90vh; display:flex; flex-direction:column; }
.modal header { padding:14px 16px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; background:#1f3a5f; color:#fff; }
.modal .body { padding:20px; overflow-y:auto; flex:1; }
.kv { display:flex; justify-content:space-between; gap:12px; padding:6px 0; border-bottom:1px solid #f3f4f6; }
.kv strong { opacity:.75; font-weight:600; min-width:100px; }
.modal footer { padding:14px 16px; display:flex; justify-content:flex-end; gap:10px; border-top:1px solid #eee; }
.xbtn { border:none; background:transparent; font-size:18px; cursor:pointer; color:#fff; }
.empty-state { text-align:center; padding:40px; color:#6b7280; }
.empty-state i { font-size:28px; display:block; margin-bottom:10px; }
.form-grid { display:grid; gap:14px; }
.form-group label { display:block; font-size:12px; font-weight:700; color:#374151; margin-bottom:5px; }
.form-group input, .form-group select { width:100%; padding:9px 11px; border-radius:8px; border:1px solid #d1d5db; font-size:14px; box-sizing:border-box; }
.form-group input:focus, .form-group select:focus { outline:none; border-color:#3b82f6; }
#toast { position:fixed; bottom:24px; right:24px; background:#1f3a5f; color:#fff; padding:12px 22px; border-radius:10px; font-size:14px; font-weight:600; box-shadow:0 8px 24px rgba(0,0,0,.25); z-index:99999; transform:translateY(80px); opacity:0; transition:all .3s; }
#toast.show { transform:translateY(0); opacity:1; }
#toast.success { background:#10b981; }
#toast.error { background:#ef4444; }
</style>
</head>
<body>
<div class="app">
<?php include __DIR__."/layout/sidebar.php"; ?>
<main class="content">
<header class="topbar">
    <span>Bienvenido, <?= htmlspecialchars($_SESSION['usuario']) ?></span>
</header>
<section class="page">
<h1><i class="fa fa-file-contract"></i> Acuerdos de Productor</h1>

<form class="toolbar" method="GET" action="acuerdo_listado.php">
    <div class="periodo-wrap">
        <label for="periodo"><i class="fa fa-calendar-alt"></i> Período</label>
        <select id="periodo" name="periodo" onchange="this.form.submit()">
            <?php foreach ($todosPeriodos as $p): ?>
                <option value="<?= $p['id_periodo'] ?>" <?= $periodo_id == $p['id_periodo'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['nombre']) ?> (<?= htmlspecialchars($p['estado']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filters-group">
        <div class="field">
            <label for="q">Buscar</label>
            <input id="q" name="q" placeholder="N° acuerdo, cédula, nombres, provincia, cantón..." value="<?= htmlspecialchars($q) ?>">
        </div>
        <div class="field">
            <label for="desde">Desde</label>
            <input type="date" id="desde" name="desde" value="<?= htmlspecialchars($desde) ?>" style="min-width:140px;">
        </div>
        <div class="field">
            <label for="hasta">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($hasta) ?>" style="min-width:140px;">
        </div>
        <button class="btn primary" type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button>
        <a class="btn ghost" href="acuerdo_listado.php"><i class="fa-solid fa-rotate-left"></i> Limpiar</a>
    </div>
</form>

<div class="form-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
            <tr>
                <th>#</th>
                <th>N° Acuerdo</th>
                <th>Cédula</th>
                <th>Nombres</th>
                <th>Provincia</th>
                <th>Cantón</th>
                <th>Parroquia</th>
                <th>Sector</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($lista)): ?>
                <tr><td colspan="9"><div class="empty-state"><i class="fa fa-file-contract"></i> No hay acuerdos para este período / filtros.</div></td></tr>
            <?php endif; ?>
            <?php foreach ($lista as $i => $row): ?>
            <tr>
                <td><?= $offset + $i + 1 ?></td>
                <td><strong><?= htmlspecialchars($row['numero_acuerdo']) ?></strong></td>
                <td><?= htmlspecialchars($row['cedula']) ?></td>
                <td><?= htmlspecialchars($row['nombres_completos']) ?></td>
                <td><?= htmlspecialchars($row['provincia']) ?></td>
                <td><?= htmlspecialchars($row['canton']) ?></td>
                <td><?= htmlspecialchars($row['parroquia']) ?></td>
                <td><?= htmlspecialchars($row['sector']) ?></td>
                <td style="white-space:nowrap;">
                    <button type="button" class="iconbtn green" title="Editar" onclick="abrirModalEditar(<?= $row['id_acuerdo'] ?>)">
                        <i class="fa-solid fa-edit"></i>
                    </button>
                    <a class="iconbtn blue" title="Imprimir PDF" target="_blank" href="acuerdo_productor_pdf.php?id=<?= $row['id_acuerdo'] ?>">
                        <i class="fa-solid fa-print"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="pager">
            <div class="meta">Total: <?= $total ?> | Página <?= $page ?> de <?= $totalPages ?></div>
            <div class="pages">
                <a class="btn" href="acuerdo_listado.php?<?= build_qs(['page'=>1]) ?>"><i class="fa-solid fa-angles-left"></i></a>
                <a class="btn" href="acuerdo_listado.php?<?= build_qs(['page'=>max(1,$page-1)]) ?>"><i class="fa-solid fa-angle-left"></i></a>
                <span class="pill">Página <?= $page ?></span>
                <a class="btn" href="acuerdo_listado.php?<?= build_qs(['page'=>min($totalPages,$page+1)]) ?>"><i class="fa-solid fa-angle-right"></i></a>
                <a class="btn" href="acuerdo_listado.php?<?= build_qs(['page'=>$totalPages]) ?>"><i class="fa-solid fa-angles-right"></i></a>
            </div>
        </div>
    </div>
</div>
</section>
</main>
</div>

<!-- Modal Editar -->
<div class="modal-backdrop" id="modalEditar">
<div class="modal">
<header>
    <strong style="color:#fff;">✏️ Editar Acuerdo</strong>
    <button class="xbtn" type="button" onclick="cerrarModalEditar()">✕</button>
</header>
<div class="body">
<form id="formEditar" class="form-grid">
    <input type="hidden" id="edit_id_acuerdo">
    <div class="form-group">
        <label>N° Acuerdo</label>
        <input type="text" id="edit_numero_acuerdo" readonly style="background:#f9fafb;">
    </div>
    <div class="form-group">
        <label>Nombres Completos</label>
        <input type="text" id="edit_nombres_completos" required>
    </div>
    <div class="form-group">
        <label>Cédula</label>
        <input type="text" id="edit_cedula" required>
    </div>
    <div class="form-group">
        <label>Provincia *</label>
        <select id="edit_provincia" required>
            <option value="">Seleccionar</option>
            <option value="Guayas">Guayas</option>
            <option value="Los Ríos">Los Ríos</option>
            <option value="Manabí">Manabí</option>
            <option value="Santo Domingo de los Tsáchilas">Santo Domingo de los Tsáchilas</option>
        </select>
    </div>
    <div class="form-group">
        <label>Cantón *</label>
        <input type="text" id="edit_canton" required placeholder="Ej: Pedro Carbo, Balzar, Mocache">
    </div>
    <div class="form-group">
        <label>Parroquia *</label>
        <input type="text" id="edit_parroquia" required placeholder="Ej: Guayas, Santa Lucia">
    </div>
    <div class="form-group">
        <label>Sector / Comunidad *</label>
        <input type="text" id="edit_sector" required placeholder="Ej: Santa Lucia, La Angela">
    </div>
</form>
</div>
<footer>
    <button class="btn" type="button" onclick="cerrarModalEditar()">Cancelar</button>
    <button class="btn primary" type="button" onclick="guardarEdicion()"><i class="fa fa-save"></i> Guardar Cambios</button>
</footer>
</div>
</div>

<div id="toast"></div>

<script>
async function abrirModalEditar(id) {
    try {
        const res  = await fetch(`acuerdo_obtener.php?id=${id}`);
        const data = await res.json();
        if (!data.success) { mostrarToast(data.message || 'Error al cargar', 'error'); return; }
        const a = data.acuerdo;
        document.getElementById('edit_id_acuerdo').value       = a.id_acuerdo;
        document.getElementById('edit_numero_acuerdo').value   = a.numero_acuerdo;
        document.getElementById('edit_nombres_completos').value = a.nombres_completos;
        document.getElementById('edit_cedula').value           = a.cedula;
        document.getElementById('edit_provincia').value        = a.provincia  || '';
        document.getElementById('edit_canton').value           = a.canton     || '';
        document.getElementById('edit_parroquia').value        = a.parroquia  || '';
        document.getElementById('edit_sector').value           = a.sector     || '';
        document.getElementById('modalEditar').style.display   = 'flex';
    } catch(e) {
        mostrarToast('Error de red', 'error');
    }
}

function cerrarModalEditar() {
    document.getElementById('modalEditar').style.display = 'none';
}

async function guardarEdicion() {
    const fd = new FormData();
    fd.append('id_acuerdo',        document.getElementById('edit_id_acuerdo').value);
    fd.append('nombres_completos', document.getElementById('edit_nombres_completos').value);
    fd.append('cedula',            document.getElementById('edit_cedula').value);
    fd.append('provincia',         document.getElementById('edit_provincia').value);
    fd.append('canton',            document.getElementById('edit_canton').value);
    fd.append('parroquia',         document.getElementById('edit_parroquia').value);
    fd.append('sector',            document.getElementById('edit_sector').value);

    try {
        const res  = await fetch('acuerdo_actualizar.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            mostrarToast('✅ Cambios guardados', 'success');
            cerrarModalEditar();
            setTimeout(() => location.reload(), 1200);
        } else {
            mostrarToast(data.message || 'Error al guardar', 'error');
        }
    } catch(e) {
        mostrarToast('Error de red', 'error');
    }
}

function mostrarToast(msg, tipo) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className   = 'show ' + (tipo || '');
    setTimeout(() => { t.className = ''; }, 3200);
}
</script>
</body>
</html>