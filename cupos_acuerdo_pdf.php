<?php
require "config/conexion.php";
require "vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;

$id_lpa   = intval($_GET['id_lpa']   ?? 0);
$id_socio = intval($_GET['id_socio'] ?? 0);

if (!$id_lpa && !$id_socio) die("Parámetros inválidos");

// ── 1. TABLA_LPA + SOCIOS ─────────────────────────────────────────────────────
$sql = "
    SELECT
        l.id_lpa, l.id_socio, l.zona, l.comunidad_grupo,
        l.area_total_ha, l.area_cacao_ha,
        l.volumen_produccion_estimado,
        l.anio, l.adendum, l.estado_lpa,
        s.identificacion,
        COALESCE(s.nombre_completo, CONCAT(s.nombres, ' ', s.apellidos)) AS nombre_completo,
        s.fecha_nacimiento
    FROM tabla_lpa l
    LEFT JOIN socios s ON s.id_socio = l.id_socio
    WHERE l.id_lpa = :id_lpa
    LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':id_lpa', $id_lpa, PDO::PARAM_INT);
$stmt->execute();
$lpa = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$lpa) die("LPA no encontrado");

$cedula_lpa = $lpa['identificacion'] ?? '';

// ── 2. ACUERDO_PRODUCTOR ──────────────────────────────────────────────────────
// FIX: busca por id_socio primero, si no encuentra busca por cedula
// Así funciona tanto para socios nuevos como para los anteriores
$ac = null;

