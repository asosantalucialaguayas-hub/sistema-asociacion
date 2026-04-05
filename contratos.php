
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}
require __DIR__ . "/layout/bootstrap.php";

// Obtener contratos del período seleccionado
$contratos = [];
if ($id_periodo_actual) {
    $stmt = $pdo->prepare("
        SELECT * FROM contrato_periodo 
        WHERE id_periodo = ? 
        ORDER BY fecha_firma DESC
    ");
    $stmt->execute([$id_periodo_actual]);
    $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contratos</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="app">
    <?php include __DIR__ . "/layout/sidebar.php"; ?>

    <main class="content">
        <header class="topbar" style="display:flex;align-items:center;">
            <span>Bienvenido, <?= htmlspecialchars($_SESSION['usuario']) ?></span>
            <div style="margin-left:auto;min-width:320px;">
                <?php include __DIR__ . "/layout/selector-periodo.php"; ?>
            </div>
        </header>

        <section class="page">
            <h1><i class="fa-solid fa-file-contract"></i> Contratos</h1>
            <!-- Contenido filtrado por período -->
            <div class="form-card">
                <?php if (!$periodoSeleccionado): ?>
                    <p>No hay período seleccionado</p>
                <?php elseif (empty($contratos)): ?>
                    <p>No hay contratos en este período</p>
                <?php else: ?>
                    <h2>Contratos del período <?= htmlspecialchars($periodoSeleccionado['nombre']) ?></h2>
                    <?php foreach ($contratos as $c): ?>
                        <div style="padding:15px;border:1px solid #ddd;margin-bottom:10px;border-radius:8px">
                            <strong><?= htmlspecialchars($c['titulo']) ?></strong>
                            <p>Fecha: <?= date('d/m/Y', strtotime($c['fecha_firma'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>