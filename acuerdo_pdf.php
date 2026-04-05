<?php
// SIN session_start ni includes de layout — cualquier output antes rompe el PDF
require "config/conexion.php";
require "vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;

$id = $_GET['id'] ?? null;
if (!$id) die("Acuerdo no válido");

$stmt = $pdo->prepare("SELECT * FROM acuerdo_productor WHERE id_acuerdo = ?");
$stmt->execute([$id]);
$acuerdo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$acuerdo) die("Acuerdo no encontrado");

$logoPath = __DIR__ . "/img/logo.png";
$logoSrc  = '';
if (file_exists($logoPath)) {
    $logoSrc = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
}

// Firmas digitales
$firmaPresidentePath = __DIR__ . "/img/firma_presidente.png";
$firmaSecretariaPath = __DIR__ . "/img/firma_secretaria.png";

$firmaPresidenteSrc = '';
if (file_exists($firmaPresidentePath)) {
    $firmaPresidenteSrc = 'data:image/png;base64,' . base64_encode(file_get_contents($firmaPresidentePath));
}

$firmaSecretariaSrc = '';
if (file_exists($firmaSecretariaPath)) {
    $firmaSecretariaSrc = 'data:image/png;base64,' . base64_encode(file_get_contents($firmaSecretariaPath));
}

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$fecha = !empty($acuerdo['fecha_firma']) ? date('d/m/Y', strtotime($acuerdo['fecha_firma'])) : '';
$estN  = isset($acuerdo['estimado_produccion_nacional']) ? $acuerdo['estimado_produccion_nacional'] : '';
if ($estN === '0' || $estN === 0 || $estN === '0.00' || $estN === 0.00 || $estN === null || trim($estN) === '') $estN = '';
$estC  = isset($acuerdo['estimado_produccion_ccn51']) ? $acuerdo['estimado_produccion_ccn51'] : '';
if ($estC === '0' || $estC === 0 || $estC === '0.00' || $estC === 0.00 || $estC === null || trim($estC) === '') $estC = '';
$riegoSi = ($acuerdo['posee_riego'] === 'SI' || $acuerdo['posee_riego'] === 'Si') ? 'X' : '&nbsp;&nbsp;';
$riegoNo = ($acuerdo['posee_riego'] === 'NO' || $acuerdo['posee_riego'] === 'No') ? 'X' : '&nbsp;&nbsp;';

