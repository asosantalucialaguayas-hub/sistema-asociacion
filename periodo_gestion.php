<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once "config/conexion.php";
require_once "helpers/periodo.php";

$periodo_actual = get_periodo_abierto($pdo);
$todos_periodos = get_all_periodos($pdo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Períodos</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .periodo-actual {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
        }
        
        .badge-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin: 5px;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .periodo-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗓️ Gestión de Períodos</h1>
        
        <!-- Período Actual -->
        <?php if ($periodo_actual): ?>
            <div class="card periodo-actual">
                <h2>Período Activo</h2>
                <h3><?= htmlspecialchars($periodo_actual['nombre']) ?></h3>
                <p>📅 Desde: <?= date('d/m/Y', strtotime($periodo_actual['fecha_apertura'])) ?></p>
                <button class="btn btn-danger" onclick="cerrarPeriodo()">
                    🔒 Cerrar Período
                </button>
            </div>
        <?php else: ?>
            <div class="card">
                <p>⚠️ No hay período abierto. <a href="periodo_nuevo.php">Crear nuevo período</a></p>
            </div>
        <?php endif; ?>
        
        <!-- Botón Nuevo -->
        <div class="card">
            <a href="periodo_nuevo.php" class="btn btn-success">+ Nuevo Período</a>
        </div>
        
        <!-- Historial -->
        <div class="card">
            <h2>📋 Historial</h2>
            <?php foreach ($todos_periodos as $p): ?>
                <div class="periodo-item">
                    <div>
                        <strong><?= htmlspecialchars($p['nombre']) ?></strong>
                        <br>
                        <small>📅 <?= date('d/m/Y', strtotime($p['fecha_apertura'])) ?></small>
                    </div>
                    <div>
                        <span class="badge badge-<?= $p['estado'] === 'ABIERTO' ? 'success' : 'secondary' ?>">
                            <?= $p['estado'] ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script src="layout/modal-message.js"></script>
    <script>
        async function cerrarPeriodo() {
            if (!confirm('¿Cerrar el período actual?\n\nEsto bloqueará las funciones principales.')) {
                return;
            }
            
            try {
                const res = await fetch('periodo_cerrar.php', { method: 'POST' });
                const data = await res.json();
                
                if (data.success) {
                    window.mostrarMensaje('✅ Éxito', data.message, 'success', 3000, () => {
                        window.location.reload();
                    });
                } else {
                    window.mostrarMensaje('❌ Error', data.message, 'error', 5000);
                }
            } catch (error) {
                window.mostrarMensaje('❌ Error', 'Error de conexión', 'error', 5000);
            }
        }
    </script>
</body>
</html>
