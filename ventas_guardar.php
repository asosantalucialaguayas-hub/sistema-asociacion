<?php
require "layout/bootstrap.php";

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require __DIR__ . "/config/conexion.php";

if (!isset($pdo) || $pdo === null) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}

try {
    // ── Recibir datos del formulario ────────────────────────────────────────
    $id_lpa         = $_POST['id_lpa']         ?? null;
    $id_socio       = $_POST['id_socio']       ?? null;
    $fecha_venta    = $_POST['fecha_venta']    ?? null;
    $cantidad_vende = $_POST['cantidad_vende'] ?? null;   // KG ingresados en el form
    $cantidad_qq    = $_POST['cantidad_qq']    ?? null;   // FIX: antes no se recibía
    $precio_kg      = $_POST['precio_kg']      ?? null;
    $total          = $_POST['total']          ?? null;
    $destino        = $_POST['destino']        ?? null;   // FIX: comprador/destino
    $floid          = $_POST['floid']          ?? null;
    $sucursal       = $_POST['sucursal']       ?? null;
    $observacion    = $_POST['observacion']    ?? '';
    $numero_doc     = $_POST['numero_doc']     ?? null;
    $descripcion    = $_POST['descripcion']    ?? null;
    $punto_emision  = $_POST['punto_emision']  ?? null;
    $punto_venta    = $_POST['punto_venta']    ?? null;

    // ── Validaciones básicas ────────────────────────────────────────────────
    if (!$id_lpa || !$id_socio || !$fecha_venta || !$cantidad_vende || !$precio_kg || !$sucursal) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
        exit;
    }

    $cantidad_vende = floatval($cantidad_vende);
    if ($cantidad_vende <= 0) {
        echo json_encode(['success' => false, 'message' => 'La cantidad debe ser mayor a 0']);
        exit;
    }

    // FIX: calcular QQ si no llegó del form
    $cantidad_qq = $cantidad_qq ? floatval($cantidad_qq) : round($cantidad_vende / 45.36, 4);

    // FIX: cantidad_kg = lo mismo que cantidad_vende (son los KG del productor)
    $cantidad_kg = $cantidad_vende;

    $sucursales_validas = ['El Empalme', 'Buena Fe', 'Quinsaloma (Matriz)'];
    if (!in_array($sucursal, $sucursales_validas)) {
        echo json_encode(['success' => false, 'message' => 'Sucursal no válida']);
        exit;
    }

    // ── Verificar cupo disponible ───────────────────────────────────────────
    $sqlCupo = "
        SELECT
            IFNULL(l.volumen_produccion_estimado, 0) AS cupo_total,
            IFNULL(
                (SELECT SUM(v.cantidad_vende)
                 FROM tabla_ventas v
                 WHERE v.id_lpa = l.id_lpa), 0
            ) AS cupo_consumido
        FROM tabla_lpa l
        WHERE l.id_lpa = :id_lpa
    ";
    $stmtCupo = $pdo->prepare($sqlCupo);
    $stmtCupo->bindValue(':id_lpa', $id_lpa, PDO::PARAM_INT);
    $stmtCupo->execute();
    $cupo = $stmtCupo->fetch(PDO::FETCH_ASSOC);

    if (!$cupo) {
        echo json_encode(['success' => false, 'message' => 'No se encontró el registro LPA']);
        exit;
    }

    $cupo_disponible = $cupo['cupo_total'] - $cupo['cupo_consumido'];
    if ($cantidad_vende > $cupo_disponible) {
        echo json_encode([
            'success' => false,
            'message' => "La cantidad ({$cantidad_vende} Kg) excede el cupo disponible ({$cupo_disponible} Kg)"
        ]);
        exit;
    }

    // ── Procesar factura PDF (OPCIONAL) ─────────────────────────────────────
    $rutaFactura = null;
    if (isset($_FILES['factura']) && $_FILES['factura']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['factura'];

        if ($archivo['type'] !== 'application/pdf') {
            echo json_encode(['success' => false, 'message' => 'Solo se permiten archivos PDF']);
            exit;
        }
        if ($archivo['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'El archivo no debe superar 5MB']);
            exit;
        }

        $directorioFacturas = __DIR__ . '/facturas';
        if (!is_dir($directorioFacturas)) {
            mkdir($directorioFacturas, 0755, true);
        }

        $nombreArchivo = 'factura_' . $id_socio . '_' . date('YmdHis') . '_' . uniqid() . '.pdf';
        $rutaDestino   = $directorioFacturas . '/' . $nombreArchivo;

        if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            echo json_encode(['success' => false, 'message' => 'Error al guardar el archivo PDF']);
            exit;
        }

        $rutaFactura = 'facturas/' . $nombreArchivo;
    }
    // FIX: si no hay PDF simplemente $rutaFactura queda null — no bloquea

    // ── INSERT con todos los campos correctos ───────────────────────────────
    $sqlInsert = "
        INSERT INTO tabla_ventas (
            id_socio,
            id_lpa,
            fecha_venta,
            cantidad_vende,
            cantidad_kg,
            cantidad_qq,
            precio_kg,
            total,
            destino,
            floid,
            sucursal,
            observacion,
            factura,
            descripcion,
            fecha_registro
        ) VALUES (
            :id_socio,
            :id_lpa,
            :fecha_venta,
            :cantidad_vende,
            :cantidad_kg,
            :cantidad_qq,
            :precio_kg,
            :total,
            :destino,
            :floid,
            :sucursal,
            :observacion,
            :factura,
            :descripcion,
            NOW()
        )
    ";

    $st = $pdo->prepare($sqlInsert);
    $st->bindValue(':id_socio',       $id_socio,       PDO::PARAM_INT);
    $st->bindValue(':id_lpa',         $id_lpa,         PDO::PARAM_INT);
    $st->bindValue(':fecha_venta',    $fecha_venta,    PDO::PARAM_STR);
    $st->bindValue(':cantidad_vende', $cantidad_vende, PDO::PARAM_STR);
    $st->bindValue(':cantidad_kg',    $cantidad_kg,    PDO::PARAM_STR);  // FIX: añadido
    $st->bindValue(':cantidad_qq',    $cantidad_qq,    PDO::PARAM_STR);  // FIX: añadido
    $st->bindValue(':precio_kg',      $precio_kg,      PDO::PARAM_STR);
    $st->bindValue(':total',          $total,          PDO::PARAM_STR);
    $st->bindValue(':destino',        $destino,        PDO::PARAM_STR);  // FIX: añadido
    $st->bindValue(':floid',          $floid,          PDO::PARAM_STR);
    $st->bindValue(':sucursal',       $sucursal,       PDO::PARAM_STR);
    $st->bindValue(':observacion',    $observacion,    PDO::PARAM_STR);
    $st->bindValue(':factura',        $rutaFactura,    PDO::PARAM_STR);
    $st->bindValue(':descripcion',    $descripcion,    PDO::PARAM_STR);  // FIX: añadido

    if ($st->execute()) {
        echo json_encode([
            'success'  => true,
            'message'  => 'Venta registrada exitosamente',
            'id_venta' => $pdo->lastInsertId()
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al ejecutar la inserción']);
    }

} catch (PDOException $e) {
    error_log("Error en ventas_guardar.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>