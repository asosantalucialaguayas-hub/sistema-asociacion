<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}
require __DIR__ . "/config/conexion.php";
require_once __DIR__ . "/helpers/periodo.php";

try {
    $id_socio = $_GET['id_socio'] ?? null;

    if (!$id_socio) {
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        exit;
    }

    // ── PERÍODO ACTIVO ────────────────────────────────────────────────────────
    $periodoAbierto = get_periodo_abierto($pdo);
    $periodo_id     = $periodoAbierto ? (int)$periodoAbierto['id_periodo'] : null;

    // ── DATOS DEL SOCIO ───────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT 
            id_socio,
            identificacion,
            COALESCE(nombre_completo, CONCAT(nombres, ' ', apellidos)) AS nombre_completo,
            nombres,
            apellidos,
            sexo,
            fecha_nacimiento,
            direccion,
            telefono,
            correo AS email,
            fecha_ingreso,
            estado,
            foto_ruta
        FROM socios 
        WHERE id_socio = ?
    ");
    $stmt->execute([$id_socio]);
    $socio = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$socio) {
        echo json_encode(['success' => false, 'message' => 'Socio no encontrado']);
        exit;
    }

    $cedula = $socio['identificacion'];

    // ── ACUERDO DEL PERÍODO ACTUAL (primero) ──────────────────────────────────
    // FIX: busca por id_socio O por cedula para cubrir acuerdos viejos sin id_socio
    $acuerdo          = null;
    $tiene_acuerdo_periodo = false;
    $acuerdo_vacio    = false;

    if ($periodo_id) {
        // Intenta primero con id_socio (acuerdos nuevos con el fix aplicado)
        $stmt = $pdo->prepare("
            SELECT * FROM acuerdo_productor
            WHERE id_periodo = ?
              AND (id_socio = ? OR cedula = ?)
            ORDER BY id_acuerdo DESC
            LIMIT 1
        ");
        $stmt->execute([$periodo_id, $id_socio, $cedula]);
        $acuerdo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($acuerdo) {
            $tiene_acuerdo_periodo = true;

            // Si el acuerdo existe pero no tiene id_socio, lo parchamos ahora
            if (empty($acuerdo['id_socio'])) {
                $pdo->prepare("
                    UPDATE acuerdo_productor 
                    SET id_socio = ? 
                    WHERE id_acuerdo = ?
                ")->execute([$id_socio, $acuerdo['id_acuerdo']]);
                $acuerdo['id_socio'] = $id_socio;
            }
        }
    }

    // Si no encontró acuerdo del período actual, trae el último acuerdo anterior
    if (!$acuerdo) {
        $stmt = $pdo->prepare("
            SELECT * FROM acuerdo_productor
            WHERE id_socio = ? OR cedula = ?
            ORDER BY id_acuerdo DESC
            LIMIT 1
        ");
        $stmt->execute([$id_socio, $cedula]);
        $acuerdo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$acuerdo) {
            $acuerdo_vacio = true; // No tiene ningún acuerdo
        }
    }

    // ── LPA ───────────────────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT 
            l.*,
            IFNULL((
                SELECT SUM(v.cantidad_vende) 
                FROM tabla_ventas v 
                WHERE v.id_lpa = l.id_lpa
            ), 0) AS cupo_consumido,
            l.volumen_produccion_estimado AS cupo_total
        FROM tabla_lpa l
        WHERE l.id_socio = ?
        ORDER BY l.id_lpa DESC
        LIMIT 1
    ");
    $stmt->execute([$id_socio]);
    $lpa = $stmt->fetch(PDO::FETCH_ASSOC);

    // ── VENTAS ACOPIO ─────────────────────────────────────────────────────────
    $ventasAcopio = [];
    if ($lpa) {
        $stmt = $pdo->prepare("
            SELECT 
                fecha_venta,
                'Acopio' AS tipo,
                cantidad_vende AS cantidad,
                precio_kg,
                total,
                sucursal,
                destino AS comprador
            FROM tabla_ventas
            WHERE id_lpa = ?
            ORDER BY fecha_venta DESC
        ");
        $stmt->execute([$lpa['id_lpa']]);
        $ventasAcopio = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── VENTAS EXTERNAS ───────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT 
            fecha_venta,
            'Externa' AS tipo,
            cantidad_kg AS cantidad,
            precio_kg,
            total,
            comprador,
            NULL AS sucursal
        FROM tabla_ventas_externas
        WHERE id_socio = ?
        ORDER BY fecha_venta DESC
    ");
    $stmt->execute([$id_socio]);
    $ventasExternas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $ventas = array_merge($ventasAcopio, $ventasExternas);

    // ── DOCUMENTOS ────────────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT * FROM documentos_productores
        WHERE id_socio = ?
        ORDER BY fecha_carga DESC
    ");
    $stmt->execute([$id_socio]);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'               => true,
        'socio'                 => $socio,
        'acuerdo'               => $acuerdo,
        'tiene_acuerdo_periodo' => $tiene_acuerdo_periodo,
        'acuerdo_vacio'         => $acuerdo_vacio,
        'lpa'                   => $lpa,
        'ventas'                => $ventas,
        'documentos'            => $documentos,
    ]);

} catch (PDOException $e) {
    error_log("consulta_general_detalle: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>