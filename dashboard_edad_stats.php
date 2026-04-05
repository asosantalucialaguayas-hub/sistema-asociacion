<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['usuario'])) { echo json_encode(['success'=>false]); exit; }
require __DIR__ . "/config/conexion.php";

try {
    $stmt = $pdo->query("
        SELECT
            UPPER(TRIM(COALESCE(sexo,''))) AS sexo,
            CASE
                WHEN fecha_nacimiento IS NULL
                  OR TRIM(fecha_nacimiento)=''
                  OR fecha_nacimiento='0000-00-00' THEN 'SD'
                WHEN (YEAR(CURDATE()) - YEAR(fecha_nacimiento)
                     - (DATE_FORMAT(CURDATE(),'%m%d') < DATE_FORMAT(fecha_nacimiento,'%m%d')))
                     BETWEEN 18 AND 35 THEN 'JOVEN'
                WHEN (YEAR(CURDATE()) - YEAR(fecha_nacimiento)
                     - (DATE_FORMAT(CURDATE(),'%m%d') < DATE_FORMAT(fecha_nacimiento,'%m%d')))
                     BETWEEN 36 AND 70 THEN 'ADULTO'
                WHEN (YEAR(CURDATE()) - YEAR(fecha_nacimiento)
                     - (DATE_FORMAT(CURDATE(),'%m%d') < DATE_FORMAT(fecha_nacimiento,'%m%d')))
                     >= 71 THEN 'MAYOR'
                ELSE 'SD'
            END AS rango
        FROM socios
    ");

    $c = ['joven_m'=>0,'joven_f'=>0,'adulto_m'=>0,'adulto_f'=>0,'mayor_m'=>0,'mayor_f'=>0,'sd_m'=>0,'sd_f'=>0];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $sexo  = $r['sexo'];
        $rango = $r['rango'];
        $pre   = match($rango) { 'JOVEN'=>'joven_','ADULTO'=>'adulto_','MAYOR'=>'mayor_', default=>'sd_' };
        $suf   = match($sexo)  { 'M'=>'m','F'=>'f', default=>'m' }; // sin sexo → m para no perder el conteo
        $c[$pre.$suf]++;
    }

    echo json_encode(array_merge(['success'=>true], $c), JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
