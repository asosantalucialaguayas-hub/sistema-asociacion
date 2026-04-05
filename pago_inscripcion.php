<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}

require "config/conexion.php";
require_once "helpers/periodo.php";

// Selector de período
$todosPeriodos = $pdo->query("SELECT * FROM periodo_comercializacion ORDER BY fecha_apertura DESC")->fetchAll(PDO::FETCH_ASSOC);
$periodo_id = isset($_GET['periodo']) ? (int)$_GET['periodo'] : null;
if (!$periodo_id && !empty($todosPeriodos)) {
    $periodoAbierto = get_periodo_abierto($pdo);
    $periodo_id = $periodoAbierto ? $periodoAbierto['id_periodo'] : $todosPeriodos[0]['id_periodo'];
}

$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// ──────────────────────────────────────────────────────────────────────────────
// FIX: Solo muestra acuerdos cuya cédula existe en solicitud_ingreso del
// mismo período. Así se separan los NUEVOS ingresantes de los socios VIEJOS
// aunque todos tengan el mismo id_periodo en acuerdo_productor.
// ──────────────────────────────────────────────────────────────────────────────
$sql = "
SELECT
    a.id_acuerdo,
    a.cedula,
    a.nombres_completos,
    COALESCE(a.cacao_nacional_has, 0) AS cacao_nacional_has,
    COALESCE(a.cacao_ccn51_has, 0)    AS cacao_ccn51_has,
    (COALESCE(a.cacao_nacional_has, 0) + COALESCE(a.cacao_ccn51_has, 0)) AS has_total,

    p.id_pago,
    p.total_debe_usd,
    p.estado AS estado_pago,
    p.fecha_creado,

    COALESCE(DATE(p.fecha_creado), DATE(a.fecha_firma)) AS fecha_ref,

    COALESCE(SUM(CASE WHEN ab.estado = 'REGISTRADO' THEN ab.monto_usd ELSE 0 END), 0) AS total_pagado
FROM acuerdo_productor a
LEFT JOIN pago_inscripcion p
    ON p.id_acuerdo = a.id_acuerdo
LEFT JOIN pago_inscripcion_abono ab
    ON ab.id_pago = p.id_pago
WHERE a.id_periodo = :periodo_id
  AND EXISTS (
      SELECT 1 FROM solicitud_ingreso si
      WHERE si.identificacion = a.cedula
        AND si.id_periodo = :periodo_id2
  )
";

$params = [
    ':periodo_id'  => $periodo_id,
    ':periodo_id2' => $periodo_id,
];

if ($search !== '') {
    $sql .= " AND (
        a.cedula               LIKE :q
        OR a.nombres_completos LIKE :q
        OR a.numero_acuerdo    LIKE :q
        OR a.provincia         LIKE :q
        OR a.canton            LIKE :q
    ) ";
    $params[':q'] = "%{$search}%";
}

$sql .= "
GROUP BY
    a.id_acuerdo, a.cedula, a.nombres_completos,
    a.cacao_nacional_has, a.cacao_ccn51_has,
    p.id_pago, p.total_debe_usd, p.estado, p.fecha_creado
ORDER BY a.id_acuerdo DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

function calcular_tarifa_por_has(float $hasTotal): float {
    if ($hasTotal >= 1  && $hasTotal <= 5)  return 200.00;
    if ($hasTotal >= 6  && $hasTotal <= 10) return 250.00;
    if ($hasTotal >= 11 && $hasTotal <= 20) return 300.00;
    if ($hasTotal >= 21 && $hasTotal <= 30) return 350.00;
    return 0.00;
}

