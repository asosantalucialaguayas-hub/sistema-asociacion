<?php
// ============================================================
// ajax_registrar_asistencia.php – Adaptado BD real
// Corregido: total correcto para convocatorias solo_directivos
// ============================================================
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . "/layout/bootstrap.php";
ob_clean();

header('Content-Type: application/json; charset=utf-8');

function jsonOut(array $data): void {
    ob_clean();
    echo json_encode($data);
    exit;
}

define('BIO_TOKEN', getenv('BIO_TOKEN') ?: 'CAMBIAR_TOKEN_SECRETO_AQUI');

$data     = json_decode(file_get_contents('php://input'), true) ?: [];
$conv_id  = intval($data['convocatoria_id'] ?? 0);
$socio_id = intval($data['socio_id']        ?? 0);
$metodo   = in_array($data['metodo'] ?? '', ['manual','biometrico','qr'])
              ? $data['metodo'] : 'manual';
$token    = $data['token'] ?? '';

$autenticado = isset($_SESSION['usuario'])
    || ($metodo === 'biometrico' && $token === BIO_TOKEN);

if (!$autenticado)       jsonOut(['ok'=>false,'msg'=>'No autorizado']);
if (!$conv_id || !$socio_id) jsonOut(['ok'=>false,'msg'=>'Datos incompletos']);

// ── Verificar convocatoria ────────────────────────────────────
try {
    $stC = $pdo->prepare("SELECT estado, tipo_asistentes FROM convocatorias WHERE id=?");
    $stC->execute([$conv_id]);
    $conv = $stC->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    jsonOut(['ok'=>false,'msg'=>'Error DB (conv): '.$e->getMessage()]);
}

if (!$conv || $conv['estado'] !== 'activa') {
    jsonOut(['ok'=>false,'msg'=>'La convocatoria no está activa']);
}

$solo_directivos = ($conv['tipo_asistentes'] ?? 'general') === 'solo_directivos';

// ── Verificar socio ───────────────────────────────────────────
try {
    $stS = $pdo->prepare("
        SELECT id_socio, nombre_completo, identificacion
        FROM socios WHERE id_socio=? AND estado='activo'
    ");
    $stS->execute([$socio_id]);
    $socio = $stS->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    jsonOut(['ok'=>false,'msg'=>'Error DB (socio): '.$e->getMessage()]);
}

if (!$socio) jsonOut(['ok'=>false,'msg'=>'Socio no encontrado o inactivo']);

// ── Si es solo directivos: verificar que el socio pertenece ──
if ($solo_directivos) {
    try {
        $stD = $pdo->prepare("
            SELECT COUNT(*) FROM directiva_miembros dm
            JOIN directiva_periodos dp ON dp.id = dm.periodo_id AND dp.estado = 'activo'
            WHERE dm.cedula_manual = ?
               OR dm.socio_id     = ?
        ");
        $stD->execute([$socio['identificacion'], $socio_id]);
        if ((int)$stD->fetchColumn() === 0) {
            jsonOut(['ok'=>false,'msg'=>$socio['nombre_completo'].' no es miembro de la directiva activa']);
        }
    } catch (PDOException $e) {
        // Si falla la verificación, permitir igual (graceful)
    }
}

// ── Insertar asistencia ───────────────────────────────────────
try {
    $ins = $pdo->prepare("
        INSERT INTO conv_asistencia
            (convocatoria_id, id_socio, hora_registro, metodo, registrado_por)
        VALUES (?, ?, NOW(), ?, ?)
        ON DUPLICATE KEY UPDATE hora_registro = hora_registro
    ");
    $reg_por = $metodo === 'biometrico' ? null : intval($_SESSION['id_usuario'] ?? 0);
    $ins->execute([$conv_id, $socio_id, $metodo, $reg_por]);

    if ($ins->rowCount() > 0) {

        // ── Total según tipo de convocatoria ──────────────────
        if ($solo_directivos) {
            // Contar directivos únicos del período activo (por cédula)
            $total = (int)$pdo->query("
                SELECT COUNT(DISTINCT COALESCE(s.identificacion, dm.cedula_manual))
                FROM directiva_miembros dm
                JOIN directiva_periodos dp ON dp.id = dm.periodo_id AND dp.estado = 'activo'
                LEFT JOIN socios s ON s.identificacion = dm.cedula_manual AND s.estado = 'activo'
            ")->fetchColumn();
        } else {
            $total = (int)$pdo->query(
                "SELECT COUNT(*) FROM socios WHERE estado='activo'"
            )->fetchColumn();
        }

        $stPr = $pdo->prepare(
            "SELECT COUNT(*) FROM conv_asistencia WHERE convocatoria_id=?"
        );
        $stPr->execute([$conv_id]);
        $presentes = (int)$stPr->fetchColumn();

        $pct = $total > 0 ? round(($presentes / $total) * 100, 1) : 0;

        jsonOut([
            'ok'         => true,
            'msg'        => 'Registrado',
            'socio'      => $socio['nombre_completo'],
            'presentes'  => $presentes,
            'total'      => $total,
            'porcentaje' => $pct,
        ]);

    } else {
        jsonOut([
            'ok'  => false,
            'msg' => $socio['nombre_completo'] . ' ya fue registrado anteriormente',
        ]);
    }

} catch (PDOException $e) {
    jsonOut(['ok'=>false,'msg'=>'Error DB: '.$e->getMessage()]);
}