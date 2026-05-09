<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require __DIR__ . "/layout/bootstrap.php";
require __DIR__ . "/fpdf/fpdf.php";

$id_aplicacion = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id_aplicacion) { header("Location: fichas_aplicaciones.php"); exit; }

// Cargar aplicación
$st = $pdo->prepare("
    SELECT fa.*, f.nombre AS nombre_ficha,
           s.nombre_completo, s.identificacion, s.telefono, s.direccion,
           u.usuario AS inspector
    FROM ficha_aplicaciones fa
    JOIN fichas f ON f.id_ficha = fa.id_ficha
    JOIN socios s ON s.id_socio = fa.id_socio
    LEFT JOIN usuarios u ON u.id_usuario = fa.id_usuario
    WHERE fa.id_aplicacion = ?
");
$st->execute([$id_aplicacion]);
$ap = $st->fetch(PDO::FETCH_ASSOC);
if (!$ap) { header("Location: fichas_aplicaciones.php"); exit; }

// Cargar secciones y preguntas con respuestas
$stS = $pdo->prepare("SELECT * FROM ficha_secciones WHERE id_ficha=? ORDER BY orden");
$stS->execute([$ap['id_ficha']]);
$secciones = $stS->fetchAll(PDO::FETCH_ASSOC);
foreach ($secciones as &$sec) {
    $stP = $pdo->prepare("
        SELECT p.*, r.respuesta_sino, r.cumplimiento, r.observacion, r.respuesta_texto
        FROM ficha_preguntas p
        LEFT JOIN ficha_respuestas r ON r.id_pregunta=p.id_pregunta AND r.id_aplicacion=?
        WHERE p.id_seccion=? ORDER BY p.orden
    ");
    $stP->execute([$id_aplicacion, $sec['id_seccion']]);
    $sec['preguntas'] = $stP->fetchAll(PDO::FETCH_ASSOC);
}
unset($sec);

// ── FPDF extendido ──────────────────────────────────────────
class FichaFPDF extends FPDF {
    function Header() {}
    function Footer() {
        $this->SetY(-12);
        $this->SetFont('Arial','I',7);
        $this->SetTextColor(150);
        $this->Cell(0,5,'Página '.$this->PageNo().' – Sistema de Gestión Asociación Santa Lucía Corotú',0,0,'C');
    }
}

$pdf = new FichaFPDF('P','mm','A4');
$pdf->SetMargins(12,12,12);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// ── ENCABEZADO ──────────────────────────────────────────────
// Logo asociación
$logo = __DIR__ . '/img/logo.png';
if (file_exists($logo)) {
    $pdf->Image($logo, 12, 10, 22);
}

// Logo Fairtrade
$logo_ft = __DIR__ . '/img/fairtrade.png';
if (file_exists($logo_ft)) {
    $pdf->Image($logo_ft, 176, 10, 20);
}

// Texto encabezado centrado
$pdf->SetFont('Arial','B',9);
$pdf->SetXY(35, 10);
$pdf->SetTextColor(0);
$pdf->MultiCell(140, 4,
    utf8_decode("Asociación de trabajadores agrícolas autónomos\n\"Santa Lucia Corotú\" acuerdo ministerial 5742.\nFlo Id 38413\nParroquia La Guayas – Cantón El Empalme – Provincia del Guayas"),
    0,'C');

$pdf->Ln(6);

// ── TÍTULO FICHA ────────────────────────────────────────────
$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(30, 58, 95);
$pdf->SetTextColor(255,255,255);
$pdf->Cell(0, 8, utf8_decode(strtoupper($ap['nombre_ficha'])), 1, 1, 'C', true);
$pdf->SetTextColor(0);
$pdf->Ln(2);

// ── DATOS DEL PRODUCTOR ─────────────────────────────────────
$pdf->SetFont('Arial','B',8);
$pdf->SetFillColor(240,240,240);
$pdf->Cell(0, 6, utf8_decode('Apellidos y Nombres del Productor: ').$ap['nombre_completo'], 1, 1, 'L', true);

$pdf->SetFont('Arial','',8);
$pdf->Cell(65, 6, utf8_decode('Cédula: '.$ap['identificacion']), 1, 0);
$pdf->Cell(65, 6, utf8_decode('Teléfono: '.($ap['telefono']??'')), 1, 0);
$pdf->Cell(0,  6, utf8_decode('Fecha: '.date('d/m/Y', strtotime($ap['fecha_aplicacion']))), 1, 1);

$pdf->Cell(65, 6, utf8_decode('Cantón: '.($ap['canton']??'')), 1, 0);
$pdf->Cell(65, 6, utf8_decode('Parroquia: '.($ap['parroquia']??'')), 1, 0);
$pdf->Cell(0,  6, utf8_decode('Sector: '.($ap['sector']??'')), 1, 1);

$pdf->Cell(0,  6,
    utf8_decode('Coordenadas Hogar: X: '.($ap['coord_hogar_x']??'____').'   Y: '.($ap['coord_hogar_y']??'____').'   Z: '.($ap['coord_hogar_z']??'____')),
    1, 1);
$pdf->Cell(0,  6,
    utf8_decode('Coordenadas Finca:  X: '.($ap['coord_finca_x']??'____').'   Y: '.($ap['coord_finca_y']??'____').'   Z: '.($ap['coord_finca_z']??'____')),
    1, 1);
$pdf->Ln(2);

// ── DATOS DEL CULTIVO ───────────────────────────────────────
$pdf->SetFont('Arial','B',8);
$pdf->SetFillColor(30,58,95);
$pdf->SetTextColor(255,255,255);
$pdf->Cell(0, 6, utf8_decode('DATOS DEL CULTIVO'), 1, 1, 'C', true);
$pdf->SetTextColor(0);
$pdf->SetFont('Arial','',8);
$pdf->Cell(48, 6, utf8_decode('Cultivo: '.($ap['cultivo']??'')), 1, 0);
$pdf->Cell(48, 6, utf8_decode('Variedad: '.($ap['variedad']??'')), 1, 0);
$pdf->Cell(48, 6, utf8_decode('Edad: '.($ap['edad_cultivo']??'')), 1, 0);
$pdf->Cell(0,  6, utf8_decode('Has: '.($ap['hectareas']??'')), 1, 1);

$riego_txt  = 'Riego: SI('.($ap['riego']?'X':' ').') NO('.($ap['riego']?'':' X').') ';
$fuente_txt = '  Fuente: Pozo('.($ap['fuente_agua']==='Pozo'?'X':' ').') Albarrada('.($ap['fuente_agua']==='Albarrada'?'X':' ').')';
$poda_txt   = '  Poda: 1er sem('.($ap['poda_semestre']==='1er semestre'?'X':' ').') 2do sem('.($ap['poda_semestre']==='2do semestre'?'X':' ').')';
$pdf->Cell(0, 6, utf8_decode($riego_txt.$fuente_txt.$poda_txt), 1, 1);
$pdf->Ln(3);

// ── TABLA DE ACTIVIDADES ────────────────────────────────────
// Anchos columnas
$wDesc = 100;
$wNo   = 10;
$wSi   = 10;
$wB    = 10;
$wR    = 10;
$wM    = 10;
$wObs  = 186 - $wDesc - $wNo - $wSi - $wB - $wR - $wM;

// Encabezado tabla
$pdf->SetFont('Arial','B',7.5);
$pdf->SetFillColor(30,58,95);
$pdf->SetTextColor(255,255,255);
$pdf->Cell($wDesc, 10, utf8_decode('Descripción de actividades'), 1, 0, 'C', true);
$pdf->Cell($wNo,   10, 'No',    1, 0, 'C', true);
$pdf->Cell($wSi,   10, utf8_decode('Sí'),    1, 0, 'C', true);

// Sub-encabezado Cumplimiento
$xCumpl = $pdf->GetX();
$yCumpl = $pdf->GetY();
$pdf->Cell($wB+$wR+$wM, 5, 'Cumplimiento', 1, 0, 'C', true);
$pdf->Cell($wObs, 10, 'Observaciones', 1, 1, 'C', true);

$pdf->SetXY($xCumpl, $yCumpl+5);
$pdf->Cell($wB, 5, 'B', 1, 0, 'C', true);
$pdf->Cell($wR, 5, 'R', 1, 0, 'C', true);
$pdf->Cell($wM, 5, 'M', 1, 1, 'C', true);
$pdf->SetTextColor(0);

// Filas
foreach ($secciones as $sec) {
    // Fila sección
    $pdf->SetFont('Arial','B',7.5);
    $pdf->SetFillColor(224,242,254);
    $pdf->SetTextColor(3,105,161);
    $pdf->Cell(0, 6, utf8_decode(strtoupper($sec['titulo'])), 1, 1, 'C', true);
    $pdf->SetTextColor(0);

    foreach ($sec['preguntas'] as $preg) {
        $pdf->SetFont('Arial','',7);
        $pdf->SetFillColor(255,255,255);

        // Calcular altura necesaria para el texto de la pregunta
        $lines = $pdf->GetStringWidth(utf8_decode($preg['texto'])) / $wDesc;
        $h = max(6, ceil($lines) * 5);

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        // Verificar salto de página
        if ($y + $h > 270) {
            $pdf->AddPage();
            // Repetir encabezado tabla
            $pdf->SetFont('Arial','B',7.5);
            $pdf->SetFillColor(30,58,95);
            $pdf->SetTextColor(255,255,255);
            $pdf->Cell($wDesc,10,utf8_decode('Descripción de actividades'),1,0,'C',true);
            $pdf->Cell($wNo,10,'No',1,0,'C',true);
            $pdf->Cell($wSi,10,utf8_decode('Sí'),1,0,'C',true);
            $xC=$pdf->GetX();$yC=$pdf->GetY();
            $pdf->Cell($wB+$wR+$wM,5,'Cumplimiento',1,0,'C',true);
            $pdf->Cell($wObs,10,'Observaciones',1,1,'C',true);
            $pdf->SetXY($xC,$yC+5);
            $pdf->Cell($wB,5,'B',1,0,'C',true);
            $pdf->Cell($wR,5,'R',1,0,'C',true);
            $pdf->Cell($wM,5,'M',1,1,'C',true);
            $pdf->SetTextColor(0);
            $pdf->SetFont('Arial','',7);
            $x=$pdf->GetX();$y=$pdf->GetY();
        }

        $sino = $preg['respuesta_sino'];
        $cumpl= $preg['cumplimiento'] ?? '';
        $obs  = utf8_decode($preg['observacion'] ?? '');
        $txt_r= utf8_decode($preg['respuesta_texto'] ?? '');

        $pdf->MultiCell($wDesc, $h, utf8_decode($preg['texto']), 'LRB', 'L');
        $newY = $pdf->GetY();

        $pdf->SetXY($x + $wDesc, $y);
        $pdf->Cell($wNo, $h, $sino==='0'?'X':'', 'LRB', 0, 'C');
        $pdf->Cell($wSi, $h, $sino==='1'?'X':'', 'LRB', 0, 'C');
        $pdf->Cell($wB,  $h, $cumpl==='B'?'X':'', 'LRB', 0, 'C');
        $pdf->Cell($wR,  $h, $cumpl==='R'?'X':'', 'LRB', 0, 'C');
        $pdf->Cell($wM,  $h, $cumpl==='M'?'X':'', 'LRB', 0, 'C');

        // Observación o texto libre
        $obsContent = ($preg['tipo']==='texto'||$preg['tipo']==='numero') ? $txt_r : $obs;
        $pdf->MultiCell($wObs, $h, $obsContent, 'LRB', 'L');

        $pdf->SetY(max($newY, $y + $h));
    }
}

// ── FIRMAS ──────────────────────────────────────────────────
$pdf->Ln(10);

// Verificar salto de página para firmas
if ($pdf->GetY() > 240) $pdf->AddPage();

$pdf->SetFont('Arial','B',8);
$yFirma = $pdf->GetY();
$xLeft  = 20;
$xRight = 115;
$wFirma = 80;

// Firma Inspector
if (!empty($ap['firma_inspector']) && strpos($ap['firma_inspector'], 'data:image') === 0) {
    $tmpI = tempnam(sys_get_temp_dir(), 'firma_') . '.png';
    $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $ap['firma_inspector']));
    file_put_contents($tmpI, $imgData);
    $pdf->Image($tmpI, $xLeft, $yFirma, $wFirma, 25);
    unlink($tmpI);
}

