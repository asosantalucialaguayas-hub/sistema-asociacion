<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}

require "config/conexion.php";

/**
 * pago_inscripcion_guardar.php
 * - Recibe POST del formulario de abono
 * - Si no existe cabecera (pago_inscripcion) para el id_acuerdo => la crea (snapshot)
 * - Inserta abono en pago_inscripcion_abono
 * - Recalcula total pagado y actualiza estado (PENDIENTE/PARCIAL/PAGADO)
 * - Responde JSON (para fetch)
 *
 * POST esperado:
 *  - id_acuerdo (int)  [obligatorio]
 *  - monto_usd (decimal) [obligatorio]
 *  - metodo_pago (EFECTIVO/TRANSFERENCIA/DEPOSITO/OTRO) [obligatorio]
 *  - referencia (string) [opcional]
 *  - observacion (string) [opcional]
 *  - comprobante_pdf (file pdf) [opcional]
 */

header('Content-Type: application/json');

function calcular_tarifa_por_has(float $hasTotal): float {
    if ($hasTotal >= 1 && $hasTotal <= 5) return 200.00;
    if ($hasTotal >= 6 && $hasTotal <= 10) return 250.00;
    if ($hasTotal >= 11 && $hasTotal <= 20) return 300.00;
    if ($hasTotal >= 21 && $hasTotal <= 30) return 350.00;
    return 0.00;
}

function fail(string $msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Método no permitido.');
}

$id_acuerdo = (int)($_POST['id_acuerdo'] ?? 0);
$monto      = (float)($_POST['monto_usd'] ?? 0);
$metodo     = trim($_POST['metodo_pago'] ?? '');
$referencia = trim($_POST['referencia'] ?? '');
$obs        = trim($_POST['observacion'] ?? '');

if ($id_acuerdo <= 0) fail('Acuerdo inválido.');
if ($monto <= 0) fail('El monto debe ser mayor a 0.');
if ($metodo === '') fail('Seleccione un método de pago.');

// subir PDF opcional
$rutaPdf = null;
if (!empty($_FILES['comprobante_pdf']['name'])) {
    $dir = __DIR__ . "/uploads/comprobantes_inscripcion";
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $ext = strtolower(pathinfo($_FILES['comprobante_pdf']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') fail('Solo se permite PDF.');

    $nombre = "comp_insc_" . time() . "_" . rand(1000,9999) . ".pdf";
    $destino = $dir . "/" . $nombre;

    if (!move_uploaded_file($_FILES['comprobante_pdf']['tmp_name'], $destino)) {
        fail('No se pudo subir el PDF.');
    }
    $rutaPdf = "uploads/comprobantes_inscripcion/" . $nombre;
}

try {
    // 1) Traer acuerdo para snapshot
    $stmt = $pdo->prepare("
        SELECT
            id_acuerdo,
            id_socio,
            COALESCE(cacao_nacional_has,0) AS has_nac,
            COALESCE(cacao_ccn51_has,0)   AS has_ccn
        FROM acuerdo_productor
        WHERE id_acuerdo = ?
        LIMIT 1
    ");
    $stmt->execute([$id_acuerdo]);
    $a = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$a) fail('No existe el acuerdo.');

    $hasNac   = (float)$a['has_nac'];
    $hasCcn   = (float)$a['has_ccn'];
    $hasTotal = $hasNac + $hasCcn;

    $debe = calcular_tarifa_por_has($hasTotal);
    if ($debe <= 0) fail('El total de hectáreas está fuera de la tabla de tarifas.');

    $pdo->beginTransaction();

    // 2) Buscar o crear cabecera
    $stmt = $pdo->prepare("SELECT id_pago, total_debe_usd FROM pago_inscripcion WHERE id_acuerdo = ? LIMIT 1");
    $stmt->execute([$id_acuerdo]);
    $cab = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cab) {
        $id_pago = (int)$cab['id_pago'];
        // Si ya existía, respeta el total_debe_usd guardado (snapshot)
        $debe = (float)$cab['total_debe_usd'];
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO pago_inscripcion
                (id_acuerdo, id_socio, has_nacional, has_ccn51, has_total, total_debe_usd, estado)
            VALUES (?,?,?,?,?,?, 'PENDIENTE')
        ");
        $stmt->execute([
            $id_acuerdo,
            (int)$a['id_socio'],
            $hasNac,
            $hasCcn,
            $hasTotal,
            $debe
        ]);
        $id_pago = (int)$pdo->lastInsertId();
    }

    // 3) Insertar abono
    $stmt = $pdo->prepare("
        INSERT INTO pago_inscripcion_abono
            (id_pago, monto_usd, metodo_pago, referencia, comprobante_pdf, observacion, estado)
        VALUES (?,?,?,?,?,?,'REGISTRADO')
    ");
    $stmt->execute([
        $id_pago,
        $monto,
        $metodo,
        $referencia !== '' ? $referencia : null,
        $rutaPdf,
        $obs !== '' ? $obs : null
    ]);

    // 4) Recalcular total pagado
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(monto_usd),0)
        FROM pago_inscripcion_abono
        WHERE id_pago = ? AND estado='REGISTRADO'
    ");
    $stmt->execute([$id_pago]);
    $totalPagado = (float)$stmt->fetchColumn();

    // 5) Estado
    $nuevoEstado = 'PENDIENTE';
    if ($totalPagado > 0 && $totalPagado < $debe) $nuevoEstado = 'PARCIAL';
    if ($totalPagado >= $debe) $nuevoEstado = 'PAGADO';

    $stmt = $pdo->prepare("UPDATE pago_inscripcion SET estado = ? WHERE id_pago = ?");
    $stmt->execute([$nuevoEstado, $id_pago]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Abono registrado correctamente.',
        'id_pago' => $id_pago,
        'estado'  => $nuevoEstado,
        'debe' => $debe,
        'totalPagado' => $totalPagado,
        'saldo' => max(0, $debe - $totalPagado),
        'ruta_pdf' => $rutaPdf
    ]);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fail('Error al guardar: ' . $e->getMessage());
}
