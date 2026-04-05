<?php
// ============================================================
// GUÍA DE INTEGRACIÓN - Lee esto antes de instalar
// ============================================================
//
// PASO 1: Ejecuta auditoria_setup.sql en tu base de datos
//
// PASO 2: Copia estos archivos a tu proyecto:
//   auditoria_helper.php  → /auditoria/auditoria_helper.php
//   gestion_usuarios.php  → /auditoria/gestion_usuarios.php
//   logs_sistema.php      → /auditoria/logs_sistema.php
//   permisos_rol.php      → /auditoria/permisos_rol.php
//
// PASO 3: Agrega al sidebar.php el nuevo menú de Auditoría
// PASO 4: Integra logs en tu login.php y páginas existentes


// ============================================================
// INTEGRACIÓN EN login.php
// Busca donde validas usuario/contraseña y agrega esto:
// ============================================================

/*
require_once 'auditoria/auditoria_helper.php';

// Cuando el login sea EXITOSO:
if ($usuario_valido) {
    $_SESSION['id_usuario'] = $row['id_usuario'];
    $_SESSION['usuario']    = $row['usuario'];

    registrarLog($pdo, $row['id_usuario'], $row['usuario'], 'LOGIN', 'Sistema', 'Inicio de sesión exitoso');
    header('Location: dashboard.php');
    exit;
}

// Cuando el login FALLE (usuario incorrecto):
registrarLog($pdo, 0, $_POST['usuario'] ?? 'desconocido', 'LOGIN', 'Sistema', 'Intento fallido de inicio de sesión');
*/


// ============================================================
// INTEGRACIÓN EN logout.php
// ============================================================

/*
require_once 'auditoria/auditoria_helper.php';

if (isset($_SESSION['id_usuario'])) {
    registrarLog($pdo, $_SESSION['id_usuario'], $_SESSION['usuario'], 'LOGOUT', 'Sistema', 'Cerró sesión');
}
session_destroy();
header('Location: auth/login.php');
exit;
*/


// ============================================================
// INTEGRACIÓN EN CUALQUIER PÁGINA (ejemplo: ventas_diarias.php)
// ============================================================

/*
require_once '../auditoria/auditoria_helper.php';

// Verificar permiso al cargar la página
if (!tienePermiso($pdo, $_SESSION['id_usuario'], 'ventas_diarias', 'puede_ver')) {
    die('Acceso denegado');
}

// Verificar permiso al guardar
if ($_POST && !tienePermiso($pdo, $_SESSION['id_usuario'], 'ventas_diarias', 'puede_agregar')) {
    die('No tienes permiso para agregar ventas');
}

// Registrar cuando alguien guarda una venta
registrarLog($pdo, $_SESSION['id_usuario'], $_SESSION['usuario'],
    'INSERT', 'Ventas Diarias',
    "Registró venta por $total_venta kg");
*/


// ============================================================
// CLAVES DE MÓDULOS (usa estas en tienePermiso())
// ============================================================
//
//  dashboard               ventas_diarias
//  socios                  directiva
//  solicitud_ingreso       comisiones
//  acuerdo_productor       asistencia
//  consulta_socios         herramientas
//  pago_inscripcion        periodo_comercializacion
//  rna                     asignacion_cupos
//  consulta_general        ubicaciones
//  lpa                     documentacion
//  datos_lpa               actas
//  estimacion_cosecha      convocatorias
//  plan_abastecimiento     documentos_socios
//  ventas                  auditoria
//                          gestion_usuarios
//                          logs_sistema
//


// ============================================================
// FRAGMENTO PARA AGREGAR AL SIDEBAR
// Pega esto en tu sidebar.php antes del botón de Salir:
// ============================================================

/*
<?php
  // Solo muestra Auditoría si tiene permiso
  $modulosPermitidos = getModulosPermitidos($pdo, $_SESSION['id_usuario']);
  if (in_array('auditoria', $modulosPermitidos)):
?>
<div class="menu-item has-submenu">
    <a href="#" class="menu-link" onclick="toggleSubmenu(event)">
        <i class="fa fa-shield-halved"></i>
        <span>Auditoría</span>
        <span class="arrow">▸</span>
    </a>
    <ul class="submenu">
        <?php if (in_array('gestion_usuarios', $modulosPermitidos)): ?>
        <li><a href="auditoria/gestion_usuarios.php"><i class="fa fa-user-gear"></i> Gestión de Usuarios</a></li>
        <?php endif; ?>
        <?php if (in_array('logs_sistema', $modulosPermitidos)): ?>
        <li><a href="auditoria/logs_sistema.php"><i class="fa fa-clock-rotate-left"></i> Logs del Sistema</a></li>
        <?php endif; ?>
    </ul>
</div>
<?php endif; ?>
*/
