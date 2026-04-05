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

// ── Filtros ──────────────────────────────────────────────────────
$q     = isset($_GET['q'])     ? trim($_GET['q'])     : '';
$desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';

$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$usarFechas = ($desde !== '' || $hasta !== '');
if ($usarFechas) {
    if ($desde === '') $desde = '2000-01-01';
    if ($hasta === '') $hasta = date('Y-m-d');
}

// WHERE siempre filtra por período
$where  = ["id_periodo = :periodo_id"];
$params = [':periodo_id' => $periodo_id];

if ($q !== '') {
    $where[]      = "(identificacion LIKE :q OR nombres_completos LIKE :q OR celular LIKE :q)";
    $params[':q'] = "%{$q}%";
}

if ($usarFechas) {
    $where[]          = "(fecha_solicitud IS NULL OR DATE(fecha_solicitud) BETWEEN :desde AND :hasta)";
    $params[':desde'] = $desde;
    $params[':hasta'] = $hasta;
}

$whereSql = "WHERE " . implode(" AND ", $where);

// Total para paginación
$sqlCount  = "SELECT COUNT(*) FROM solicitud_ingreso $whereSql";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$total      = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// Datos
$sql = "SELECT id_solicitud, identificacion, nombres_completos, celular, fecha_solicitud
        FROM solicitud_ingreso
        $whereSql
        ORDER BY id_solicitud DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper para mantener querystring al paginar (incluye período)
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
    <title>Solicitudes de ingreso</title>

    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
            margin: 12px 0 18px;
        }
        .filters .field { display: grid; gap: 6px; }
        .filters label  { font-size: 12px; opacity: .8; }
        .filters input, .filters select {
            height: 38px;
            padding: 8px 10px;
            border: 1px solid #e6e6e6;
            border-radius: 10px;
            outline: none;
            font-size: 13px;
            background: #fff;
        }
        .filters .actions { display: flex; gap: 10px; align-items: flex-end; }

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
        .periodo-wrap label { font-size: 12px; color: #1f3a5f; font-weight: 700; opacity: 1; }
        .periodo-wrap select { border-color: #93c5fd; min-width: 230px; }

        .btn {
            height: 38px;
            padding: 0 14px;
            border-radius: 10px;
            border: 1px solid #e6e6e6;
            background: #fff;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }
        .btn.primary { background: #1f3a5f; color: #fff; border-color: #1f3a5f; }
        .btn.primary:hover { background: #16304d; }
        .btn.ghost:hover { background: #f3f4f6; }
        .btn-icon { display: inline-flex; align-items: center; gap: 8px; }

        .pill {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid #e6e6e6;
            background: #fff;
            text-decoration: none;
            display: inline-flex;
            gap: 6px;
            align-items: center;
        }

        .pager {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 14px;
            gap: 10px;
            flex-wrap: wrap;
        }
        .pager .pages { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .pager .meta  { font-size: 13px; opacity: .85; }

        /* Modal */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.45);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            z-index: 9999;
        }
        .modal {
            width: min(560px, 100%);
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,.25);
            overflow: hidden;
        }
        .modal header {
            padding: 14px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
        }
        .modal .body { padding: 16px; display: grid; gap: 10px; }
        .kv { display: flex; justify-content: space-between; gap: 12px; }
        .kv strong { opacity: .75; font-weight: 600; }
        .modal footer {
            padding: 14px 16px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid #eee;
        }
        .xbtn { border: none; background: transparent; font-size: 18px; cursor: pointer; }

        .table-card { background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; overflow: hidden; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table thead th {
            background: #1f3a5f !important;
            color: #fff !important;
            text-align: left;
            padding: 14px;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        .data-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #e5e7eb;
            color: #374151;
        }
        .data-table tbody tr:hover td { background: #f9fafb; }
    </style>
</head>
<body>

<div class="app">
    <?php include __DIR__ . "/layout/sidebar.php"; ?>

    <main class="content">
        <header class="topbar">
            <span>Bienvenido, <?= htmlspecialchars($_SESSION['usuario']) ?></span>
        </header>

        <section class="page">
            <h1>Solicitudes de ingreso</h1>

            <form class="filters" method="GET" action="solicitud_listado.php">

                <!-- ── SELECTOR DE PERÍODO ── -->
                <div class="periodo-wrap">
                    <label for="periodo"><i class="fa fa-calendar-alt"></i> Período</label>
                    <select id="periodo" name="periodo" onchange="this.form.submit()">
                        <?php foreach ($todosPeriodos as $p): ?>
                            <option value="<?= $p['id_periodo'] ?>"
                                <?= $periodo_id == $p['id_periodo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nombre']) ?>
                                (<?= htmlspecialchars($p['estado']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- ── BUSCADOR ── -->
                <div class="field" style="min-width:220px; flex:1;">
                    <label for="q">Buscar</label>
                    <input id="q" name="q" placeholder="Cédula, nombres o celular"
                           value="<?= htmlspecialchars($q) ?>">
                </div>

                <!-- ── FECHAS ── -->
                <div class="field">
                    <label for="desde">Desde</label>
                    <input type="date" id="desde" name="desde" value="<?= htmlspecialchars($desde) ?>">
                </div>

                <div class="field">
                    <label for="hasta">Hasta</label>
                    <input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
                </div>

                <!-- ── BOTONES ── -->
                <div class="actions">
                    <button class="btn primary" type="submit">
                        <i class="fa-solid fa-filter"></i> Filtrar
                    </button>
                    <a class="btn ghost" href="solicitud_listado.php">
                        <i class="fa-solid fa-rotate-left"></i> Limpiar
                    </a>
                </div>

                <!-- ── ATAJOS DE FECHA (conservan el período activo) ── -->
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-self:flex-end;">
                    <?php
                    $today = date('Y-m-d');
                    $d7    = date('Y-m-d', strtotime('-7 days'));
                    $m1    = date('Y-m-01');
                    ?>
                    <a class="pill" href="solicitud_listado.php?<?= build_qs(['desde'=>$today,'hasta'=>$today,'page'=>1]) ?>">
                        <i class="fa-regular fa-calendar"></i> Hoy
                    </a>
                    <a class="pill" href="solicitud_listado.php?<?= build_qs(['desde'=>$d7,'hasta'=>$today,'page'=>1]) ?>">
                        <i class="fa-regular fa-clock"></i> 7 días
                    </a>
                    <a class="pill" href="solicitud_listado.php?<?= build_qs(['desde'=>$m1,'hasta'=>$today,'page'=>1]) ?>">
                        <i class="fa-regular fa-calendar-days"></i> Este mes
                    </a>
                </div>

            </form>

            <div class="table-card">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Cédula</th>
                        <th>Nombres</th>
                        <th>Celular</th>
                        <th>Fecha ingreso</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>

                    <?php if (count($solicitudes) === 0): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:30px; color:#6b7280;">
                                <i class="fa fa-inbox" style="font-size:24px; display:block; margin-bottom:8px;"></i>
                                No existen solicitudes para este período / filtros.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($solicitudes as $s): ?>
                        <?php $fecha = $s['fecha_solicitud'] ? date('Y-m-d', strtotime($s['fecha_solicitud'])) : '—'; ?>
                        <tr>
                            <td><?= (int)$s['id_solicitud'] ?></td>
                            <td><?= htmlspecialchars($s['identificacion']) ?></td>
                            <td><?= htmlspecialchars($s['nombres_completos']) ?></td>
                            <td><?= htmlspecialchars($s['celular']) ?></td>
                            <td><?= htmlspecialchars($fecha) ?></td>
                            <td style="display:flex; gap:10px; flex-wrap:wrap;">
                                <button
                                    type="button"
                                    class="btn btn-icon"
                                    data-open-modal
                                    data-id="<?= (int)$s['id_solicitud'] ?>"
                                    data-identificacion="<?= htmlspecialchars($s['identificacion'], ENT_QUOTES) ?>"
                                    data-nombres="<?= htmlspecialchars($s['nombres_completos'], ENT_QUOTES) ?>"
                                    data-celular="<?= htmlspecialchars($s['celular'], ENT_QUOTES) ?>"
                                    data-fecha="<?= htmlspecialchars($fecha, ENT_QUOTES) ?>"
                                >
                                    <i class="fa-regular fa-eye"></i> Ver
                                </button>
                                <a class="btn btn-icon primary"
                                   target="_blank"
                                   href="solicitud_pdf.php?id=<?= (int)$s['id_solicitud'] ?>">
                                    <i class="fa fa-print"></i> Imprimir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    </tbody>
                </table>

                <div class="pager">
                    <div class="meta">
                        Total: <?= $total ?> | Página <?= $page ?> de <?= $totalPages ?>
                    </div>
                    <div class="pages">
                        <a class="btn" href="solicitud_listado.php?<?= build_qs(['page'=>1]) ?>">
                            <i class="fa-solid fa-angles-left"></i>
                        </a>
                        <a class="btn" href="solicitud_listado.php?<?= build_qs(['page'=>max(1,$page-1)]) ?>">
                            <i class="fa-solid fa-angle-left"></i>
                        </a>
                        <span class="pill">Página <?= $page ?></span>
                        <a class="btn" href="solicitud_listado.php?<?= build_qs(['page'=>min($totalPages,$page+1)]) ?>">
                            <i class="fa-solid fa-angle-right"></i>
                        </a>
                        <a class="btn" href="solicitud_listado.php?<?= build_qs(['page'=>$totalPages]) ?>">
                            <i class="fa-solid fa-angles-right"></i>
                        </a>
                    </div>
                </div>
            </div>

        </section>
    </main>
</div>

<!-- Modal Ver detalle -->
<div class="modal-backdrop" id="modalBackdrop" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <header>
            <strong id="modalTitle">Detalle de solicitud</strong>
            <button class="xbtn" type="button" id="modalClose" aria-label="Cerrar">✕</button>
        </header>
        <div class="body">
            <div class="kv"><strong>ID</strong><span id="m_id"></span></div>
            <div class="kv"><strong>Cédula</strong><span id="m_identificacion"></span></div>
            <div class="kv"><strong>Nombres</strong><span id="m_nombres"></span></div>
            <div class="kv"><strong>Celular</strong><span id="m_celular"></span></div>
            <div class="kv"><strong>Fecha</strong><span id="m_fecha"></span></div>
        </div>
        <footer>
            <button class="btn" type="button" id="modalCancel">Cerrar</button>
            <a class="btn primary btn-icon" id="modalPrint" target="_blank" href="#">
                <i class="fa fa-print"></i> Imprimir
            </a>
        </footer>
    </div>
</div>

<script>
(function () {
    const backdrop   = document.getElementById('modalBackdrop');
    const closeBtn   = document.getElementById('modalClose');
    const cancelBtn  = document.getElementById('modalCancel');
    const modalPrint = document.getElementById('modalPrint');

    function openModal(data) {
        document.getElementById('m_id').textContent             = data.id;
        document.getElementById('m_identificacion').textContent = data.identificacion;
        document.getElementById('m_nombres').textContent        = data.nombres;
        document.getElementById('m_celular').textContent        = data.celular;
        document.getElementById('m_fecha').textContent          = data.fecha;
        modalPrint.href = "solicitud_pdf.php?id=" + encodeURIComponent(data.id);
        backdrop.style.display = 'flex';
        backdrop.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        backdrop.style.display = 'none';
        backdrop.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-open-modal]');
        if (!btn) return;
        openModal({
            id:             btn.dataset.id,
            identificacion: btn.dataset.identificacion,
            nombres:        btn.dataset.nombres,
            celular:        btn.dataset.celular,
            fecha:          btn.dataset.fecha
        });
    });

    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', e => { if (e.target === backdrop) closeModal(); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && backdrop.getAttribute('aria-hidden') === 'false') closeModal();
    });
})();
</script>

</body>
</html>