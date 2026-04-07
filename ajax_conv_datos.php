<?php
// ============================================================
// ajax_conv_datos.php  – Datos de una convocatoria para editar
// ============================================================
if (session_status()===PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }
require __DIR__ . "/layout/bootstrap.php";
header('Content-Type: application/json; charset=utf-8');

$id = intval($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }

try {
    $stC = $pdo->prepare("SELECT * FROM convocatorias WHERE id=?");
    $stC->execute([$id]);
    $conv = $stC->fetch(PDO::FETCH_ASSOC);
    if (!$conv) { echo json_encode(['ok'=>false,'msg'=>'No encontrada']); exit; }

    $stP = $pdo->prepare("SELECT descripcion FROM convocatoria_puntos WHERE convocatoria_id=? ORDER BY numero");
    $stP->execute([$id]);
    $puntos = $stP->fetchAll(PDO::FETCH_COLUMN);

    $stF = $pdo->prepare("SELECT cargo,nombre FROM convocatoria_firmas WHERE convocatoria_id=? ORDER BY orden");
    $stF->execute([$id]);
    $firmas = $stF->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok'=>true,'conv'=>$conv,'puntos'=>$puntos,'firmas'=>$firmas]);
} catch(Exception $e) {
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
