<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['usuario'])) { echo json_encode(['success'=>false,'message'=>'No autenticado']); exit; }
require "config/conexion.php";
if (!isset($pdo)) { echo json_encode(['success'=>false,'message'=>'Sin conexión']); exit; }

try {
    $input    = json_decode(file_get_contents('php://input'), true);
    $registros = $input['registros'] ?? [];
    $tipo      = $input['tipo']      ?? 'ACOPIO';
    $sucursal  = $input['sucursal']  ?? '';
    $usuario   = $_SESSION['usuario'];

    if (empty($registros)) {
        echo json_encode(['success'=>false,'message'=>'No hay registros para importar']); exit;
    }

    $pdo->beginTransaction();
    $importados = 0;

    if ($tipo === 'ACOPIO') {
        // ── Insertar en tabla_ventas ──────────────────────────
        $sql = "INSERT INTO tabla_ventas
            (id_socio, id_lpa, fecha_venta, punto_emision, punto_venta,
             numero_doc, producto, cantidad_vende, qq, precio_kg,
             total, prima, comprador, floid, sucursal,
             tipo_origen, fecha_registro)
            VALUES
            (:id_socio,:id_lpa,:fecha,:pto_e,:pto_v,
             :numero,:producto,:kg,:qq,:precio,
             :total,:prima,:comprador,:floid,:sucursal,
             'IMPORTADO', NOW())";

        $st = $pdo->prepare($sql);

        foreach ($registros as $r) {
            if (empty($r['id_socio']) || empty($r['id_lpa'])) continue;

            // Verificar cupo disponible
            $stCupo = $pdo->prepare("
                SELECT IFNULL(l.volumen_produccion_estimado,0) -
                       IFNULL((SELECT SUM(v.cantidad_vende) FROM tabla_ventas v WHERE v.id_lpa=l.id_lpa),0)
                       AS cupo_disp
                FROM tabla_lpa l WHERE l.id_lpa = :id_lpa");
            $stCupo->execute([':id_lpa' => $r['id_lpa']]);
            $cupo = floatval($stCupo->fetchColumn());

            if ($r['kg'] > $cupo) continue; // Omitir si excede cupo

            $st->execute([
                ':id_socio' => $r['id_socio'],
                ':id_lpa'   => $r['id_lpa'],
                ':fecha'    => $r['fecha'],
                ':pto_e'    => $r['pto_emision'],
                ':pto_v'    => $r['pto_venta'],
                ':numero'   => $r['numero'],
                ':producto' => $r['producto'],
                ':kg'       => $r['kg'],
                ':qq'       => $r['qq'],
                ':precio'   => $r['precio'],
                ':total'    => $r['total'],
                ':prima'    => $r['prima'],
                ':comprador'=> $r['comprador'],
                ':floid'    => $r['floid'],
                ':sucursal' => $sucursal,
            ]);
            $importados++;
        }

    } else {
        // ── Insertar en tabla_ventas_externas ─────────────────
        $sql = "INSERT INTO tabla_ventas_externas
            (id_socio, id_lpa, fecha_venta, punto_emision, punto_venta,
             numero_doc, producto, qq, cantidad_kg, precio_kg,
             total, prima, comprador, floid,
             tipo_origen, fecha_registro, usuario_registro)
            VALUES
            (:id_socio,:id_lpa,:fecha,:pto_e,:pto_v,
             :numero,:producto,:qq,:kg,:precio,
             :total,:prima,:comprador,:floid,
             'IMPORTADO', NOW(), :usuario)";

        $st = $pdo->prepare($sql);

        foreach ($registros as $r) {
            if (empty($r['id_socio']) || empty($r['id_lpa'])) continue;
            $st->execute([
                ':id_socio' => $r['id_socio'],
                ':id_lpa'   => $r['id_lpa'],
                ':fecha'    => $r['fecha'],
                ':pto_e'    => $r['pto_emision'],
                ':pto_v'    => $r['pto_venta'],
                ':numero'   => $r['numero'],
                ':producto' => $r['producto'],
                ':qq'       => $r['qq'],
                ':kg'       => $r['kg'],
                ':precio'   => $r['precio'],
                ':total'    => $r['total'],
                ':prima'    => $r['prima'],
                ':comprador'=> $r['comprador'],
                ':floid'    => $r['floid'],
                ':usuario'  => $usuario,
            ]);
            $importados++;
        }
    }

    $pdo->commit();
    echo json_encode(['success'=>true,'importados'=>$importados]);

} catch(Exception $e) {
    $pdo->rollBack();
    error_log($e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
}
?>