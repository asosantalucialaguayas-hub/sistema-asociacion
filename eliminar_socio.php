<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require "config/conexion.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id_solicitud = isset($_POST['id_solicitud']) ? intval($_POST['id_solicitud']) : 0;

if (!$id_solicitud) {
    echo json_encode(['success' => false, 'message' => 'ID de solicitud inválido']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Obtener cédula de la solicitud
    $sqlGetSolicitud = "SELECT identificacion FROM solicitud_ingreso WHERE id_solicitud = ?";
    $stmtGet = $pdo->prepare($sqlGetSolicitud);
    $stmtGet->execute([$id_solicitud]);
    $solicitud = $stmtGet->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
        exit;
    }

    $cedula = $solicitud['identificacion'];

    // Obtener acuerdo relacionado (para saber su numero antes de eliminarlo)
    $sqlGetAcuerdo = "SELECT id_acuerdo, numero_acuerdo FROM acuerdo_productor WHERE cedula = ? LIMIT 1";
    $stmtAcuerdo = $pdo->prepare($sqlGetAcuerdo);
    $stmtAcuerdo->execute([$cedula]);
    $acuerdo = $stmtAcuerdo->fetch(PDO::FETCH_ASSOC);

    // Eliminar documentos asociados a esta solicitud
    $sqlDelDocs = "DELETE FROM documentos_socios WHERE id_solicitud = ?";
    $stmtDelDocs = $pdo->prepare($sqlDelDocs);
    $stmtDelDocs->execute([$id_solicitud]);

    // Eliminar acuerdo si existe
    if ($acuerdo) {
        $sqlDelAcuerdo = "DELETE FROM acuerdo_productor WHERE id_acuerdo = ?";
        $stmtDelAcuerdo = $pdo->prepare($sqlDelAcuerdo);
        $stmtDelAcuerdo->execute([$acuerdo['id_acuerdo']]);
    }

    // Eliminar solicitud
    $sqlDelSolicitud = "DELETE FROM solicitud_ingreso WHERE id_solicitud = ?";
    $stmtDelSolicitud = $pdo->prepare($sqlDelSolicitud);
    $stmtDelSolicitud->execute([$id_solicitud]);

    // Renumerar acuerdos: si se eliminó ACP-2025-006, los siguientes deben reducir su número
    if ($acuerdo) {
        $numeroActual = intval(explode('-', $acuerdo['numero_acuerdo'])[2]);
        
        // Obtener todos los acuerdos con número mayor que el eliminado
        $sqlGetMayores = "SELECT id_acuerdo, numero_acuerdo FROM acuerdo_productor WHERE numero_acuerdo LIKE 'ACP-2025-%' AND CAST(SUBSTRING_INDEX(numero_acuerdo, '-', -1) AS UNSIGNED) > ? ORDER BY numero_acuerdo ASC";
        $stmtMayores = $pdo->prepare($sqlGetMayores);
        $stmtMayores->execute([$numeroActual]);
        $acuerdosMayores = $stmtMayores->fetchAll(PDO::FETCH_ASSOC);

        // Decrementar números en 1
        foreach ($acuerdosMayores as $a) {
            $numeroViejo = intval(explode('-', $a['numero_acuerdo'])[2]);
            $numeroNuevo = $numeroViejo - 1;
            $nuevoNumero = 'ACP-2025-' . str_pad($numeroNuevo, 4, '0', STR_PAD_LEFT);
            
            $sqlUpdate = "UPDATE acuerdo_productor SET numero_acuerdo = ? WHERE id_acuerdo = ?";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([$nuevoNumero, $a['id_acuerdo']]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Socio y sus documentos eliminados correctamente'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Error eliminando socio: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar: ' . $e->getMessage()
    ]);
}
?>
