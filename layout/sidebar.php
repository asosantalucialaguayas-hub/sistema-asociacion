<?php
// ============================================================
// layout/sidebar.php
// ============================================================

// El $pdo ya viene del archivo padre que hace include de este sidebar.
// Solo cargamos el helper si existe (no rompe si aún no está instalado).
$_helper = dirname(__DIR__) . '/auditoria_helper.php';
if (file_exists($_helper)) {
    require_once $_helper;
}

$id_usr = (int)($_SESSION['id_usuario'] ?? 0);

if ($id_usr && function_exists('getModulosPermitidos') && isset($pdo)) {
    $permitidos = getModulosPermitidos($pdo, $id_usr);
} else {
    // Si aún no hay sistema de permisos, muestra todo (no rompe nada)
    $permitidos = ['dashboard','socios','solicitud_ingreso','acuerdo_productor',
        'consulta_socios','pago_inscripcion','rna','consulta_general','lpa',
        'datos_lpa','estimacion_cosecha','plan_abastecimiento','ventas',
        'ventas_diarias','directiva','comisiones','asistencia','herramientas',
        'periodo_comercializacion','asignacion_cupos','ubicaciones','documentacion',
        'actas','convocatorias','documentos_socios','auditoria','gestion_usuarios','logs_sistema'];
}

