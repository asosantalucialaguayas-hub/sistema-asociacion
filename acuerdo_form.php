<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}

require "config/conexion.php";
require_once "helpers/periodo.php"; // 🔒 candado
// Candado de período HTML
$periodo = require_periodo_abierto_html($pdo, 'index.php');

/* =========================
   BUSCAR SOCIO
   ✅ CAMBIO: se agrega AND id_periodo para buscar solo en el periodo actual
========================= */
$persona = null;
if (isset($_GET['buscar']) && $_GET['buscar'] !== '') {
    $buscar = "%".$_GET['buscar']."%";
    $stmt = $pdo->prepare("
        SELECT identificacion, nombres_completos, fecha_nacimiento 
        FROM solicitud_ingreso
        WHERE id_periodo = ?
          AND (identificacion LIKE ? OR nombres_completos LIKE ?)
        LIMIT 1
    ");
    $stmt->execute([$periodo['id_periodo'], $buscar, $buscar]);
    $persona = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar si ya tiene acuerdo en este periodo
    if ($persona) {
        $chk = $pdo->prepare("
            SELECT numero_acuerdo FROM acuerdo_productor
            WHERE cedula = ? AND id_periodo = ?
            LIMIT 1
        ");
        $chk->execute([$persona['identificacion'], $periodo['id_periodo']]);
        $acuerdoExistente = $chk->fetchColumn();
    }
}

/* =========================
   ✅ CAMBIO: Se eliminó el bloque if POST con INSERT directo.
   Todo guardado pasa por acuerdo_guardar.php vía AJAX (ya estaba en el JS).
   Tenerlo aquí causaba que se guardara SIN numero_acuerdo y a veces sin id_periodo.
========================= */
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Acuerdo de productor</title>

<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/modal-message.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* BUSCADOR */
.search-row {
    display: flex;
    gap: 12px;
    align-items: center;
}

.search-row input {
    flex: 1;
    padding: 12px 14px;
    font-size: 15px;
}

.btn-search {
    background: #1f3a5f;
    color: #fff;
    border: none;
    padding: 12px 22px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
}

.btn-search:hover {
    background: #16304d;
}

/* MODAL FLOTANTE */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.modal-overlay.active {
    display: flex;
}

.modal-box {
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
    text-align: center;
    max-width: 400px;
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-box h2 {
    color: #1f3a5f;
    margin-bottom: 10px;
    font-size: 20px;
}

.icon-success {
    font-size: 48px;
    color: #10b981;
    margin-bottom: 15px;
}

.modal-box button {
    background: #1f3a5f;
    color: #fff;
    border: none;
    padding: 10px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}

.modal-box button:hover {
    background: #16304d;
}

.btn-actions {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
}

.btn-actions a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: #2563eb;
    color: #fff;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
}

.btn-actions a:hover {
    background: #1d4ed8;
}

/* ✅ Alerta acuerdo ya existe */
.alerta-duplicado {
    background: #fef3c7;
    border: 2px solid #f59e0b;
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    color: #92400e;
}
.alerta-duplicado i {
    font-size: 22px;
    color: #d97706;
    flex-shrink: 0;
}
</style>
<?php include 'layout/modals.php'; ?>
</head>
<body>
<script src="layout/modal-message.js"></script>

<div class="app">
<?php include __DIR__."/layout/sidebar.php"; ?>

<main class="content">
<header class="topbar">
    <span>Bienvenido, <?= $_SESSION['usuario'] ?></span>
</header>

<section class="page">
<h1>Acuerdo de productor</h1>
<div style="margin-bottom:10px;color:#2563eb;font-weight:bold;">Período actual: <?= htmlspecialchars($periodo['nombre']) ?></div>

<div class="btn-actions">
    <a href="acuerdo_listado.php">
        <i class="fa fa-list"></i> Ver acuerdos
    </a>
</div>

<!-- BUSCADOR -->
<div class="form-card">
<form method="GET">
    <label>Buscar por cédula o nombre</label>
    <div class="search-row">
        <input type="text" name="buscar"
               value="<?= htmlspecialchars($_GET['buscar'] ?? '') ?>"
               placeholder="Ingrese cédula o nombre" required>
        <button class="btn-search">
            <i class="fa fa-search"></i> Buscar
        </button>
    </div>
</form>
</div>

<?php if (isset($_GET['buscar']) && !$persona): ?>
<div class="form-card">
    <p style="color:#dc2626;">
        <i class="fa fa-circle-xmark"></i>
        No se encontró ninguna solicitud de ingreso en el periodo actual con ese dato.
    </p>
</div>
<?php endif; ?>

<?php if ($persona): ?>

    <?php if (!empty($acuerdoExistente)): ?>
    <!-- ✅ YA TIENE ACUERDO — mostrar alerta en lugar de formulario duplicado -->
    <div class="alerta-duplicado">
        <i class="fa fa-triangle-exclamation"></i>
        <div>
            <strong><?= htmlspecialchars($persona['nombres_completos']) ?></strong>
            ya tiene el acuerdo <strong><?= htmlspecialchars($acuerdoExistente) ?></strong>
            registrado en el periodo <strong><?= htmlspecialchars($periodo['nombre']) ?></strong>.
            No es necesario crear otro.
        </div>
    </div>

    <?php else: ?>
    <!-- FORMULARIO -->
    <div class="form-card">
    <form method="POST">
    <div class="form-grid">

    <div class="form-group">
        <label>Cédula</label>
        <input type="text" name="cedula" value="<?= $persona['identificacion'] ?>" readonly>
    </div>

    <div class="form-group">
        <label>Nombres completos</label>
        <input type="text" name="nombres_completos" value="<?= $persona['nombres_completos'] ?>" readonly>
    </div>

    <div class="form-group">
        <label>Fecha nacimiento</label>
        <input type="date" name="fecha_nacimiento" value="<?= $persona['fecha_nacimiento'] ?>">
    </div>

    <div class="form-group">
        <label>Provincia</label>
        <input type="text" name="provincia">
    </div>

    <div class="form-group">
        <label>Cantón</label>
        <input type="text" name="canton">
    </div>

    <div class="form-group">
        <label>Parroquia</label>
        <input type="text" name="parroquia">
    </div>

    <div class="form-group">
        <label>Sector</label>
        <input type="text" name="sector">
    </div>

    <div class="form-group">
        <label>¿Posee riego?</label>
        <select name="posee_riego">
            <option value="SI">SI</option>
            <option value="NO">NO</option>
        </select>
    </div>

    <div class="form-group">
        <label>Periodo de fertilización</label>
        <input type="text" name="periodo_de_fertilizacion">
    </div>

    <div class="form-group">
        <label>Cacao Nacional (Has)</label>
        <input type="number" step="0.01" name="cacao_nacional_has">
    </div>

    <div class="form-group">
        <label>Producción Nacional estimada</label>
        <input type="number" step="0.01" name="estimado_produccion_nacional">
    </div>

    <div class="form-group">
        <label>Cacao CCN51 (Has)</label>
        <input type="number" step="0.01" name="cacao_ccn51_has">
    </div>

    <div class="form-group">
        <label>Producción CCN51 estimada</label>
        <input type="number" step="0.01" name="estimado_produccion_ccn51">
    </div>

    <div class="form-group">
        <label>Fecha de firma</label>
        <input type="date" name="fecha_firma">
    </div>

    </div>

    <div class="form-actions">
        <button type="submit">
            <i class="fa fa-save"></i> Guardar acuerdo
        </button>
    </div>

    </form>
    </div>
    <?php endif; ?>

<?php endif; ?>

</section>
</main>
</div>

</body>
</html>

<!-- MODAL FLOTANTE DE ÉXITO -->
<div id="modalSuccess" class="modal-overlay">
    <div class="modal-box">
        <div class="icon-success">
            <i class="fa fa-check-circle"></i>
        </div>
        <h2>¡Éxito!</h2>
        <p id="modalMessage">Datos guardado exitosamente</p>
        <p id="modalNumero"></p>
        <button onclick="cerrarModal()">Aceptar</button>
    </div>
</div>

<script>
// Manejar envío del formulario
document.querySelectorAll('form[method="POST"]').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('acuerdo_guardar.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar modal
                const modal = document.getElementById('modalSuccess');
                const modalNumero = document.getElementById('modalNumero');
                if (data.numero_acuerdo) {
                    modalNumero.innerHTML = `<strong>Acuerdo: ${data.numero_acuerdo}</strong>`;
                }
                modal.classList.add('active');
                
                // Resetear formulario
                this.reset();
            } else {
                mostrarMensaje('Error', 'Error: ' + (data.message || 'No se pudo guardar'), 'error');
            }
        })
        .catch(error => {
            mostrarMensaje('Error', 'Error en la solicitud: ' + error, 'error');
        });
    });
});

function cerrarModal() {
    document.getElementById('modalSuccess').classList.remove('active');
}
</script>