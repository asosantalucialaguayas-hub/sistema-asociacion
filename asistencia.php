<?php
// ============================================================
// asistencia.php - Módulo principal de Asistencia
// Sistema Asociación Santa Lucía
// ============================================================
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../config/db.php';      // Tu conexión PDO existente: $pdo
require_once '../config/funciones.php'; // Tus funciones auxiliares

$usuario_id = $_SESSION['usuario_id'];
$rol        = $_SESSION['rol'] ?? 'socio';

// ── Parámetros de vista ──────────────────────────────────────
$vista = $_GET['vista'] ?? 'lista'; // lista | nueva | detalle
$conv_id = intval($_GET['id'] ?? 0);

// ── Datos para lista de convocatorias ───────────────────────
if ($vista === 'lista') {
    $stmt = $pdo->query("
        SELECT c.*,
               COUNT(a.id) AS total_asistieron,
               (SELECT COUNT(*) FROM socios WHERE estado='activo') AS total_socios
        FROM convocatorias c
        LEFT JOIN asistencia a ON a.convocatoria_id = c.id
        GROUP BY c.id
        ORDER BY c.fecha DESC
    ");
    $convocatorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Datos para detalle / registro ───────────────────────────
if ($vista === 'detalle' && $conv_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM convocatorias WHERE id = ?");
    $stmt->execute([$conv_id]);
    $convocatoria = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$convocatoria) { header('Location: asistencia.php'); exit; }

    $puntos = $pdo->prepare("SELECT * FROM convocatoria_puntos WHERE convocatoria_id = ? ORDER BY numero");
    $puntos->execute([$conv_id]);
    $puntos = $puntos->fetchAll(PDO::FETCH_ASSOC);

    // Socios que YA asistieron
    $stmt2 = $pdo->prepare("
        SELECT a.*, s.cedula, s.nombres, s.apellidos, s.foto, a.metodo, a.hora_registro
        FROM asistencia a
        JOIN socios s ON s.id = a.socio_id
        WHERE a.convocatoria_id = ?
        ORDER BY a.hora_registro DESC
    ");
    $stmt2->execute([$conv_id]);
    $asistieron = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Total socios activos
    $total_socios = $pdo->query("SELECT COUNT(*) FROM socios WHERE estado='activo'")->fetchColumn();
    $total_presentes = count($asistieron);
    $porcentaje = $total_socios > 0 ? round(($total_presentes / $total_socios) * 100, 1) : 0;
    $faltantes = $total_socios - $total_presentes;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Asistencia - Asociación</title>
<!-- Bootstrap 5 (igual que tu sistema) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- SweetAlert2 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
:root {
    --azul:    #2c3e7a;
    --verde:   #27ae60;
    --naranja: #e67e22;
    --rojo:    #e74c3c;
    --gris:    #f4f6f9;
}
body { background: var(--gris); font-family: 'Segoe UI', sans-serif; }

/* ── Tarjetas KPI ──────────────────────────────────────── */
.kpi-card {
    border-radius: 16px;
    padding: 24px 20px;
    color: #fff;
    display: flex; align-items: center; gap: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,.12);
    transition: transform .2s;
}
.kpi-card:hover { transform: translateY(-3px); }
.kpi-card .kpi-icon { font-size: 2.4rem; opacity: .85; }
.kpi-card .kpi-num  { font-size: 2rem; font-weight: 700; line-height: 1; }
.kpi-card .kpi-lbl  { font-size: .85rem; opacity: .9; margin-top: 2px; }
.bg-kpi-blue   { background: linear-gradient(135deg,#2c3e7a,#4a6cf7); }
.bg-kpi-green  { background: linear-gradient(135deg,#27ae60,#2ecc71); }
.bg-kpi-orange { background: linear-gradient(135deg,#e67e22,#f39c12); }
.bg-kpi-red    { background: linear-gradient(135deg,#e74c3c,#c0392b); }

/* ── Barra de progreso asistencia ──────────────────────── */
.progreso-wrap { background:#e9ecef; border-radius:50px; height:28px; overflow:hidden; }
.progreso-bar  {
    height:100%; border-radius:50px;
    background: linear-gradient(90deg,#27ae60,#2ecc71);
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-weight:700; font-size:.9rem;
    transition: width 1s ease;
    min-width: 40px;
}

/* ── Tabla socios ──────────────────────────────────────── */
.tbl-socios thead { background: var(--azul); color:#fff; }
.tbl-socios tbody tr:hover { background:#eef2ff; }
.badge-metodo {
    font-size:.75rem; padding:4px 10px; border-radius:20px; font-weight:600;
}
.badge-bio    { background:#dff0d8; color:#27ae60; }
.badge-manual { background:#d9edf7; color:#2c3e7a; }
.badge-qr     { background:#fcf8e3; color:#e67e22; }

/* ── Buscar socio ─────────────────────────────────────── */
#inputBuscar { border-radius:50px; padding-left:40px; }
.search-wrap { position:relative; }
.search-wrap .fa-magnifying-glass { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#aaa; }

/* ── Card convocatoria lista ──────────────────────────── */
.conv-card {
    border:none; border-radius:14px;
    box-shadow: 0 2px 12px rgba(0,0,0,.08);
    transition: transform .2s, box-shadow .2s;
    overflow: hidden;
}
.conv-card:hover { transform:translateY(-4px); box-shadow:0 8px 24px rgba(0,0,0,.14); }
.conv-card .conv-header {
    background: linear-gradient(135deg,#2c3e7a,#4a6cf7);
    color:#fff; padding:16px 20px;
}
.conv-card .conv-body { padding:16px 20px; }
.badge-estado { font-size:.78rem; padding:5px 12px; border-radius:20px; font-weight:600; }
.estado-programada { background:#d9edf7; color:#2c3e7a; }
.estado-activa     { background:#dff0d8; color:#27ae60; }
.estado-cerrada    { background:#f2f2f2; color:#555; }
.estado-cancelada  { background:#f9d6d5; color:#e74c3c; }

/* ── Ficha socio en modal biométrico ─────────────────── */
.ficha-socio {
    text-align:center; padding:20px;
    border-radius:14px; background:#f0f7ff;
    border: 2px solid #4a6cf7;
}
.ficha-socio img { width:90px; height:90px; border-radius:50%; object-fit:cover; border:3px solid #4a6cf7; }
</style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="main-content" style="margin-left:220px; padding:30px 24px;">

<!-- ══════════════════════════════════════════════════════ -->
<!--  VISTA: LISTA DE CONVOCATORIAS                        -->
<!-- ══════════════════════════════════════════════════════ -->
<?php if ($vista === 'lista'): ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0"><i class="fa-solid fa-calendar-check text-primary me-2"></i>Módulo de Asistencia</h3>
        <p class="text-muted mb-0">Gestión de convocatorias y control de asistencia</p>
    </div>
    <?php if (in_array($rol, ['admin','secretario'])): ?>
    <a href="nueva_convocatoria.php" class="btn btn-primary rounded-pill px-4">
        <i class="fa-solid fa-plus me-1"></i> Nueva Convocatoria
    </a>
    <?php endif; ?>
</div>

<!-- KPIs rápidos -->
<?php
$tot_conv  = count($convocatorias);
$activas   = array_filter($convocatorias, fn($c) => $c['estado'] === 'activa');
$cerradas  = array_filter($convocatorias, fn($c) => $c['estado'] === 'cerrada');
?>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card bg-kpi-blue">
            <div class="kpi-icon"><i class="fa-solid fa-calendar-days"></i></div>
            <div><div class="kpi-num"><?= $tot_conv ?></div><div class="kpi-lbl">Total Convocatorias</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card bg-kpi-green">
            <div class="kpi-icon"><i class="fa-solid fa-circle-play"></i></div>
            <div><div class="kpi-num"><?= count($activas) ?></div><div class="kpi-lbl">Activas Ahora</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card bg-kpi-orange">
            <div class="kpi-icon"><i class="fa-solid fa-users"></i></div>
            <div><div class="kpi-num"><?= $convocatorias[0]['total_socios'] ?? 0 ?></div><div class="kpi-lbl">Socios Activos</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card bg-kpi-red" style="background:linear-gradient(135deg,#555,#333);">
            <div class="kpi-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div><div class="kpi-num"><?= count($cerradas) ?></div><div class="kpi-lbl">Cerradas</div></div>
        </div>
    </div>
</div>

<!-- Tarjetas de convocatorias -->
<div class="row g-3">
<?php if (empty($convocatorias)): ?>
    <div class="col-12 text-center py-5 text-muted">
        <i class="fa-solid fa-calendar-xmark fa-3x mb-3"></i>
        <p>No hay convocatorias registradas aún.</p>
    </div>
<?php else: ?>
    <?php foreach ($convocatorias as $c): ?>
    <div class="col-md-6 col-xl-4">
        <div class="conv-card card h-100">
            <div class="conv-header d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-bold fs-6"><?= htmlspecialchars($c['titulo']) ?></div>
                    <div class="small opacity-75 mt-1">
                        <i class="fa-solid fa-calendar me-1"></i><?= date('d/m/Y', strtotime($c['fecha'])) ?>
                        &nbsp;<i class="fa-solid fa-clock ms-2 me-1"></i><?= substr($c['hora'],0,5) ?>
                    </div>
                </div>
                <span class="badge-estado estado-<?= $c['estado'] ?>"><?= ucfirst($c['estado']) ?></span>
            </div>
            <div class="conv-body">
                <div class="text-muted small mb-2">
                    <i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($c['lugar']) ?>
                </div>
                <!-- Mini barra de asistencia -->
                <?php
                    $pct = $c['total_socios'] > 0 ? round(($c['total_asistieron']/$c['total_socios'])*100) : 0;
                ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Asistencia</span>
                        <span><b><?= $c['total_asistieron'] ?></b> / <?= $c['total_socios'] ?> (<?= $pct ?>%)</span>
                    </div>
                    <div class="progreso-wrap" style="height:12px;">
                        <div class="progreso-bar" style="width:<?= $pct ?>%;font-size:.7rem;"><?= $pct > 15 ? $pct.'%' : '' ?></div>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <a href="asistencia.php?vista=detalle&id=<?= $c['id'] ?>" class="btn btn-sm btn-primary rounded-pill flex-fill">
                        <i class="fa-solid fa-eye me-1"></i>Ver / Registrar
                    </a>
                    <?php if (in_array($rol, ['admin','secretario'])): ?>
                    <a href="editar_convocatoria.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!--  VISTA: DETALLE + REGISTRO DE ASISTENCIA              -->
<!-- ══════════════════════════════════════════════════════ -->
<?php elseif ($vista === 'detalle' && isset($convocatoria)): ?>

<!-- Encabezado -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="asistencia.php" class="btn btn-sm btn-outline-secondary rounded-pill me-2">
            <i class="fa-solid fa-arrow-left me-1"></i>Regresar
        </a>
        <span class="fw-bold fs-5"><?= htmlspecialchars($convocatoria['titulo']) ?></span>
        <span class="badge-estado estado-<?= $convocatoria['estado'] ?> ms-2"><?= ucfirst($convocatoria['estado']) ?></span>
    </div>
    <div class="d-flex gap-2">
        <!-- Botón resumen -->
        <button class="btn btn-success rounded-pill" onclick="mostrarResumen()">
            <i class="fa-solid fa-chart-pie me-1"></i>Ver Resumen
        </button>
        <!-- Activar/cerrar sesión (solo admin/secretario) -->
        <?php if (in_array($rol, ['admin','secretario'])): ?>
            <?php if ($convocatoria['estado'] === 'programada'): ?>
            <button class="btn btn-primary rounded-pill" onclick="cambiarEstado(<?= $conv_id ?>,'activa')">
                <i class="fa-solid fa-circle-play me-1"></i>Iniciar Sesión
            </button>
            <?php elseif ($convocatoria['estado'] === 'activa'): ?>
            <button class="btn btn-danger rounded-pill" onclick="cambiarEstado(<?= $conv_id ?>,'cerrada')">
                <i class="fa-solid fa-lock me-1"></i>Cerrar Sesión
            </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Info convocatoria + Orden del día -->
<div class="row g-3 mb-4">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-circle-info me-2"></i>Información</h6>
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted" style="width:130px">Fecha</td><td><b><?= date('d/m/Y', strtotime($convocatoria['fecha'])) ?></b></td></tr>
                    <tr><td class="text-muted">Hora</td><td><b><?= substr($convocatoria['hora'],0,5) ?></b></td></tr>
                    <tr><td class="text-muted">Lugar</td><td><?= htmlspecialchars($convocatoria['lugar']) ?></td></tr>
                    <tr><td class="text-muted">Tipo</td><td><?= ucfirst($convocatoria['tipo']) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-list-ol me-2"></i>Orden del Día</h6>
                <?php if (empty($puntos)): ?>
                    <p class="text-muted small">Sin puntos registrados.</p>
                <?php else: ?>
                    <ol class="mb-0 ps-3">
                        <?php foreach ($puntos as $p): ?>
                        <li class="mb-1 small"><?= htmlspecialchars($p['descripcion']) ?></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- KPIs asistencia -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-card bg-kpi-blue p-3">
            <div class="kpi-icon fs-3"><i class="fa-solid fa-users"></i></div>
            <div><div class="kpi-num"><?= $total_socios ?></div><div class="kpi-lbl">Total Socios</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card bg-kpi-green p-3">
            <div class="kpi-icon fs-3"><i class="fa-solid fa-user-check"></i></div>
            <div><div class="kpi-num"><?= $total_presentes ?></div><div class="kpi-lbl">Presentes</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card bg-kpi-red p-3">
            <div class="kpi-icon fs-3"><i class="fa-solid fa-user-xmark"></i></div>
            <div><div class="kpi-num"><?= $faltantes ?></div><div class="kpi-lbl">Ausentes</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card bg-kpi-orange p-3">
            <div class="kpi-icon fs-3"><i class="fa-solid fa-percent"></i></div>
            <div><div class="kpi-num"><?= $porcentaje ?>%</div><div class="kpi-lbl">Porcentaje</div></div>
        </div>
    </div>
</div>

<!-- Barra de progreso grande -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
            <span class="fw-semibold">Progreso de Asistencia</span>
            <span class="text-muted"><?= $total_presentes ?> de <?= $total_socios ?> socios</span>
        </div>
        <div class="progreso-wrap">
            <div class="progreso-bar" id="barraAsistencia" style="width:<?= $porcentaje ?>%">
                <?= $porcentaje ?>%
            </div>
        </div>
    </div>
</div>

<!-- Registro manual + búsqueda -->
<?php if (in_array($rol, ['admin','secretario']) && $convocatoria['estado'] === 'activa'): ?>
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Registrar Asistencia Manual</h6>
        <div class="search-wrap mb-3">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="inputBuscar" class="form-control" placeholder="Buscar socio por nombre o cédula..." oninput="buscarSocio(this.value)">
        </div>
        <div id="resultadosBusqueda"></div>
    </div>
</div>
<?php endif; ?>

<!-- Tabla de asistentes -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white border-0 pt-3 pb-0">
        <h6 class="fw-bold"><i class="fa-solid fa-clipboard-list me-2 text-success"></i>Lista de Asistentes</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table tbl-socios mb-0" id="tablaAsistentes">
                <thead>
                    <tr>
                        <th class="ps-4">#</th>
                        <th>Socio</th>
                        <th>Cédula</th>
                        <th>Hora Registro</th>
                        <th>Método</th>
                        <?php if (in_array($rol, ['admin','secretario'])): ?>
                        <th>Acción</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="cuerpoTabla">
                <?php if (empty($asistieron)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i>Aún no hay asistentes registrados
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($asistieron as $i => $a): ?>
                    <tr>
                        <td class="ps-4"><?= $i+1 ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if (!empty($a['foto'])): ?>
                                <img src="<?= htmlspecialchars($a['foto']) ?>" width="36" height="36" class="rounded-circle object-fit-cover">
                                <?php else: ?>
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:36px;height:36px;font-size:.8rem;flex-shrink:0;">
                                    <?= strtoupper(substr($a['nombres'],0,1).substr($a['apellidos'],0,1)) ?>
                                </div>
                                <?php endif; ?>
                                <span><?= htmlspecialchars($a['nombres'].' '.$a['apellidos']) ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($a['cedula']) ?></td>
                        <td><?= date('H:i:s', strtotime($a['hora_registro'])) ?></td>
                        <td>
                            <span class="badge-metodo badge-<?= $a['metodo'] === 'biometrico' ? 'bio' : ($a['metodo'] === 'qr' ? 'qr' : 'manual') ?>">
                                <?php if ($a['metodo'] === 'biometrico'): ?><i class="fa-solid fa-fingerprint me-1"></i>
                                <?php elseif ($a['metodo'] === 'qr'): ?><i class="fa-solid fa-qrcode me-1"></i>
                                <?php else: ?><i class="fa-solid fa-hand-pointer me-1"></i><?php endif; ?>
                                <?= ucfirst($a['metodo']) ?>
                            </span>
                        </td>
                        <?php if (in_array($rol, ['admin','secretario'])): ?>
                        <td>
                            <button class="btn btn-sm btn-outline-danger rounded-pill" onclick="eliminarAsistencia(<?= $a['id'] ?>, '<?= htmlspecialchars($a['nombres']) ?>')">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; // fin vista detalle ?>
</div><!-- /main-content -->

<!-- ══════════════════════════════════════════════════════ -->
<!--  MODAL RESUMEN DE ASISTENCIA                          -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalResumen" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 rounded-4 shadow-lg">
      <div class="modal-header border-0" style="background:linear-gradient(135deg,#2c3e7a,#4a6cf7); color:#fff; border-radius:16px 16px 0 0;">
          <h5 class="modal-title fw-bold"><i class="fa-solid fa-chart-pie me-2"></i>Resumen de Asistencia</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
          <!-- Donut chart SVG dinámico -->
          <div class="text-center mb-4">
              <div style="position:relative; display:inline-block;">
                  <svg width="180" height="180" viewBox="0 0 36 36">
                      <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e9ecef" stroke-width="3.5"/>
                      <circle cx="18" cy="18" r="15.9" fill="none" stroke="#27ae60" stroke-width="3.5"
                              stroke-dasharray="<?= isset($porcentaje) ? $porcentaje : 0 ?> 100"
                              stroke-linecap="round"
                              transform="rotate(-90 18 18)"
                              id="donutArc"/>
                  </svg>
                  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;">
                      <div style="font-size:1.8rem;font-weight:700;color:#2c3e7a;"><?= isset($porcentaje) ? $porcentaje : 0 ?>%</div>
                      <div style="font-size:.75rem;color:#888;">asistencia</div>
                  </div>
              </div>
          </div>

          <!-- Stats detalladas -->
          <div class="row g-3 text-center mb-3">
              <div class="col-4">
                  <div style="background:#f0f7ff;border-radius:12px;padding:16px 8px;">
                      <div style="font-size:2rem;font-weight:700;color:#2c3e7a;"><?= isset($total_socios) ? $total_socios : 0 ?></div>
                      <div class="text-muted small">Total Socios</div>
                  </div>
              </div>
              <div class="col-4">
                  <div style="background:#f0fff5;border-radius:12px;padding:16px 8px;">
                      <div style="font-size:2rem;font-weight:700;color:#27ae60;"><?= isset($total_presentes) ? $total_presentes : 0 ?></div>
                      <div class="text-muted small">Presentes</div>
                  </div>
              </div>
              <div class="col-4">
                  <div style="background:#fff5f5;border-radius:12px;padding:16px 8px;">
                      <div style="font-size:2rem;font-weight:700;color:#e74c3c;"><?= isset($faltantes) ? $faltantes : 0 ?></div>
                      <div class="text-muted small">Ausentes</div>
                  </div>
              </div>
          </div>

          <!-- Mensaje de quórum -->
          <?php if (isset($porcentaje)): ?>
          <?php $quorum = 50; // ajusta según estatutos ?>
          <div class="alert <?= $porcentaje >= $quorum ? 'alert-success' : 'alert-warning' ?> rounded-3 text-center mb-0">
              <i class="fa-solid <?= $porcentaje >= $quorum ? 'fa-circle-check' : 'fa-triangle-exclamation' ?> me-2"></i>
              <?php if ($porcentaje >= $quorum): ?>
                  <b>¡Quórum alcanzado!</b> La sesión puede proceder con validez.
              <?php else: ?>
                  <b>Quórum incompleto.</b> Faltan <?= $faltantes ?> socios para el <?= $quorum ?>%.
              <?php endif; ?>
          </div>
          <?php endif; ?>
      </div>
      <div class="modal-footer border-0">
          <button class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cerrar</button>
          <button class="btn btn-primary rounded-pill" onclick="imprimirReporte()">
              <i class="fa-solid fa-print me-1"></i>Imprimir
          </button>
      </div>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CONV_ID = <?= json_encode($conv_id) ?>;

// ── Mostrar modal resumen ────────────────────────────────
function mostrarResumen() {
    new bootstrap.Modal(document.getElementById('modalResumen')).show();
}

// ── Buscar socio (AJAX) ──────────────────────────────────
let timer;
function buscarSocio(q) {
    clearTimeout(timer);
    if (q.length < 2) { document.getElementById('resultadosBusqueda').innerHTML = ''; return; }
    timer = setTimeout(() => {
        fetch(`ajax_buscar_socio.php?q=${encodeURIComponent(q)}&conv_id=${CONV_ID}`)
            .then(r => r.json())
            .then(data => {
                let html = '<div class="list-group">';
                if (data.length === 0) {
                    html += '<div class="list-group-item text-muted">Sin resultados</div>';
                } else {
                    data.forEach(s => {
                        const ya = s.ya_registro ? '✅ Ya registrado' : '';
                        const dis = s.ya_registro ? 'disabled' : '';
                        html += `
                        <button class="list-group-item list-group-item-action d-flex align-items-center gap-3 ${dis}" 
                                onclick="registrarManual(${s.id}, '${escHtml(s.nombres+' '+s.apellidos)}')">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" 
                                 style="width:38px;height:38px;flex-shrink:0;font-size:.8rem;">
                                ${s.iniciales}
                            </div>
                            <div class="text-start">
                                <div class="fw-semibold">${escHtml(s.nombres)} ${escHtml(s.apellidos)}</div>
                                <small class="text-muted">${s.cedula}</small>
                            </div>
                            <div class="ms-auto">
                                ${ya ? `<span class="badge bg-success">${ya}</span>` 
                                     : '<span class="badge bg-primary">Registrar</span>'}
                            </div>
                        </button>`;
                    });
                }
                html += '</div>';
                document.getElementById('resultadosBusqueda').innerHTML = html;
            });
    }, 350);
}

function escHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ── Registrar asistencia manual ──────────────────────────
function registrarManual(socio_id, nombre) {
    Swal.fire({
        title: '¿Registrar asistencia?',
        html: `<b>${nombre}</b><br><small class="text-muted">Se marcará como presente (manual)</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, registrar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#27ae60'
    }).then(r => {
        if (!r.isConfirmed) return;
        fetch('ajax_registrar_asistencia.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ convocatoria_id: CONV_ID, socio_id, metodo: 'manual' })
        })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                Swal.fire({ icon:'success', title:'¡Registrado!', text: nombre+' marcado como presente.', timer:2000, showConfirmButton:false });
                setTimeout(() => location.reload(), 2000);
            } else {
                Swal.fire('Error', d.msg || 'No se pudo registrar', 'error');
            }
        });
    });
}

// ── Eliminar asistencia ──────────────────────────────────
function eliminarAsistencia(id, nombre) {
    Swal.fire({
        title: '¿Eliminar registro?',
        html: `Se eliminará la asistencia de <b>${nombre}</b>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#e74c3c'
    }).then(r => {
        if (!r.isConfirmed) return;
        fetch('ajax_eliminar_asistencia.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ id })
        })
        .then(r => r.json())
        .then(d => {
            if (d.ok) { location.reload(); }
            else { Swal.fire('Error', d.msg, 'error'); }
        });
    });
}

// ── Cambiar estado convocatoria ──────────────────────────
function cambiarEstado(id, estado) {
    const labels = { activa: 'iniciar', cerrada: 'cerrar' };
    Swal.fire({
        title: `¿${labels[estado]?.charAt(0).toUpperCase()+labels[estado]?.slice(1)} sesión?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Confirmar',
        confirmButtonColor: estado === 'activa' ? '#27ae60' : '#e74c3c'
    }).then(r => {
        if (!r.isConfirmed) return;
        fetch('ajax_estado_convocatoria.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ id, estado })
        })
        .then(r => r.json())
        .then(d => {
            if (d.ok) location.reload();
            else Swal.fire('Error', d.msg, 'error');
        });
    });
}

// ── Imprimir reporte ─────────────────────────────────────
function imprimirReporte() {
    window.open(`reporte_asistencia.php?id=${CONV_ID}`, '_blank');
}
</script>
</body>
</html>
