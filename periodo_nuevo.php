<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

require_once "config/conexion.php";
require_once "helpers/periodo.php";

$periodo_actual = get_periodo_abierto($pdo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Período</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid;
        }
        
        .alert-info {
            background: #e7f3ff;
            color: #004085;
            border-color: #0066cc;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s;
            flex: 1;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗓️ Nuevo Período de Comercialización</h1>
        <p class="subtitle">Crea un nuevo período para gestionar las operaciones del sistema</p>
        
        <?php if ($periodo_actual): ?>
            <div class="alert alert-info">
                <strong>⚠️ Atención:</strong> 
                El período actual "<strong><?= htmlspecialchars($periodo_actual['nombre']) ?></strong>" se cerrará automáticamente al crear este nuevo período.
            </div>
        <?php endif; ?>
        
        <form id="formNuevoPeriodo">
            <div class="form-group">
                <label for="nombre">Nombre del Período *</label>
                <input 
                    type="text" 
                    id="nombre" 
                    name="nombre" 
                    placeholder="Ejemplo: CONTRATO 2027" 
                    required
                    autocomplete="off"
                >
            </div>
            
            <div class="form-group">
                <label for="fecha_apertura">Fecha de Apertura *</label>
                <input 
                    type="date" 
                    id="fecha_apertura" 
                    name="fecha_apertura" 
                    value="<?= date('Y-m-d') ?>"
                    required
                >
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary" id="btnSubmit">
                    ✅ Crear Período
                </button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='periodos.php'">
                    ❌ Cancelar
                </button>
            </div>
        </form>
    </div>
    
    <script src="layout/modal-message.js"></script>
    <script>
        document.getElementById('formNuevoPeriodo').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const submitBtn = document.getElementById('btnSubmit');
            
            // Deshabilitar botón
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Creando período...';
            
            try {
                const response = await fetch('periodo_guardar.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // ✅ MENSAJE BONITO DE ÉXITO
                    window.mostrarMensaje(
                        '✅ Período Creado', 
                        data.message, 
                        'success', 
                        3000, 
                        () => {
                            // Redirigir a la página de períodos
                            window.location.href = 'periodos.php';
                        }
                    );
                } else {
                    // ❌ ERROR
                    window.mostrarMensaje(
                        '❌ Error', 
                        data.message, 
                        'error', 
                        5000
                    );
                    
                    // Rehabilitar botón
                    submitBtn.disabled = false;
                    submitBtn.textContent = '✅ Crear Período';
                }
            } catch (error) {
                console.error('Error:', error);
                window.mostrarMensaje(
                    '❌ Error de Conexión', 
                    'No se pudo conectar con el servidor. Intenta nuevamente.', 
                    'error', 
                    5000
                );
                
                // Rehabilitar botón
                submitBtn.disabled = false;
                submitBtn.textContent = '✅ Crear Período';
            }
        });
    </script>
</body>
</html>