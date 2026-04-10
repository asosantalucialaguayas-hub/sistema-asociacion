<?php
// ============================================================
// ver_log.php  – Ver el log del biométrico ISUP
// Subir en: /asosantalu/ver_log.php
// BORRAR después de terminar las pruebas
// ============================================================
if (session_status()===PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }

$log_file = __DIR__ . '/logs/isup_' . date('Y-m-d') . '.log';
$contenido = file_exists($log_file) ? file_get_contents($log_file) : null;
$lineas    = $contenido ? array_reverse(array_filter(explode("\n", trim($contenido)))) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta http-equiv="refresh" content="5">
<title>Log Biométrico ISUP</title>
<style>
body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:24px;margin:0;}
h2{color:#38bdf8;margin:0 0 6px;}
.sub{color:#64748b;font-size:12px;margin-bottom:20px;}
.status{display:inline-flex;align-items:center;gap:8px;background:#1e293b;border-radius:8px;padding:10px 16px;margin-bottom:20px;font-size:13px;}
.dot-ok  {width:10px;height:10px;border-radius:50%;background:#22c55e;animation:pulse 1.5s infinite;}
.dot-warn{width:10px;height:10px;border-radius:50%;background:#f59e0b;}
.dot-err {width:10px;height:10px;border-radius:50%;background:#ef4444;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
.linea{background:#1e293b;border-radius:6px;padding:10px 14px;margin-bottom:8px;font-size:12px;border-left:3px solid #334155;line-height:1.6;}
.linea.ok   {border-color:#22c55e;color:#86efac;}
.linea.ya   {border-color:#f59e0b;color:#fcd34d;}
.linea.err  {border-color:#ef4444;color:#fca5a5;}
.linea.keep {border-color:#334155;color:#64748b;}
.vacio{text-align:center;padding:40px;color:#475569;}
.vacio .icono{font-size:40px;margin-bottom:12px;}
.badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:bold;margin-left:8px;}
.badge-ok  {background:#166534;color:#86efac;}
.badge-ya  {background:#78350f;color:#fcd34d;}
.badge-err {background:#7f1d1d;color:#fca5a5;}
.badge-keep{background:#1e293b;color:#64748b;}
.btn{display:inline-flex;align-items:center;gap:6px;background:#1e3a5f;color:#93c5fd;border:none;border-radius:8px;padding:8px 16px;font-size:13px;cursor:pointer;text-decoration:none;margin-right:8px;}
.btn:hover{background:#1d4ed8;}
</style>
</head>
<body>

<h2>Monitor Biométrico ISUP</h2>
<div class="sub">Se actualiza cada 5 segundos · Archivo: logs/isup_<?= date('Y-m-d') ?>.log · <?= date('H:i:s') ?></div>

<!-- Estado del archivo -->
<?php if (!$contenido): ?>
<div class="status">
    <div class="dot-warn"></div>
    <span>Log vacío — el biométrico aún no ha enviado ningún evento hoy</span>
</div>
<?php else: ?>
<div class="status">
    <div class="dot-ok"></div>
    <span>Recibiendo datos · <?= count($lineas) ?> evento(s) registrado(s) hoy</span>
</div>
<?php endif; ?>

<a href="ver_log.php" class="btn">↺ Actualizar ahora</a>
<a href="ver_log.php?borrar=1" class="btn" onclick="return confirm('¿Borrar el log de hoy?')" style="background:#7f1d1d;color:#fca5a5;">Borrar log</a>

<br><br>

<?php
// Borrar log si se pide
if (isset($_GET['borrar']) && file_exists($log_file)) {
    unlink($log_file);
    echo '<meta http-equiv="refresh" content="0;url=ver_log.php">';
    exit;
}
?>

<?php if (empty($lineas)): ?>
<div class="vacio">
    <div class="icono">&#128268;</div>
    <div>Esperando eventos del biométrico...</div>
    <div style="color:#334155;font-size:12px;margin-top:8px;">
        Pasa una huella en el dispositivo y este log debería actualizarse en segundos
    </div>
</div>
<?php else: ?>

<?php foreach ($lineas as $linea):
    if (empty(trim($linea))) continue;

    // Clasificar la línea
    $clase  = 'keep';
    $badge  = '';
    if (str_contains($linea, 'REGISTRADO:'))   { $clase='ok';  $badge='<span class="badge badge-ok">REGISTRADO</span>'; }
    elseif (str_contains($linea, 'YA EXISTIA')){ $clase='ya';  $badge='<span class="badge badge-ya">YA REGISTRADO</span>'; }
    elseif (str_contains($linea, 'NO ENCONTRADO')){ $clase='err'; $badge='<span class="badge badge-err">SOCIO NO ENCONTRADO</span>'; }
    elseif (str_contains($linea, 'SIN SESION')){ $clase='err'; $badge='<span class="badge badge-err">SIN REUNIÓN ACTIVA</span>'; }
    elseif (str_contains($linea, 'ERROR'))      { $clase='err'; $badge='<span class="badge badge-err">ERROR</span>'; }
    elseif (str_contains($linea, 'keepalive')) { $clase='keep';$badge='<span class="badge badge-keep">KEEPALIVE</span>'; }
    elseif (str_contains($linea, 'BODY:'))     { $clase='keep';$badge='<span class="badge badge-keep">RAW</span>'; }
?>
<div class="linea <?= $clase ?>">
    <?= $badge ?>
    <?= htmlspecialchars($linea) ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

</body>
</html>