$html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@page { margin: 2cm 2.5cm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.5; margin:0; padding:0; }
body::before { content:""; position:fixed; top:50%; left:50%; width:400px; height:400px; margin-left:-200px; margin-top:-200px; background-image:url("'.$logoSrc.'"); background-size:contain; background-position:center; background-repeat:no-repeat; opacity:0.08; z-index:-1; }
.header-title { text-align:center; font-weight:bold; font-size:12px; text-decoration:underline; margin-bottom:20px; margin-top:0; }
p { text-align:justify; margin:0 0 10px 0; line-height:1.6; }
.info-line { margin:8px 0; }
.section-title { font-weight:bold; font-size:11px; margin-top:15px; margin-bottom:10px; }
ol { margin:8px 0; padding-left:25px; }
ol li { margin-bottom:8px; text-align:justify; line-height:1.6; }
.table-data { width:100%; margin:15px 0; }
.table-data td { padding:5px 0; vertical-align:top; }
.firma-section { margin-top:150px; text-align:center; }
.firma-line-main { width:300px; border-top:1.5px solid #000; margin:0 auto 15px auto; }
.firma-text { font-weight:bold; font-size:11px; margin-bottom:80px; }
.firma-container { display:table; width:100%; margin-top:60px; }
.firma-col { display:table-cell; width:50%; text-align:center; vertical-align:top; }
.firma-line { width:70%; border-top:1px solid #000; margin:0 auto 10px auto; }
.firma-name { font-weight:bold; font-size:10.5px; }
.firma-cargo { font-size:10px; line-height:1.3; }
</style></head><body>
<div class="header-title">ACUERDO DE PRODUCTOR</div>
<p>Por parte del Sr. Rosendo Muñoz Benavides presidente de la, Aso Santa Lucia Corotú, dentro de lo cual el presente documento es prueba que el productor ha sido informado de los objetivos y metas de la Aso. Santa Lucia Corotú en forma que pueda decir y comunicar su interés en participar en el programa de comercio justo que la Aso. Santa Lucia Corotú está en capacidad de cumplir, y hacer cumplir los deberes y beneficios correspondientes.</p>
<div class="info-line">De otra parte, el (a): <strong>'.htmlspecialchars($acuerdo['nombres_completos']).'</strong></div>
<div class="info-line">Con Cédula #: <strong>'.htmlspecialchars($acuerdo['cedula']).'</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; fecha de nacimiento: <strong>'.htmlspecialchars($acuerdo['fecha_nacimiento']).'</strong></div>
<p>Quien confirma que ha sido informado, sin compromiso previo establecido entre las partes, quien es el propietario de la finca ubicada en el <strong>'.htmlspecialchars($acuerdo['sector']).'</strong>, de <strong>'.htmlspecialchars($acuerdo['parroquia']).'</strong> cantón <strong>'.htmlspecialchars($acuerdo['canton']).'</strong></p>
<p>En la provincia: <strong>'.htmlspecialchars($acuerdo['provincia']).'</strong>, con un área cultivada de cacao y un estimado de producción detallado a continuación:</p>
<table class="table-data">
<tr><td style="width:30%">Cacao Nacional</td><td style="width:15%"><strong>'.htmlspecialchars($acuerdo['cacao_nacional_has']).' Ha</strong></td><td style="width:35%">Estimado de producción</td><td style="width:20%"><strong>'.htmlspecialchars($estN).' QQ</strong></td></tr>
<tr><td>Cacao CCN51</td><td><strong>'.htmlspecialchars($acuerdo['cacao_ccn51_has']).' Ha</strong></td><td>Estimado de producción</td><td><strong>'.htmlspecialchars($estC).' QQ</strong></td></tr>
<tr><td>Posee riego si ( '.$riegoSi.' ) o no ( '.$riegoNo.' )</td><td colspan="2">Periodo de Fertilización por año</td><td><strong>'.htmlspecialchars($acuerdo['periodo_de_fertilizacion']).'</strong></td></tr>
</table>
<div class="section-title">OBLIGACIONES DE LAS PARTES</div>
<p><strong>La Asociación se compromete a lo siguiente:</strong></p>
<ol>
<li>Establecer un sistema interno de control para el manejo agronómico, cosecha y transporte del cacao certificado, entregándole al productor los materiales para el registro de todas las actividades según formato establecido.</li>
<li>Dar Asistencia Técnica necesaria para uso racional de recursos naturales y crear condiciones para mejorar las fincas de sus socios.</li>
<li>La Asociación se compromete en desarrollar proyectos productivos de manera equitativa con los ingresos por prima Fairtrade.</li>
<li>La organización se compromete en dar a conocer y hacer cumplir los criterios Fairtrade para pequeños productores.</li>
</ol>
<p><strong>El productor se compromete a lo siguiente:</strong></p>
<ol>
<li>De forma voluntaria aceptar que la asociación me incluya en la lista de productores a trabajar bajo la certificación Fairtrade.</li>
<li>Cumplir integra y responsable con el reglamento interno de la asociación.</li>
<li>Notificar si el caso lo requiere la adquisición de nuevas unidades de producción y acatar o apelar sanciones correspondientes.</li>
<li>En caso de contratar personal, garantizar el cumplimiento de las normas, leyes, reglamentos y convenios internacionales vigentes.</li>
<li>Vender exclusivamente a nuestro socio comercial el cacao certificado inscrito para la certificación de comercio justo.</li>
<li>Permitir el acceso a su finca y todas las áreas bajo su administración al personal debidamente identificado.</li>
<li>Cuidar áreas protegidas, mantener y proteger las especies (flora y fauna) si hubiese, en la finca y su entorno.</li>
<li>Mantener libres de cualquier tipo de contaminación o basura, su finca y la comunidad donde residen.</li>
<li>Tiene la potestad de apelar y expresar sus desacuerdos a través de los mecanismos establecidos para tales fines.</li>
<li>El productor se compromete a cumplir con las normativas internacionales como el estándar Fairtrade, debida diligencia de los derechos humanos y ambientales (deforestación cero) y otras exigencias nacionales e internacionales que se estipulen.</li>
<li>El productor se compromete en dar permiso a la organización para recopilar, almacenar y compartir datos.</li>
<li>El productor se compromete a que su finca sea físicamente rastreable.</li>
<li>El productor se compromete con la asociación a desarrollar sus cultivos sin la utilización de la deforestación, e implementa acciones que permiten la conservación del medio ambiente.</li>
</ol>
<div class="section-title">VIGENCIA DEL ACUERDO</div>
<p>El presente acuerdo tiene una vigencia de (1) año con una renovación en mutuo acuerdo entre ambas partes.</p>
<p style="margin-top:20px;">Fecha de firma del Acuerdo: <strong>'.htmlspecialchars($fecha).'</strong></p>
<div class="firma-section">
<div class="firma-line-main"></div>
<div class="firma-text">FIRMA DEL PRODUCTOR</div>
<div class="firma-container">
<div class="firma-col">' .
    ($firmaPresidenteSrc ? '<img src="' . $firmaPresidenteSrc . '" style="max-width:180px;max-height:60px;margin-bottom:8px;">' : '') . '
    <div class="firma-line"></div>
    <div class="firma-name">Ing. Rosendo Muñoz</div>
    <div class="firma-cargo">Presidente Aso. SANTA LUCIA COROTU</div>
</div>
<div class="firma-col">' .
    ($firmaSecretariaSrc ? '<img src="' . $firmaSecretariaSrc . '" style="max-width:180px;max-height:60px;margin-bottom:8px;">' : '') . '
    <div class="firma-line"></div>
    <div class="firma-name">Ing. Jean Carlos Ponce</div>
    <div class="firma-cargo">Secretario Aso. SANTA LUCIA COROTU</div>
</div>
</div>
</div>
</body></html>';

while (ob_get_level()) ob_end_clean();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Acuerdo_" . $acuerdo['numero_acuerdo'] . ".pdf", ["Attachment" => false]);
exit;