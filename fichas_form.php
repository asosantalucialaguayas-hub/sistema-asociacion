<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require __DIR__ . "/layout/bootstrap.php";

$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
if ($id_usuario && function_exists('tienePermiso') && isset($pdo)) {
    $puede_agregar   = tienePermiso($pdo, $id_usuario, 'fairtrade', 'puede_agregar');
    $puede_modificar = tienePermiso($pdo, $id_usuario, 'fairtrade', 'puede_modificar');
} else {
    $puede_agregar = $puede_modificar = false;
}

if (!$puede_agregar && !$puede_modificar) {
    die('<div style="text-align:center;margin-top:80px;font-family:sans-serif;"><h2>⛔ Acceso denegado</h2><a href="fichas_lista.php">← Volver</a></div>');
}

$id_ficha = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$ficha    = null;
$secciones = [];

if ($id_ficha) {
    $st = $pdo->prepare("SELECT * FROM fichas WHERE id_ficha=?");
    $st->execute([$id_ficha]);
    $ficha = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ficha) { header("Location: fichas_lista.php"); exit; }

    $stS = $pdo->prepare("SELECT * FROM ficha_secciones WHERE id_ficha=? ORDER BY orden");
    $stS->execute([$id_ficha]);
    $secciones_raw = $stS->fetchAll(PDO::FETCH_ASSOC);
    foreach ($secciones_raw as $s) {
        $stP = $pdo->prepare("SELECT * FROM ficha_preguntas WHERE id_seccion=? ORDER BY orden");
        $stP->execute([$s['id_seccion']]);
        $s['preguntas'] = $stP->fetchAll(PDO::FETCH_ASSOC);
        $secciones[] = $s;
    }
}

