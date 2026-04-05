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

background:linear-gradient(135deg,#1e3a8a 0%, #3b82f6 100%);

}

/* CONTENEDOR */

.login-wrapper{
width:100%;
display:flex;
justify-content:center;
align-items:center;
}

/* TARJETA LOGIN */

.login-card{

background:white;

width:380px;

border-radius:14px;

padding:40px;

box-shadow:0 10px 30px rgba(0,0,0,0.15);

text-align:center;

}

/* LOGO */

.logo{
width:90px;
margin-bottom:10px;
}

/* TITULOS */

h2{
color:#1f3a5f;
margin-bottom:5px;
}

.subtitle{
font-size:14px;
color:#6c757d;
margin-bottom:25px;
}

/* INPUTS */

.field{

display:flex;
align-items:center;

background:#f3f4f6;

border-radius:8px;

padding:12px;

margin-bottom:15px;

border:1px solid #e5e7eb;

transition:all .2s;

}

.field:focus-within{
border-color:#3b82f6;
background:white;
}

.field i{
margin-right:10px;
color:#3b82f6;
}

.field input{

border:none;
background:none;
outline:none;

width:100%;
font-size:14px;

}

/* BOTON */

button{

width:100%;

padding:12px;

border:none;

border-radius:8px;

background:linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%);

color:white;

font-size:15px;
font-weight:600;

cursor:pointer;

transition:all .3s;

}

button:hover{

transform:translateY(-2px);

box-shadow:0 6px 15px rgba(59,130,246,0.4);

}

/* ERROR */

.error-msg{

background:#fee2e2;

color:#b91c1c;

padding:10px;

border-radius:8px;

margin-bottom:15px;

font-size:13px;

display:flex;
align-items:center;
gap:8px;

}

/* FOOTER */

.footer{

margin-top:20px;

font-size:12px;

color:#6c757d;

}

</style>

</head>

<body>

<div class="login-wrapper">

<div class="login-card">

<img src="../img/logo.png" class="logo">

<h2>Sistema de Gestión</h2>

<p class="subtitle">Asociación Santa Lucía Corotú</p>

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

<button type="submit">
<i class="fa fa-right-to-bracket"></i> Ingresar
</button>

</form>

<span class="footer">
© 2025 Asociación Santa Lucía Corotú
</span>

</div>

</div>

</body>
</html>
