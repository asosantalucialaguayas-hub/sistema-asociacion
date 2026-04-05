<?php
/**
 * ============================================
 * HELPER: Control de Períodos de Comercialización
 * ============================================
 * Gestiona el acceso a módulos según el estado del período
 * 
 * @author Sistema Asociación
 * @version 3.0 - Con soporte de adendas
 */

/**
 * Obtiene el período actualmente ABIERTO
 */
function get_periodo_abierto($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id_periodo,
                nombre,
                fecha_apertura,
                fecha_cierre,
                estado,
                adenda_activa,
                fecha_adenda_inicio,
                fecha_adenda_fin,
                creado_en
            FROM periodo_comercializacion
            WHERE estado = 'ABIERTO' 
            LIMIT 1
        ");
        
        $stmt->execute();
        $periodo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $periodo ?: null;
        
    } catch (PDOException $e) {
        error_log("Error get_periodo_abierto: " . $e->getMessage());
        return null;
    }
}

/**
 * CANDADO JSON: Requiere período abierto (ESTRICTO)
 */
function require_periodo_abierto_json($pdo) {
    $periodo = get_periodo_abierto($pdo);
    
    if (!$periodo) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        
        echo json_encode([
            'success' => false,
            'message' => 'No hay un período de comercialización ABIERTO actualmente. Esta acción está bloqueada.',
            'error_code' => 'PERIODO_CERRADO'
        ], JSON_UNESCAPED_UNICODE);
        
        exit;
    }
    
    return $periodo;
}

/**
 * CANDADO HTML: Requiere período abierto
 */
function require_periodo_abierto_html($pdo, $redirect_url = 'index.php') {
    $periodo = get_periodo_abierto($pdo);
    
    if (!$periodo) {
        $_SESSION['mensaje_error'] = 'No hay un período de comercialización ABIERTO. Esta página no está disponible.';
        $_SESSION['tipo_mensaje'] = 'warning';
        header("Location: $redirect_url");
        exit;
    }
    
    return $periodo;
}

/**
 * Verifica si se puede inscribir nuevos socios
 * (Período abierto O adenda activa)
 */
function puede_inscribir_socios($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT * FROM periodo_comercializacion 
            WHERE estado = 'ABIERTO' OR adenda_activa = TRUE
            ORDER BY fecha_apertura DESC
            LIMIT 1
        ");
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error puede_inscribir_socios: " . $e->getMessage());
        return null;
    }
}

/**
 * CANDADO FLEXIBLE: Para inscripciones
 */
function require_puede_inscribir_json($pdo) {
    $periodo = puede_inscribir_socios($pdo);
    
    if (!$periodo) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        
        echo json_encode([
            'success' => false,
            'message' => 'No se pueden registrar nuevas inscripciones. El período está cerrado y no hay adenda activa.',
            'error_code' => 'INSCRIPCIONES_CERRADAS'
        ], JSON_UNESCAPED_UNICODE);
        
        exit;
    }
    
    return $periodo;
}

/**
 * Verifica si hay adenda activa
 */
function get_adenda_activa($pdo, $id_periodo) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM periodo_adendas 
            WHERE id_periodo = ? AND estado = 'ACTIVA'
            ORDER BY fecha_inicio DESC
            LIMIT 1
        ");
        $stmt->execute([$id_periodo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error get_adenda_activa: " . $e->getMessage());
        return null;
    }
}

/**
 * Verifica si hay un período abierto (devuelve boolean)
 */
function hay_periodo_abierto($pdo) {
    return get_periodo_abierto($pdo) !== null;
}

/**
 * Cierra el período actualmente abierto
 */
function cerrar_periodo_actual($pdo, $fecha_cierre = null) {
    try {
        $fecha = $fecha_cierre ?? date('Y-m-d');
        
        $stmt = $pdo->prepare("
            UPDATE periodo_comercializacion 
            SET estado = 'CERRADO', 
                fecha_cierre = ?,
                adenda_activa = FALSE,
                fecha_adenda_fin = CASE WHEN adenda_activa = TRUE THEN ? ELSE fecha_adenda_fin END
            WHERE estado = 'ABIERTO'
        ");
        
        return $stmt->execute([$fecha, $fecha]);
    } catch (PDOException $e) {
        error_log("Error cerrar_periodo: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene todos los períodos (histórico)
 */
function get_all_periodos($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                p.id_periodo,
                p.nombre,
                p.fecha_apertura,
                p.fecha_cierre,
                p.estado,
                p.adenda_activa,
                p.creado_en,
                c.titulo AS contrato_titulo,
                c.estado AS contrato_estado
            FROM periodo_comercializacion p
            LEFT JOIN contrato_periodo c ON c.id_periodo = p.id_periodo
            ORDER BY p.fecha_apertura DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error get_all_periodos: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtiene un período específico por ID
 */
function get_periodo_by_id($pdo, $id_periodo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                c.id_contrato,
                c.titulo AS contrato_titulo,
                c.numero AS contrato_numero,
                c.fecha_firma AS contrato_fecha_firma,
                c.estado AS contrato_estado
            FROM periodo_comercializacion p
            LEFT JOIN contrato_periodo c ON c.id_periodo = p.id_periodo
            WHERE p.id_periodo = ?
        ");
        
        $stmt->execute([$id_periodo]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log("Error get_periodo_by_id: " . $e->getMessage());
        return null;
    }
}

/**
 * Abre un período cerrado
 */
function abrir_periodo($pdo, $id_periodo) {
    try {
        $pdo->beginTransaction();
        
        // Cerrar otros períodos
        $pdo->exec("UPDATE periodo_comercializacion SET estado = 'CERRADO', adenda_activa = FALSE WHERE estado = 'ABIERTO'");
        
        // Abrir el seleccionado
        $stmt = $pdo->prepare("
            UPDATE periodo_comercializacion 
            SET estado = 'ABIERTO', fecha_cierre = NULL 
            WHERE id_periodo = ?
        ");
        
        $stmt->execute([$id_periodo]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error abrir_periodo: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene estadísticas del período
 */
function get_estadisticas_periodo($pdo, $id_periodo) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tabla_lpa WHERE id_periodo = ?");
        $stmt->execute([$id_periodo]);
        $total_lpas = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM acuerdo_productor WHERE id_periodo = ?");
        $stmt->execute([$id_periodo]);
        $total_acuerdos = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(monto), 0) FROM pago_inscripcion WHERE id_periodo = ?");
        $stmt->execute([$id_periodo]);
        $pagos = $stmt->fetch(PDO::FETCH_NUM);
        
        return [
            'total_lpas' => $total_lpas,
            'total_acuerdos' => $total_acuerdos,
            'total_pagos_inscripcion' => $pagos[0],
            'monto_total_inscripciones' => $pagos[1]
        ];
    } catch (PDOException $e) {
        error_log("Error get_estadisticas_periodo: " . $e->getMessage());
        return [
            'total_lpas' => 0,
            'total_acuerdos' => 0,
            'total_pagos_inscripcion' => 0,
            'monto_total_inscripciones' => 0
        ];
    }
}