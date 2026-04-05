<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;

}

?>
<!DOCTYPE html>
<?php
require_once 'config/conexion.php';
require "layout/bootstrap.php";

?><html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud de ingreso</title>

    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/modal-message.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="app">

    <?php include __DIR__ . "/layout/sidebar.php"; ?>

    <main class="content">
        <header class="topbar">
            <span>Bienvenido, <?php echo $_SESSION['usuario']; ?></span>
        </header>

        <section class="page">
            <h1>Solicitud de ingreso</h1>

            <div class="form-card">
                <form id="formSolicitud" autocomplete="off">

                    <div class="form-grid">

                        <div class="form-group">
                            <label>Identificación</label>
                            <input type="text" name="identificacion" required>
                        </div>

                        <div class="form-group">
                            <label>Nombres completos</label>
                            <input type="text" name="nombres_completos" required>
                        </div>

                        <div class="form-group">
                            <label>Correo</label>
                            <input type="email" name="correo">
                        </div>

                        <div class="form-group">
                            <label>Celular</label>
                            <input type="text" name="celular">
                        </div>

                        <div class="form-group">
                            <label>Fecha de nacimiento</label>
                            <input type="date" name="fecha_nacimiento">
                        </div>

                        <div class="form-group">
                            <label>Dirección</label>
                            <input type="text" name="direccion">
                        </div>

                        <div class="form-group full">
                            <label>Observaciones</label>
                            <textarea name="observaciones"></textarea>
                        </div>

                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-primary" id="btnGuardarSolicitud">
                            <i class="fa fa-save"></i> Guardar solicitud
                        </button>

                        <a href="solicitud_listado.php" class="btn-secondary">
                            <i class="fa fa-list"></i> Ver solicitudes
                        </a>
                    </div>

                </form>
            </div>

        </section>
    </main>

</div>

<!-- Modal bonito -->
<script src="layout/modal-message.js?v=20260211"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {

    const btn = document.getElementById('btnGuardarSolicitud');
    const form = document.getElementById('formSolicitud');

    btn.addEventListener('click', async function() {

        const formData = new FormData(form);

        btn.disabled = true;

        try {

            const response = await fetch('solicitud_guardar.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            
        if (data.success) {

    mostrarMensaje(
        'Éxito',
        data.message || 'Solicitud creada con éxito',
        'success',
        2000, // se cierra en 2 segundos
        () => {
            window.location.reload(); // 🔥 aquí se recarga
        }
    );

}
else {

                mostrarMensaje(
                    'Error',
                    data.message || 'No se pudo guardar la solicitud',
                    'error'
                );

            }

        } catch (error) {

            mostrarMensaje(
                'Error',
                'Error de conexión con el servidor',
                'error'
            );

        } finally {
            btn.disabled = false;
        }

    });

});
</script>

</body>
</html>
