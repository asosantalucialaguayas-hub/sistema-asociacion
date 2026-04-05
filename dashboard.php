<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require __DIR__ . "/layout/bootstrap.php";

$stats = ['socios_activos'=>0,'lpas_total'=>0,'acuerdos_total'=>0,'ventas_count'=>0,'ventas_kg'=>0,'ventas_monto'=>0];
$top_productores = [];

if ($periodoSeleccionado) {
    $id_periodo = $periodoSeleccionado['id_periodo'];
    try { $stats['socios_activos'] = $pdo->query("SELECT COUNT(*) FROM socios WHERE estado='activo'")->fetchColumn() ?: 0; } catch(PDOException $e) {}
    try { $s=$pdo->prepare("SELECT COUNT(*) FROM tabla_lpa WHERE id_periodo=?"); $s->execute([$id_periodo]); $stats['lpas_total']=$s->fetchColumn() ?: 0; } catch(PDOException $e) {}
    try { $s=$pdo->prepare("SELECT COUNT(*) FROM acuerdo_productor WHERE id_periodo=?"); $s->execute([$id_periodo]); $stats['acuerdos_total']=$s->fetchColumn() ?: 0; } catch(PDOException $e) {}
    try {
        $s=$pdo->prepare("SELECT COUNT(v.id_venta) AS total_ventas, COALESCE(SUM(v.cantidad_vende),0) AS total_kg, COALESCE(SUM(v.total),0) AS total_monto FROM tabla_ventas v INNER JOIN tabla_lpa l ON v.id_lpa=l.id_lpa WHERE l.id_periodo=?");
        $s->execute([$id_periodo]); $vd=$s->fetch(PDO::FETCH_ASSOC);
        $stats['ventas_count']=$vd['total_ventas']??0; $stats['ventas_kg']=$vd['total_kg']??0; $stats['ventas_monto']=$vd['total_monto']??0;
    } catch(PDOException $e) {}
    try {
        $s=$pdo->prepare("SELECT s.nombre_completo AS nombre_socio, SUM(v.cantidad_vende) AS total_kg, COUNT(v.id_venta) AS num_ventas FROM tabla_ventas v INNER JOIN tabla_lpa l ON v.id_lpa=l.id_lpa INNER JOIN socios s ON l.id_socio=s.id_socio WHERE l.id_periodo=? GROUP BY s.id_socio, s.nombre_completo ORDER BY total_kg DESC LIMIT 5");
        $s->execute([$id_periodo]); $top_productores=$s->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {}
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['periodo'])) { $_SESSION['periodo_activo']=(int)$_POST['periodo']; header("Location: ".$_SERVER['PHP_SELF']); exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard - Sistema Asociación</title>
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/modal-message.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.periodo-selector-compact{display:inline-flex;align-items:center;gap:8px;font-size:13px}
.periodo-selector-compact select{padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;background:white;font-size:13px;cursor:pointer}
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-bottom:30px}
.kpi-card{background:linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%);border-radius:12px;padding:20px;color:white;transition:transform .3s}
.kpi-card:hover{transform:translateY(-5px)}
.kpi-card.green {background:linear-gradient(135deg,#047857 0%,#10b981 100%)}
.kpi-card.orange{background:linear-gradient(135deg,#c2410c 0%,#f97316 100%)}
.kpi-card.blue  {background:linear-gradient(135deg,#0369a1 0%,#0ea5e9 100%)}
.kpi-icon{font-size:32px;margin-bottom:10px;opacity:.9}
.kpi-value{font-size:32px;font-weight:bold;margin-bottom:5px}
.kpi-label{font-size:13px;opacity:.9}
.dashboard-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
.chart-card{background:white;border-radius:12px;padding:25px;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.chart-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f3f4f6}
.chart-header h3{margin:0;color:#1f3a5f;font-size:18px}
.ranking-list{list-style:none;padding:0;margin:0}
.ranking-item{padding:12px;background:#f9fafb;border-radius:8px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;transition:all .3s}
.ranking-item:hover{background:#f3f4f6;transform:translateX(5px)}
.ranking-number{width:30px;height:30px;background:linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%);color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:14px}
.ranking-info{flex:1;margin-left:15px}
.ranking-info h4{margin:0 0 5px;font-size:14px;color:#1f3a5f}
.ranking-info p{margin:0;font-size:12px;color:#6c757d}
.ranking-value{font-size:18px;font-weight:bold;color:#1f3a5f}
.quick-access-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px}
.quick-access-btn{background:white;padding:25px 20px;border-radius:12px;text-align:center;text-decoration:none;transition:all .3s;border:2px solid #e5e7eb}
.quick-access-btn:hover{transform:translateY(-5px);box-shadow:0 5px 20px rgba(0,0,0,.1);border-color:#3b82f6}
.quick-access-btn i{font-size:40px;margin-bottom:10px;color:#3b82f6}
.quick-access-btn span{display:block;font-weight:600;color:#1f3a5f;font-size:14px}
@media(max-width:768px){.dashboard-grid{grid-template-columns:1fr}.kpi-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<script src="layout/modal-message.js"></script>
<div class="app">
<?php include __DIR__ . "/layout/sidebar.php"; ?>
<main class="content">
<header class="topbar" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
    <span>Bienvenido, <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong></span>
    <div style="display:flex;align-items:center;gap:14px;margin-left:auto;">
        <?php include __DIR__ . "/notificaciones.php"; ?>
        <div class="periodo-selector-compact">
            <i class="fa-solid fa-calendar-days"></i>
            <form method="post" action="" id="form-periodo" style="margin:0">
                <select name="periodo" onchange="this.form.submit()">
                    <?php
                    $todosPeriodos = get_all_periodos($pdo);
                    if (!empty($todosPeriodos)):
                        foreach ($todosPeriodos as $p): ?>
                            <option value="<?= $p['id_periodo'] ?>" <?= ($periodoSeleccionado && $periodoSeleccionado['id_periodo']==$p['id_periodo'])?'selected':'' ?>>
                                <?= htmlspecialchars($p['nombre']) ?> (<?= $p['estado'] ?>)
                            </option>
                    <?php endforeach; else: ?>
                        <option value="">Sin períodos</option>
                    <?php endif; ?>
                </select>
            </form>
        </div>
    </div>
</header>

<section class="page">
<h1><i class="fa-solid fa-chart-line"></i> Dashboard Ejecutivo</h1>

<?php if (!$periodoSeleccionado): ?>
    <div style="background:#fff3cd;padding:20px;border-radius:8px;border-left:4px solid #ffc107">
        <strong>⚠️ No hay período seleccionado</strong>
        <p>Por favor, <a href="periodos.php">crea o selecciona un período</a> para ver las estadísticas.</p>
    </div>
<?php else: ?>

<!-- KPIs -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-icon"><i class="fa-solid fa-users"></i></div>
        <div class="kpi-value"><?= number_format($stats['socios_activos']) ?></div>
        <div class="kpi-label">Socios Activos</div>
    </div>
    <div class="kpi-card green">
        <div class="kpi-icon"><i class="fa-solid fa-file-contract"></i></div>
        <div class="kpi-value"><?= number_format($stats['lpas_total']) ?></div>
        <div class="kpi-label">LPAs Registradas</div>
    </div>
    <div class="kpi-card orange">
        <div class="kpi-icon"><i class="fa-solid fa-shopping-cart"></i></div>
        <div class="kpi-value"><?= number_format($stats['ventas_count']) ?></div>
        <div class="kpi-label">Ventas Realizadas</div>
    </div>
    <div class="kpi-card blue">
        <div class="kpi-icon"><i class="fa-solid fa-weight-scale"></i></div>
        <div class="kpi-value"><?= number_format($stats['ventas_kg'], 2) ?> kg</div>
        <div class="kpi-label">Total Vendido</div>
    </div>
</div>

<!-- Gráficas y Rankings -->
<div class="dashboard-grid">
    <div class="chart-card">
        <div class="chart-header">
            <h3><i class="fa-solid fa-dollar-sign"></i> Resumen Financiero</h3>
        </div>
        <div style="display:grid;gap:15px">
            <div style="padding:20px;background:#f9fafb;border-radius:8px;text-align:center">
                <div style="font-size:28px;font-weight:bold;color:#10b981">$<?= number_format($stats['ventas_monto'],2) ?></div>
                <div style="font-size:13px;color:#6c757d;margin-top:5px">Total en Ventas</div>
            </div>
            <div style="padding:15px;background:#e7f3ff;border-radius:8px">
                <div style="font-size:14px;color:#1f3a5f;margin-bottom:8px"><strong>📊 Estadísticas del Período</strong></div>
                <div style="font-size:13px;color:#6c757d;line-height:1.8">
                    • <strong><?= number_format($stats['lpas_total']) ?></strong> LPAs registradas<br>
                    • <strong><?= number_format($stats['acuerdos_total']) ?></strong> Acuerdos firmados<br>
                    • <strong><?= number_format($stats['ventas_count']) ?></strong> Transacciones realizadas
                </div>
            </div>
        </div>
    </div>
    <div class="chart-card">
        <div class="chart-header">
            <h3><i class="fa-solid fa-trophy"></i> Top Productores</h3>
        </div>
        <?php if (empty($top_productores)): ?>
            <p style="text-align:center;color:#6c757d;padding:20px">Sin datos de ventas aún</p>
        <?php else: ?>
            <ul class="ranking-list">
                <?php foreach ($top_productores as $i => $p): ?>
                <li class="ranking-item">
                    <div class="ranking-number"><?= $i+1 ?></div>
                    <div class="ranking-info">
                        <h4><?= htmlspecialchars($p['nombre_socio']) ?></h4>
                        <p><?= $p['num_ventas'] ?> ventas</p>
                    </div>
                    <div class="ranking-value"><?= number_format($p['total_kg'],2) ?> kg</div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- ══ DISTRIBUCIÓN POR GÉNERO ══ -->
<div class="chart-card" style="margin-bottom:20px;">
    <div class="chart-header">
        <h3><i class="fa-solid fa-venus-mars"></i> Distribución por Género</h3>
        <a href="exportar_genero_excel.php" style="text-decoration:none;" title="Descargar Excel con socios por género">
            <button type="button" style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;padding:9px 18px;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:7px;box-shadow:0 4px 12px rgba(16,185,129,.3);">
                <i class="fa fa-file-excel"></i> Exportar Excel
            </button>
        </a>
    </div>
    <div style="display:flex;align-items:center;justify-content:center;gap:40px;flex-wrap:wrap;padding:6px 0 4px;">
        <!-- Donut canvas -->
        <div style="position:relative;width:190px;height:190px;flex-shrink:0;">
            <canvas id="chartGenero" width="190" height="190"></canvas>
            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none;">
                <div style="font-size:30px;font-weight:700;color:#1f3a5f;line-height:1;" id="chartTotal">-</div>
                <div style="font-size:10px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-top:3px;">Total</div>
            </div>
        </div>
        <!-- Leyenda -->
        <div style="display:flex;flex-direction:column;gap:18px;min-width:175px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:16px;height:16px;border-radius:50%;background:#C0392B;flex-shrink:0;box-shadow:0 2px 6px rgba(192,57,43,.4);"></div>
                <div>
                    <div style="font-size:14px;font-weight:700;color:#1f3a5f;">🚺 Mujeres</div>
                    <div style="font-size:12px;color:#6b7280;margin-top:2px;" id="legendMujeres">Cargando...</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:16px;height:16px;border-radius:50%;background:#1A5276;flex-shrink:0;box-shadow:0 2px 6px rgba(26,82,118,.4);"></div>
                <div>
                    <div style="font-size:14px;font-weight:700;color:#1f3a5f;">🚹 Hombres</div>
                    <div style="font-size:12px;color:#6b7280;margin-top:2px;" id="legendHombres">Cargando...</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:16px;height:16px;border-radius:4px;background:#9ca3af;flex-shrink:0;"></div>
                <div>
                    <div style="font-size:14px;font-weight:700;color:#1f3a5f;">Sin definir</div>
                    <div style="font-size:12px;color:#6b7280;margin-top:2px;" id="legendOtros">Cargando...</div>
                </div>
            </div>
        </div>
    </div>
</div>



<!-- ══ PIRÁMIDE ETARIA ══ -->
<div class="chart-card" style="margin-bottom:20px;" id="edadCard">
    <div class="chart-header">
        <h3><i class="fa-solid fa-users-between-lines" style="color:#6366f1;"></i> Distribución por Edad y Género</h3>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button onclick="exportarEdadExcel()" style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;padding:8px 16px;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-file-excel"></i> Excel
            </button>
            <button onclick="exportarEdadPDF()" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border:none;padding:8px 16px;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-file-pdf"></i> PDF
            </button>
        </div>
    </div>

    <!-- Leyenda rangos -->
    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;font-size:12px;">
        <div style="display:flex;align-items:center;gap:6px;">
            <div style="width:14px;height:14px;border-radius:3px;background:#6366f1;"></div>
            <span style="font-weight:600;color:#374151;">Jóvenes 18–35</span>
        </div>
        <div style="display:flex;align-items:center;gap:6px;">
            <div style="width:14px;height:14px;border-radius:3px;background:#3b82f6;"></div>
            <span style="font-weight:600;color:#374151;">Adultos 36–70</span>
        </div>
        <div style="display:flex;align-items:center;gap:6px;">
            <div style="width:14px;height:14px;border-radius:3px;background:#f59e0b;"></div>
            <span style="font-weight:600;color:#374151;">Adultos Mayores 71+</span>
        </div>
        <div style="display:flex;align-items:center;gap:6px;">
            <div style="width:12px;height:12px;border-radius:50%;background:#C0392B;border:2px solid #C0392B;"></div>
            <span style="color:#C0392B;font-weight:600;">Mujeres</span>
        </div>
        <div style="display:flex;align-items:center;gap:6px;">
            <div style="width:12px;height:12px;border-radius:50%;background:#1A5276;border:2px solid #1A5276;"></div>
            <span style="color:#1A5276;font-weight:600;">Hombres</span>
        </div>
    </div>

    <div style="width:100%;overflow-x:auto;">
        <canvas id="canvasEdad" height="260" style="display:block;min-width:420px;"></canvas>
    </div>

    <!-- Tabla resumen debajo -->
    <div id="tablaEdadResumen" style="margin-top:18px;overflow-x:auto;"></div>
</div>

<script>
(function(){
    fetch('dashboard_edad_stats.php')
        .then(function(r){ return r.json(); })
        .then(function(d){
            if(!d.success) return;
            renderEdad(d);
            renderTablaEdad(d);
        }).catch(function(){});
})();

function renderEdad(d) {
    var canvas = document.getElementById('canvasEdad');
    if(!canvas) return;

  var grupos = [
    { label:'Jóvenes\n18–35',      muj: d.joven_f,  hom: d.joven_m,  color_m:'#7c3aed', color_h:'#4f46e5' },
    { label:'Adultos\n36–70',      muj: d.adulto_f, hom: d.adulto_m, color_m:'#2563eb', color_h:'#1d4ed8' },
    { label:'Ad. Mayores\n71+',    muj: d.mayor_f,  hom: d.mayor_m,  color_m:'#d97706', color_h:'#b45309' }
];

    var W       = Math.max(420, canvas.parentElement ? canvas.parentElement.offsetWidth - 20 : 600);
    var H       = 260;
    canvas.width  = W;
    canvas.height = H;
    canvas.style.width  = W + 'px';

    var ctx     = canvas.getContext('2d');
    ctx.clearRect(0,0,W,H);

    var padL=50, padR=20, padT=20, padB=50;
    var chartW  = W - padL - padR;
    var chartH  = H - padT - padB;

    // Máximo valor
    var maxVal = 1;
    grupos.forEach(function(g){
        maxVal = Math.max(maxVal, g.muj, g.hom);
    });
    maxVal = Math.ceil(maxVal * 1.15);

    // Líneas de cuadrícula
    var steps = 5;
    ctx.strokeStyle = '#f3f4f6';
    ctx.lineWidth   = 1;
    ctx.fillStyle   = '#9ca3af';
    ctx.font        = '10px Arial';
    ctx.textAlign   = 'right';
    for(var i=0; i<=steps; i++){
        var val = Math.round(maxVal * i / steps);
        var yy  = padT + chartH - (i/steps)*chartH;
        ctx.beginPath(); ctx.moveTo(padL, yy); ctx.lineTo(padL+chartW, yy); ctx.stroke();
        ctx.fillText(val, padL-6, yy+4);
    }

    // Barras
    var grupoW  = chartW / grupos.length;
    var barW    = grupoW * 0.28;
    var gap     = grupoW * 0.06;

    grupos.forEach(function(g, gi) {
        var xCenter = padL + gi * grupoW + grupoW/2;
        var xMuj    = xCenter - barW - gap/2;
        var xHom    = xCenter + gap/2;

        // Barra Mujeres
        var hMuj = maxVal > 0 ? (g.muj/maxVal)*chartH : 0;
        var gradM = ctx.createLinearGradient(0, padT+chartH-hMuj, 0, padT+chartH);
        gradM.addColorStop(0, g.color_m); gradM.addColorStop(1, lighten(g.color_m));
        ctx.fillStyle = gradM;
        roundRect(ctx, xMuj, padT+chartH-hMuj, barW, hMuj, 4);
        ctx.fill();
        // Valor sobre barra
        if(g.muj > 0) {
            ctx.fillStyle = '#374151'; ctx.font='bold 11px Arial'; ctx.textAlign='center';
            ctx.fillText(g.muj, xMuj+barW/2, padT+chartH-hMuj-5);
        }

        // Barra Hombres
        var hHom = maxVal > 0 ? (g.hom/maxVal)*chartH : 0;
        var gradH = ctx.createLinearGradient(0, padT+chartH-hHom, 0, padT+chartH);
        gradH.addColorStop(0, g.color_h); gradH.addColorStop(1, lighten(g.color_h));
        ctx.fillStyle = gradH;
        roundRect(ctx, xHom, padT+chartH-hHom, barW, hHom, 4);
        ctx.fill();
        if(g.hom > 0) {
            ctx.fillStyle = '#374151'; ctx.font='bold 11px Arial'; ctx.textAlign='center';
            ctx.fillText(g.hom, xHom+barW/2, padT+chartH-hHom-5);
        }

        // Indicador ♀/♂ pequeño bajo las barras
        ctx.fillStyle='#C0392B'; ctx.font='10px Arial'; ctx.textAlign='center';
        ctx.fillText('♀', xMuj+barW/2, padT+chartH+12);
        ctx.fillStyle='#1A5276';
        ctx.fillText('♂', xHom+barW/2, padT+chartH+12);

        // Etiqueta grupo (multilinea simulada)
        var lines = g.label.split('\n');
        ctx.fillStyle='#374151'; ctx.font='bold 11px Arial'; ctx.textAlign='center';
        lines.forEach(function(ln,li){
            ctx.fillText(ln, xCenter, padT+chartH+26+li*13);
        });
    });

    // Eje Y label
    ctx.save();
    ctx.translate(12, padT+chartH/2);
    ctx.rotate(-Math.PI/2);
    ctx.fillStyle='#9ca3af'; ctx.font='10px Arial'; ctx.textAlign='center';
    ctx.fillText('N° Socios', 0, 0);
    ctx.restore();
}

function lighten(hex) {
    var r=parseInt(hex.slice(1,3),16), g=parseInt(hex.slice(3,5),16), b=parseInt(hex.slice(5,7),16);
    r=Math.min(255,r+60); g=Math.min(255,g+60); b=Math.min(255,b+60);
    return 'rgb('+r+','+g+','+b+')';
}
function roundRect(ctx,x,y,w,h,r){
    if(h<=0){return;}
    if(h<r) r=h;
    ctx.beginPath();
    ctx.moveTo(x+r,y); ctx.lineTo(x+w-r,y);
    ctx.quadraticCurveTo(x+w,y,x+w,y+r);
    ctx.lineTo(x+w,y+h); ctx.lineTo(x,y+h);
    ctx.lineTo(x,y+r);
    ctx.quadraticCurveTo(x,y,x+r,y);
    ctx.closePath();
}

function renderTablaEdad(d) {
    var cont = document.getElementById('tablaEdadResumen');
    if(!cont) return;
    var total = d.joven_m+d.joven_f+d.adulto_m+d.adulto_f+d.mayor_m+d.mayor_f;
    var filas = [
        ['Jóvenes (18–35)',         d.joven_m,  d.joven_f,  d.joven_m+d.joven_f],
        ['Adultos (36–70)',         d.adulto_m, d.adulto_f, d.adulto_m+d.adulto_f],
        ['Adultos Mayores (71+)',   d.mayor_m,  d.mayor_f,  d.mayor_m+d.mayor_f],
        ['Sin fecha / No calculado',d.sd_m,     d.sd_f,     d.sd_m+d.sd_f],
    ];
    var h = '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
    h    += '<thead><tr style="background:#1f3a5f;color:#fff;">';
    h    += '<th style="padding:9px 12px;text-align:left;border-radius:0;">Rango de Edad</th>';
    h    += '<th style="padding:9px 12px;text-align:center;"><span style="color:#90cdf4;">🚹 Hombres</span></th>';
    h    += '<th style="padding:9px 12px;text-align:center;"><span style="color:#fca5a5;">🚺 Mujeres</span></th>';
    h    += '<th style="padding:9px 12px;text-align:center;">Total</th>';
    h    += '<th style="padding:9px 12px;text-align:center;">% del Total</th>';
    h    += '</tr></thead><tbody>';
    var colores = ['#ede9fe','#dbeafe','#fef3c7','#f3f4f6'];
    filas.forEach(function(f,i){
        var pct = total>0 ? ((f[3]/total)*100).toFixed(1) : '0.0';
        h += '<tr style="background:'+colores[i]+';border-bottom:1px solid #e5e7eb;">';
        h += '<td style="padding:9px 12px;font-weight:600;color:#374151;">'+f[0]+'</td>';
        h += '<td style="padding:9px 12px;text-align:center;font-weight:700;color:#1A5276;">'+f[1]+'</td>';
        h += '<td style="padding:9px 12px;text-align:center;font-weight:700;color:#C0392B;">'+f[2]+'</td>';
        h += '<td style="padding:9px 12px;text-align:center;font-weight:700;color:#1f3a5f;">'+f[3]+'</td>';
        h += '<td style="padding:9px 12px;text-align:center;color:#6b7280;">'+pct+'%</td>';
        h += '</tr>';
    });
    // Fila total
    var tm=d.joven_m+d.adulto_m+d.mayor_m+d.sd_m, tf=d.joven_f+d.adulto_f+d.mayor_f+d.sd_f;
    h += '<tr style="background:#1f3a5f;color:#fff;font-weight:700;">';
    h += '<td style="padding:9px 12px;">TOTAL</td>';
    h += '<td style="padding:9px 12px;text-align:center;">'+tm+'</td>';
    h += '<td style="padding:9px 12px;text-align:center;">'+tf+'</td>';
    h += '<td style="padding:9px 12px;text-align:center;">'+total+'</td>';
    h += '<td style="padding:9px 12px;text-align:center;">100%</td>';
    h += '</tr></tbody></table>';
    cont.innerHTML = h;
}

function exportarEdadExcel() {
    window.open('dashboard_edad_excel.php','_blank');
}
function exportarEdadPDF() {
    var style = document.createElement('style');
    style.id  = '__printEdad';
    style.innerHTML = '@media print { body > * { display:none!important; } .app { display:block!important; } .sidebar,.topbar { display:none!important; } .content { display:block!important; overflow:visible!important; height:auto!important; } .page > *:not(#edadCard) { display:none!important; } #edadCard { display:block!important; box-shadow:none!important; } }';
    document.head.appendChild(style);
    window.print();
    setTimeout(function(){ var s=document.getElementById('__printEdad'); if(s) s.remove(); },1000);
}
window.addEventListener('resize', function(){
    fetch('dashboard_edad_stats.php').then(function(r){return r.json();}).then(function(d){ if(d.success) renderEdad(d); }).catch(function(){});
});
</script>

<!-- ══ MAPA DE CALOR ZONAS/COMUNIDADES ══ -->
<div class="chart-card" style="margin-bottom:20px;" id="mapaCalorCard">
    <div class="chart-header">
        <h3><i class="fa-solid fa-fire-flame-curved" style="color:#e74c3c;"></i> Mapa de Calor — Socios por Zona / Comunidad</h3>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button onclick="exportarMapaExcel()" style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;padding:8px 16px;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-file-excel"></i> Excel
            </button>
            <button onclick="exportarMapaPDF()" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border:none;padding:8px 16px;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-file-pdf"></i> PDF
            </button>
        </div>
    </div>

    <!-- Filtro agrupación -->
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
        <span style="font-size:13px;font-weight:600;color:#374151;">Agrupar por:</span>
        <button onclick="cambiarVistaCalor('zona')" id="btnCalorZona"
            style="padding:6px 14px;border-radius:6px;border:2px solid #1f3a5f;background:#1f3a5f;color:#fff;font-size:12px;font-weight:700;cursor:pointer;">
            Zona
        </button>
        <button onclick="cambiarVistaCalor('comunidad')" id="btnCalorComunidad"
            style="padding:6px 14px;border-radius:6px;border:2px solid #d1d5db;background:#fff;color:#6b7280;font-size:12px;font-weight:700;cursor:pointer;">
            Comunidad
        </button>
    </div>

    <!-- Canvas mapa de calor -->
    <div style="width:100%;overflow-x:auto;">
        <canvas id="canvasCalor" style="display:block;min-width:400px;"></canvas>
    </div>

    <!-- Leyenda gradiente -->
    <div style="display:flex;align-items:center;gap:10px;margin-top:14px;flex-wrap:wrap;">
        <span style="font-size:11px;color:#6b7280;font-weight:600;">Pocos</span>
        <div id="gradLeyenda" style="flex:1;min-width:120px;max-width:260px;height:12px;border-radius:6px;background:linear-gradient(to right,#fff7ed,#fdba74,#f97316,#c2410c,#7f1d1d);border:1px solid #e5e7eb;"></div>
        <span style="font-size:11px;color:#6b7280;font-weight:600;">Muchos</span>
        <span id="mapaCalorMaxLabel" style="font-size:11px;color:#1f3a5f;font-weight:700;margin-left:6px;"></span>
    </div>
</div>

<script>
var _caloresDatos = null;
var _caloresVista = 'zona';

// Cargar datos al iniciar
(function(){
    fetch('dashboard_mapa_calor.php')
        .then(function(r){return r.json();})
        .then(function(d){
            if(!d.success) return;
            _caloresDatos = d;
            renderCalor('zona');
        }).catch(function(){});
})();

function cambiarVistaCalor(vista) {
    _caloresVista = vista;
    document.getElementById('btnCalorZona').style.background     = vista==='zona'      ? '#1f3a5f' : '#fff';
    document.getElementById('btnCalorZona').style.color          = vista==='zona'      ? '#fff'    : '#6b7280';
    document.getElementById('btnCalorZona').style.borderColor    = vista==='zona'      ? '#1f3a5f' : '#d1d5db';
    document.getElementById('btnCalorComunidad').style.background= vista==='comunidad' ? '#1f3a5f' : '#fff';
    document.getElementById('btnCalorComunidad').style.color     = vista==='comunidad' ? '#fff'    : '#6b7280';
    document.getElementById('btnCalorComunidad').style.borderColor=vista==='comunidad' ? '#1f3a5f' : '#d1d5db';
    if(_caloresDatos) renderCalor(vista);
}

function renderCalor(vista) {
    var datos = vista==='zona' ? _caloresDatos.zonas : _caloresDatos.comunidades;
    if(!datos || !datos.length) return;

    // Ordenar desc
    datos = datos.slice().sort(function(a,b){return b.total-a.total;});

    var canvas  = document.getElementById('canvasCalor');
    var ctx     = canvas.getContext('2d');
    var maxVal  = datos[0].total || 1;
    var rowH    = 36;
    var barMaxW = 0;
    var labelW  = 0;
    var numW    = 48;
    var padLeft = 10;
    var padRight= 14;

    // Calcular ancho label
    ctx.font = 'bold 12px Arial';
    datos.forEach(function(d){
        var w = ctx.measureText(d.nombre).width;
        if(w > labelW) labelW = w;
    });
    labelW = Math.min(labelW + 16, 220);

    var totalW  = Math.max(500, (canvas.parentElement ? canvas.parentElement.offsetWidth : 600) - 20);
    barMaxW     = totalW - labelW - numW - padLeft - padRight;
    var totalH  = datos.length * rowH + 30;

    canvas.width  = totalW;
    canvas.height = totalH;
    canvas.style.width  = totalW + 'px';
    canvas.style.height = totalH + 'px';

    ctx.clearRect(0, 0, totalW, totalH);

    function heatColor(val, max) {
        var t = max > 0 ? val/max : 0;
        var r, g, b;
        if(t < 0.25)      { r=255; g=Math.round(247+(t/0.25)*(186-247)); b=Math.round(237+(t/0.25)*(116-237)); }
        else if(t < 0.5)  { var p=(t-0.25)/0.25; r=255; g=Math.round(186+p*(122-186)); b=Math.round(116+p*(20-116)); }
        else if(t < 0.75) { var p=(t-0.5)/0.25;  r=Math.round(255+p*(194-255)); g=Math.round(122+p*(65-122)); b=Math.round(20+p*(12-20)); }
        else               { var p=(t-0.75)/0.25; r=Math.round(194+p*(127-194)); g=Math.round(65+p*(29-65));  b=Math.round(12+p*(29-12)); }
        return 'rgb('+r+','+g+','+b+')';
    }

    datos.forEach(function(d, i) {
        var y       = i * rowH + 28;
        var barW    = maxVal > 0 ? (d.total / maxVal) * barMaxW : 0;
        var x0      = padLeft + labelW;
        var fillCol = heatColor(d.total, maxVal);

        // Fondo alternado
        ctx.fillStyle = i%2===0 ? '#f9fafb' : '#ffffff';
        ctx.fillRect(0, y - rowH + 8, totalW, rowH);

        // Label
        ctx.fillStyle = '#1f3a5f';
        ctx.font = 'bold 12px Arial';
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';
        var labelText = d.nombre.length > 26 ? d.nombre.substring(0,24)+'…' : d.nombre;
        ctx.fillText(labelText, padLeft + labelW - 8, y - rowH/2 + 8);

        // Barra
        if(barW > 0) {
            var grad = ctx.createLinearGradient(x0, 0, x0+barW, 0);
            grad.addColorStop(0, fillCol);
            grad.addColorStop(1, heatColor(Math.max(d.total*1.3, d.total), maxVal));
            ctx.fillStyle = grad;
            ctx.beginPath();
            ctx.roundRect ? ctx.roundRect(x0, y - rowH + 12, barW, rowH-8, 4)
                          : ctx.rect(x0, y - rowH + 12, barW, rowH-8);
            ctx.fill();
        }

        // Número
        ctx.fillStyle = '#1f3a5f';
        ctx.font = 'bold 12px Arial';
        ctx.textAlign = 'left';
        ctx.fillText(d.total, x0 + barW + 6, y - rowH/2 + 8);
    });

    // Título eje
    ctx.fillStyle = '#9ca3af';
    ctx.font = '11px Arial';
    ctx.textAlign = 'left';
    ctx.fillText('Número de socios →', padLeft + labelW, 14);

    document.getElementById('mapaCalorMaxLabel').textContent = 'Máx: '+maxVal+' socios';
}

function exportarMapaExcel() {
    window.open('dashboard_mapa_calor_excel.php', '_blank');
}

function exportarMapaPDF() {
    // Guardar estado, imprimir solo la sección del mapa
    var style = document.createElement('style');
    style.id  = '__printStyle';
    style.innerHTML = '@media print { body > * { display:none!important; } .app { display:block!important; } .sidebar { display:none!important; } .content { display:block!important; overflow:visible!important; height:auto!important; } .topbar { display:none!important; } .page > *:not(#mapaCalorCard) { display:none!important; } #mapaCalorCard { display:block!important; box-shadow:none!important; } }';
    document.head.appendChild(style);
    window.print();
    setTimeout(function(){ var s=document.getElementById('__printStyle'); if(s) s.remove(); }, 1000);
}

window.addEventListener('resize', function(){
    if(_caloresDatos) renderCalor(_caloresVista);
});
</script>

<!-- Accesos Rápidos -->
<div class="chart-card">
    <div class="chart-header">
        <h3><i class="fa-solid fa-bolt"></i> Accesos Rápidos</h3>
    </div>
    <div class="quick-access-grid">
        <a href="socios_consulta.php" class="quick-access-btn"><i class="fa-solid fa-users"></i><span>Socios</span></a>
        <a href="lpa_consulta.php"    class="quick-access-btn"><i class="fa-solid fa-file-contract"></i><span>LPAs</span></a>
        <a href="ventas_consulta.php" class="quick-access-btn"><i class="fa-solid fa-cart-shopping"></i><span>Ventas</span></a>
        <a href="acuerdo_listado.php" class="quick-access-btn"><i class="fa-solid fa-handshake"></i><span>Acuerdos</span></a>
        <a href="periodos.php"        class="quick-access-btn"><i class="fa-solid fa-calendar-days"></i><span>Períodos</span></a>
    </div>
</div>

<?php endif; ?>
</section>
</main>
</div>

<script>
(function(){
    fetch('dashboard_genero_stats.php')
        .then(function(r){return r.json();})
        .then(function(d){
            if(!d.success) return;
            var total=d.total||0, muj=d.mujeres||0, hom=d.hombres||0, otros=d.otros||0;
            var pctM=total?((muj/total)*100).toFixed(1):0;
            var pctH=total?((hom/total)*100).toFixed(1):0;
            var pctO=total?((otros/total)*100).toFixed(1):0;
            document.getElementById('chartTotal').textContent    = total;
            document.getElementById('legendMujeres').textContent = muj+' socias ('+pctM+'%)';
            document.getElementById('legendHombres').textContent = hom+' socios ('+pctH+'%)';
            document.getElementById('legendOtros').textContent   = otros+' ('+pctO+'%)';
            dibujarDonut(muj, hom, otros, total);
        })
        .catch(function(){
            document.getElementById('chartTotal').textContent    = '?';
            document.getElementById('legendMujeres').textContent = 'Error';
            document.getElementById('legendHombres').textContent = 'Error';
            document.getElementById('legendOtros').textContent   = '';
        });
})();

function dibujarDonut(muj, hom, otros, total) {
    var canvas = document.getElementById('chartGenero');
    if (!canvas || !canvas.getContext) return;
    var ctx = canvas.getContext('2d');
    var cx=95, cy=95, r=82, ri=54;

    var segs = [
        {val:muj,   color:'#C0392B'},
        {val:hom,   color:'#1A5276'},
        {val:otros, color:'#9ca3af'}
    ].filter(function(s){ return s.val > 0; });

    if (!segs.length) {
        ctx.fillStyle='#e5e7eb';
        ctx.beginPath(); ctx.arc(cx,cy,r,0,Math.PI*2); ctx.fill();
        ctx.fillStyle='#fff';
        ctx.beginPath(); ctx.arc(cx,cy,ri,0,Math.PI*2); ctx.fill();
        return;
    }

    var startAngle = -Math.PI / 2;
    segs.forEach(function(s) {
        var slice = (s.val / total) * Math.PI * 2;
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, r, startAngle, startAngle + slice);
        ctx.closePath();
        ctx.fillStyle = s.color;
        ctx.fill();
        ctx.strokeStyle = '#fff';
        ctx.lineWidth   = 3;
        ctx.stroke();
        startAngle += slice;
    });

    // Hueco donut
    ctx.beginPath();
    ctx.arc(cx, cy, ri, 0, Math.PI * 2);
    ctx.fillStyle = '#fff';
    ctx.fill();
    ctx.beginPath();
    ctx.arc(cx, cy, ri, 0, Math.PI * 2);
    ctx.strokeStyle = 'rgba(0,0,0,0.04)';
    ctx.lineWidth   = 2;
    ctx.stroke();
}
</script>

</body>
</html>