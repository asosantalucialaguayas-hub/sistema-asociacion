<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingreso | Asociación</title>
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .error-msg {
            background-color: #fde8e8;
            color: #c0392b;
            border: 1px solid #f5c6c6;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 12px;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <img src="../img/logo.png" class="logo" alt="Logo Asociación">
        <h2>Sistema de Gestión</h2>
        <p>Asociación Santa Lucía Corotú</p>

        <?php if (isset($_GET['error'])): ?>
            <div class="error-msg">
                <i class="fa fa-circle-exclamation"></i>
                Usuario o contraseña incorrectos
            </div>
        <?php endif; ?>

        <form action="validar.php" method="POST">
            <div class="field">
                <i class="fa fa-user"></i>
                <input type="text" name="usuario" placeholder="Usuario" required>
            </div>
            <div class="field">
                <i class="fa fa-lock"></i>
                <input type="password" name="clave" placeholder="Contraseña" required>
            </div>
            <button type="submit">Ingresar</button>
        </form>
        <span class="footer">© 2025 Asociación</span>
    </div>
</div>
</body>
</html>