<?php
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require 'config/conexion.php';

$id = isset($_POST['id_solicitud']) ? (int)$_POST['id_solicitud'] : 0;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Obtener la cédula y período de la solicitud
    $stmt = $pdo->prepare('SELECT identificacion, id_periodo FROM solicitud_ingreso WHERE id_solicitud = ? LIMIT 1');
    $stmt->execute([$id]);
    $sol = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sol) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
        exit;
    }

    $cedula    = $sol['identificacion'];
    $id_periodo = $sol['id_periodo'];

    // 2. Eliminar archivos físicos y registros de documentos_socios
    $stmt = $pdo->prepare('SELECT ruta_archivo FROM documentos_socios WHERE id_solicitud = ?');
    $stmt->execute([$id]);
    $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($docs as $d) {
        if (!empty($d['ruta_archivo'])) {
            $path = __DIR__ . '/' . ltrim($d['ruta_archivo'], '/');
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    $pdo->prepare('DELETE FROM documentos_socios WHERE id_solicitud = ?')->execute([$id]);

    // 3. Eliminar acuerdo del mismo período (no todos los períodos)
    if ($cedula && $id_periodo) {
        $stmt = $pdo->prepare('SELECT archivo_pdf, id_acuerdo FROM acuerdo_productor WHERE cedula = ? AND id_periodo = ?');
        $stmt->execute([$cedula, $id_periodo]);
        $acuerdos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($acuerdos as $ac) {
            // Eliminar archivo PDF físico si existe
            if (!empty($ac['archivo_pdf'])) {
                $path = __DIR__ . '/' . ltrim($ac['archivo_pdf'], '/');
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
            // Eliminar también los pagos de inscripción relacionados
            $stmt2 = $pdo->prepare('SELECT id_pago FROM pago_inscripcion WHERE id_acuerdo = ?');
            $stmt2->execute([$ac['id_acuerdo']]);
            $pagos = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            foreach ($pagos as $pago) {
                $pdo->prepare('DELETE FROM pago_inscripcion_abono WHERE id_pago = ?')->execute([$pago['id_pago']]);
            }
            $pdo->prepare('DELETE FROM pago_inscripcion WHERE id_acuerdo = ?')->execute([$ac['id_acuerdo']]);

            // Eliminar el acuerdo
            $pdo->prepare('DELETE FROM acuerdo_productor WHERE id_acuerdo = ?')->execute([$ac['id_acuerdo']]);
        }
    }

    // 4. Eliminar la solicitud
    $pdo->prepare('DELETE FROM solicitud_ingreso WHERE id_solicitud = ?')->execute([$id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Aspirante eliminado correctamente'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Error eliminar_aspirante: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar: ' . $e->getMessage()
    ]);
}
exit;