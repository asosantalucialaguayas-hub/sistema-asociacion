<?php
// ============================================================
// ajax_resumen_asistencia.php
// Devuelve datos de asistencia para el resumen público
// y para el auto-refresh de asistencia.php
// ============================================================
require __DIR__ . "/layout/bootstrap.php";
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$conv_id = intval($_GET['conv_id'] ?? 0);
if (!$conv_id) {
    echo json_encode(['ok' => false, 'msg' => 'Falta conv_id']);
    exit;
}

try {
    // Datos de la convocatoria
    $stC = $pdo->prepare("SELECT id, titulo, fecha, hora, lugar, estado FROM convocatorias WHERE id = ?");
    $stC->execute([$conv_id]);
    $conv = $stC->fetch(PDO::FETCH_ASSOC);

    if (!$conv) {
        echo json_encode(['ok' => false, 'msg' => 'Convocatoria no encontrada']);
        exit;
    }

    // Total socios activos
    $total = (int)$pdo->query("SELECT COUNT(*) FROM socios WHERE estado='activo'")->fetchColumn();

    // Presentes
    $stP = $pdo->prepare("SELECT COUNT(*) FROM conv_asistencia WHERE convocatoria_id = ?");
    $stP->execute([$conv_id]);
    $presentes = (int)$stP->fetchColumn();

    $porcentaje = $total > 0 ? round(($presentes / $total) * 100, 1) : 0;

    // Últimos 10 registros
    $stU = $pdo->prepare("
        SELECT
            a.hora_registro,
            a.metodo,
            s.nombre_completo,
            s.identificacion AS cedula
        FROM conv_asistencia a
        JOIN socios s ON s.id_socio = a.id_socio
        WHERE a.convocatoria_id = ?
        ORDER BY a.hora_registro DESC
        LIMIT 10
    ");
    $stU->execute([$conv_id]);
    $ultimos = $stU->fetchAll(PDO::FETCH_ASSOC);

    // Formatear hora
    foreach ($ultimos as &$u) {
        $u['hora_registro'] = date('H:i:s', strtotime($u['hora_registro']));
    }

    echo json_encode([
        'ok'         => true,
        'titulo'     => $conv['titulo'],
        'fecha'      => date('d/m/Y', strtotime($conv['fecha'])),
        'hora'       => substr($conv['hora'], 0, 5),
        'lugar'      => $conv['lugar'],
        'estado'     => $conv['estado'],
        'total'      => $total,
        'presentes'  => $presentes,
        'ausentes'   => max(0, $total - $presentes),
        'porcentaje' => $porcentaje,
        'ultimos'    => $ultimos,
    ]);

} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
