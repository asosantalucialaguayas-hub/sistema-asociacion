<?php
// ============================================================
// descargar_conjunto.php – Descarga ZIP con convocatoria + acta
// ============================================================
if (session_status()===PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require __DIR__ . "/layout/bootstrap.php";

$id = intval($_GET['id']??0);
if (!$id) { header('Location: asistencia.php'); exit; }

$st = $pdo->prepare("SELECT * FROM convocatorias WHERE id=?");
$st->execute([$id]); $c = $st->fetch(PDO::FETCH_ASSOC);
if (!$c) { header('Location: asistencia.php'); exit; }

// Crear ZIP en memoria
$tmpZip = tempnam(sys_get_temp_dir(),'asoc_').'zip';
$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::CREATE) !== true) {
    die('No se pudo crear el ZIP');
}

// Agregar acta si existe
if ($c['acta_pdf_path'] && file_exists(__DIR__.'/'.$c['acta_pdf_path'])) {
    $nombre_acta = 'acta_'.date('d-m-Y',strtotime($c['fecha_reunion'])).'.pdf';
    $zip->addFile(__DIR__.'/'.$c['acta_pdf_path'], $nombre_acta);
}

// Agregar nota de texto con info de la convocatoria
$stP = $pdo->prepare("SELECT * FROM convocatoria_puntos WHERE convocatoria_id=? ORDER BY numero");
$stP->execute([$id]); $puntos = $stP->fetchAll(PDO::FETCH_ASSOC);

$txt = "CONVOCATORIA\n";
$txt .= str_repeat('=',50)."\n";
$txt .= "Título    : ".$c['titulo']."\n";
$txt .= "Fecha     : ".date('d/m/Y',strtotime($c['fecha_reunion']))."\n";
$txt .= "Hora      : ".substr($c['hora_reunion'],0,5)."\n";
$txt .= "Lugar     : ".$c['lugar']."\n";
$txt .= "Tipo      : ".ucfirst($c['tipo_reunion'])."\n";
$txt .= "Emitida   : ".$c['nombre_creador']."\n\n";
$txt .= "ORDEN DEL DÍA:\n";
foreach($puntos as $p) { $txt .= $p['numero'].". ".$p['descripcion']."\n"; }
$zip->addFromString('convocatoria_info.txt', $txt);

// Agregar reporte de asistencia (HTML a texto)
$stA = $pdo->prepare("
    SELECT s.cedula,s.nombre_completo,a.hora_registro,a.metodo
    FROM conv_asistencia a JOIN socios s ON s.id_socio=a.id_socio
    WHERE a.convocatoria_id=? ORDER BY s.nombre_completo");
$stA->execute([$id]); $asistentes = $stA->fetchAll(PDO::FETCH_ASSOC);

$csv = "Cedula,Nombre,Hora,Metodo\n";
foreach($asistentes as $a) {
    $csv .= '"'.$a['cedula'].'","'.$a['nombre_completo'].'","'.date('H:i:s',strtotime($a['hora_registro'])).'","'.$a['metodo'].'"'."\n";
}
$zip->addFromString('asistencia_'.date('d-m-Y',strtotime($c['fecha_reunion'])).'.csv', $csv);

$zip->close();

$nombre_zip = 'conv_'.preg_replace('/[^a-z0-9]/i','_',substr($c['titulo'],0,30)).'_'.date('d-m-Y',strtotime($c['fecha_reunion'])).'.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$nombre_zip.'"');
header('Content-Length: '.filesize($tmpZip));
header('Cache-Control: no-store');
readfile($tmpZip);
unlink($tmpZip);
exit;