// Firma Productor
if (!empty($ap['firma_productor']) && strpos($ap['firma_productor'], 'data:image') === 0) {
    $tmpP = tempnam(sys_get_temp_dir(), 'firma_') . '.png';
    $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $ap['firma_productor']));
    file_put_contents($tmpP, $imgData);
    $pdf->Image($tmpP, $xRight, $yFirma, $wFirma, 25);
    unlink($tmpP);
}

$pdf->SetY($yFirma + 26);
$pdf->SetFont('Arial','',8);

// Línea firma inspector
$pdf->SetX($xLeft);
$pdf->Cell($wFirma, 0, '', 'T', 0, 'C');
$pdf->SetX($xRight);
$pdf->Cell($wFirma, 0, '', 'T', 1, 'C');

$pdf->SetX($xLeft);
$pdf->Cell($wFirma, 5, utf8_decode('Firma Inspector Interno'), 0, 0, 'C');
$pdf->SetX($xRight);
$pdf->Cell($wFirma, 5, utf8_decode('Firma Productor'), 0, 1, 'C');

$pdf->SetX($xLeft);
$pdf->SetFont('Arial','B',8);
$pdf->Cell($wFirma, 5, utf8_decode($ap['inspector'] ?? ''), 0, 0, 'C');
$pdf->SetX($xRight);
$pdf->Cell($wFirma, 5, utf8_decode($ap['nombre_completo']), 0, 1, 'C');

// ── OUTPUT ──────────────────────────────────────────────────
$nombre_archivo = 'Ficha_'.preg_replace('/[^a-zA-Z0-9]/', '_', $ap['nombre_completo']).'_'.date('Ymd').'.pdf';
$pdf->Output('I', $nombre_archivo);
exit;
