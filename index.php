
<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
if (!isset($_SESSION['usuario'])) {
	header("Location: /auth/login.php");
	exit;
}
require __DIR__ . "/layout/bootstrap.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Sistema Asociación - Inicio</title>
	<link rel="stylesheet" href="css/dashboard.css">
	<link rel="stylesheet" href="css/style.css">
	<link rel="stylesheet" href="css/modal-message.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
	<style>
		.topbar {
			display: flex;
			align-items: center;
			gap: 15px;
			padding: 15px 20px;
			background: white;
			border-bottom: 1px solid #e5e7eb;
		}
	</style>
</head>
<body>
<script src="layout/modal-message.js"></script>
<div class="app">
	<?php include __DIR__ . "/layout/sidebar.php"; ?>
	<main class="content">
		<header class="topbar">
			<span>Bienvenido, <?= htmlspecialchars($_SESSION['usuario']) ?></span>
		</header>
		<section class="page">
			<h1><i class="fa-solid fa-house"></i> Panel Principal</h1>
			<div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin-bottom: 20px;">
				<p>No tenemos períodos abiertos actualmente.<br>Por favor, crea un período en <a href="periodos.php">Gestión de Períodos</a></p>
			</div>
			<p>Seleccione una opción del menú lateral para comenzar.</p>
		</section>
	</main>
</div>
</body>
</html>
