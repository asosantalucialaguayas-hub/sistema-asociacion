<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require "config/conexion.php";
require_once "helpers/periodo.php";

header('Content-Type: application/json');

// ── Obtener período abierto — OBLIGATORIO ────────────────────────────────────
$periodoAbierto = get_periodo_abierto($pdo);
if (!$periodoAbierto) {
    echo json_encode([
        'success' => false,
        'message' => 'No hay un período abierto. Por favor abra un período antes de registrar acuerdos.'
    ]);
    exit;
}
$periodo_id = (int)$periodoAbierto['id_periodo'];

// ── Validar campos requeridos ────────────────────────────────────────────────
$cedula            = trim($_POST['cedula']            ?? '');
$nombres_completos = trim($_POST['nombres_completos'] ?? '');

if ($cedula === '' || $nombres_completos === '') {
    echo json_encode(['success' => false, 'message' => 'La cédula y nombres son obligatorios.']);
    exit;
}

// ── Verificar duplicado en el mismo período ──────────────────────────────────
$check = $pdo->prepare("
    SELECT id_acuerdo FROM acuerdo_productor
    WHERE cedula = ? AND id_periodo = ?
");
$check->execute([$cedula, $periodo_id]);
if ($check->fetch()) {
    echo json_encode([
        'success' => false,
        'message' => "Ya existe un acuerdo para la cédula {$cedula} en el período: {$periodoAbierto['nombre']}."
    ]);
    exit;
}

// ── Generar número de acuerdo automático ─────────────────────────────────────
// Formato: ACU-{año}-{correlativo 4 dígitos}  →  ACU-2026-0005
$anio  = date('Y');
$stUlt = $pdo->prepare("
    SELECT numero_acuerdo FROM acuerdo_productor
    WHERE id_periodo = ? AND numero_acuerdo LIKE ?
    ORDER BY id_acuerdo DESC LIMIT 1
");
$stUlt->execute([$periodo_id, "ACU-{$anio}-%"]);
$ultimoNum = $stUlt->fetchColumn();

if ($ultimoNum) {
    $partes      = explode('-', $ultimoNum);
    $correlativo = (int)end($partes) + 1;
} else {
    $correlativo = 1;
}
$numero_acuerdo = 'ACU-' . $anio . '-' . str_pad($correlativo, 4, '0', STR_PAD_LEFT);

// ── INSERT — id_periodo SIEMPRE presente ─────────────────────────────────────
try {
    $pdo->prepare("
        INSERT INTO acuerdo_productor (
            id_periodo,
            numero_acuerdo,
            cedula,
            fecha_nacimiento,
            nombres_completos,
            provincia,
            canton,
            parroquia,
            sector,
            posee_riego,
            periodo_de_fertilizacion,
            cacao_nacional_has,
            estimado_produccion_nacional,
            cacao_ccn51_has,
            estimado_produccion_ccn51,
            fecha_firma
        ) VALUES (
            :id_periodo,
            :numero_acuerdo,
            :cedula,
            :fecha_nacimiento,
            :nombres_completos,
            :provincia,
            :canton,
            :parroquia,
            :sector,
            :posee_riego,
            :periodo_de_fertilizacion,
            :cacao_nacional_has,
            :estimado_produccion_nacional,
            :cacao_ccn51_has,
            :estimado_produccion_ccn51,
            :fecha_firma
        )
    ")->execute([
        ':id_periodo'                   => $periodo_id,           // ← SIEMPRE del periodo abierto
        ':numero_acuerdo'               => $numero_acuerdo,
        ':cedula'                       => $cedula,
        ':fecha_nacimiento'             => $_POST['fecha_nacimiento']             ?: null,
        ':nombres_completos'            => $nombres_completos,
        ':provincia'                    => trim($_POST['provincia']               ?? ''),
        ':canton'                       => trim($_POST['canton']                  ?? ''),
        ':parroquia'                    => trim($_POST['parroquia']               ?? ''),
        ':sector'                       => trim($_POST['sector']                  ?? ''),
        ':posee_riego'                  => $_POST['posee_riego']                  ?? 'NO',
        ':periodo_de_fertilizacion'     => trim($_POST['periodo_de_fertilizacion'] ?? ''),
        ':cacao_nacional_has'           => floatval($_POST['cacao_nacional_has']   ?? 0),
        ':estimado_produccion_nacional' => floatval($_POST['estimado_produccion_nacional'] ?? 0),
        ':cacao_ccn51_has'              => floatval($_POST['cacao_ccn51_has']      ?? 0),
        ':estimado_produccion_ccn51'    => floatval($_POST['estimado_produccion_ccn51']    ?? 0),
        ':fecha_firma'                  => $_POST['fecha_firma']                  ?: null,
    ]);

    $id = $pdo->lastInsertId();

    // ── Marcar solicitud como APROBADO ───────────────────────────────────────
    $pdo->prepare("
        UPDATE solicitud_ingreso
        SET estado_solicitud = 'APROBADO'
        WHERE identificacion = ? AND id_periodo = ?
    ")->execute([$cedula, $periodo_id]);

    echo json_encode([
        'success'        => true,
        'message'        => 'Acuerdo guardado correctamente.',
        'numero_acuerdo' => $numero_acuerdo,
        'id_acuerdo'     => $id,
        'id_periodo'     => $periodo_id,
        'periodo'        => $periodoAbierto['nombre'],
    ]);

} catch (PDOException $e) {
    error_log("acuerdo_guardar: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
}
?>