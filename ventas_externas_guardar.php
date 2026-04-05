<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['usuario'])) { echo json_encode(['success'=>false,'message'=>'No autenticado']); exit; }
require "config/conexion.php";
if (!isset($pdo)) { echo json_encode(['success'=>false,'message'=>'Sin conexión']); exit; }

try {
    $id_socio     = $_POST['id_socio']      ?? null;
    $id_lpa       = $_POST['id_lpa']        ?? null;
    $fecha        = $_POST['fecha_venta']   ?? null;
    $pto_emision  = $_POST['punto_emision'] ?? null;
    $pto_venta    = $_POST['punto_venta']   ?? null;
    $numero       = $_POST['numero_doc']    ?? null;
    $producto     = $_POST['producto']      ?? null;
    $kg           = floatval($_POST['cantidad_kg'] ?? 0);
    $qq           = floatval($_POST['qq']          ?? 0);
    $precio       = floatval($_POST['precio_kg']   ?? 0);
    $total        = floatval($_POST['total']        ?? 0);
    $prima        = floatval($_POST['prima']        ?? 0);
    $comprador    = $_POST['comprador']     ?? null;
    $floid        = $_POST['floid']         ?? null;
    $observacion  = $_POST['observacion']   ?? '';

    if (!$id_socio || !$id_lpa || !$fecha || $kg <= 0) {
        echo json_encode(['success'=>false,'message'=>'Faltan datos requeridos']);
        exit;
    }

    $sql = "INSERT INTO tabla_ventas_externas
        (id_socio, id_lpa, fecha_venta, punto_emision, punto_venta, numero_doc,
         producto, qq, cantidad_kg, precio_kg, total, prima, comprador, floid,
         observacion, tipo_origen, fecha_registro, usuario_registro)
        VALUES
        (:id_socio, :id_lpa, :fecha, :pto_e, :pto_v, :numero,
         :producto, :qq, :kg, :precio, :total, :prima, :comprador, :floid,
         :obs, 'MANUAL', NOW(), :usuario)";

    $st = $pdo->prepare($sql);
    $st->execute([
        ':id_socio'  => $id_socio,
        ':id_lpa'    => $id_lpa,
        ':fecha'     => $fecha,
        ':pto_e'     => $pto_emision,
        ':pto_v'     => $pto_venta,
        ':numero'    => $numero,
        ':producto'  => $producto,
        ':qq'        => $qq,
        ':kg'        => $kg,
        ':precio'    => $precio,
        ':total'     => $total,
        ':prima'     => $prima,
        ':comprador' => $comprador,
        ':floid'     => $floid,
        ':obs'       => $observacion,
        ':usuario'   => $_SESSION['usuario'],
    ]);

    echo json_encode(['success'=>true,'message'=>'Venta externa registrada']);

} catch(PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
}
?>