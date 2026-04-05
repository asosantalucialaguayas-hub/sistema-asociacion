<?php
session_start();
require_once "../config/conexion.php";

$usuario = $_POST['usuario'] ?? '';
$clave   = $_POST['clave'] ?? '';

$sql = "SELECT * FROM usuarios 
        WHERE usuario = ? 
        AND contrasena = ?
        AND estado = 'activo'
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$usuario, $clave]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $_SESSION['usuario']    = $user['usuario'];
    $_SESSION['id_usuario'] = $user['id_usuario'];
    header("Location: ../dashboard.php");
    exit;
} else {
    header("Location: login.php?error=1");
    exit;
}