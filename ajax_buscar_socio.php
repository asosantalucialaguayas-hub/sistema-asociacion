<?php
// ============================================================
// ajax_buscar_socio.php  VERSIÓN CORREGIDA
// Lee los nombres REALES de columnas en tiempo de ejecución
// ============================================================
if (session_status()===PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { echo json_encode([]); exit; }
require __DIR__ . "/layout/bootstrap.php";
header('Content-Type: application/json; charset=utf-8');

// Detectar columnas reales de la tabla socios
function detectarCampos(PDO $pdo): array {
    // Usamos la sesión como caché para no hacer SHOW COLUMNS en cada llamada
    if (isset($_SESSION['_campos_socios'])) return $_SESSION['_campos_socios'];
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM socios")->fetchAll(PDO::FETCH_COLUMN);
    } catch(Exception $e) {
        return ['pk'=>'id_socio','nombre'=>'nombre_completo','cedula'=>'cedula','estado'=>'estado','apellido'=>null,'cols'=>[]];
    }
    $pk     = 'id_socio';  foreach(['id_socio','id','socio_id'] as $c){ if(in_array($c,$cols)){$pk=$c;break;} }
    $nombre = 'nombre_completo'; foreach(['nombre_completo','nombre','nombres','nombre_socio'] as $c){ if(in_array($c,$cols)){$nombre=$c;break;} }
    $cedula = 'cedula';    foreach(['cedula','ci','dni','documento','ruc'] as $c){ if(in_array($c,$cols)){$cedula=$c;break;} }
    $estado = 'estado';    foreach(['estado','status'] as $c){ if(in_array($c,$cols)){$estado=$c;break;} }
    $apellido = null;
    if (!in_array($nombre,$cols)) {
        foreach(['apellidos','apellido','primer_apellido'] as $c){ if(in_array($c,$cols)){$apellido=$c;break;} }
    }
    $r = compact('pk','nombre','cedula','estado','apellido','cols');
    $_SESSION['_campos_socios'] = $r;
    return $r;
}

$c       = detectarCampos($pdo);
$conv_id = intval($_GET['conv_id'] ?? 0);
$q       = '%'.trim($_GET['q'] ?? '').'%';

// Expresión para nombre completo
$expr = in_array($c['nombre'], $c['cols']) ? "s.`{$c['nombre']}`" : "s.`{$c['nombre']}`";
if ($c['apellido'] && !in_array($c['nombre'],$c['cols'])) {
    // nombres + apellidos separados
    $expr = "CONCAT(s.`{$c['nombre']}`,' ',s.`{$c['apellido']}`)";
}

// Valor del estado activo (puede ser 'activo', '1', 'si', etc.)
// Intentamos con 'activo' primero; si no hay resultados el filtro se omite
$val_activo = 'activo';

try {
    $sql = "
        SELECT s.`{$c['pk']}` AS id,
               s.`{$c['cedula']}` AS cedula,
               $expr AS nombre_completo,
               IF(a.id IS NOT NULL,1,0) AS ya_registro
        FROM socios s
        LEFT JOIN conv_asistencia a ON a.id_socio=s.`{$c['pk']}` AND a.convocatoria_id=?
        WHERE s.`{$c['estado']}`='$val_activo'
          AND ($expr LIKE ? OR s.`{$c['cedula']}` LIKE ?)
        ORDER BY $expr
        LIMIT 10
    ";
    $st = $pdo->prepare($sql);
    $st->execute([$conv_id, $q, $q]);
    $socios = $st->fetchAll(PDO::FETCH_ASSOC);

    // Si no hay resultados con filtro de activo, intentar sin él
    if (empty($socios)) {
        $sql2 = "
            SELECT s.`{$c['pk']}` AS id,
                   s.`{$c['cedula']}` AS cedula,
                   $expr AS nombre_completo,
                   IF(a.id IS NOT NULL,1,0) AS ya_registro
            FROM socios s
            LEFT JOIN conv_asistencia a ON a.id_socio=s.`{$c['pk']}` AND a.convocatoria_id=?
            WHERE ($expr LIKE ? OR s.`{$c['cedula']}` LIKE ?)
            ORDER BY $expr
            LIMIT 10
        ";
        $st2 = $pdo->prepare($sql2);
        $st2->execute([$conv_id, $q, $q]);
        $socios = $st2->fetchAll(PDO::FETCH_ASSOC);
    }

    foreach($socios as &$s) {
        $partes = explode(' ', trim($s['nombre_completo']));
        $s['iniciales'] = strtoupper(substr($partes[0],0,1).(isset($partes[1])?substr($partes[1],0,1):''));
    }
    echo json_encode($socios);

} catch(Exception $e) {
    // Devolver error legible para depuración
    echo json_encode([['_error' => $e->getMessage()]]);
}