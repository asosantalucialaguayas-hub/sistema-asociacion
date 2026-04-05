<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}
require __DIR__ . "/bootstrap.php";
?>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['periodo'])) {
    $_SESSION['periodo_activo'] = (int)$_POST['periodo'];
    echo "<script>location.href=location.href;</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema Asociación</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="app">
    <?php include __DIR__ . "/sidebar.php"; ?>
    <main class="content">

        <header class="topbar" style="display:flex;align-items:center;gap:15px;">

            <!-- Bienvenida -->
            <span>Bienvenido, <?= htmlspecialchars($_SESSION['usuario']); ?></span>

            <div style="margin-left:auto;display:flex;align-items:center;gap:12px;">

                <!-- 🔔 CAMPANITA DE NOTIFICACIONES -->
                <?php include __DIR__ . "/../notificaciones.php"; ?>

                <!-- Selector de período -->
                <form method="post" action="" id="form-periodo" style="margin:0;display:inline;">
                    <select name="periodo" id="periodo-select"
                            style="padding:6px 12px;border-radius:6px;min-width:200px;"
                            onchange="document.getElementById('form-periodo').submit()">
                        <?php if (!empty($todos = get_all_periodos($pdo))): ?>
                            <?php foreach ($todos as $p): ?>
                                <option value="<?= $p['id_periodo'] ?>"
                                    <?= ($periodoSeleccionado && $periodoSeleccionado['id_periodo'] == $p['id_periodo']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </form>

            </div>
        </header>

        <section class="page">
            <h1>Panel principal</h1>
            <p>Seleccione una opción del menú lateral.</p>
        </section>
    </main>
</div>
</body>
</html>