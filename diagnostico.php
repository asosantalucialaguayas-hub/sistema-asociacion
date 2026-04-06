<?php
// ============================================================
// diagnostico.php  – Ejecutar UNA SOLA VEZ para ver errores
// Subir a /asosantalu/diagnostico.php y abrir en el navegador
// BORRAR DESPUÉS DE USAR
// ============================================================
if (session_status()===PHP_SESSION_NONE) session_start();

// Carga tu conexión igual que el resto del sistema
$boot = __DIR__ . "/layout/bootstrap.php";
$boot_ok = file_exists($boot);
if ($boot_ok) {
    try {
        require $boot;
        $boot_error = null;
    } catch(Throwable $e) {
        $boot_error = $e->getMessage();
        $pdo = null;
    }
} else {
    $boot_error = "No se encontró layout/bootstrap.php";
    $pdo = null;
}

function tabla($pdo, $nombre) {
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `$nombre`")->fetchAll(PDO::FETCH_ASSOC);
        return $cols;
    } catch(Exception $e) {
        return ['_error' => $e->getMessage()];
    }
}

function existe($pdo, $nombre) {
    try {
        $r = $pdo->query("SHOW TABLES LIKE '$nombre'")->fetchAll();
        return count($r) > 0;
    } catch(Exception $e) { return false; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Diagnóstico – Asociación</title>
<style>
body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:30px;font-size:13px;}
h1{color:#38bdf8;font-size:1.4rem;margin-bottom:20px;}
h2{color:#818cf8;margin:24px 0 8px;font-size:1rem;}
.ok  {color:#4ade80;font-weight:bold;}
.err {color:#f87171;font-weight:bold;}
.warn{color:#fbbf24;font-weight:bold;}
table{border-collapse:collapse;margin-bottom:16px;width:100%;max-width:700px;}
th{background:#1e3a5f;color:#93c5fd;padding:6px 12px;text-align:left;}
td{padding:5px 12px;border-bottom:1px solid #1e293b;}
td:first-child{color:#67e8f9;}
pre{background:#1e293b;padding:14px;border-radius:8px;overflow-x:auto;color:#86efac;max-width:900px;}
.box{background:#1e293b;border-radius:10px;padding:16px;margin-bottom:16px;max-width:800px;}
</style>
</head>
<body>
<h1>🔍 Diagnóstico del Sistema</h1>

<!-- ── 1. bootstrap.php ─────────────────────────────────── -->
<h2>1. layout/bootstrap.php</h2>
<div class="box">
<?php if (!$boot_ok): ?>
    <span class="err">❌ Archivo NO encontrado: <?= htmlspecialchars($boot) ?></span>
<?php elseif ($boot_error): ?>
    <span class="err">❌ Error al cargar bootstrap.php:<br><?= htmlspecialchars($boot_error) ?></span>
<?php else: ?>
    <span class="ok">✅ Cargado correctamente</span><br><br>
    <b>Variables disponibles:</b><br>
    $pdo: <?= isset($pdo) ? '<span class="ok">✅ Disponible</span>' : '<span class="err">❌ NO definido</span>' ?><br>
    $periodoSeleccionado: <?= isset($periodoSeleccionado) ? '<span class="ok">✅ Disponible → '.htmlspecialchars(json_encode($periodoSeleccionado)).'</span>' : '<span class="warn">⚠️ NO definido (necesario para asistencia.php)</span>' ?><br>
    $_SESSION[usuario]: <?= isset($_SESSION['usuario']) ? '<span class="ok">✅ '.htmlspecialchars($_SESSION['usuario']).'</span>' : '<span class="warn">⚠️ No hay sesión activa</span>' ?><br>
    $_SESSION[rol]: <?= isset($_SESSION['rol']) ? '<span class="ok">✅ '.htmlspecialchars($_SESSION['rol']).'</span>' : '<span class="warn">⚠️ No definido</span>' ?><br>
    $_SESSION[id_usuario]: <?= isset($_SESSION['id_usuario']) ? '<span class="ok">✅ '.$_SESSION['id_usuario'].'</span>' : '<span class="warn">⚠️ No definido</span>' ?>
<?php endif; ?>
</div>

<?php if (isset($pdo)): ?>

<!-- ── 2. Tablas existentes ─────────────────────────────── -->
<h2>2. Tablas del módulo (¿existen?)</h2>
<div class="box">
<?php
$tablas_modulo = ['convocatorias','convocatoria_puntos','convocatoria_firmas','conv_asistencia','biometrico_log'];
foreach($tablas_modulo as $t):
    $ex = existe($pdo,$t);
?>
    <?= $ex ? '<span class="ok">✅</span>' : '<span class="err">❌ FALTA</span>' ?>
    <b><?= $t ?></b><br>
<?php endforeach; ?>
</div>

<!-- ── 3. Columnas de socios ────────────────────────────── -->
<h2>3. Columnas de la tabla <code>socios</code> (las más importantes)</h2>
<div class="box">
<?php
$cols_socios = tabla($pdo,'socios');
if (isset($cols_socios['_error'])): ?>
    <span class="err">❌ Error: <?= htmlspecialchars($cols_socios['_error']) ?></span>
<?php else: ?>
<table>
    <tr><th>Campo</th><th>Tipo</th><th>¿Null?</th><th>Default</th></tr>
    <?php foreach($cols_socios as $col): ?>
    <tr>
        <td><?= htmlspecialchars($col['Field']) ?></td>
        <td><?= htmlspecialchars($col['Type']) ?></td>
        <td><?= $col['Null'] ?></td>
        <td><?= htmlspecialchars($col['Default']??'') ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<br>
<?php
// Detectar campos clave
$campos = array_column($cols_socios,'Field');
$tiene_nombre_completo = in_array('nombre_completo',$campos);
$tiene_id_socio        = in_array('id_socio',$campos);
$tiene_cedula          = in_array('cedula',$campos);
$tiene_estado          = in_array('estado',$campos);
?>
<b>Revisión campos clave que usa asistencia.php:</b><br>
nombre_completo: <?= $tiene_nombre_completo ? '<span class="ok">✅ existe</span>' : '<span class="err">❌ NO existe — busca el nombre real arriba</span>' ?><br>
id_socio:        <?= $tiene_id_socio        ? '<span class="ok">✅ existe</span>' : '<span class="err">❌ NO existe — busca el nombre real arriba</span>' ?><br>
cedula:          <?= $tiene_cedula          ? '<span class="ok">✅ existe</span>' : '<span class="err">❌ NO existe</span>' ?><br>
estado:          <?= $tiene_estado          ? '<span class="ok">✅ existe</span>' : '<span class="err">❌ NO existe</span>' ?>
<?php endif; ?>
</div>

<!-- ── 4. Columnas de convocatorias ─────────────────────── -->
<h2>4. Columnas de <code>convocatorias</code> (si ya existe)</h2>
<div class="box">
<?php
$cols_conv = tabla($pdo,'convocatorias');
if (isset($cols_conv['_error'])): ?>
    <span class="warn">⚠️ La tabla no existe todavía o hay error: <?= htmlspecialchars($cols_conv['_error']) ?></span>
<?php else: ?>
<table>
    <tr><th>Campo</th><th>Tipo</th></tr>
    <?php foreach($cols_conv as $col): ?>
    <tr><td><?= htmlspecialchars($col['Field']) ?></td><td><?= htmlspecialchars($col['Type']) ?></td></tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
</div>

<!-- ── 5. Tabla periodos ─────────────────────────────────── -->
<h2>5. Columnas de <code>periodos</code></h2>
<div class="box">
<?php
$cols_per = tabla($pdo,'periodos');
if (isset($cols_per['_error'])): ?>
    <span class="err">❌ <?= htmlspecialchars($cols_per['_error']) ?></span>
<?php else: ?>
<table>
    <tr><th>Campo</th><th>Tipo</th></tr>
    <?php foreach($cols_per as $col): ?>
    <tr><td><?= htmlspecialchars($col['Field']) ?></td><td><?= htmlspecialchars($col['Type']) ?></td></tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
</div>

<!-- ── 6. Último error PHP en log ─────────────────────────── -->
<h2>6. Último error de PHP (error_log del servidor)</h2>
<div class="box">
<?php
$posibles_logs = [
    '/var/log/apache2/error.log',
    '/var/log/nginx/error.log',
    ini_get('error_log'),
    __DIR__.'/../logs/error_log',
    __DIR__.'/../../logs/error_log',
];
$encontrado = false;
foreach($posibles_logs as $log) {
    if ($log && file_exists($log) && is_readable($log)) {
        $lines = array_slice(file($log), -20);
        echo '<span class="ok">Log encontrado: '.htmlspecialchars($log).'</span><br>';
        echo '<pre>'.htmlspecialchars(implode('',$lines)).'</pre>';
        $encontrado = true;
        break;
    }
}
if (!$encontrado) {
    echo '<span class="warn">⚠️ No se pudo leer el log del servidor. Revisa en cPanel → Logs de errores.</span>';
}
?>
</div>

<!-- ── 7. Test consulta socios ───────────────────────────── -->
<h2>7. Test: obtener 3 socios activos</h2>
<div class="box">
<?php
try {
    $test = $pdo->query("SELECT * FROM socios WHERE estado='activo' LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    if ($test) {
        echo '<span class="ok">✅ Consulta OK — '.count($test).' resultado(s)</span><br><br>';
        echo '<b>Columnas disponibles en socios:</b><br>';
        echo '<pre>'.htmlspecialchars(json_encode(array_keys($test[0]),JSON_PRETTY_PRINT)).'</pre>';
    } else {
        echo '<span class="warn">⚠️ La tabla socios está vacía o no hay activos</span>';
    }
} catch(Exception $e) {
    echo '<span class="err">❌ Error: '.htmlspecialchars($e->getMessage()).'</span>';
}
?>
</div>

<!-- ── 8. Resumen y acciones ─────────────────────────────── -->
<h2>8. Resumen — ¿Qué hacer?</h2>
<div class="box">
<b>Basado en el diagnóstico, ajustes necesarios en asistencia.php y ajax_buscar_socio.php:</b><br><br>
<?php
$acciones = [];

if (!$tiene_nombre_completo) {
    $candidatos = array_filter($campos, fn($f) => stripos($f,'nombre')!==false || stripos($f,'apellido')!==false);
    $acciones[] = '❌ Reemplaza <code>nombre_completo</code> por: <b>'.htmlspecialchars(implode(', ', $candidatos)).'</b>';
}
if (!$tiene_id_socio) {
    $candidatos = array_filter($campos, fn($f) => stripos($f,'id')!==false);
    $acciones[] = '❌ Reemplaza <code>id_socio</code> por: <b>'.htmlspecialchars(implode(', ', $candidatos)).'</b>';
}
if (!$tiene_cedula) {
    $acciones[] = '❌ La tabla socios no tiene campo <code>cedula</code> — busca el nombre real';
}
if (!isset($periodoSeleccionado)) {
    $acciones[] = '⚠️ <code>$periodoSeleccionado</code> no está definido en bootstrap.php — asistencia.php no podrá cargar convocatorias por período';
}

if (empty($acciones)) {
    echo '<span class="ok">✅ Todo parece correcto. El error 500 puede ser otro (ver log arriba).</span>';
} else {
    foreach($acciones as $a) echo '<div style="margin-bottom:8px;">'.$a.'</div>';
}
?>
</div>

<?php endif; // pdo disponible ?>

<br><div style="background:#1e293b;border-radius:8px;padding:12px 16px;color:#fbbf24;max-width:600px;">
    ⚠️ <b>IMPORTANTE:</b> Borra este archivo del servidor después de usarlo.<br>
    <code>rm /ruta/asosantalu/diagnostico.php</code>
</div>
</body>
</html>
