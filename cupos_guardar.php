<?php
// cupos_guardar.php
// Acepta:
//   POST individual: id_lpa + nuevo_cupo   (application/x-www-form-urlencoded)
//   POST en lote:    { lote: [{id_lpa, nuevo_cupo}, ...] }  (application/json)
// Actualiza tabla_lpa.volumen_produccion_estimado
// También actualiza la distribución mensual proporcional

require "layout/bootstrap.php";
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
require __DIR__ . "/config/conexion.php";

// Distribución mensual por defecto (misma que usa el front)
const DIST = [
    'enero'      => 0.15, 'febrero'    => 0.10, 'marzo'      => 0.08,
    'abril'      => 0.05, 'mayo'       => 0.03, 'junio'      => 0.02,
    'julio'      => 0.02, 'agosto'     => 0.02, 'septiembre' => 0.03,
    'octubre'    => 0.05, 'noviembre'  => 0.20, 'diciembre'  => 0.25,
];

/**
 * Actualiza el volumen_produccion_estimado y la distribución mensual de un LPA.
 */
function actualizarCupo(PDO $pdo, int $idLpa, float $nuevoCupo): array {
    // Verificar que el LPA existe
    $stCheck = $pdo->prepare("SELECT id_lpa FROM tabla_lpa WHERE id_lpa = :id");
    $stCheck->bindValue(':id', $idLpa, PDO::PARAM_INT);
    $stCheck->execute();
    if (!$stCheck->fetch()) {
        return ['success' => false, 'message' => "LPA #{$idLpa} no encontrado"];
    }

    // Verificar que no haya consumido más de lo que vamos a asignar
    $stCons = $pdo->prepare("
        SELECT IFNULL(SUM(cantidad_vende), 0) AS consumido
        FROM tabla_ventas
        WHERE id_lpa = :id
    ");
    $stCons->bindValue(':id', $idLpa, PDO::PARAM_INT);
    $stCons->execute();
    $consumido = floatval($stCons->fetchColumn());

    // Construir UPDATE con meses
    $sets   = ['volumen_produccion_estimado = :cupo'];
    $params = [':cupo' => $nuevoCupo, ':id' => $idLpa];

    foreach (DIST as $mes => $pct) {
        $sets[]           = "`{$mes}` = :{$mes}";
        $params[":{$mes}"] = round($nuevoCupo * $pct, 2);
    }

    $sql = "UPDATE tabla_lpa SET " . implode(', ', $sets) . " WHERE id_lpa = :id";
    $st  = $pdo->prepare($sql);
    foreach ($params as $k => $v) $st->bindValue($k, $v);
    $st->execute();

    return [
        'success'    => true,
        'id_lpa'     => $idLpa,
        'nuevo_cupo' => $nuevoCupo,
        'consumido'  => $consumido,
        'disponible' => $nuevoCupo - $consumido,
    ];
}

try {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    // ── LOTE (JSON) ───────────────────────────────────────────────────────────
    if (strpos($contentType, 'application/json') !== false) {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        if (empty($data['lote']) || !is_array($data['lote'])) {
            echo json_encode(['success' => false, 'message' => 'Payload inválido']);
            exit;
        }

        $actualizados = 0;
        $errores      = [];

        $pdo->beginTransaction();
        foreach ($data['lote'] as $item) {
            $idLpa     = intval($item['id_lpa']     ?? 0);
            $nuevoCupo = floatval($item['nuevo_cupo'] ?? -1);

            if (!$idLpa || $nuevoCupo < 0) {
                $errores[] = "Item inválido: " . json_encode($item);
                continue;
            }

            $res = actualizarCupo($pdo, $idLpa, $nuevoCupo);
            if ($res['success']) {
                $actualizados++;
            } else {
                $errores[] = $res['message'];
            }
        }
        $pdo->commit();

        echo json_encode([
            'success'     => true,
            'actualizados'=> $actualizados,
            'errores'     => $errores,
            'message'     => "Se actualizaron {$actualizados} cupo(s) correctamente.",
        ]);
        exit;
    }

    // ── INDIVIDUAL (form-urlencoded) ──────────────────────────────────────────
    $idLpa     = intval($_POST['id_lpa']     ?? 0);
    $nuevoCupo = floatval($_POST['nuevo_cupo'] ?? -1);

    if (!$idLpa || $nuevoCupo < 0) {
        echo json_encode(['success' => false, 'message' => 'Datos requeridos: id_lpa y nuevo_cupo']);
        exit;
    }

    $resultado = actualizarCupo($pdo, $idLpa, $nuevoCupo);
    echo json_encode($resultado);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("cupos_guardar.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("cupos_guardar.php (general): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>