// GUARDAR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre      = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $activa      = isset($_POST['activa']) ? 1 : 0;

    if (!$nombre) {
        $_SESSION['flash'] = ['tipo'=>'error','msg'=>'El nombre es obligatorio.'];
        header("Location: fichas_form.php" . ($id_ficha ? "?id=$id_ficha" : "")); exit;
    }

    $pdo->beginTransaction();
    try {
        if ($id_ficha) {
            $pdo->prepare("UPDATE fichas SET nombre=?,descripcion=?,activa=? WHERE id_ficha=?")
                ->execute([$nombre,$descripcion,$activa,$id_ficha]);
            // Borrar secciones y preguntas para recrear
            $pdo->prepare("DELETE FROM ficha_secciones WHERE id_ficha=?")->execute([$id_ficha]);
        } else {
            $pdo->prepare("INSERT INTO fichas (nombre,descripcion,activa,creado_por) VALUES (?,?,?,?)")
                ->execute([$nombre,$descripcion,$activa,$id_usuario]);
            $id_ficha = $pdo->lastInsertId();
        }

        $secs     = $_POST['sec_titulo']    ?? [];
        $preg_sec = $_POST['preg_sec']      ?? [];
        $preg_txt = $_POST['preg_texto']    ?? [];
        $preg_tip = $_POST['preg_tipo']     ?? [];

        $orden_s = 1;
        foreach ($secs as $idx => $titulo) {
            $titulo = trim($titulo);
            if (!$titulo) continue;
            $pdo->prepare("INSERT INTO ficha_secciones (id_ficha,titulo,orden) VALUES (?,?,?)")
                ->execute([$id_ficha, $titulo, $orden_s]);
            $id_sec = $pdo->lastInsertId();

            $orden_p = 1;
            foreach ($preg_sec as $pi => $si) {
                if ((int)$si !== $idx) continue;
                $texto = trim($preg_txt[$pi] ?? '');
                $tipo  = $preg_tip[$pi] ?? 'cumplimiento';
                if (!$texto) continue;
                $pdo->prepare("INSERT INTO ficha_preguntas (id_seccion,texto,tipo,orden) VALUES (?,?,?,?)")
                    ->execute([$id_sec,$texto,$tipo,$orden_p]);
                $orden_p++;
            }
            $orden_s++;
        }

        $pdo->commit();
        $_SESSION['flash'] = ['tipo'=>'success','msg'=>'✅ Ficha guardada correctamente.'];
        header("Location: fichas_lista.php"); exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash'] = ['tipo'=>'error','msg'=>'Error: '.$e->getMessage()];
        header("Location: fichas_form.php" . ($id_ficha ? "?id=$id_ficha" : "")); exit;
    }
}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= $ficha ? 'Editar' : 'Nueva' ?> Ficha</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--azul:#1f3a5f;--azul2:#2563eb;--gris:#f8fafc;--borde:#e2e8f0;}
body{font-family:'Segoe UI',sans-serif;background:var(--gris);}
.btn-prim{display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#1f3a5f,#2563eb);color:#fff;border:none;border-radius:10px;padding:10px 20px;font-weight:700;font-size:.875rem;cursor:pointer;text-decoration:none;transition:.2s;}
.btn-prim:hover{transform:translateY(-2px);color:#fff;}
.btn-sec{display:inline-flex;align-items:center;gap:6px;background:#fff;color:var(--azul);border:1.5px solid var(--borde);border-radius:10px;padding:9px 16px;font-weight:600;font-size:.85rem;cursor:pointer;text-decoration:none;}
.flash{padding:12px 18px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-weight:600;}
.flash.success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0;}
.flash.error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}
.card{background:#fff;border-radius:14px;border:1.5px solid var(--borde);overflow:hidden;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.06);}
.card-head{background:linear-gradient(135deg,#1f3a5f,#2563eb);padding:14px 20px;color:#fff;display:flex;align-items:center;justify-content:space-between;}
.card-head h3{margin:0;font-size:.95rem;font-weight:700;display:flex;align-items:center;gap:8px;}
.card-body{padding:18px 20px;}
.fg{display:flex;flex-direction:column;gap:5px;margin-bottom:14px;}
.fg label{font-size:.8rem;font-weight:700;color:var(--azul);}
.fg input,.fg select,.fg textarea{border:1.5px solid var(--borde);border-radius:8px;padding:9px 12px;font-size:.875rem;font-family:inherit;outline:none;transition:.2s;width:100%;}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--azul2);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.seccion-bloque{background:#f8fafc;border:1.5px solid var(--borde);border-radius:12px;padding:16px;margin-bottom:14px;position:relative;}
.seccion-header{display:flex;align-items:center;gap:10px;margin-bottom:12px;}
.seccion-num{width:28px;height:28px;background:var(--azul);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;}
.pregunta-row{display:grid;grid-template-columns:1fr auto auto;gap:8px;align-items:center;margin-bottom:8px;}
.btn-add{border:2px dashed #cbd5e1;background:transparent;color:#64748b;border-radius:8px;padding:7px 14px;cursor:pointer;font-size:.82rem;font-weight:600;display:flex;align-items:center;gap:6px;justify-content:center;width:100%;transition:.2s;margin-top:6px;}
.btn-add:hover{border-color:var(--azul2);color:var(--azul2);background:#eff6ff;}
.btn-rm{background:none;border:none;color:#ef4444;cursor:pointer;padding:4px 7px;border-radius:6px;font-size:.85rem;}
.btn-rm:hover{background:#fee2e2;}
.tipo-badge{display:inline-block;padding:3px 8px;border-radius:6px;font-size:.72rem;font-weight:700;}
</style>
</head>
<body>
<div class="app">
<?php include __DIR__ . "/layout/sidebar.php"; ?>
<main class="content">
<header class="topbar">
    <span>Bienvenido, <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong></span>
</header>
<section class="page">

<?php if ($flash): ?>
<div class="flash <?= $flash['tipo'] ?>">
    <i class="fa-solid <?= $flash['tipo']==='success'?'fa-circle-check':'fa-triangle-exclamation' ?>"></i>
    <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif; ?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
    <a href="fichas_lista.php" style="color:#64748b;text-decoration:none;"><i class="fa-solid fa-arrow-left"></i></a>
    <h1 style="font-size:1.4rem;font-weight:800;color:var(--azul);margin:0;">
        <i class="fa-solid fa-clipboard-list" style="color:var(--azul2);"></i>
        <?= $ficha ? 'Editar Ficha' : 'Nueva Ficha' ?>
    </h1>
</div>

<form method="POST" id="frmFicha">

<!-- Datos generales -->
<div class="card">
    <div class="card-head"><h3><i class="fa-solid fa-circle-info"></i> Datos Generales</h3></div>
    <div class="card-body">
        <div class="fg">
            <label>Nombre de la ficha *</label>
            <input type="text" name="nombre" value="<?= htmlspecialchars($ficha['nombre'] ?? '') ?>"
                   placeholder="Ej: Ficha de Inspección Interna Fairtrade" required>
        </div>
        <div class="fg">
            <label>Descripción</label>
            <textarea name="descripcion" rows="2" placeholder="Descripción breve de la ficha..."><?= htmlspecialchars($ficha['descripcion'] ?? '') ?></textarea>
        </div>
        <label style="display:flex;align-items:center;gap:8px;font-size:.875rem;font-weight:600;color:var(--azul);cursor:pointer;">
            <input type="checkbox" name="activa" <?= (!$ficha || $ficha['activa']) ? 'checked' : '' ?>>
            Ficha activa (disponible en la app móvil)
        </label>
    </div>
</div>

<!-- Secciones y preguntas -->
<div class="card">
    <div class="card-head">
        <h3><i class="fa-solid fa-layer-group"></i> Secciones y Preguntas</h3>
        <button type="button" class="btn-sec" onclick="agregarSeccion()" style="background:rgba(255,255,255,.15);color:#fff;border-color:rgba(255,255,255,.3);">
            <i class="fa-solid fa-plus"></i> Sección
        </button>
    </div>
    <div class="card-body">
        <div id="contenedorSecciones">
            <?php foreach ($secciones as $si => $sec): ?>
            <div class="seccion-bloque" id="sec-<?= $si ?>">
                <input type="hidden" name="sec_titulo[]" id="sec_titulo_<?= $si ?>" value="<?= htmlspecialchars($sec['titulo']) ?>">
                <div class="seccion-header">
                    <div class="seccion-num"><?= $si+1 ?></div>
                    <input type="text" value="<?= htmlspecialchars($sec['titulo']) ?>"
                           placeholder="Título de la sección"
                           oninput="document.getElementById('sec_titulo_<?= $si ?>').value=this.value"
                           style="flex:1;border:1.5px solid var(--borde);border-radius:8px;padding:8px 12px;font-size:.875rem;font-weight:700;color:var(--azul);">
                    <button type="button" class="btn-rm" onclick="eliminarSeccion(<?= $si ?>)" title="Eliminar sección">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div id="preguntas-<?= $si ?>">
                    <?php foreach ($sec['preguntas'] as $pi => $preg): ?>
                    <div class="pregunta-row" id="pr-<?= $si ?>-<?= $pi ?>">
                        <input type="hidden" name="preg_sec[]" value="<?= $si ?>">
                        <input type="text" name="preg_texto[]" value="<?= htmlspecialchars($preg['texto']) ?>"
                               placeholder="Texto de la pregunta..." style="border:1.5px solid var(--borde);border-radius:8px;padding:7px 10px;font-size:.85rem;">
                        <select name="preg_tipo[]" style="border:1.5px solid var(--borde);border-radius:8px;padding:7px 8px;font-size:.8rem;">
                            <option value="cumplimiento" <?= $preg['tipo']==='cumplimiento'?'selected':'' ?>>B/R/M</option>
                            <option value="si_no"        <?= $preg['tipo']==='si_no'?'selected':'' ?>>Sí/No</option>
                            <option value="texto"        <?= $preg['tipo']==='texto'?'selected':'' ?>>Texto</option>
                            <option value="numero"       <?= $preg['tipo']==='numero'?'selected':'' ?>>Número</option>
                        </select>
                        <button type="button" class="btn-rm" onclick="this.closest('.pregunta-row').remove()">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn-add" onclick="agregarPregunta(<?= $si ?>)">
                    <i class="fa-solid fa-plus"></i> Agregar pregunta
                </button>
            </div>
            <?php endforeach; ?>
            <?php if (empty($secciones)): ?>
            <div style="text-align:center;padding:30px;color:#94a3b8;">
                <i class="fa-solid fa-layer-group" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                <p>Aún no hay secciones. Agrega una para comenzar.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;">
    <a href="fichas_lista.php" class="btn-sec"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    <button type="submit" class="btn-prim"><i class="fa-solid fa-floppy-disk"></i> Guardar Ficha</button>
</div>

</form>
</section>
</main>
</div>

<script>
let cntSec = <?= count($secciones) ?>;

function agregarSeccion() {
    const idx = cntSec++;
    const div = document.createElement('div');
    div.className = 'seccion-bloque';
    div.id = 'sec-' + idx;
    div.innerHTML = `
        <input type="hidden" name="sec_titulo[]" id="sec_titulo_${idx}" value="">
        <div class="seccion-header">
            <div class="seccion-num">${idx+1}</div>
            <input type="text" placeholder="Título de la sección"
                   oninput="document.getElementById('sec_titulo_${idx}').value=this.value"
                   style="flex:1;border:1.5px solid var(--borde);border-radius:8px;padding:8px 12px;font-size:.875rem;font-weight:700;color:var(--azul);">
            <button type="button" class="btn-rm" onclick="eliminarSeccion(${idx})">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div id="preguntas-${idx}"></div>
        <button type="button" class="btn-add" onclick="agregarPregunta(${idx})">
            <i class="fa-solid fa-plus"></i> Agregar pregunta
        </button>
    `;
    const cont = document.getElementById('contenedorSecciones');
    // Quitar mensaje vacío si existe
    const empty = cont.querySelector('div[style*="text-align:center"]');
    if (empty) empty.remove();
    cont.appendChild(div);
}

function eliminarSeccion(idx) {
    if (!confirm('¿Eliminar esta sección y todas sus preguntas?')) return;
    document.getElementById('sec-'+idx)?.remove();
}

let cntPreg = 1000;
function agregarPregunta(secIdx) {
    const pi = cntPreg++;
    const row = document.createElement('div');
    row.className = 'pregunta-row';
    row.id = 'pr-' + secIdx + '-' + pi;
    row.innerHTML = `
        <input type="hidden" name="preg_sec[]" value="${secIdx}">
        <input type="text" name="preg_texto[]" placeholder="Texto de la pregunta..."
               style="border:1.5px solid var(--borde);border-radius:8px;padding:7px 10px;font-size:.85rem;">
        <select name="preg_tipo[]" style="border:1.5px solid var(--borde);border-radius:8px;padding:7px 8px;font-size:.8rem;">
            <option value="cumplimiento">B/R/M</option>
            <option value="si_no">Sí/No</option>
            <option value="texto">Texto</option>
            <option value="numero">Número</option>
        </select>
        <button type="button" class="btn-rm" onclick="this.closest('.pregunta-row').remove()">
            <i class="fa-solid fa-xmark"></i>
        </button>
    `;
    document.getElementById('preguntas-'+secIdx).appendChild(row);
}
</script>
</body>
</html>
