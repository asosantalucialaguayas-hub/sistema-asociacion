<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}
require "config/conexion.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Estimación de Producción</title>

<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.btn-actions{display:flex;gap:10px;margin-bottom:15px}
.btn-secondary{background:#1f3a5f;color:#fff;padding:10px 18px;border-radius:8px;border:none;font-weight:600;cursor:pointer}
.btn-secondary:hover{background:#16304d}

.data-table{width:100%;border-collapse:collapse;font-size:12px;overflow-x:auto}
.data-table thead th{background:#1f3a5f;color:#fff;padding:10px 8px;text-align:center;white-space:nowrap}
.data-table td{padding:8px 6px;border-bottom:1px solid #e5e7eb;text-align:center}
.data-table tbody tr:hover{background:#f9fafb}

.table-container{overflow-x:auto;margin-top:15px}

/* Responsive */
@media (max-width: 1400px) {
    .data-table{font-size:11px}
    .data-table thead th{padding:8px 6px}
    .data-table td{padding:6px 4px}
}
</style>
</head>

<body>
<div class="app">
<?php include __DIR__."/layout/sidebar.php"; ?>

<main class="content">
<header class="topbar">
    <span>Bienvenido, <?= $_SESSION['usuario'] ?></span>
</header>

<section class="page">
<h1>Estimación de Producción</h1>

<div class="btn-actions">
    <button class="btn-secondary" onclick="exportarExcel()">
        <i class="fa fa-file-excel"></i> Exportar a Excel
    </button>
</div>

<div class="form-card">
<div class="table-container">
<table class="data-table" id="tablaEstimacion">
<thead>
<tr>
    <th>N°</th>
    <th>Zona</th>
    <th>Comunidad/Grupo</th>
    <th>Cédula</th>
    <th>Productor/a</th>
    <th>Sexo</th>
    <th>Celular</th>
    <th>Vol. Producción</th>
    <th>Enero</th>
    <th>Febrero</th>
    <th>Marzo</th>
    <th>Abril</th>
    <th>Mayo</th>
    <th>Junio</th>
    <th>Julio</th>
    <th>Agosto</th>
    <th>Septiembre</th>
    <th>Octubre</th>
    <th>Noviembre</th>
    <th>Diciembre</th>
</tr>
</thead>
<tbody id="cuerpoTablaEstimacion">
    <!-- Cargado por JavaScript -->
</tbody>
<tfoot>
<tr style="background:#f3f4f6;font-weight:bold">
    <td colspan="7" style="text-align:right;padding-right:10px">TOTAL:</td>
    <td id="totalProduccion">0</td>
    <td id="totalEnero">0</td>
    <td id="totalFebrero">0</td>
    <td id="totalMarzo">0</td>
    <td id="totalAbril">0</td>
    <td id="totalMayo">0</td>
    <td id="totalJunio">0</td>
    <td id="totalJulio">0</td>
    <td id="totalAgosto">0</td>
    <td id="totalSeptiembre">0</td>
    <td id="totalOctubre">0</td>
    <td id="totalNoviembre">0</td>
    <td id="totalDiciembre">0</td>
</tr>
</tfoot>
</table>
</div>
</div>

</section>
</main>
</div>

<script>
window.onload = function() {
    cargarEstimacion();
};

function cargarEstimacion() {
    fetch('estimacion_obtener.php')
        .then(r => r.json())
        .then(datos => {
            const tbody = document.getElementById('cuerpoTablaEstimacion');
            tbody.innerHTML = '';
            
            let totales = {
                produccion: 0,
                enero: 0, febrero: 0, marzo: 0, abril: 0,
                mayo: 0, junio: 0, julio: 0, agosto: 0,
                septiembre: 0, octubre: 0, noviembre: 0, diciembre: 0
            };
            
            datos.forEach((row, idx) => {
                const nombreCompleto = row.nombre_completo || (row.nombres + ' ' + row.apellidos) || '-';
                
                // Sumar totales
                totales.produccion += parseFloat(row.volumen_produccion_estimado || 0);
                totales.enero += parseFloat(row.enero || 0);
                totales.febrero += parseFloat(row.febrero || 0);
                totales.marzo += parseFloat(row.marzo || 0);
                totales.abril += parseFloat(row.abril || 0);
                totales.mayo += parseFloat(row.mayo || 0);
                totales.junio += parseFloat(row.junio || 0);
                totales.julio += parseFloat(row.julio || 0);
                totales.agosto += parseFloat(row.agosto || 0);
                totales.septiembre += parseFloat(row.septiembre || 0);
                totales.octubre += parseFloat(row.octubre || 0);
                totales.noviembre += parseFloat(row.noviembre || 0);
                totales.diciembre += parseFloat(row.diciembre || 0);
                
                tbody.innerHTML += `
                    <tr>
                        <td>${idx+1}</td>
                        <td>${row.zona || '-'}</td>
                        <td>${row.comunidad_grupo || '-'}</td>
                        <td>${row.identificacion || '-'}</td>
                        <td style="text-align:left">${nombreCompleto}</td>
                        <td>${row.sexo || '-'}</td>
                        <td>${row.telefono || '-'}</td>
                        <td>${parseFloat(row.volumen_produccion_estimado || 0).toFixed(2)}</td>
                        <td>${parseFloat(row.enero || 0).toFixed(2)}</td>
                        <td>${parseFloat(row.febrero || 0).toFixed(2)}</td>
                        <td>${parseFloat(row.marzo || 0).toFixed(2)}</td>
                        <td>${parseFloat(row.abril || 0).toFixed(2)}</td>
                        <td>${parseFloat(row.mayo || 0).toFixed(2)}</td>
                        <td>${parseFloat(row.junio || 0).toFixed(2)}</td>
                        <td>${parseFloat(row.julio || 0).toFixed(2)}</td>
                        <td>${parseFloat(row.agosto || 0).toFixed(2)}</td>
                        <td>${parseFloat(row.septiembre || 0).toFixed(2)}</td>
                        <td>${parseFloat(row.octubre || 0).toFixed(2)}</td>
                        <td>${parseFloat(row.noviembre || 0).toFixed(2)}</td>
                        <td>${parseFloat(row.diciembre || 0).toFixed(2)}</td>
                    </tr>
                `;
            });
            
            // Actualizar totales
            document.getElementById('totalProduccion').textContent = totales.produccion.toFixed(2);
            document.getElementById('totalEnero').textContent = totales.enero.toFixed(2);
            document.getElementById('totalFebrero').textContent = totales.febrero.toFixed(2);
            document.getElementById('totalMarzo').textContent = totales.marzo.toFixed(2);
            document.getElementById('totalAbril').textContent = totales.abril.toFixed(2);
            document.getElementById('totalMayo').textContent = totales.mayo.toFixed(2);
            document.getElementById('totalJunio').textContent = totales.junio.toFixed(2);
            document.getElementById('totalJulio').textContent = totales.julio.toFixed(2);
            document.getElementById('totalAgosto').textContent = totales.agosto.toFixed(2);
            document.getElementById('totalSeptiembre').textContent = totales.septiembre.toFixed(2);
            document.getElementById('totalOctubre').textContent = totales.octubre.toFixed(2);
            document.getElementById('totalNoviembre').textContent = totales.noviembre.toFixed(2);
            document.getElementById('totalDiciembre').textContent = totales.diciembre.toFixed(2);
        })
        .catch(err => console.error('Error:', err));
}

function exportarExcel() {
    window.location.href = 'estimacion_exportar.php';
}
</script>
</body>
</html>