<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ingreso | Asociación</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>

*{
    box-sizing:border-box;
    margin:0;
    padding:0;
    font-family:"Segoe UI", Arial, sans-serif;
}

body{
    height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg,#0f4c75,#3282b8,#1b6ca8);
}

/* CONTENEDOR */

.login-wrapper{
    display:flex;
    justify-content:center;
    align-items:center;
    width:100%;
}

/* TARJETA */

.login-card{

    width:360px;
    padding:40px;

    background:rgba(255,255,255,0.18);

    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);

    border-radius:20px;
    border:1px solid rgba(255,255,255,0.35);

    box-shadow:
        0 10px 40px rgba(0,0,0,0.25),
        inset 0 0 12px rgba(255,255,255,0.2);

    text-align:center;
    color:white;

}

/* LOGO */

.logo{
    width:80px;
    margin-bottom:10px;
}

/* TITULOS */

h2{
    margin-top:10px;
    margin-bottom:5px;
}

p{
    margin-bottom:25px;
    opacity:0.9;
    font-size:14px;
}

/* CAMPOS */

.field{

    display:flex;
    align-items:center;

    background:rgba(255,255,255,0.25);

    border-radius:12px;
    padding:12px;
    margin-bottom:15px;

}

.field i{
    margin-right:10px;
    opacity:0.8;
}

.field input{

    border:none;
    background:transparent;
    outline:none;

    color:white;
    width:100%;

}

.field input::placeholder{
    color:rgba(255,255,255,0.7);
}

/* BOTON */

button{

    width:100%;
    padding:12px;

    border:none;
    border-radius:12px;

    background:rgba(255,255,255,0.35);

    color:white;
    font-weight:600;

    cursor:pointer;

    transition:all .3s;

}

button:hover{

    background:rgba(255,255,255,0.55);
    transform:translateY(-2px);

}

/* MENSAJE ERROR */

.error-msg{

    background:rgba(255,0,0,0.15);
    border:1px solid rgba(255,0,0,0.4);

    padding:10px;
    border-radius:10px;

    margin-bottom:15px;

    display:flex;
    align-items:center;
    gap:8px;

}

/* FOOTER */

.footer{

    display:block;
    margin-top:20px;

    font-size:12px;
    opacity:0.8;

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

<span class="footer">© 2025 Asociación Santa Lucía Corotú</span>

</div>

</div>

</body>
</html>