// Intento 1: por id_socio (socios con acuerdo bien vinculado)
if (!empty($lpa['id_socio'])) {
    $stAcuerdo = $pdo->prepare("
        SELECT * FROM acuerdo_productor
        WHERE id_socio = :id_socio
        ORDER BY id_acuerdo DESC
        LIMIT 1
    ");
    $stAcuerdo->bindValue(':id_socio', $lpa['id_socio'], PDO::PARAM_INT);
    $stAcuerdo->execute();
    $ac = $stAcuerdo->fetch(PDO::FETCH_ASSOC);
}

// Intento 2: por cedula (acuerdos sin id_socio o socios viejos)
if (!$ac && !empty($cedula_lpa)) {
    $stAcuerdo = $pdo->prepare("
        SELECT * FROM acuerdo_productor
        WHERE cedula = :cedula
        ORDER BY id_acuerdo DESC
        LIMIT 1
    ");
    $stAcuerdo->bindValue(':cedula', $cedula_lpa, PDO::PARAM_STR);
    $stAcuerdo->execute();
    $ac = $stAcuerdo->fetch(PDO::FETCH_ASSOC);
}

// ── 3. DATOS PERSONALES ───────────────────────────────────────────────────────
$nombres_completos =  $lpa['nombre_completo'] ?? '';
$cedula            = $lpa['identificacion']   ?? '';
$fecha_nacimiento  = $lpa['fecha_nacimiento'] ?? '';

// ── 4. UBICACIÓN ──────────────────────────────────────────────────────────────
// FIX: sin valores por defecto inventados — si no hay dato, queda vacío
$provincia = $ac['provincia'] ?? '';
$canton    = $ac['canton']    ?? '';
$parroquia = $ac['parroquia'] ?? '';
$sector    = $ac['sector']    ?? (!empty($lpa['comunidad_grupo']) ? $lpa['comunidad_grupo'] : ($lpa['zona'] ?? ''));

// ── 5. RIEGO Y FERTILIZACIÓN ──────────────────────────────────────────────────
$posee_riego  = $ac['posee_riego']              ?? 'NO';
$periodo_fert = $ac['periodo_de_fertilizacion'] ?? '';

// ── 6. NÚMERO DE ACUERDO Y FECHA FIRMA ───────────────────────────────────────
$numero_acuerdo = $ac['numero_acuerdo'] ?? 'LPA-' . str_pad($id_lpa, 4, '0', STR_PAD_LEFT);
$fecha_firma    = $ac['fecha_firma']    ?? date('Y-m-d');

// ── 7. CACAO Y CUPO ───────────────────────────────────────────────────────────
$ha_nacional   = floatval($ac['cacao_nacional_has']  ?? 0);
$ha_ccn51      = floatval($ac['cacao_ccn51_has']     ?? 0);
$cupo_asignado = floatval($lpa['volumen_produccion_estimado'] ?? 0);

$tiene_nacional = $ha_nacional > 0;
$tiene_ccn51    = $ha_ccn51    > 0;
$tiene_ambos    = $tiene_nacional && $tiene_ccn51;

if ($tiene_ambos) {
    $est_nacional = floatval($ac['estimado_produccion_nacional'] ?? 0);
    $est_ccn51    = floatval($ac['estimado_produccion_ccn51']    ?? 0);
    if ($est_nacional == 0 && $est_ccn51 == 0) {
        $est_nacional = $cupo_asignado;
        $est_ccn51    = 0;
    }
} elseif ($tiene_ccn51) {
    $est_nacional = 0;
    $est_ccn51    = $cupo_asignado;
} else {
    $ha_nacional  = $ha_nacional > 0 ? $ha_nacional : floatval($lpa['area_cacao_ha'] ?? 0);
    $est_nacional = $cupo_asignado;
    $est_ccn51    = 0;
}

// ── 8. FORMATEAR NÚMEROS ──────────────────────────────────────────────────────
function fmtNum($val) {
    if ($val <= 0) return '';
    return rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.');
}

$haNacFmt = fmtNum($ha_nacional);
$estNFmt  = fmtNum($est_nacional);
$haCCNFmt = fmtNum($ha_ccn51);
$estCFmt  = fmtNum($est_ccn51);

// ── 9. FECHAS ─────────────────────────────────────────────────────────────────
$anio          = 2026;
$fechaFirmaFmt = !empty($fecha_firma)      ? date('d/m/Y', strtotime($fecha_firma))      : date('d/m/Y');
$fechaNacFmt   = !empty($fecha_nacimiento) ? date('d/m/Y', strtotime($fecha_nacimiento)) : '';

$riegoSi = ($posee_riego === 'SI' || $posee_riego === 'Sí') ? 'X' : '&nbsp;';
$riegoNo = ($posee_riego === 'NO')                           ? 'X' : '&nbsp;';

// ── 10. LOGO Y FIRMAS ─────────────────────────────────────────────────────────
$logoSrc = '';
$logoPath = __DIR__ . "/img/logo.png";
if (file_exists($logoPath)) {
    $logoSrc = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
}

$firmaPresidenteSrc = '';
$firmaPresidentePath = __DIR__ . "/img/firma_presidente.png";
if (file_exists($firmaPresidentePath)) {
    $firmaPresidenteSrc = 'data:image/png;base64,' . base64_encode(file_get_contents($firmaPresidentePath));
}

$firmaSecretariaSrc = '';
$firmaSecretariaPath = __DIR__ . "/img/firma_secretaria.png";
if (file_exists($firmaSecretariaPath)) {
    $firmaSecretariaSrc = 'data:image/png;base64,' . base64_encode(file_get_contents($firmaSecretariaPath));
}

// ── 11. HTML (diseño idéntico al original) ────────────────────────────────────
$html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@page { margin: 2cm 2.5cm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.5; margin: 0; padding: 0; }
body::before {
    content: ""; position: fixed; top: 50%; left: 50%;
    width: 400px; height: 400px; margin-left: -200px; margin-top: -200px;
    background-image: url("' . $logoSrc . '");
    background-size: contain; background-position: center;
    background-repeat: no-repeat; opacity: 0.08; z-index: -1;
}
.header-title { text-align: center; font-weight: bold; font-size: 12px; text-decoration: underline; margin-bottom: 20px; margin-top: 0; }
p { text-align: justify; margin: 0 0 8px 0; line-height: 1.5; }
.info-line { margin: 6px 0; }
.section-title { font-weight: bold; font-size: 11px; margin-top: 12px; margin-bottom: 8px; }
ol { margin: 6px 0; padding-left: 25px; }
ol li { margin-bottom: 5px; text-align: justify; line-height: 1.5; }
.table-data { width: 100%; margin: 10px 0; }
.table-data td { padding: 3px 0; vertical-align: top; }
.firma-section { margin-top: 25px; text-align: center; page-break-inside: avoid; }
.firma-line-main { width: 300px; border-top: 1.5px solid #000; margin: 0 auto 10px auto; }
.firma-text { font-weight: bold; font-size: 11px; margin-bottom: 15px; }
.firma-container { display: table; width: 100%; margin-top: 8px; page-break-inside: avoid; }
.firma-col { display: table-cell; width: 50%; text-align: center; vertical-align: top; }
.firma-line { width: 70%; border-top: 1px solid #000; margin: 0 auto 6px auto; }
.firma-name { font-weight: bold; font-size: 10.5px; }
.firma-cargo { font-size: 10px; line-height: 1.3; }
.num-acuerdo { font-size: 10px; color: #6b7280; text-align: right; margin-bottom: 8px; }
</style></head><body>

<div class="num-acuerdo">N° Acuerdo: ' . htmlspecialchars($numero_acuerdo) . ' &nbsp;|&nbsp; Período: ' . $anio . '</div>
<div class="header-title">ACUERDO DE PRODUCTOR</div>

<p>Por parte del Sr. Rosendo Muñoz Benavides presidente de la, Aso Santa Lucia Corotú, dentro de lo cual el presente documento es prueba que el productor ha sido informado de los objetivos y metas de la Aso. Santa Lucia Corotú en forma que pueda decir y comunicar su interés en participar en el programa de comercio justo que la Aso. Santa Lucia Corotú está en capacidad de cumplir, y hacer cumplir los deberes y beneficios correspondientes.</p>

<div class="info-line">De otra parte, el (a): <strong>' . htmlspecialchars($nombres_completos) . '</strong></div>
<div class="info-line">Con Cédula #: <strong>' . htmlspecialchars($cedula) . '</strong>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
fecha de nacimiento: <strong>' . htmlspecialchars($fechaNacFmt) . '</strong></div>

<p>Quien confirma que ha sido informado, sin compromiso previo establecido entre las partes, quien es el propietario de la finca ubicada en el
<strong>' . htmlspecialchars($sector) . '</strong>, de la parroquia
<strong>' . htmlspecialchars($parroquia) . '</strong> cantón
<strong>' . htmlspecialchars($canton) . '</strong></p>

<p>En la provincia del <strong>' . htmlspecialchars($provincia) . '</strong>, con un área cultivada de cacao y un estimado de producción detallado a continuación:</p>

<table class="table-data">
<tr>
    <td style="width:30%">Cacao Nacional</td>
    <td style="width:15%"><strong>' . ($haNacFmt !== '' ? $haNacFmt . ' Ha' : '') . '</strong></td>
    <td style="width:35%">Estimado de producción</td>
    <td style="width:20%"><strong>' . ($estNFmt  !== '' ? $estNFmt  . ' QQ' : '') . '</strong></td>
</tr>
<tr>
    <td>Cacao CCN51</td>
    <td><strong>' . ($haCCNFmt !== '' ? $haCCNFmt . ' Ha' : '') . '</strong></td>
    <td>Estimado de producción</td>
    <td><strong>' . ($estCFmt  !== '' ? $estCFmt  . ' QQ' : '') . '</strong></td>
</tr>
<tr>
    <td>Posee riego si ( ' . $riegoSi . ' ) o no ( ' . $riegoNo . ' )</td>
    <td colspan="2">Periodo de Fertilización por año</td>
    <td><strong>' . htmlspecialchars((string)$periodo_fert) . '</strong></td>
</tr>
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
<li>Notificar si el caso lo requiere la adquisición de nuevas unidades de producción (Incremento del área de producción) y acatar o apelar sanciones correspondientes o violaciones que puedan cumplir.</li>
<li>En caso de contratar personal, garantizar el cumplimiento de las normas, leyes, reglamentos y convenios internacionales vigentes.</li>
<li>Vender exclusivamente a nuestro socio comercial, el cacao certificado que ha sido producida bajo su administración e inscritas para la certificación de comercio justo.</li>
<li>Permitir el acceso a su finca y todas las áreas bajo su administración al personal debidamente identificado.</li>
<li>Cuidar áreas protegidas, mantener y proteger las especies (flora y fauna) si hubiese, en la finca y su entorno.</li>
<li>Mantener libres de cualquier tipo de contaminación o basura, su finca y la comunidad donde residen.</li>
<li>Tiene la potestad de apelar y expresar sus desacuerdos a través de los mecanismos establecidos para tales fines.</li>
<li>El productor se compromete a cumplir con las normativas internacionales como el estándar Fairtrade, debida diligencia de los derechos humanos y ambientales (deforestación cero) y otras exigencias nacionales e internacionales que se estipulen.</li>
<li>El productor se compromete en dar permiso a la organización para recopilar, almacenar y compartir datos.</li>
<li>El productor se compromete a que su finca sea físicamente rastreable.</li>
<li>El productor se compromete con la asociación a desarrollar sus cultivos sin la utilización de la deforestación, y por otro lado implementa acciones que permiten la conservación del medio ambiente.</li>
</ol>

<div class="section-title">VIGENCIA DEL ACUERDO</div>
<p>El presente acuerdo tiene una vigencia de (1) año con una renovación en mutuo acuerdo entre ambas partes.</p>
<p style="margin-top:10px;">Fecha de firma del Acuerdo: <strong>' . htmlspecialchars($fechaFirmaFmt) . '</strong></p>

<div class="firma-section">
<br><br><br><br><br><br><br><br>
<div class="firma-line-main"></div>
<div class="firma-text">FIRMA DEL PRODUCTOR</div>
<div class="firma-container">
<div class="firma-col">' .
    ($firmaPresidenteSrc ? '<img src="' . $firmaPresidenteSrc . '" style="max-width:200px;max-height:80px;margin-bottom:4px;">' : '') . '
    <div class="firma-line"></div>
    <div class="firma-name">Ing. Rosendo Muñoz</div>
    <div class="firma-cargo">Presidente de la Aso. SANTA LUCIA COROTU</div>
</div>
<div class="firma-col">' .
    ($firmaSecretariaSrc ? '<img src="' . $firmaSecretariaSrc . '" style="max-width:200px;max-height:80px;margin-bottom:4px;">' : '') . '
    <div class="firma-line"></div>
    <div class="firma-name">Ing. Jean Carlos Ponce</div>
    <div class="firma-cargo">Secretario de la Aso. SANTA LUCIA COROTU</div>
</div>
</div>
</div>

</body></html>';

// ── 12. GENERAR PDF ───────────────────────────────────────────────────────────
while (ob_get_level()) ob_end_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$nombreArchivo = 'AcuerdoProductor_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $cedula) . '_' . $anio . '.pdf';
$dompdf->stream($nombreArchivo, ['Attachment' => false]);
exit;
?>