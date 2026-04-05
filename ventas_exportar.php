<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario'])) {
    die('No autenticado');
}

require __DIR__ . "/config/conexion.php";

if (!isset($pdo) || $pdo === null) {
    die('Error de conexión a la base de datos');
}

// ── Instalar PhpSpreadsheet si no existe ────────────────────────────────────
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    die('Error: ejecuta "composer require phpoffice/phpspreadsheet" en el servidor');
}
require $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

try {
    $mes  = $_GET['mes']  ?? '';
    $anio = $_GET['anio'] ?? '';

    // ── Colores ─────────────────────────────────────────────────────────────
    $COLOR_ACOPIO   = '1E40AF'; // azul oscuro
    $COLOR_EXTERNA  = '9D174D'; // rosa oscuro
    $COLOR_CONSOL   = '065F46'; // verde oscuro
    $COLOR_RESUMEN  = '1F3A5F'; // azul marino
    $COLOR_BLANCO   = 'FFFFFF';
    $COLOR_FILA_PAR = 'EFF6FF';

    // ── Función helper: aplicar estilo encabezado ────────────────────────────
    function estiloHeader($sheet, $rango, $colorFondo, $colorTexto = 'FFFFFF') {
        $sheet->getStyle($rango)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => $colorTexto], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colorFondo]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ]);
    }

    function estiloDatos($sheet, $rango) {
        $sheet->getStyle($rango)->applyFromArray([
            'font'      => ['size' => 9],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
        ]);
    }

    function estiloMoney($sheet, $rango) {
        $sheet->getStyle($rango)->getNumberFormat()->setFormatCode('"$"#,##0.00');
    }

    function estiloNum($sheet, $rango, $dec = 2) {
        $fmt = $dec === 4 ? '#,##0.0000' : '#,##0.00';
        $sheet->getStyle($rango)->getNumberFormat()->setFormatCode($fmt);
    }

    function tituloPrincipal($sheet, $texto, $rango, $color) {
        $sheet->mergeCells($rango);
        $sheet->getStyle($rango)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->setCellValue(explode(':', $rango)[0], $texto);
        $sheet->getRowDimension(1)->setRowHeight(30);
    }

    // ── CONSULTA ACOPIO ─────────────────────────────────────────────────────
    $sqlA = "
        SELECT
            s.identificacion       AS cedula,
            s.nombre_completo      AS productor,
            l.id_lpa,
            v.fecha_venta,
            v.numero_doc,
            v.cantidad_vende       AS kg,
            v.cantidad_qq          AS qq,
            v.precio_kg,
            v.total,
            v.destino              AS comprador,
            v.floid,
            v.sucursal,
            v.prima,
            v.observacion
        FROM tabla_ventas v
        INNER JOIN tabla_lpa l ON v.id_lpa = l.id_lpa
        INNER JOIN socios s    ON l.id_socio = s.id_socio
        WHERE 1=1
    ";
    $paramsA = [];
    if ($mes  !== '') { $sqlA .= " AND MONTH(v.fecha_venta) = :mes";  $paramsA[':mes']  = $mes; }
    if ($anio !== '') { $sqlA .= " AND YEAR(v.fecha_venta) = :anio";  $paramsA[':anio'] = $anio; }
    $sqlA .= " ORDER BY v.fecha_venta DESC, s.nombre_completo ASC";
    $stA = $pdo->prepare($sqlA);
    foreach ($paramsA as $k => $v) $stA->bindValue($k, $v);
    $stA->execute();
    $acopio = $stA->fetchAll(PDO::FETCH_ASSOC);

    // ── CONSULTA EXTERNAS ───────────────────────────────────────────────────
    $sqlE = "
        SELECT
            s.identificacion       AS cedula,
            s.nombre_completo      AS productor,
            l.id_lpa,
            ve.fecha_venta,
            ve.numero_doc,
            ve.cantidad_kg         AS kg,
            ve.qq,
            ve.precio_kg,
            ve.total,
            ve.comprador,
            ve.floid,
            ve.observacion
        FROM tabla_ventas_externas ve
        INNER JOIN tabla_lpa l ON ve.id_lpa = l.id_lpa
        INNER JOIN socios s    ON l.id_socio = s.id_socio
        WHERE 1=1
    ";
    $paramsE = [];
    if ($mes  !== '') { $sqlE .= " AND MONTH(ve.fecha_venta) = :mes";  $paramsE[':mes']  = $mes; }
    if ($anio !== '') { $sqlE .= " AND YEAR(ve.fecha_venta) = :anio";  $paramsE[':anio'] = $anio; }
    $sqlE .= " ORDER BY ve.fecha_venta DESC, s.nombre_completo ASC";
    $stE = $pdo->prepare($sqlE);
    foreach ($paramsE as $k => $v) $stE->bindValue($k, $v);
    $stE->execute();
    $externas = $stE->fetchAll(PDO::FETCH_ASSOC);

    // ── CREAR LIBRO ─────────────────────────────────────────────────────────
    $wb = new Spreadsheet();
    $wb->getProperties()
        ->setTitle('Reporte de Ventas')
        ->setCreator('Sistema Gestión')
        ->setDescription('Exportación ventas acopio y externas');

    // ════════════════════════════════════════════════════════════════════════
    //  HOJA 1: VENTAS ACOPIO
    // ════════════════════════════════════════════════════════════════════════
    $shA = $wb->getActiveSheet();
    $shA->setTitle('Ventas Acopio');

    tituloPrincipal($shA, '🏭 VENTAS DE ACOPIO', 'A1:N1', $COLOR_ACOPIO);
    $shA->mergeCells('A2:N2');
    $shA->setCellValue('A2', 'Generado: ' . date('d/m/Y H:i:s') . ($mes ? "  |  Mes: $mes" : '') . ($anio ? "  |  Año: $anio" : ''));
    $shA->getStyle('A2')->applyFromArray(['font' => ['italic' => true, 'size' => 9], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
    $shA->getRowDimension(2)->setRowHeight(15);

    $headersA = ['#','Cédula','Productor/a','ID LPA','Fecha','N° Doc','KG','QQ','Precio/KG','Total','Prima','Comprador','FLOID','Sucursal'];
    $colA = 'A';
    foreach ($headersA as $h) {
        $shA->setCellValue($colA.'3', $h);
        $colA++;
    }
    estiloHeader($shA, 'A3:N3', $COLOR_ACOPIO);
    $shA->getRowDimension(3)->setRowHeight(22);

    $rowA = 4;
    $totalKgA = 0; $totalQqA = 0; $totalUsdA = 0;
    foreach ($acopio as $i => $v) {
        $shA->setCellValue('A'.$rowA, $i+1);
        $shA->setCellValue('B'.$rowA, $v['cedula']);
        $shA->setCellValue('C'.$rowA, $v['productor']);
        $shA->setCellValue('D'.$rowA, $v['id_lpa']);
        $shA->setCellValue('E'.$rowA, $v['fecha_venta']);
        $shA->setCellValue('F'.$rowA, $v['numero_doc'] ?? '-');
        $shA->setCellValue('G'.$rowA, floatval($v['kg']));
        $shA->setCellValue('H'.$rowA, floatval($v['qq']));
        $shA->setCellValue('I'.$rowA, floatval($v['precio_kg']));
        $shA->setCellValue('J'.$rowA, floatval($v['total']));
        $shA->setCellValue('K'.$rowA, floatval($v['prima'] ?? 0));
        $shA->setCellValue('L'.$rowA, $v['comprador'] ?? '-');
        $shA->setCellValue('M'.$rowA, $v['floid'] ?? '-');
        $shA->setCellValue('N'.$rowA, $v['sucursal'] ?? '-');

        $totalKgA  += floatval($v['kg']);
        $totalQqA  += floatval($v['qq']);
        $totalUsdA += floatval($v['total']);

        if ($i % 2 === 1) {
            $shA->getStyle('A'.$rowA.':N'.$rowA)->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $COLOR_FILA_PAR]]]);
        }
        $rowA++;
    }
    // Fila totales acopio
    $shA->mergeCells('A'.$rowA.':F'.$rowA);
    $shA->setCellValue('A'.$rowA, 'TOTALES');
    $shA->setCellValue('G'.$rowA, $totalKgA);
    $shA->setCellValue('H'.$rowA, $totalQqA);
    $shA->setCellValue('J'.$rowA, $totalUsdA);
    estiloHeader($shA, 'A'.$rowA.':N'.$rowA, $COLOR_ACOPIO);

    estiloDatos($shA, 'A4:N'.($rowA-1));
    estiloMoney($shA, 'I4:J'.$rowA);
    estiloNum($shA,   'G4:G'.$rowA);
    estiloNum($shA,   'H4:H'.$rowA, 4);

    // Anchos columnas acopio
    $anchosA = ['A'=>5,'B'=>14,'C'=>28,'D'=>8,'E'=>12,'F'=>14,'G'=>10,'H'=>10,'I'=>12,'J'=>12,'K'=>10,'L'=>20,'M'=>10,'N'=>18];
    foreach ($anchosA as $col => $ancho) $shA->getColumnDimension($col)->setWidth($ancho);

    // ════════════════════════════════════════════════════════════════════════
    //  HOJA 2: VENTAS EXTERNAS
    // ════════════════════════════════════════════════════════════════════════
    $shE = $wb->createSheet();
    $shE->setTitle('Ventas Externas');

    tituloPrincipal($shE, '🌐 VENTAS EXTERNAS', 'A1:L1', $COLOR_EXTERNA);
    $shE->mergeCells('A2:L2');
    $shE->setCellValue('A2', 'Generado: ' . date('d/m/Y H:i:s') . ($mes ? "  |  Mes: $mes" : '') . ($anio ? "  |  Año: $anio" : ''));
    $shE->getStyle('A2')->applyFromArray(['font' => ['italic' => true, 'size' => 9], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

    $headersE = ['#','Cédula','Productor/a','ID LPA','Fecha','N° Doc','KG','QQ','Precio/KG','Total','Comprador','FLOID'];
    $colE = 'A';
    foreach ($headersE as $h) {
        $shE->setCellValue($colE.'3', $h);
        $colE++;
    }
    estiloHeader($shE, 'A3:L3', $COLOR_EXTERNA);
    $shE->getRowDimension(3)->setRowHeight(22);

    $rowE = 4;
    $totalKgE = 0; $totalQqE = 0; $totalUsdE = 0;
    foreach ($externas as $i => $v) {
        $shE->setCellValue('A'.$rowE, $i+1);
        $shE->setCellValue('B'.$rowE, $v['cedula']);
        $shE->setCellValue('C'.$rowE, $v['productor']);
        $shE->setCellValue('D'.$rowE, $v['id_lpa']);
        $shE->setCellValue('E'.$rowE, $v['fecha_venta']);
        $shE->setCellValue('F'.$rowE, $v['numero_doc'] ?? '-');
        $shE->setCellValue('G'.$rowE, floatval($v['kg']));
        $shE->setCellValue('H'.$rowE, floatval($v['qq']));
        $shE->setCellValue('I'.$rowE, floatval($v['precio_kg']));
        $shE->setCellValue('J'.$rowE, floatval($v['total']));
        $shE->setCellValue('K'.$rowE, $v['comprador'] ?? '-');
        $shE->setCellValue('L'.$rowE, $v['floid'] ?? '-');

        $totalKgE  += floatval($v['kg']);
        $totalQqE  += floatval($v['qq']);
        $totalUsdE += floatval($v['total']);

        if ($i % 2 === 1) {
            $shE->getStyle('A'.$rowE.':L'.$rowE)->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FDF2F8']]]);
        }
        $rowE++;
    }
    $shE->mergeCells('A'.$rowE.':F'.$rowE);
    $shE->setCellValue('A'.$rowE, 'TOTALES');
    $shE->setCellValue('G'.$rowE, $totalKgE);
    $shE->setCellValue('H'.$rowE, $totalQqE);
    $shE->setCellValue('J'.$rowE, $totalUsdE);
    estiloHeader($shE, 'A'.$rowE.':L'.$rowE, $COLOR_EXTERNA);

    estiloDatos($shE, 'A4:L'.($rowE-1));
    estiloMoney($shE, 'I4:J'.$rowE);
    estiloNum($shE,   'G4:G'.$rowE);
    estiloNum($shE,   'H4:H'.$rowE, 4);

    $anchosE = ['A'=>5,'B'=>14,'C'=>28,'D'=>8,'E'=>12,'F'=>14,'G'=>10,'H'=>10,'I'=>12,'J'=>12,'K'=>20,'L'=>10];
    foreach ($anchosE as $col => $ancho) $shE->getColumnDimension($col)->setWidth($ancho);

    // ════════════════════════════════════════════════════════════════════════
    //  HOJA 3: CONSOLIDADO (acopio + externas por productor)
    // ════════════════════════════════════════════════════════════════════════
    $shC = $wb->createSheet();
    $shC->setTitle('Consolidado');

    tituloPrincipal($shC, '📊 CONSOLIDADO POR PRODUCTOR', 'A1:J1', $COLOR_CONSOL);
    $shC->mergeCells('A2:J2');
    $shC->setCellValue('A2', 'Generado: ' . date('d/m/Y H:i:s'));
    $shC->getStyle('A2')->applyFromArray(['font' => ['italic' => true, 'size' => 9], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

    $headersC = ['#','Cédula','Productor/a','KG Acopio','QQ Acopio','$ Acopio','KG Externas','QQ Externas','$ Externas','$ TOTAL'];
    $colC = 'A';
    foreach ($headersC as $h) {
        $shC->setCellValue($colC.'3', $h);
        $colC++;
    }
    estiloHeader($shC, 'A3:J3', $COLOR_CONSOL);

    // Agrupar por productor
    $consolidado = [];
    foreach ($acopio as $v) {
        $k = $v['cedula'];
        if (!isset($consolidado[$k])) $consolidado[$k] = ['cedula'=>$v['cedula'],'productor'=>$v['productor'],'kg_a'=>0,'qq_a'=>0,'usd_a'=>0,'kg_e'=>0,'qq_e'=>0,'usd_e'=>0];
        $consolidado[$k]['kg_a']  += floatval($v['kg']);
        $consolidado[$k]['qq_a']  += floatval($v['qq']);
        $consolidado[$k]['usd_a'] += floatval($v['total']);
    }
    foreach ($externas as $v) {
        $k = $v['cedula'];
        if (!isset($consolidado[$k])) $consolidado[$k] = ['cedula'=>$v['cedula'],'productor'=>$v['productor'],'kg_a'=>0,'qq_a'=>0,'usd_a'=>0,'kg_e'=>0,'qq_e'=>0,'usd_e'=>0];
        $consolidado[$k]['kg_e']  += floatval($v['kg']);
        $consolidado[$k]['qq_e']  += floatval($v['qq']);
        $consolidado[$k]['usd_e'] += floatval($v['total']);
    }
    usort($consolidado, fn($a,$b) => strcmp($a['productor'], $b['productor']));

    $rowC = 4;
    $tKgA=0;$tQqA=0;$tUsdA=0;$tKgE=0;$tQqE=0;$tUsdE=0;
    foreach ($consolidado as $i => $c) {
        $total = $c['usd_a'] + $c['usd_e'];
        $shC->setCellValue('A'.$rowC, $i+1);
        $shC->setCellValue('B'.$rowC, $c['cedula']);
        $shC->setCellValue('C'.$rowC, $c['productor']);
        $shC->setCellValue('D'.$rowC, $c['kg_a']);
        $shC->setCellValue('E'.$rowC, $c['qq_a']);
        $shC->setCellValue('F'.$rowC, $c['usd_a']);
        $shC->setCellValue('G'.$rowC, $c['kg_e']);
        $shC->setCellValue('H'.$rowC, $c['qq_e']);
        $shC->setCellValue('I'.$rowC, $c['usd_e']);
        $shC->setCellValue('J'.$rowC, $total);

        $tKgA+=$c['kg_a'];$tQqA+=$c['qq_a'];$tUsdA+=$c['usd_a'];
        $tKgE+=$c['kg_e'];$tQqE+=$c['qq_e'];$tUsdE+=$c['usd_e'];

        if ($i % 2 === 1) {
            $shC->getStyle('A'.$rowC.':J'.$rowC)->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECFDF5']]]);
        }
        $rowC++;
    }
    $shC->mergeCells('A'.$rowC.':C'.$rowC);
    $shC->setCellValue('A'.$rowC, 'TOTALES');
    $shC->setCellValue('D'.$rowC, $tKgA);
    $shC->setCellValue('E'.$rowC, $tQqA);
    $shC->setCellValue('F'.$rowC, $tUsdA);
    $shC->setCellValue('G'.$rowC, $tKgE);
    $shC->setCellValue('H'.$rowC, $tQqE);
    $shC->setCellValue('I'.$rowC, $tUsdE);
    $shC->setCellValue('J'.$rowC, $tUsdA + $tUsdE);
    estiloHeader($shC, 'A'.$rowC.':J'.$rowC, $COLOR_CONSOL);

    estiloDatos($shC, 'A4:J'.($rowC-1));
    estiloMoney($shC, 'F4:J'.$rowC);
    estiloNum($shC, 'D4:E'.$rowC, 2);
    estiloNum($shC, 'G4:H'.$rowC, 2);

    $anchosC = ['A'=>5,'B'=>14,'C'=>28,'D'=>12,'E'=>10,'F'=>13,'G'=>12,'H'=>10,'I'=>13,'J'=>14];
    foreach ($anchosC as $col => $ancho) $shC->getColumnDimension($col)->setWidth($ancho);

    // ════════════════════════════════════════════════════════════════════════
    //  HOJA 4: RESUMEN GENERAL
    // ════════════════════════════════════════════════════════════════════════
    $shR = $wb->createSheet();
    $shR->setTitle('Resumen');

    tituloPrincipal($shR, '📋 RESUMEN GENERAL', 'A1:C1', $COLOR_RESUMEN);
    $shR->getColumnDimension('A')->setWidth(30);
    $shR->getColumnDimension('B')->setWidth(20);
    $shR->getColumnDimension('C')->setWidth(20);

    $periodo = ($mes ? "Mes $mes" : 'Todo el año') . ($anio ? " / $anio" : '');
    $shR->setCellValue('A3', 'PERÍODO');
    $shR->setCellValue('B3', $periodo);
    $shR->mergeCells('B3:C3');

    $datos_resumen = [
        ['', '', ''],
        ['VENTAS DE ACOPIO', '', ''],
        ['Total registros',    count($acopio),  ''],
        ['Total KG',           $totalKgA,        'kg'],
        ['Total QQ',           $totalQqA,        'qq'],
        ['Total USD',          $totalUsdA,       '$'],
        ['', '', ''],
        ['VENTAS EXTERNAS', '', ''],
        ['Total registros',    count($externas), ''],
        ['Total KG',           $totalKgE,        'kg'],
        ['Total QQ',           $totalQqE,        'qq'],
        ['Total USD',          $totalUsdE,       '$'],
        ['', '', ''],
        ['TOTALES GENERALES', '', ''],
        ['Total KG combinado', $totalKgA + $totalKgE, 'kg'],
        ['Total QQ combinado', $totalQqA + $totalQqE, 'qq'],
        ['Total USD combinado',$totalUsdA + $totalUsdE,'$'],
        ['Productores únicos', count($consolidado), ''],
    ];

    $rowR = 4;
    foreach ($datos_resumen as $fila) {
        $shR->setCellValue('A'.$rowR, $fila[0]);
        $shR->setCellValue('B'.$rowR, $fila[1]);
        $shR->setCellValue('C'.$rowR, $fila[2]);

        // Filas de sección (negrita con color)
        if (in_array($fila[0], ['VENTAS DE ACOPIO','VENTAS EXTERNAS','TOTALES GENERALES'])) {
            $shR->mergeCells('A'.$rowR.':C'.$rowR);
            estiloHeader($shR, 'A'.$rowR.':C'.$rowR,
                $fila[0]==='VENTAS DE ACOPIO' ? $COLOR_ACOPIO :
                ($fila[0]==='VENTAS EXTERNAS' ? $COLOR_EXTERNA : $COLOR_CONSOL));
        } elseif ($fila[2] === '$') {
            $shR->getStyle('B'.$rowR)->getNumberFormat()->setFormatCode('"$"#,##0.00');
            $shR->getStyle('B'.$rowR)->getFont()->setBold(true);
        }
        $rowR++;
    }

    estiloHeader($shR, 'A3:C3', $COLOR_RESUMEN);

    // ── Orden de hojas ───────────────────────────────────────────────────────
    $wb->setActiveSheetIndex(0);

    // ── Enviar al navegador ──────────────────────────────────────────────────
    $filename = 'Ventas_' . date('Y-m-d_His') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($wb);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    error_log("Error ventas_exportar.php: " . $e->getMessage());
    die('Error al exportar: ' . $e->getMessage());
}
?>