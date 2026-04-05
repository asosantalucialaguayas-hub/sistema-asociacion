<?php
require "layout/bootstrap.php";
require "config/conexion.php";
require "vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;

$id = $_GET['id'] ?? null;
if (!$id) {
    die("Solicitud no válida");
}

$sql  = "SELECT * FROM solicitud_ingreso WHERE id_solicitud = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$s = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$s) {
    die("Solicitud no encontrada");
}

// Logo en base64
$logoPath = __DIR__ . "/img/logo.png";
if (!file_exists($logoPath)) {
    die("Logo no encontrado en: " . $logoPath);
}
$logoData = base64_encode(file_get_contents($logoPath));
$logoSrc  = 'data:image/png;base64,' . $logoData;

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$html = '
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 12px;
    margin: 40px;
}
.top-line {
    width: 100%;
    height: 3px;
    background: #000;
    margin-bottom: 15px;
}
.watermark {
    position: fixed;
    top: 28%;
    left: 18%;
    width: 420px;
    opacity: 0.08;
    z-index: -1;
}
.header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.logo { width: 90px; }
.header-right { text-align: right; }
.title { font-size: 16px; font-weight: bold; }
.section {
    margin-top: 18px;
    text-align: justify;
}
.line {
    display: inline-block;
    border-bottom: 1px solid #000;
    width: 260px;
}
.footer { margin-top: 70px; text-align: center; }
.sign {
    display: inline-block;
    width: 45%;
    margin-top: 50px;
    text-align: center;
}
</style>
</head>
<body>

<img src="'.$logoSrc.'" class="watermark">

<div class="header">
    <img src="'.$logoSrc.'" class="logo">
    <div class="header-right">
        <center><div class="title">Solicitud de ingreso</div></center>
        <p>Recinto Santa Lucía Corotú: ____________________________</p>
    </div>
</div>

<div class="section">
<p>
Asociación de Trabajadores Agrícolas Autónomos "Santa Lucía Corotú" de la parroquia Guayas, Cantón El Empalme, Provincia del Guayas.
</p>

<p><strong>Acuerdo ministerial N° 5742</strong></p>

<p>Señor presidente/a de la Org.</p>

<p>
Yo: <span class="line">'.htmlspecialchars($s['nombres_completos']).'</span>
&nbsp;&nbsp; N°: <span class="line">'.htmlspecialchars($s['identificacion']).'</span>
</p>

<p>
Correo: <span class="line">'.htmlspecialchars($s['correo']).'</span>
&nbsp;&nbsp; Tlf: <span class="line">'.htmlspecialchars($s['celular']).'</span>
</p>

<p>
Solicito de la manera más formal se me acoja, se me inscriba como asociado de dicha organización, que usted preside para ser miembro de la misma y obtener todos los beneficios de ley gubernamental y probada que se presenten.
</p>

<p>
Me comprometo a dar fiel cumplimiento a todos los deberes y obligaciones que dictamine la misma y cumplir fielmente con todo lo expuesto en sus estatutos legales e internos que obtenga dicha Asociación.
</p>

<p>
Esperando tener la favorable acogida y aprobación de la Asamblea y directiva les quedo muy agradecido.
</p>

<p>Atentamente,</p>

<br><br>
<p> ____________________________</p>
<p>C.I. ____________________________</p>
</div>

<div class="footer">
    <div class="sign">
        ____________________________<br>
        Aprobado por el presidente
    </div>
    <div class="sign">
        ____________________________<br>
        Certificado el/la secretario/a
    </div>
    <br><br>
    <div class="sign">
        <strong>Ing. Rosendo Muñoz</strong><br>
        Presidente de la Aso. Santa Lucía Corotú
    </div>
    <div class="sign">
        <strong>Ing. Jean Carlos Ponce</strong><br>
        Secretaria de la Aso. Santa Lucía Corotú
    </div>
</div>

</body>
</html>
';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// ── Solo mostrar en el navegador, sin guardar ni registrar en BD ──
$dompdf->stream(
    "Solicitud_Ingreso_" . $s['identificacion'] . ".pdf",
    ["Attachment" => false]   // false = mostrar en navegador, true = descargar
);
exit;