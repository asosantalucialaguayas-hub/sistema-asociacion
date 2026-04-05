<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Acceso Restringido</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
body{
    margin:0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background:#f4f6f9;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}

.card{
    background:#ffffff;
    padding:40px;
    border-radius:12px;
    box-shadow:0 8px 25px rgba(0,0,0,0.08);
    text-align:center;
    width:400px;
}

.card i{
    font-size:60px;
    color:#dc3545;
    margin-bottom:20px;
}

.card h2{
    margin:10px 0;
    color:#1f3a5f;
}

.card p{
    color:#6c757d;
    margin-bottom:30px;
}

.btn-dashboard{
    text-decoration:none;
    background:#1f3a5f;
    color:#fff;
    padding:12px 20px;
    border-radius:8px;
    font-weight:600;
    display:inline-flex;
    align-items:center;
    gap:8px;
    transition:0.3s;
}

.btn-dashboard:hover{
    background:#162a45;
}
</style>
</head>

<body>

<div class="card">
    <i class="fa-solid fa-lock"></i>
    <h2>Acceso Restringido</h2>
    <p>Usted no tiene acceso a estas funciones.</p>

    <a href="dashboard.php" class="btn-dashboard">
        <i class="fa fa-home"></i> Regresar a la pantalla principal
    </a>
</div>

</body>
</html>