function money($n): string {
    return '$ ' . number_format((float)$n, 2);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Pagos de inscripción</title>

<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.table-responsive{ overflow-x:auto; max-width:100%; -webkit-overflow-scrolling:touch; }

.data-table{
  width:100%;
  border-collapse:collapse;
  font-size:13px;
  table-layout:fixed;
}
.data-table thead{ background:#1f3a5f !important; }
.data-table th{
  text-align:left; padding:14px; font-weight:600;
  background:#1f3a5f !important; color:#fff !important;
  position:sticky; top:0; z-index:2;
  white-space:normal; line-height:1.1;
}
.data-table td{
  padding:12px 14px; border-bottom:1px solid #e5e7eb; color:#374151 !important;
  vertical-align:middle;
  overflow:visible;
  text-overflow:clip;
  word-break:break-word;
}
.data-table tbody tr:hover{ background:#f9fafb !important; }
.data-table strong{ color:#1f3a5f !important; }

@media (max-width:900px){
  .data-table th, .data-table td{ padding:10px 10px; font-size:12px; }
}

/* Anchos de columnas */
.col-num     { width:35px;  }
.col-cedula  { width:95px;  }
.col-nombres { width:180px; }
.col-has     { width:70px;  }
.col-total   { width:75px;  }
.col-money   { width:85px;  }
.col-progreso{ width:180px; }
.col-estado  { width:90px;  }
.col-acciones{ width:110px; }

/* Badges */
.badge{
  display:inline-flex; align-items:center;
  padding:6px 10px; border-radius:999px;
  font-weight:700; font-size:11px; letter-spacing:.2px;
  white-space:nowrap;
}
.badge-ok { background:#dcfce7; color:#166534; }
.badge-mid{ background:#fef9c3; color:#854d0e; }
.badge-no { background:#fee2e2; color:#991b1b; }
.badge-off{ background:#f3f4f6; color:#4b5563; }

/* Botones */
.btn-icon{
  display:inline-flex; align-items:center; justify-content:center;
  width:32px; height:32px; background:#2563eb; color:#fff;
  border:none; border-radius:6px; cursor:pointer;
  font-size:14px; text-decoration:none; margin-right:6px;
}
.btn-icon:hover{ background:#1d4ed8; }
.btn-green{ background:#16a34a; }
.btn-green:hover{ background:#15803d; }

.td-actions{ white-space:nowrap; overflow:visible !important; }

/* Toolbar */
.toolbar{
  display:flex; align-items:center; justify-content:space-between;
  gap:12px; margin-bottom:14px; flex-wrap:wrap;
}
.filters{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }

.input{
  height:36px; border:1px solid #e5e7eb; border-radius:8px;
  padding:0 12px; font-size:13px; outline:none; background:#fff;
}
.input:focus{ border-color:#93c5fd; box-shadow:0 0 0 3px rgba(37,99,235,.15); }

.search-wrap{
  display:flex; align-items:center; gap:8px;
  padding:6px 8px; border:1px solid #e5e7eb; border-radius:10px; background:#fff;
  max-width:360px;
}
.search-wrap i{ color:#6b7280; }
.search-wrap input{
  border:none; outline:none; height:24px; width:100%; min-width:180px; font-size:13px;
}
@media (max-width:900px){
  .search-wrap{ max-width:100%; width:100%; }
  .search-wrap input{ min-width:0; }
}

.btn-small{
  display:inline-flex; align-items:center; gap:8px;
  height:36px; padding:0 12px; background:#2563eb; color:#fff;
  border-radius:10px; text-decoration:none; font-weight:800; font-size:13px;
  border:none; cursor:pointer;
}
.btn-small:hover{ background:#1d4ed8; }

.btn-ghost{
  display:inline-flex; align-items:center; gap:8px;
  height:36px; padding:0 12px; background:#f3f4f6; color:#111827;
  border-radius:10px; text-decoration:none; font-weight:800; font-size:13px;
  border:1px solid #e5e7eb; cursor:pointer;
}
.btn-ghost:hover{ background:#e5e7eb; }

/* Progreso */
.progress-wrap{ min-width:160px; }
.progress-bar{
  width:160px; max-width:100%;
  height:10px; background:#e5e7eb; border-radius:999px; overflow:hidden;
}
.progress-fill{ height:100%; width:0%; border-radius:999px; background:#2563eb; }
.progress-meta{
  display:flex; justify-content:space-between; margin-top:6px;
  font-size:11px; color:#6b7280; gap:10px; flex-wrap:wrap; white-space:normal;
}

.mono{ font-variant-numeric: tabular-nums; }
.small-muted{ color:#6b7280; font-size:12px; }
.nowrap{ white-space:nowrap; }

/* Banner informativo */
.info-banner {
    background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px;
    padding:12px 18px; margin-bottom:16px; font-size:13px; color:#1e40af;
    display:flex; align-items:center; gap:10px;
}
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
<h1>Pagos de inscripción</h1>

<div class="info-banner">
    <i class="fa fa-circle-info"></i>
    Solo se muestran los <strong>nuevos ingresantes</strong> del período seleccionado
    (socios con solicitud de ingreso registrada en ese período).
</div>

<div class="toolbar">
    <form class="filters" method="GET" action="pago_inscripcion.php">
        <div class="search-wrap">
            <i class="fa fa-magnifying-glass"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Buscar socio / acuerdo...">
        </div>
        <div>
            <select class="input" name="periodo" onchange="this.form.submit()">
                <?php foreach ($todosPeriodos as $p): ?>
                    <option value="<?= $p['id_periodo'] ?>"
                        <?= $periodo_id == $p['id_periodo'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['nombre']) ?> (<?= htmlspecialchars($p['estado']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn-ghost" type="submit">
            <i class="fa fa-filter"></i> Filtrar
        </button>
        <a class="btn-ghost" href="pago_inscripcion.php">
            <i class="fa fa-rotate-left"></i> Limpiar
        </a>
    </form>
    <a href="pago_inscripcion_form.php" class="btn-small">
        <i class="fa fa-plus"></i> Nuevo
    </a>
</div>

<div class="form-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-num">#</th>
                    <th class="col-cedula">Cédula</th>
                    <th class="col-nombres">Nombres</th>
                    <th class="col-has">Has.<br>Nac.</th>
                    <th class="col-has">Has.<br>CCN51</th>
                    <th class="col-total">Has.<br>Total</th>
                    <th class="col-money">Tarifa</th>
                    <th class="col-money">Debe</th>
                    <th class="col-money">Pagado</th>
                    <th class="col-progreso">Progreso</th>
                    <th class="col-estado">Estado</th>
                    <th class="col-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($filas)):
            ?>
                <tr>
                    <td colspan="12" style="text-align:center; padding:30px; color:#6b7280;">
                        <i class="fa fa-inbox" style="font-size:24px; display:block; margin-bottom:8px;"></i>
                        No hay nuevos ingresantes para este período.
                    </td>
                </tr>
            <?php else: ?>
            <?php $i = 1; foreach ($filas as $row): ?>
            <?php
                $hasTotal   = (float)$row['has_total'];
                $tarifa     = calcular_tarifa_por_has($hasTotal);
                $debe       = (float)($row['total_debe_usd'] ?? $tarifa);
                $pagado     = (float)$row['total_pagado'];
                $saldo      = max(0, $debe - $pagado);
                $pct        = $debe > 0 ? min(100, round($pagado / $debe * 100)) : 0;
                $estadoPago = $row['estado_pago'] ?? null;

                if (!$estadoPago || $estadoPago === 'PENDIENTE') {
                    $badgeClass = 'badge-no';
                    $badgeText  = 'Pendiente';
                } elseif ($estadoPago === 'PARCIAL') {
                    $badgeClass = 'badge-mid';
                    $badgeText  = 'Parcial';
                } elseif ($estadoPago === 'PAGADO') {
                    $badgeClass = 'badge-ok';
                    $badgeText  = 'Pagado';
                } else {
                    $badgeClass = 'badge-off';
                    $badgeText  = htmlspecialchars($estadoPago);
                }

                $fillColor = $pct >= 100 ? '#16a34a' : ($pct >= 50 ? '#d97706' : '#dc2626');
            ?>
                <tr>
                    <td class="small-muted"><?= $i++ ?></td>
                    <td class="mono nowrap"><?= htmlspecialchars($row['cedula']) ?></td>
                    <td>
                        <strong><?= htmlspecialchars($row['nombres_completos']) ?></strong>
                        <?php if ($row['fecha_ref']): ?>
                            <br><span class="small-muted"><?= htmlspecialchars($row['fecha_ref']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="mono"><?= number_format((float)$row['cacao_nacional_has'], 2) ?></td>
                    <td class="mono"><?= number_format((float)$row['cacao_ccn51_has'], 2) ?></td>
                    <td class="mono"><strong><?= number_format($hasTotal, 2) ?></strong></td>
                    <td class="mono"><?= money($tarifa) ?></td>
                    <td class="mono"><?= money($debe) ?></td>
                    <td class="mono"><?= money($pagado) ?></td>
                    <td>
                        <div class="progress-wrap">
                            <div class="progress-bar">
                                <div class="progress-fill"
                                     style="width:<?= $pct ?>%; background:<?= $fillColor ?>;"></div>
                            </div>
                            <div class="progress-meta">
                                <span><?= $pct ?>%</span>
                                <span>Saldo: <?= money($saldo) ?></span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                    </td>
                    <td class="td-actions">
                        <a href="pago_inscripcion_detalle.php?id=<?= $row['id_acuerdo'] ?>&periodo=<?= $periodo_id ?>"
                           class="btn-icon" title="Ver detalle">
                            <i class="fa fa-eye"></i>
                        </a>
                        <a href="pago_inscripcion_form.php?buscar=<?= urlencode($row['cedula']) ?>"
                           class="btn-icon btn-green" title="Registrar abono">
                            <i class="fa fa-dollar-sign"></i>
                        </a>
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
</body>
</html>