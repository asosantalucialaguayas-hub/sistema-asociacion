<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once dirname(__DIR__) . '/config/conexion.php';
// $pdo ya está disponible desde conexion.php