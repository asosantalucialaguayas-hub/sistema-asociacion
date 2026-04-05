
<?php
require "layout/bootstrap.php";

// Verificar conexión
if (!isset($pdo) || $pdo === null) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}
require __DIR__ . "/config/periodo_guard.php";
header('Content-Type: application/json; charset=utf-8');
$periodo = require_periodo_abierto_json($pdo); // 🔒 candado de período

try {
    // Recibir datos del formulario
    $id_socio = intval($_POST['id_socio'] ?? 0);
    $id_lpa = intval($_POST['id_lpa'] ?? 0);
    $fecha_compra = $_POST['fecha_compra'] ?? date('Y-m-d');
    $documento = $_POST['documento'] ?? '';
    $numero_documento = $_POST['numero_documento'] ?? '';
    $ticket = $_POST['ticket'] ?? '';
    $producto = $_POST['producto'] ?? '';
    
    // El usuario ingresa QQ directamente
    $peso_neto_qq = floatval($_POST['peso_neto_qq'] ?? 0);
    
    $precio_kg = floatval($_POST['precio_kg'] ?? 0);
    $total_usd = floatval($_POST['total_usd'] ?? 0);
    
    // Validaciones
    if ($id_socio <= 0 || $id_lpa <= 0) {
        echo json_encode(['success' => false, 'message' => 'Datos de socio inválidos']);
        exit;
    }
    
    if ($peso_neto_qq <= 0) {
        echo json_encode(['success' => false, 'message' => 'El peso neto debe ser mayor a 0']);
        exit;
    }
    
    // El usuario debe ingresar el total USD; el precio es opcional (no queremos cálculos automáticos)
    if ($total_usd <= 0) {
        echo json_encode(['success' => false, 'message' => 'El total en USD debe ser mayor a 0']);
        exit;
    }
    
    // CONVERSIÓN: QQ a KG para almacenar
    $peso_neto_kg = $peso_neto_qq * 45.36;
    
    // No recalculamos el total: usamos el valor ingresado por el usuario en el formulario
    
    // Usuario actual
    $usuario = $_SESSION['usuario'] ?? 'SYSTEM';
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    // 1. INSERTAR en tabla_consolidado_detalle (SOLO EL INCREMENTO)
    // Almacenamos tanto peso_neto_kg como peso_neto_qq
    $sqlDetalle = "INSERT INTO tabla_consolidado_detalle 
                   (id_periodo, id_socio, id_lpa, fecha_compra, documento, numero_documento, 
                    ticket, producto, peso_neto_kg, peso_neto_qq, precio_kg, total_usd, usuario_registro) 
                   VALUES (:id_periodo, :id_socio, :id_lpa, :fecha_compra, :documento, :numero_documento, 
                           :ticket, :producto, :peso_neto_kg, :peso_neto_qq, :precio_kg, :total_usd, :usuario_registro)";

    $stmtDetalle = $pdo->prepare($sqlDetalle);
    $stmtDetalle->execute([
        ':id_periodo' => $periodo['id_periodo'],
        ':id_socio' => $id_socio,
        ':id_lpa' => $id_lpa,
        ':fecha_compra' => $fecha_compra,
        ':documento' => $documento,
        ':numero_documento' => $numero_documento,
        ':ticket' => $ticket,
        ':producto' => $producto,
        ':peso_neto_kg' => $peso_neto_kg,
        ':peso_neto_qq' => $peso_neto_qq,
        ':precio_kg' => $precio_kg,
        ':total_usd' => $total_usd,
        ':usuario_registro' => $usuario
    ]);
    
    // 2. ACTUALIZAR tabla_ventas (SUMAR SOLO EL INCREMENTO al acumulado)
    // Usamos solo cantidad_kg para almacenar en KG (no mezclamos con cantidad_vende)
    $sqlCheckVenta = "SELECT id_venta, cantidad_kg as cantidad_actual
                      FROM tabla_ventas 
                      WHERE id_socio = :id_socio AND id_lpa = :id_lpa
                      ORDER BY fecha_registro DESC 
                      LIMIT 1";
    
    $stmtCheck = $pdo->prepare($sqlCheckVenta);
    $stmtCheck->execute([':id_socio' => $id_socio, ':id_lpa' => $id_lpa]);
    $ventaActual = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($ventaActual) {
        // YA EXISTE - ACTUALIZAR: solo sumamos la cantidad (KG). No recalculamos ni modificamos el total/precio automáticamente.
        $nueva_cantidad_kg = $ventaActual['cantidad_actual'] + $peso_neto_kg;

        $sqlUpdateVenta = "UPDATE tabla_ventas 
                          SET cantidad_kg = :cantidad_kg,
                              fecha_venta = :fecha_venta
                          WHERE id_venta = :id_venta";

        $stmtUpdate = $pdo->prepare($sqlUpdateVenta);
        $stmtUpdate->execute([
            ':cantidad_kg' => $nueva_cantidad_kg,
            ':fecha_venta' => $fecha_compra,
            ':id_venta' => $ventaActual['id_venta']
        ]);

    } else {
        // NO EXISTE - INSERTAR NUEVO: guardamos la cantidad y el total tal como lo ingresó el usuario
        $sqlInsertVenta = "INSERT INTO tabla_ventas 
                          (id_periodo, id_socio, id_lpa, fecha_venta, cantidad_kg,
                           precio_kg, total, floid, sucursal) 
                          VALUES (:id_periodo, :id_socio, :id_lpa, :fecha_venta, :cantidad_kg,
                                  :precio_kg, :total, 'CONSOLIDADO', 'Sistema')";

        $stmtInsert = $pdo->prepare($sqlInsertVenta);
        $stmtInsert->execute([
            ':id_periodo' => $periodo['id_periodo'],
            ':id_socio' => $id_socio,
            ':id_lpa' => $id_lpa,
            ':fecha_venta' => $fecha_compra,
            ':cantidad_kg' => $peso_neto_kg,
            ':precio_kg' => $precio_kg,
            ':total' => $total_usd
        ]);
    }
    
    // Confirmar transacción
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Consolidado guardado correctamente',
        'peso_qq' => $peso_neto_qq,
        'peso_kg' => $peso_neto_kg,
        'total_usd' => $total_usd
    ]);
    
} catch (Exception $e) {
    // Revertir cambios en caso de error
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false, 
        'message' => 'Error al guardar: ' . $e->getMessage()
    ]);
}
?>