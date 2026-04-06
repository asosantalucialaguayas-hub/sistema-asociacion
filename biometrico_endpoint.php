<?php
// ============================================================
// biometrico_endpoint.php
// ============================================================
// CONFIGURAR EN EL DISPOSITIVO (Hikvision / ZKTeco / Anviz):
//   URL: https://asosantalucialaguayas.com/asosantalu/biometrico_endpoint.php
//   Método: POST (JSON o form-data)
//   Parámetros: { "cedula":"0912345678", "token":"CAMBIAR_TOKEN_SECRETO_2026" }
//
// TAMBIÉN acepta formato Hikvision nativo con campo "employee_id" o "card_number"
// ============================================================
define('BIO_TOKEN', getenv('BIO_TOKEN') ?: 'CAMBIAR_TOKEN_SECRETO_2026');

require __DIR__ . "/layout/bootstrap.php";
header('Content-Type: application/json; charset=utf-8');
// Permitir CORS si el biométrico lo necesita
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD']==='OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD']!=='POST')    { http_response_code(405); echo json_encode(['ok'=>false,'msg'=>'Método no permitido']); exit; }

// Leer datos (JSON o form-data)
$raw  = file_get_contents('php://input');
$data = json_decode($raw,true);
if (!$data) $data = $_POST;

$token  = $data['token']  ?? $data['Token']  ?? '';
$cedula = $data['cedula'] ?? $data['Cedula'] ?? $data['employee_id'] ?? $data['card_number'] ?? $data['EmployeeNo'] ?? '';

// Validar token
if ($token !== BIO_TOKEN) {
    // Log intento fallido
    try { $pdo->prepare("INSERT INTO biometrico_log (cedula_recibida,resultado,ip_origen) VALUES (?,?,?)")->execute([$cedula,'error',$_SERVER['REMOTE_ADDR']??'']); } catch(Exception $e){}
    http_response_code(401);
    echo json_encode(['ok'=>false,'msg'=>'Token inválido']);
    exit;
}

$cedula = preg_replace('/\D/','',$cedula);
if (strlen($cedula)<6) { echo json_encode(['ok'=>false,'msg'=>'Cédula inválida']); exit; }

// Buscar socio
$stS = $pdo->prepare("SELECT id_socio,nombre_completo FROM socios WHERE cedula=? AND estado='activo'");
$stS->execute([$cedula]);
$socio = $stS->fetch(PDO::FETCH_ASSOC);

if (!$socio) {
    try { $pdo->prepare("INSERT INTO biometrico_log (cedula_recibida,resultado,ip_origen) VALUES (?,?,?)")->execute([$cedula,'no_encontrado',$_SERVER['REMOTE_ADDR']??'']); } catch(Exception $e){}
    echo json_encode(['ok'=>false,'msg'=>"Socio con cédula $cedula no registrado o inactivo"]);
    exit;
}

// Buscar convocatoria activa HOY
$conv = $pdo->query("SELECT id FROM convocatorias WHERE estado='activa' AND fecha_reunion=CURDATE() ORDER BY hora_reunion DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$conv) {
    try { $pdo->prepare("INSERT INTO biometrico_log (cedula_recibida,id_socio,resultado,ip_origen) VALUES (?,?,?,?)")->execute([$cedula,$socio['id_socio'],'sin_sesion',$_SERVER['REMOTE_ADDR']??'']); } catch(Exception $e){}
    echo json_encode(['ok'=>false,'msg'=>'No hay sesión activa hoy']);
    exit;
}

$conv_id = $conv['id'];

// Registrar asistencia
try {
    $ins = $pdo->prepare("
        INSERT INTO conv_asistencia (convocatoria_id,id_socio,hora_registro,metodo,registrado_por)
        VALUES (?,?,NOW(),'biometrico',NULL)
        ON DUPLICATE KEY UPDATE hora_registro=hora_registro
    ");
    $ins->execute([$conv_id,$socio['id_socio']]);

    $resultado = $ins->rowCount()>0 ? 'ok' : 'ya_registrado';
    try { $pdo->prepare("INSERT INTO biometrico_log (convocatoria_id,cedula_recibida,id_socio,resultado,ip_origen) VALUES (?,?,?,?,?)")->execute([$conv_id,$cedula,$socio['id_socio'],$resultado,$_SERVER['REMOTE_ADDR']??'']); } catch(Exception $e){}

    if ($ins->rowCount()>0) {
        $total    = $pdo->query("SELECT COUNT(*) FROM socios WHERE estado='activo'")->fetchColumn();
        $stPr     = $pdo->prepare("SELECT COUNT(*) FROM conv_asistencia WHERE convocatoria_id=?");
        $stPr->execute([$conv_id]);
        $presentes = $stPr->fetchColumn();
        $pct = $total>0 ? round(($presentes/$total)*100,1):0;

        echo json_encode([
            'ok'         => true,
            'msg'        => 'Bienvenido/a, '.$socio['nombre_completo'],
            'socio'      => $socio['nombre_completo'],
            'presentes'  => (int)$presentes,
            'total'      => (int)$total,
            'porcentaje' => $pct
        ]);
    } else {
        echo json_encode(['ok'=>false,'msg'=>$socio['nombre_completo'].' ya fue registrado/a anteriormente']);
    }
} catch(PDOException $e) {
    echo json_encode(['ok'=>false,'msg'=>'Error: '.$e->getMessage()]);
}