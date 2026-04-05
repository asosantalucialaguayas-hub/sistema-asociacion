<?php
$host = "localhost"; // en Hostinger se usa localhost
$db = "u241263046_asociacion2";
$user = "u241263046_pedro";
$pass = "40242745aA"; // exactamente la misma que ves en Bases de datos MySQL

try {
 $pdo = new PDO(
 "mysql:host=$host;dbname=$db;charset=utf8mb4",
 $user,
 $pass,
 [
 PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES => false
 ]
 );
} catch (PDOException $e) {
 die("Error conexión BD: " . $e->getMessage());
}