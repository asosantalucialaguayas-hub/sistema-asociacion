<?php
// ============================================================
// biometrico_endpoint.php  – Endpoint para biométrico
// BD real: socios.identificacion (no cedula)
// Convocatorias: fecha (no fecha_reunion)
// ============================================================
define('BIO_TOKEN', getenv('BIO_TOKEN') ?: 'CAMBIAR_TOKEN_SECRETO_AQUI');

require __DIR__ . "/layout/bootstrap.php";
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
if ($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['ok'=>false,'msg'=>'Solo POST']);exit;}

$raw  = file_get_contents('php://input');
$data = json_decode($raw,true);
if (!$data) $data=$_POST;

$token = $data['token']??$data['Token']??'';
$ident = $data['cedula']??$data['Cedula']??$data['identificacion']??$data['employee_id']??$data['card_number']??$data['EmployeeNo']??'';

if ($token!==BIO_TOKEN) {
    try{$pdo->prepare("INSERT INTO biometrico_log(cedula_recibida,resultado,ip_origen) VALUES(?,?,?)")->execute([$ident,'error',$_SERVER['REMOTE_ADDR']??'']);}catch(Exception $e){}
    http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'Token inválido']); exit;
}

$ident = preg_replace('/\D/','',$ident);
if (strlen($ident)<6){echo json_encode(['ok'=>false,'msg'=>'Identificación inválida']);exit;}

// Buscar socio por identificacion
$stS=$pdo->prepare("SELECT id_socio,nombre_completo FROM socios WHERE identificacion=? AND estado='activo'");
$stS->execute([$ident]); $socio=$stS->fetch(PDO::FETCH_ASSOC);
if (!$socio){
    try{$pdo->prepare("INSERT INTO biometrico_log(cedula_recibida,resultado,ip_origen) VALUES(?,?,?)")->execute([$ident,'no_encontrado',$_SERVER['REMOTE_ADDR']??'']);}catch(Exception $e){}
    echo json_encode(['ok'=>false,'msg'=>"Socio con identificación $ident no registrado"]);exit;
}

// Buscar convocatoria activa HOY (usa columna 'fecha' real)
$conv=$pdo->query("SELECT id FROM convocatorias WHERE estado='activa' AND fecha=CURDATE() ORDER BY hora DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$conv){
    try{$pdo->prepare("INSERT INTO biometrico_log(cedula_recibida,id_socio,resultado,ip_origen) VALUES(?,?,?,?)")->execute([$ident,$socio['id_socio'],'sin_sesion',$_SERVER['REMOTE_ADDR']??'']);}catch(Exception $e){}
    echo json_encode(['ok'=>false,'msg'=>'No hay sesión activa hoy']); exit;
}

$conv_id=$conv['id'];
try {
    $ins=$pdo->prepare("
        INSERT INTO conv_asistencia (convocatoria_id,id_socio,hora_registro,metodo,registrado_por)
        VALUES (?,?,NOW(),'biometrico',NULL)
        ON DUPLICATE KEY UPDATE hora_registro=hora_registro
    ");
    $ins->execute([$conv_id,$socio['id_socio']]);
    $res=$ins->rowCount()>0?'ok':'ya_registrado';
    try{$pdo->prepare("INSERT INTO biometrico_log(convocatoria_id,cedula_recibida,id_socio,resultado,ip_origen) VALUES(?,?,?,?,?)")->execute([$conv_id,$ident,$socio['id_socio'],$res,$_SERVER['REMOTE_ADDR']??'']);}catch(Exception $e){}

    if ($ins->rowCount()>0) {
        $total=(int)$pdo->query("SELECT COUNT(*) FROM socios WHERE estado='activo'")->fetchColumn();
        $stPr=$pdo->prepare("SELECT COUNT(*) FROM conv_asistencia WHERE convocatoria_id=?");
        $stPr->execute([$conv_id]); $pres=(int)$stPr->fetchColumn();
        $pct=$total>0?round(($pres/$total)*100,1):0;
        echo json_encode(['ok'=>true,'msg'=>'Bienvenido/a, '.$socio['nombre_completo'],'socio'=>$socio['nombre_completo'],'presentes'=>$pres,'total'=>$total,'porcentaje'=>$pct]);
    } else {
        echo json_encode(['ok'=>false,'msg'=>$socio['nombre_completo'].' ya fue registrado/a']);
    }
} catch(PDOException $e){echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);}