$puede = fn($clave) => in_array($clave, $permitidos);
?>
<aside class="sidebar" style="position:fixed!important;top:0!important;left:0!important;height:100vh!important;width:240px!important;overflow-y:auto!important;z-index:9999!important;">
<link rel="stylesheet" href="css/modal-message.css">
<script src="layout/modal-message.js"></script>

    <div class="brand">
        <img src="img/logo.png" alt="Logo">
        <span>Asociación</span>
    </div>

    <nav>

        <?php if ($puede('dashboard')): ?>
        <a href="dashboard.php"><i class="fa fa-home"></i> Dashboard</a>
        <?php endif; ?>

        <?php if ($puede('socios') || $puede('solicitud_ingreso') || $puede('acuerdo_productor') || $puede('consulta_socios') || $puede('pago_inscripcion')): ?>
        <div class="menu-item has-submenu">
            <a href="#" class="menu-link" onclick="toggleSubmenu(event)">
                <i class="fa fa-users"></i>
                <span>Socios</span>
                <span class="arrow">▸</span>
            </a>
            <ul class="submenu">
                <?php if ($puede('solicitud_ingreso')): ?>
                <li><a href="solicitud_form.php"><i class="fa fa-file-alt"></i> Solicitud de ingreso</a></li>
                <?php endif; ?>
                <?php if ($puede('acuerdo_productor')): ?>
                <li><a href="acuerdo_form.php"><i class="fa fa-handshake"></i> Acuerdo de productor</a></li>
                <?php endif; ?>
                <?php if ($puede('consulta_socios')): ?>
                <li><a href="socios_consulta.php"><i class="fa fa-search"></i> Consulta de socios</a></li>
                <?php endif; ?>
                <?php if ($puede('pago_inscripcion')): ?>
                <li><a href="pago_inscripcion.php"><i class="fa-regular fa-money-bill-1"></i> Pago por inscripcion</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($puede('rna')): ?>
        <a href="rna_consulta.php"><i class="fa fa-file-lines"></i> RNA</a>
        <?php endif; ?>

        <?php if ($puede('consulta_general')): ?>
        <a href="consulta_general.php"><i class="fa fa-search-plus"></i> Consulta General</a>
        <?php endif; ?>

        <?php if ($puede('lpa') || $puede('datos_lpa') || $puede('estimacion_cosecha') || $puede('plan_abastecimiento')): ?>
        <div class="menu-item has-submenu">
            <a href="#" class="menu-link" onclick="toggleSubmenu(event)">
                <i class="fa fa-chart-column"></i>
                <span>LPA</span>
                <span class="arrow">▸</span>
            </a>
            <ul class="submenu">
                <?php if ($puede('datos_lpa')): ?>
                <li><a href="lpa_consulta.php"><i class="fa fa-list-check"></i> Datos de LPA</a></li>
                <?php endif; ?>
                <?php if ($puede('estimacion_cosecha')): ?>
                <li><a href="estimacion.php"><i class="fa-solid fa-calculator"></i> Estimacion de cosecha</a></li>
                <?php endif; ?>
                <?php if ($puede('plan_abastecimiento')): ?>
                <li><a href="lpa_estimacion.php"><i class="fa-solid fa-seedling"></i> Plan de abastecimiento</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($puede('ventas') || $puede('ventas_diarias')): ?>
        <div class="menu-item has-submenu">
            <a href="#" class="menu-link" onclick="toggleSubmenu(event)">
                <i class="fa-brands fa-shopify"></i>
                <span>Ventas</span>
                <span class="arrow">▸</span>
            </a>
            <ul class="submenu">
                <?php if ($puede('ventas_diarias')): ?>
                <li><a href="ventas_consulta.php"><i class="fa-solid fa-file-invoice-dollar"></i> Ventas diarias</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($puede('directiva')): ?>
        <a href="#"><i class="fa fa-user-tie"></i> Directiva</a>
        <?php endif; ?>

        <?php if ($puede('comisiones')): ?>
        <a href="#"><i class="fa fa-people-group"></i> Comisiones</a>
        <?php endif; ?>

        <?php if ($puede('asistencia')): ?>
        <a href="#"><i class="fa fa-calendar-check"></i> Asistencia</a>
        <?php endif; ?>

        <?php if ($puede('herramientas') || $puede('periodo_comercializacion') || $puede('asignacion_cupos')): ?>
        <div class="menu-item has-submenu">
            <a href="#" class="menu-link" onclick="toggleSubmenu(event)">
                <i class="fa-solid fa-screwdriver-wrench"></i>
                <span>Herramientas</span>
                <span class="arrow">▸</span>
            </a>
            <ul class="submenu">
                <?php if ($puede('periodo_comercializacion')): ?>
                <li><a href="acceso.php"><i class="fa-solid fa-calendar-week"></i> Período de Comercialización</a></li>
                <?php endif; ?>
                <?php if ($puede('asignacion_cupos')): ?>
                <li><a href="cupos_lpa.php"><i class="fa fa-layer-group"></i> Asignación de Cupos</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($puede('ubicaciones')): ?>
        <a href="ubicaciones_consulta.php">
            <i class="fa fa-map-location-dot"></i> Ubicaciones
        </a>
        <?php endif; ?>

        <?php if ($puede('documentacion') || $puede('actas') || $puede('convocatorias') || $puede('documentos_socios')): ?>
        <div class="menu-item has-submenu">
            <a href="#" class="menu-link" onclick="toggleSubmenu(event)">
                <i class="fa-solid fa-file"></i>
                <span>Documentacion</span>
                <span class="arrow">▸</span>
            </a>
            <ul class="submenu">
                <?php if ($puede('actas')): ?>
                <li><a href="lpa_consulta.php"><i class="fa-solid fa-clipboard"></i> Actas</a></li>
                <?php endif; ?>
                <?php if ($puede('convocatorias')): ?>
                <li><a href="estimacion.php"><i class="fa-solid fa-people-group"></i> Convocatorias</a></li>
                <?php endif; ?>
                <?php if ($puede('documentos_socios')): ?>
                <li><a href="documentos_socios.php"><i class="fa fa-folder-open"></i> Documentos de Socios</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($puede('auditoria') || $puede('gestion_usuarios') || $puede('logs_sistema')): ?>
        <div class="menu-item has-submenu">
            <a href="#" class="menu-link" onclick="toggleSubmenu(event)">
                <i class="fa fa-shield-halved"></i>
                <span>Auditoría</span>
                <span class="arrow">▸</span>
            </a>
            <ul class="submenu">
                <?php if ($puede('gestion_usuarios')): ?>
                <li><a href="gestion_usuarios.php"><i class="fa fa-user-gear"></i> Gestión de Usuarios</a></li>
                <?php endif; ?>
                <?php if ($puede('logs_sistema')): ?>
                <li><a href="logs_sistema.php"><i class="fa fa-clock-rotate-left"></i> Logs del Sistema</a></li>
                <?php endif; ?>
                <?php if ($puede('gestion_usuarios')): ?>
                <li><a href="permisos_rol.php"><i class="fa fa-shield-halved"></i> Permisos por Rol</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <a href="auth/logout.php" class="logout">
            <i class="fa fa-right-from-bracket"></i> Salir
        </a>

    </nav>
</aside>

<script>
(function(){
    var s = document.querySelector('.sidebar');
    if(s){
        s.style.setProperty('position','fixed','important');
        s.style.setProperty('top','0','important');
        s.style.setProperty('left','0','important');
        s.style.setProperty('height','100vh','important');
        s.style.setProperty('width','240px','important');
        s.style.setProperty('overflow-y','auto','important');
        s.style.setProperty('z-index','9999','important');
    }
    var c = document.querySelector('.content');
    if(c){
        c.style.setProperty('margin-left','240px','important');
    }
})();
</script>

<script>
function toggleSubmenu(event) {
    event.preventDefault();
    const menuItem = event.currentTarget.parentElement;
    menuItem.classList.toggle('open');
}
</script>