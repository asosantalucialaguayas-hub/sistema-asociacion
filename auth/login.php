<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ingreso | Asociación</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; font-family: "Segoe UI", Arial, sans-serif; }

body {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1e3a5f 0%, #1a4f8a 60%, #1a6fb5 100%);
  padding: 1rem;
}

.login-container {
  display: flex;
  width: 100%;
  max-width: 780px;
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,0.35);
}

/* PANEL IZQUIERDO */
.panel-marca {
  flex: 1;
  background: rgba(255,255,255,0.07);
  padding: 2.5rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border-right: 1px solid rgba(255,255,255,0.12);
}

.panel-marca .logo-circle {
  width: 200px;
  height: 200px;
  background: rgba(255,255,255,0.15);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
  border: 2px solid rgba(255,255,255,0.35);
}

.panel-marca .logo-circle img {
  width: 175px;
  height: 175px;
  object-fit: contain;
}

.panel-marca h3 {
  color: rgba(255,255,255,0.95);
  font-size: 15px;
  font-weight: 600;
  text-align: center;
  margin-bottom: 6px;
}

.panel-marca p {
  color: rgba(255,255,255,0.55);
  font-size: 12px;
  text-align: center;
}

.panel-features {
  margin-top: 2rem;
  width: 100%;
  border-top: 1px solid rgba(255,255,255,0.1);
  padding-top: 1.5rem;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.feature-item {
  display: flex;
  align-items: center;
  gap: 10px;
  color: rgba(255,255,255,0.5);
  font-size: 13px;
}

.feature-item i { font-size: 15px; }

/* PANEL DERECHO */
.panel-form {
  flex: 1.2;
  background: white;
  padding: 2.5rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.panel-form .eyebrow {
  font-size: 12px;
  color: #6b7280;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  margin-bottom: 4px;
}

.panel-form h2 {
  font-size: 22px;
  font-weight: 600;
  color: #1e3a5f;
  margin-bottom: 1.75rem;
}

.field-group { margin-bottom: 14px; }

.field-group label {
  font-size: 12px;
  font-weight: 600;
  color: #4b5563;
  display: block;
  margin-bottom: 6px;
}

.field-wrap {
  display: flex;
  align-items: center;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 10px 14px;
  gap: 10px;
  background: #f9fafb;
  transition: all .2s;
}

.field-wrap:focus-within {
  border-color: #1a4f8a;
  background: white;
  box-shadow: 0 0 0 3px rgba(26,79,138,0.1);
}

.field-wrap i { color: #9ca3af; font-size: 14px; }
.field-wrap:focus-within i { color: #1a4f8a; }

.field-wrap input {
  border: none;
  background: none;
  outline: none;
  width: 100%;
  font-size: 14px;
  color: #1f2937;
}

.error-msg {
  background: #fee2e2;
  color: #b91c1c;
  padding: 10px 14px;
  border-radius: 8px;
  margin-bottom: 16px;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-ingresar {
  width: 100%;
  padding: 12px;
  background: #1e3a5f;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 8px;
  transition: all .2s;
}

.btn-ingresar:hover {
  background: #1a4f8a;
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(26,79,138,0.35);
}

.footer-text {
  font-size: 11px;
  color: #9ca3af;
  text-align: center;
  margin-top: 1.5rem;
}

@media (max-width: 580px) {
  .panel-marca { display: none; }
  .panel-form { padding: 2rem; }
}
</style>
</head>
<body>

<div class="login-container">

  <!-- Panel marca -->
  <div class="panel-marca">
    <div class="logo-circle">
      <img src="../img/logo.png" alt="Logo">
    </div>
    <h3>Sistema de Gestión</h3>
    <p>Asociación Santa Lucía Corotú</p>

    <div class="panel-features">
      <div class="feature-item">
        <i class="fa fa-database"></i> Gestión de datos
      </div>
    </div>
  </div>

  <!-- Panel formulario -->
  <div class="panel-form">
    <p class="eyebrow">Bienvenido</p>
    <h2>Ingresa a tu cuenta</h2>

    <?php if (isset($_GET['error'])): ?>
    <div class="error-msg">
      <i class="fa fa-circle-exclamation"></i>
      Usuario o contraseña incorrectos
    </div>
    <?php endif; ?>

    <form action="validar.php" method="POST">

      <div class="field-group">
        <label>Usuario</label>
        <div class="field-wrap">
          <i class="fa fa-user"></i>
          <input type="text" name="usuario" placeholder="Tu usuario" required>
        </div>
      </div>

      <div class="field-group">
        <label>Contraseña</label>
        <div class="field-wrap">
          <i class="fa fa-lock"></i>
          <input type="password" name="clave" placeholder="Tu contraseña" required>
        </div>
      </div>

      <button type="submit" class="btn-ingresar">
        <i class="fa fa-right-to-bracket"></i> Ingresar al sistema
      </button>

    </form>

    <p class="footer-text">© 2025 Asociación Santa Lucía Corotú</p>
  </div>

</div>

</body>
</html>