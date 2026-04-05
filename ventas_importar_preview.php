<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['usuario'])) { echo json_encode(['success'=>false,'message'=>'No autenticado']); exit; }
require "config/conexion.php";
if (!isset($pdo)) { echo json_encode(['success'=>false,'message'=>'Sin conexión']); exit; }

// Requiere PhpSpreadsheet: composer require phpoffice/phpspreadsheet
require __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success'=>false,'message'=>'Archivo requerido']); exit;
    }

    $spreadsheet = IOFactory::load($_FILES['archivo']['tmp_name']);
    $sheet       = $spreadsheet->getActiveSheet();
    $rows        = $sheet->toArray(null, true, true, false); // indexado desde 0

    // Buscar fila de cabecera (busca "PRODUCTOR")
    $headerIdx = -1;
    $colMap    = [];
    foreach ($rows as $i => $row) {
        foreach ($row as $j => $cell) {
            if (strtoupper(trim($cell ?? '')) === 'PRODUCTOR') {
                $headerIdx = $i;
                break 2;
            }
        }
    }

    if ($headerIdx === -1) {
        echo json_encode(['success'=>false,'message'=>'No se encontró la fila de cabecera (PRODUCTOR)']); exit;
    }

    // Mapear columnas
    $header = $rows[$headerIdx];
    foreach ($header as $j => $name) {
        $n = strtoupper(trim($name ?? ''));
        if ($n === 'PRODUCTOR')           $colMap['productor']  = $j;
        if ($n === 'FECHA')               $colMap['fecha']      = $j;
        if (str_contains($n,'EMISI'))     $colMap['pto_emision']= $j;
        if (str_contains($n,'VENTA'))     $colMap['pto_venta']  = $j;
        if ($n === 'NÚMERO' || $n === 'NUMERO') $colMap['numero'] = $j;
        if ($n === 'PRODUCTO')            $colMap['producto']   = $j;
        if ($n === 'QQ')                  $colMap['qq']         = $j;
        if ($n === 'KG')                  $colMap['kg']         = $j;
        if ($n === 'PRECIO')              $colMap['precio']     = $j;
        if ($n === 'TOTAL')               $colMap['total']      = $j;
        if ($n === 'PRIMA')               $colMap['prima']      = $j;
        if (str_contains($n,'COMPRAD'))   $colMap['comprador']  = $j;
        if ($n === 'FLOID')               $colMap['floid']      = $j;
    }

    if (!isset($colMap['productor'],$colMap['kg'])) {
        echo json_encode(['success'=>false,'message'=>'Columnas requeridas no encontradas (PRODUCTOR, KG)']); exit;
    }

    $registros = [];

    for ($i = $headerIdx + 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $productor = trim($row[$colMap['productor']] ?? '');
        $kg        = floatval(str_replace([','], ['.'], $row[$colMap['kg']] ?? '0'));

        if (empty($productor) || $kg <= 0) continue;

        // Buscar socio por nombre
        $st = $pdo->prepare("SELECT id_socio, nombre_completo, id_lpa FROM socios
            INNER JOIN tabla_lpa ON tabla_lpa.id_socio = socios.id_socio
            WHERE UPPER(TRIM(nombre_completo)) = UPPER(:nombre)
            AND tabla_lpa.estado_lpa = 'ACTIVO' LIMIT 1");
        $st->execute([':nombre' => $productor]);
        $socio = $st->fetch(PDO::FETCH_ASSOC);

        // Formatear fecha
        $fechaRaw = $row[$colMap['fecha']] ?? '';
        $fecha    = '';
        if ($fechaRaw) {
            // Puede venir como 02/10/2025 o timestamp de Excel
            if (is_numeric($fechaRaw)) {
                $fecha = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($fechaRaw)->format('Y-m-d');
            } else {
                $parts = date_parse_from_format('d/m/Y', $fechaRaw);
                if ($parts && !$parts['error_count']) {
                    $fecha = sprintf('%04d-%02d-%02d', $parts['year'], $parts['month'], $parts['day']);
                }
            }
        }

        $registros[] = [
            'productor'    => $productor,
            'id_socio'     => $socio['id_socio']       ?? null,
            'id_lpa'       => $socio['id_lpa']         ?? null,
            'nombre_socio' => $socio['nombre_completo'] ?? '',
            'fecha'        => $fecha,
            'pto_emision'  => $row[$colMap['pto_emision']] ?? '',
            'pto_venta'    => $row[$colMap['pto_venta']]   ?? '',
            'numero'       => $row[$colMap['numero']]      ?? '',
            'producto'     => $row[$colMap['producto']]    ?? 'CACAO ANSN FAIRTRADE FISICAMENTE RASTREABLE',
            'qq'           => floatval(str_replace(',','.',$row[$colMap['qq']]   ?? '0')),
            'kg'           => $kg,
            'precio'       => floatval(str_replace(',','.',$row[$colMap['precio']]?? '0')),
            'total'        => floatval(str_replace(',','.',$row[$colMap['total']] ?? '0')),
            'prima'        => floatval(str_replace(',','.',$row[$colMap['prima']] ?? '0')),
            'comprador'    => $row[$colMap['comprador']] ?? '',
            'floid'        => $row[$colMap['floid']]     ?? '',
        ];
    }

    echo json_encode(['success'=>true, 'registros'=>$registros]);

} catch(Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Error al leer Excel: '.$e->getMessage()]);
}
?>