<?php
// ============================================================
// nueva_convocatoria.php - Crear nueva convocatoria
// ============================================================
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: ../login.php'); exit; }
if (!in_array($_SESSION['rol'] ?? '', ['admin','secretario'])) {
    header('Location: asistencia.php'); exit;
}

require_once '../config/db.php';

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo  = trim($_POST['titulo'] ?? '');
    $fecha   = $_POST['fecha'] ?? '';
    $hora    = $_POST['hora'] ?? '';
    $lugar   = trim($_POST['lugar'] ?? '');
    $tipo    = $_POST['tipo'] ?? 'ordinaria';
    $puntos  = array_filter(array_map('trim', $_POST['puntos'] ?? []));

    if (!$titulo || !$fecha || !$hora || !$lugar) {
        $error = 'Por favor completa todos los campos obligatorios.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO convocatorias (titulo, fecha, hora, lugar, tipo, estado, creado_por)
                VALUES (?, ?, ?, ?, ?, 'programada', ?)
            ");
            $stmt->execute([$titulo, $fecha, $hora, $lugar, $tipo, $_SESSION['usuario_id']]);
            $conv_id = $pdo->lastInsertId();

            // Insertar puntos del orden del día
            $stmtP = $pdo->prepare("INSERT INTO convocatoria_puntos (convocatoria_id, numero, descripcion) VALUES (?,?,?)");
            $num = 1;
            foreach ($puntos as $p) {
                if ($p !== '') {
                    $stmtP->execute([$conv_id, $num++, $p]);
                }
            }

            $pdo->commit();
            header("Location: asistencia.php?vista=detalle&id=$conv_id&nuevo=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error al guardar: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nueva Convocatoria</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { background:#f4f6f9; font-family:'Segoe UI',sans-serif; }
.form-card { max-width:760px; margin:40px auto; background:#fff; border-radius:18px; box-shadow:0 4px 24px rgba(0,0,0,.1); overflow:hidden; }
.form-header { background:linear-gradient(135deg,#2c3e7a,#4a6cf7); color:#fff; padding:28px 32px; }
.form-body { padding:32px; }
.punto-item { display:flex; gap:10px; align-items:center; margin-bottom:10px; }
.punto-num { background:#2c3e7a; color:#fff; border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; font-size:.8rem; flex-shrink:0; }
.btn-add-punto { border:2px dashed #4a6cf7; color:#4a6cf7; background:transparent; border-radius:10px; padding:8px 16px; width:100%; margin-top:6px; transition:.2s; }
.btn-add-punto:hover { background:#f0f4ff; }
</style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div style="margin-left:220px; padding:30px 24px;">
<div class="form-card">
    <div class="form-header">
        <h4 class="fw-bold mb-0"><i class="fa-solid fa-calendar-plus me-2"></i>Nueva Convocatoria</h4>
        <p class="opacity-75 mb-0 mt-1">Crea una convocatoria para registrar asistencia de socios</p>
    </div>
    <div class="form-body">

    <?php if ($error): ?>
        <div class="alert alert-danger rounded-3"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="formConvocatoria">

        <div class="row g-3 mb-4">
            <div class="col-12">
                <label class="form-label fw-semibold">Título de la Convocatoria <span class="text-danger">*</span></label>
                <input type="text" name="titulo" class="form-control rounded-3" placeholder="Ej: Asamblea General Ordinaria 2026" value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Fecha <span class="text-danger">*</span></label>
                <input type="date" name="fecha" class="form-control rounded-3" value="<?= htmlspecialchars($_POST['fecha'] ?? date('Y-m-d')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Hora <span class="text-danger">*</span></label>
                <input type="time" name="hora" class="form-control rounded-3" value="<?= htmlspecialchars($_POST['hora'] ?? '09:00') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Tipo</label>
                <select name="tipo" class="form-select rounded-3">
                    <option value="ordinaria" <?= ($_POST['tipo']??'ordinaria')==='ordinaria'?'selected':'' ?>>Ordinaria</option>
                    <option value="extraordinaria" <?= ($_POST['tipo']??'')==='extraordinaria'?'selected':'' ?>>Extraordinaria</option>
                    <option value="urgente" <?= ($_POST['tipo']??'')==='urgente'?'selected':'' ?>>Urgente</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Lugar / Dirección <span class="text-danger">*</span></label>
                <input type="text" name="lugar" class="form-control rounded-3" placeholder="Ej: Salón comunal Asociación, Guayas" value="<?= htmlspecialchars($_POST['lugar'] ?? '') ?>" required>
            </div>
        </div>

        <!-- Orden del día -->
        <div class="mb-4">
            <label class="form-label fw-semibold d-block mb-3">
                <i class="fa-solid fa-list-ol me-2 text-primary"></i>Orden del Día
                <small class="text-muted fw-normal">(agrega los puntos a tratar)</small>
            </label>
            <div id="contenedorPuntos">
                <?php
                $puntosPost = $_POST['puntos'] ?? ['','',''];
                foreach ($puntosPost as $i => $pt):
                ?>
                <div class="punto-item" id="punto-<?= $i ?>">
                    <div class="punto-num"><?= $i+1 ?></div>
                    <input type="text" name="puntos[]" class="form-control rounded-3" 
                           placeholder="Describe el punto <?= $i+1 ?>..."
                           value="<?= htmlspecialchars($pt) ?>">
                    <?php if ($i > 0): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="eliminarPunto(this)">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn-add-punto mt-2" onclick="agregarPunto()">
                <i class="fa-solid fa-plus me-1"></i> Agregar Punto
            </button>
        </div>

        <hr>
        <div class="d-flex gap-3 justify-content-end">
            <a href="asistencia.php" class="btn btn-outline-secondary rounded-pill px-4">Cancelar</a>
            <button type="submit" class="btn btn-primary rounded-pill px-5">
                <i class="fa-solid fa-floppy-disk me-2"></i>Guardar Convocatoria
            </button>
        </div>
    </form>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let contadorPuntos = <?= count($_POST['puntos'] ?? ['','','']) ?>;

function agregarPunto() {
    contadorPuntos++;
    const div = document.createElement('div');
    div.className = 'punto-item';
    div.id = `punto-${contadorPuntos}`;
    div.innerHTML = `
        <div class="punto-num">${contadorPuntos}</div>
        <input type="text" name="puntos[]" class="form-control rounded-3" placeholder="Describe el punto ${contadorPuntos}...">
        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="eliminarPunto(this)">
            <i class="fa-solid fa-xmark"></i>
        </button>
    `;
    document.getElementById('contenedorPuntos').appendChild(div);
    renumerarPuntos();
}

function eliminarPunto(btn) {
    btn.closest('.punto-item').remove();
    renumerarPuntos();
}

function renumerarPuntos() {
    document.querySelectorAll('.punto-num').forEach((el, i) => el.textContent = i + 1);
}
</script>
</body>
</html>
