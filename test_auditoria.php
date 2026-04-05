<?php
// ============================================================
// test_auditoria.php — DIAGNÓSTICO (borra después de usarlo)
// Sube a: public_html/asosantalu/test_auditoria.php
// Abre en: tudominio.com/asosantalu/test_auditoria.php
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Diagnóstico del sistema</h2><pre>";

// 1. Versión PHP
echo "PHP versión: " . PHP_VERSION . "\n";

// 2. Verificar archivos
$archivos = [
    'config/db.php',
    'auditoria_helper.php',
    'gestion_usuarios.php',
    'logs_sistema.php',
    'permisos_rol.php',
    'layout/sidebar.php',
];
echo "\n--- Archivos ---\n";
foreach ($archivos as $f) {
    echo ($f . ": " . (file_exists($f) ? "✅ existe" : "❌ NO EXISTE") . "\n");
}

// 3. Probar conexión BD
echo "\n--- Conexión BD ---\n";
try {
    require_once 'config/db.php';
    echo "Conexión: ✅ OK\n";
} catch (Exception $e) {
    echo "Conexión: ❌ " . $e->getMessage() . "\n";
}

// 4. Verificar tablas
echo "\n--- Tablas de auditoría ---\n";
$tablas = ['modulos', 'permisos_rol', 'auditoria_logs', 'roles', 'usuario_rol'];
foreach ($tablas as $t) {
    try {
        $r = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo "$t: ✅ existe ($r registros)\n";
    } catch (Exception $e) {
        echo "$t: ❌ NO EXISTE o error\n";
    }
}

// 5. Probar helper
echo "\n--- Helper ---\n";
try {
    require_once 'auditoria_helper.php';
    echo "auditoria_helper.php: ✅ cargado\n";
    echo "función tienePermiso: " . (function_exists('tienePermiso') ? "✅" : "❌") . "\n";
    echo "función registrarLog: " . (function_exists('registrarLog') ? "✅" : "❌") . "\n";
    echo "función getModulosPermitidos: " . (function_exists('getModulosPermitidos') ? "✅" : "❌") . "\n";
} catch (Exception $e) {
    echo "❌ Error en helper: " . $e->getMessage() . "\n";
}

// 6. Sesión
echo "\n--- Sesión ---\n";
session_start();
echo "id_usuario en sesión: " . ($_SESSION['id_usuario'] ?? '❌ no hay sesión') . "\n";
echo "usuario en sesión: " . ($_SESSION['usuario'] ?? '❌ no hay sesión') . "\n";

echo "</pre><p style='color:red'><strong>⚠ Borra este archivo después de usarlo.</strong></p>";
