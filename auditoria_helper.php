<?php
// ============================================================
// auditoria_helper.php
// Incluir en cualquier página con: require_once 'auditoria_helper.php';
// ============================================================

/**
 * Registra una acción en la tabla auditoria_logs
 * 
 * @param PDO    $pdo         Conexión a la BD
 * @param int    $id_usuario  ID del usuario
 * @param string $usuario     Nombre de usuario
 * @param string $accion      LOGIN | LOGOUT | INSERT | UPDATE | DELETE | VIEW
 * @param string $modulo      Nombre del módulo
 * @param string $descripcion Detalle de la acción (opcional)
 */
function registrarLog(PDO $pdo, int $id_usuario, string $usuario, string $accion, string $modulo, string $descripcion = ''): void {
    try {
        $ip         = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmt = $pdo->prepare("
            INSERT INTO auditoria_logs (id_usuario, usuario, accion, modulo, descripcion, ip, user_agent)
            VALUES (:id_usuario, :usuario, :accion, :modulo, :descripcion, :ip, :user_agent)
        ");
        $stmt->execute([
            ':id_usuario'  => $id_usuario,
            ':usuario'     => $usuario,
            ':accion'      => strtoupper($accion),
            ':modulo'      => $modulo,
            ':descripcion' => $descripcion,
            ':ip'          => $ip,
            ':user_agent'  => substr($user_agent, 0, 255),
        ]);
    } catch (Exception $e) {
        // No interrumpir el flujo por error en log
        error_log("Error al registrar log: " . $e->getMessage());
    }
}

/**
 * Verifica si el usuario actual tiene permiso en un módulo
 * 
 * @param PDO    $pdo       Conexión a la BD
 * @param int    $id_usuario ID del usuario en sesión
 * @param string $clave_modulo Clave del módulo (ej: 'ventas_diarias')
 * @param string $tipo      puede_ver | puede_agregar | puede_modificar | puede_eliminar
 * @return bool
 */
function tienePermiso(PDO $pdo, int $id_usuario, string $clave_modulo, string $tipo = 'puede_ver'): bool {
    $tipos_validos = ['puede_ver', 'puede_agregar', 'puede_modificar', 'puede_eliminar'];
    if (!in_array($tipo, $tipos_validos)) return false;

    $stmt = $pdo->prepare("
        SELECT pr.$tipo
        FROM usuario_rol ur
        JOIN permisos_rol pr ON ur.id_rol = pr.id_rol
        JOIN modulos m ON pr.id_modulo = m.id_modulo
        WHERE ur.id_usuario = :id_usuario
          AND m.clave = :clave
        LIMIT 1
    ");
    $stmt->execute([':id_usuario' => $id_usuario, ':clave' => $clave_modulo]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row && (int)$row[$tipo] === 1;
}

/**
 * Obtiene todos los módulos visibles para un usuario (para construir el menú dinámico)
 * 
 * @param PDO $pdo
 * @param int $id_usuario
 * @return array  Lista de claves de módulos con permiso de ver
 */
function getModulosPermitidos(PDO $pdo, int $id_usuario): array {
    $stmt = $pdo->prepare("
        SELECT m.clave
        FROM usuario_rol ur
        JOIN permisos_rol pr ON ur.id_rol = pr.id_rol
        JOIN modulos m ON pr.id_modulo = m.id_modulo
        WHERE ur.id_usuario = :id_usuario AND pr.puede_ver = 1
    ");
    $stmt->execute([':id_usuario' => $id_usuario]);
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'clave');
}
