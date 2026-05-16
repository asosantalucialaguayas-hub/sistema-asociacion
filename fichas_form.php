<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require __DIR__ . "/layout/bootstrap.php";

$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
if ($id_usuario && function_exists('tienePermiso') && isset($pdo)) {
    $puede_agregar   = tienePermiso($pdo, $id_usuario, 'fichas', 'puede_agregar');
    $puede_modificar = tienePermiso($pdo, $id_usuario, 'fichas', 'puede_modificar');
} else {
    $puede_agregar = $puede_modificar = false;
}

if (!$puede_agregar && !$puede_modificar) {
    die('<div style="text-align:center;margin-top:80px;font-family:sans-serif;"><h2>⛔ Acceso denegado</h2><a href="fichas_lista.php">← Volver</a></div>');
}

$id_ficha  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$ficha     = null;
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
            $pdo->prepare("DELETE FROM ficha_secciones WHERE id_ficha=?")->execute([$id_ficha]);
        } else {
            $pdo->prepare("INSERT INTO fichas (nombre,descripcion,activa,creado_por) VALUES (?,?,?,?)")
                ->execute([$nombre,$descripcion,$activa,$id_usuario]);
            $id_ficha = $pdo->lastInsertId();
        }

        $secs      = $_POST['sec_titulo']    ?? [];
        $preg_sec  = $_POST['preg_sec']      ?? [];
        $preg_txt  = $_POST['preg_texto']    ?? [];
        // ── CAMBIO: leer tipo desde campo hidden "preg_tipo_val[]" en lugar del select ──
        $preg_tip  = $_POST['preg_tipo_val'] ?? [];
        $preg_opts = $_POST['preg_opciones'] ?? [];

        $orden_s = 1;
        foreach ($secs as $idx => $titulo) {
            $titulo = trim($titulo);
            if (!$titulo) continue;
            $pdo->prepare("INSERT INTO ficha_secciones (id_ficha,titulo,orden) VALUES (?,?,?)")
                ->execute([$id_ficha, $titulo, $orden_s]);
            $id_sec = $pdo->lastInsertId();

            $orden_p = 1;
            foreach ($preg_sec as $pi => $si) {
                if ((int)$si !== (int)$idx) continue;
                $texto = trim($preg_txt[$pi] ?? '');
                $tipo  = trim($preg_tip[$pi] ?? 'si_no_aplica');
                if (!$texto) continue;
                // Validar que tipo sea un valor permitido
                $tipos_validos = ['si_no_aplica','cumplimiento','si_no','texto','numero','coordenadas','opciones'];
                if (!in_array($tipo, $tipos_validos)) $tipo = 'si_no_aplica';

                $opciones_raw  = $preg_opts[$pi] ?? '';
                $opciones_json = null;
                if ($tipo === 'opciones' && $opciones_raw) {
                    $opts = array_filter(array_map('trim', explode('|', $opciones_raw)));
                    $opciones_json = json_encode(array_values($opts), JSON_UNESCAPED_UNICODE);
                }
                $pdo->prepare("INSERT INTO ficha_preguntas (id_seccion,texto,tipo,opciones_json,orden) VALUES (?,?,?,?,?)")
                    ->execute([$id_sec, $texto, $tipo, $opciones_json, $orden_p]);
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

function renderPreview(string $tipo): string {
    $map = [
        'si_no_aplica' => '<span class="prev-tag prev-si">SI</span><span class="prev-tag prev-no">NO</span><span class="prev-tag prev-aplica">APLICA</span><span class="prev-tag prev-obs">Observaciones</span>',
        'cumplimiento' => '<span class="prev-tag prev-b">B</span><span class="prev-tag prev-r">R</span><span class="prev-tag prev-m">M</span><span class="prev-tag prev-obs">Observaciones</span>',
        'si_no'        => '<span class="prev-tag prev-si">SI</span><span class="prev-tag prev-no">NO</span><span class="prev-tag prev-obs">Observaciones</span>',
        'texto'        => '<span class="prev-tag prev-obs">✏️ Texto libre</span>',
        'numero'       => '<span class="prev-tag prev-obs">🔢 Número</span>',
        'coordenadas'  => '<span class="prev-tag prev-obs">📍 Lat, Lon</span>',
        'opciones'     => '<span class="prev-tag" style="background:#fce7f3;color:#db2777;">☑️ Opciones personalizables</span>',
    ];
    return '<span style="font-size:.7rem;color:#94a3b8;margin-right:4px;">Vista previa:</span>' . ($map[$tipo] ?? '');
}
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
.fg input,.fg select,.fg textarea{border:1.5px solid var(--borde);border-radius:8px;padding:9px 12px;font-size:.875rem;font-family:inherit;outline:none;transition:.2s;width:100%;box-sizing:border-box;}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--azul2);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.seccion-bloque{background:#f8fafc;border:1.5px solid var(--borde);border-radius:12px;padding:16px;margin-bottom:14px;}
.seccion-header{display:flex;align-items:center;gap:10px;margin-bottom:12px;}
.seccion-num{width:28px;height:28px;background:var(--azul);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;}
.pregunta-card{background:#fff;border:1.5px solid var(--borde);border-radius:10px;padding:12px 14px;margin-bottom:8px;}
.pregunta-top{display:grid;grid-template-columns:1fr 220px auto;gap:8px;align-items:center;}
.pregunta-top input[type=text]{border:1.5px solid var(--borde);border-radius:8px;padding:7px 10px;font-size:.85rem;width:100%;box-sizing:border-box;}
.pregunta-top select{border:1.5px solid var(--borde);border-radius:8px;padding:7px 8px;font-size:.82rem;}
.tipo-preview{margin-top:8px;padding:7px 10px;background:#f8fafc;border-radius:8px;font-size:.75rem;color:#64748b;display:flex;align-items:center;gap:6px;flex-wrap:wrap;border:1px dashed #e2e8f0;}
.prev-tag{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:12px;font-weight:700;font-size:.72rem;}
.prev-si{background:#dcfce7;color:#166534;}
.prev-no{background:#fee2e2;color:#991b1b;}
.prev-aplica{background:#e0f2fe;color:#0369a1;}
.prev-b{background:#dcfce7;color:#166534;}
.prev-r{background:#fef3c7;color:#92400e;}
.prev-m{background:#fee2e2;color:#991b1b;}
.prev-obs{background:#f1f5f9;color:#374151;}
.opciones-panel{margin-top:8px;padding:10px 12px;background:#eff6ff;border:1.5px dashed #93c5fd;border-radius:8px;display:none;}
.opciones-panel.visible{display:block;}
.opciones-list{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;}
.opcion-chip{display:inline-flex;align-items:center;gap:5px;background:#dbeafe;color:#1e40af;border-radius:20px;padding:4px 10px;font-size:.78rem;font-weight:600;}
.opcion-chip button{background:none;border:none;cursor:pointer;color:#3b82f6;padding:0;line-height:1;}
.opcion-chip button:hover{color:#dc2626;}
.add-opcion-row{display:flex;gap:6px;}
.add-opcion-row input{flex:1;border:1.5px solid #93c5fd;border-radius:8px;padding:6px 10px;font-size:.82rem;}
.add-opcion-row button{background:#2563eb;color:#fff;border:none;border-radius:8px;padding:6px 12px;font-size:.8rem;cursor:pointer;}
.btn-add{border:2px dashed #cbd5e1;background:transparent;color:#64748b;border-radius:8px;padding:7px 14px;cursor:pointer;font-size:.82rem;font-weight:600;display:flex;align-items:center;gap:6px;justify-content:center;width:100%;transition:.2s;margin-top:6px;box-sizing:border-box;}
.btn-add:hover{border-color:var(--azul2);color:var(--azul2);background:#eff6ff;}
.btn-rm{background:none;border:none;color:#ef4444;cursor:pointer;padding:4px 8px;border-radius:6px;font-size:.9rem;}
.btn-rm:hover{background:#fee2e2;}
.leyenda{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;padding:10px 14px;background:#f1f5f9;border-radius:9px;align-items:center;}
.ley-item{padding:3px 9px;border-radius:12px;font-size:.72rem;font-weight:700;}
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
            <textarea name="descripcion" rows="2" placeholder="Descripción breve..."><?= htmlspecialchars($ficha['descripcion'] ?? '') ?></textarea>
        </div>
        <label style="display:flex;align-items:center;gap:8px;font-size:.875rem;font-weight:600;color:var(--azul);cursor:pointer;">
            <input type="checkbox" name="activa" <?= (!$ficha || $ficha['activa']) ? 'checked' : '' ?>>
            Ficha activa (disponible en la app móvil)
        </label>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h3><i class="fa-solid fa-layer-group"></i> Secciones y Preguntas</h3>
        <button type="button" class="btn-sec" onclick="agregarSeccion()"
                style="background:rgba(255,255,255,.15);color:#fff;border-color:rgba(255,255,255,.3);">
            <i class="fa-solid fa-plus"></i> Añadir Sección
        </button>
    </div>
    <div class="card-body">

        <div class="leyenda">
            <span style="font-size:.72rem;font-weight:700;color:#64748b;margin-right:4px;">TIPOS:</span>
            <span class="ley-item" style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;">✅ SI / NO / APLICA</span>
            <span class="ley-item" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;">⚖️ B / R / M</span>
            <span class="ley-item" style="background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;">☑️ Solo Sí / No</span>
            <span class="ley-item" style="background:#f1f5f9;color:#374151;border:1px solid #e2e8f0;">✏️ Texto libre</span>
            <span class="ley-item" style="background:#dcfce7;color:#059669;border:1px solid #a7f3d0;">🔢 Número</span>
            <span class="ley-item" style="background:#ffedd5;color:#ea580c;border:1px solid #fed7aa;">📍 Coordenadas</span>
            <span class="ley-item" style="background:#fce7f3;color:#db2777;border:1px solid #fbcfe8;">☑️ Opciones personalizadas</span>
        </div>

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
                    <button type="button" class="btn-rm" onclick="eliminarSeccion(<?= $si ?>)">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div id="preguntas-<?= $si ?>">
                    <?php foreach ($sec['preguntas'] as $pi => $preg):
                        $uid = $si.'_'.$pi;
                        $tipo_actual = $preg['tipo'] ?: 'si_no_aplica';
                        $opts_stored = '';
                        if ($tipo_actual === 'opciones' && !empty($preg['opciones_json'])) {
                            $arr = json_decode($preg['opciones_json'], true) ?? [];
                            $opts_stored = implode('|', $arr);
                        }
                    ?>
                    <div class="pregunta-card" id="pr-<?= $uid ?>">
                        <input type="hidden" name="preg_sec[]"      value="<?= $si ?>">
                        <input type="hidden" name="preg_opciones[]" id="popts_<?= $uid ?>" value="<?= htmlspecialchars($opts_stored) ?>">
                        <!-- ── CAMPO HIDDEN PARA TIPO (siempre sincronizado con el select) ── -->
                        <input type="hidden" name="preg_tipo_val[]" id="ptipo_<?= $uid ?>" value="<?= htmlspecialchars($tipo_actual) ?>">
                        <div class="pregunta-top">
                            <input type="text" name="preg_texto[]"
                                   value="<?= htmlspecialchars($preg['texto']) ?>"
                                   placeholder="Texto de la pregunta...">
                            <select onchange="onTipoChange(this,'<?= $uid ?>')">
                                <option value="si_no_aplica" <?= $tipo_actual==='si_no_aplica'?'selected':'' ?>>✅ SI / NO / APLICA</option>
                                <option value="cumplimiento" <?= $tipo_actual==='cumplimiento'?'selected':'' ?>>⚖️ B / R / M</option>
                                <option value="si_no"        <?= $tipo_actual==='si_no'?'selected':'' ?>>☑️ Solo Sí / No</option>
                                <option value="texto"        <?= $tipo_actual==='texto'?'selected':'' ?>>✏️ Texto libre</option>
                                <option value="numero"       <?= $tipo_actual==='numero'?'selected':'' ?>>🔢 Número</option>
                                <option value="coordenadas"  <?= $tipo_actual==='coordenadas'?'selected':'' ?>>📍 Coordenadas</option>
                                <option value="opciones"     <?= $tipo_actual==='opciones'?'selected':'' ?>>☑️ Opciones personalizadas</option>
                            </select>
                            <button type="button" class="btn-rm" onclick="this.closest('.pregunta-card').remove()">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                        <div class="tipo-preview" id="prev_<?= $uid ?>">
                            <?= renderPreview($tipo_actual) ?>
                        </div>
                        <div class="opciones-panel <?= $tipo_actual==='opciones'?'visible':'' ?>" id="opanel_<?= $uid ?>">
                            <label style="font-size:.75rem;font-weight:700;color:#1e40af;display:block;margin-bottom:6px;">
                                <i class="fa-solid fa-list-check"></i> Define las opciones:
                            </label>
                            <div class="opciones-list" id="olist_<?= $uid ?>">
                                <?php if (!empty($preg['opciones_json'])):
                                    foreach (json_decode($preg['opciones_json'], true) ?? [] as $opt): ?>
                                <span class="opcion-chip">
                                    <?= htmlspecialchars($opt) ?>
                                    <button type="button" onclick="removeChip(this,'<?= $uid ?>')">×</button>
                                </span>
                                <?php endforeach; endif; ?>
                            </div>
                            <div class="add-opcion-row">
                                <input type="text" id="onew_<?= $uid ?>" placeholder="Ej: Pozo, Albarrada..."
                                       onkeydown="if(event.key==='Enter'){event.preventDefault();addChip('<?= $uid ?>');}">
                                <button type="button" onclick="addChip('<?= $uid ?>')">
                                    <i class="fa-solid fa-plus"></i> Añadir
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn-add" onclick="agregarPregunta(<?= $si ?>)">
                    <i class="fa-solid fa-plus"></i> Agregar pregunta
                </button>
            </div>
            <?php endforeach; ?>
            <?php if (empty($secciones)): ?>
            <div id="msgVacio" style="text-align:center;padding:40px;color:#94a3b8;">
                <i class="fa-solid fa-layer-group" style="font-size:2.5rem;display:block;margin-bottom:10px;opacity:.4;"></i>
                <p style="margin:0;">Aún no hay secciones. Pulsa <strong>Añadir Sección</strong> para comenzar.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;">
    <a href="fichas_lista.php" class="btn-sec"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    <button type="submit" class="btn-prim"><i class="fa-solid fa-floppy-disk"></i> Guardar Ficha</button>
</div>
</form>
</section>
</main>
</div>

<script>
let cntSec  = <?= count($secciones) ?>;
let cntPreg = 1000;

const PREVIEWS = {
    si_no_aplica: `<span style="font-size:.7rem;color:#94a3b8;margin-right:4px;">Vista previa:</span><span class="prev-tag prev-si">SI</span><span class="prev-tag prev-no">NO</span><span class="prev-tag prev-aplica">APLICA</span><span class="prev-tag prev-obs">Observaciones</span>`,
    cumplimiento: `<span style="font-size:.7rem;color:#94a3b8;margin-right:4px;">Vista previa:</span><span class="prev-tag prev-b">B</span><span class="prev-tag prev-r">R</span><span class="prev-tag prev-m">M</span><span class="prev-tag prev-obs">Observaciones</span>`,
    si_no:        `<span style="font-size:.7rem;color:#94a3b8;margin-right:4px;">Vista previa:</span><span class="prev-tag prev-si">SI</span><span class="prev-tag prev-no">NO</span><span class="prev-tag prev-obs">Observaciones</span>`,
    texto:        `<span style="font-size:.7rem;color:#94a3b8;margin-right:4px;">Vista previa:</span><span class="prev-tag prev-obs">✏️ Texto libre</span>`,
    numero:       `<span style="font-size:.7rem;color:#94a3b8;margin-right:4px;">Vista previa:</span><span class="prev-tag prev-obs">🔢 Número</span>`,
    coordenadas:  `<span style="font-size:.7rem;color:#94a3b8;margin-right:4px;">Vista previa:</span><span class="prev-tag prev-obs">📍 Lat, Lon (acepta negativos)</span>`,
    opciones:     `<span style="font-size:.7rem;color:#94a3b8;margin-right:4px;">Vista previa:</span><span class="prev-tag" style="background:#fce7f3;color:#db2777;">☑️ Opciones personalizables</span>`,
};

// Al cambiar el select: actualiza hidden + preview + panel opciones
function onTipoChange(sel, uid) {
    const tipo = sel.value;
    // ── CLAVE: actualizar el campo hidden con el tipo seleccionado ──
    const hidden = document.getElementById('ptipo_' + uid);
    if (hidden) hidden.value = tipo;

    const prev = document.getElementById('prev_' + uid);
    const pan  = document.getElementById('opanel_' + uid);
    if (prev) prev.innerHTML = PREVIEWS[tipo] || '';
    if (pan)  pan.classList.toggle('visible', tipo === 'opciones');
}

function addChip(uid) {
    const inp = document.getElementById('onew_' + uid);
    const val = inp.value.trim();
    if (!val) return;
    const list = document.getElementById('olist_' + uid);
    const chip = document.createElement('span');
    chip.className = 'opcion-chip';
    chip.innerHTML = `${esc(val)} <button type="button" onclick="removeChip(this,'${uid}')">×</button>`;
    list.appendChild(chip);
    inp.value = ''; syncOpts(uid); inp.focus();
}
function removeChip(btn, uid) { btn.closest('.opcion-chip').remove(); syncOpts(uid); }
function syncOpts(uid) {
    const vals = [...document.getElementById('olist_'+uid).querySelectorAll('.opcion-chip')]
                 .map(c => c.childNodes[0].textContent.trim());
    document.getElementById('popts_'+uid).value = vals.join('|');
}
function esc(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function agregarSeccion() {
    const idx = cntSec++;
    const div = document.createElement('div');
    div.className = 'seccion-bloque'; div.id = 'sec-'+idx;
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
        </button>`;
    document.getElementById('msgVacio')?.remove();
    document.getElementById('contenedorSecciones').appendChild(div);
}
function eliminarSeccion(idx) {
    if (!confirm('¿Eliminar esta sección y sus preguntas?')) return;
    document.getElementById('sec-'+idx)?.remove();
}

function agregarPregunta(secIdx) {
    const pi  = cntPreg++;
    const uid = secIdx + '_' + pi;
    const row = document.createElement('div');
    row.className = 'pregunta-card'; row.id = 'pr-'+uid;
    row.innerHTML = `
        <input type="hidden" name="preg_sec[]"      value="${secIdx}">
        <input type="hidden" name="preg_opciones[]" id="popts_${uid}" value="">
        <!-- hidden tipo sincronizado con el select -->
        <input type="hidden" name="preg_tipo_val[]" id="ptipo_${uid}" value="si_no_aplica">
        <div class="pregunta-top">
            <input type="text" name="preg_texto[]" placeholder="Texto de la pregunta...">
            <select onchange="onTipoChange(this,'${uid}')">
                <option value="si_no_aplica" selected>✅ SI / NO / APLICA</option>
                <option value="cumplimiento">⚖️ B / R / M</option>
                <option value="si_no">☑️ Solo Sí / No</option>
                <option value="texto">✏️ Texto libre</option>
                <option value="numero">🔢 Número</option>
                <option value="coordenadas">📍 Coordenadas</option>
                <option value="opciones">☑️ Opciones personalizadas</option>
            </select>
            <button type="button" class="btn-rm" onclick="this.closest('.pregunta-card').remove()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="tipo-preview" id="prev_${uid}">${PREVIEWS['si_no_aplica']}</div>
        <div class="opciones-panel" id="opanel_${uid}">
            <label style="font-size:.75rem;font-weight:700;color:#1e40af;display:block;margin-bottom:6px;">
                <i class="fa-solid fa-list-check"></i> Define las opciones:
            </label>
            <div class="opciones-list" id="olist_${uid}"></div>
            <div class="add-opcion-row">
                <input type="text" id="onew_${uid}" placeholder="Ej: Pozo, Albarrada..."
                       onkeydown="if(event.key==='Enter'){event.preventDefault();addChip('${uid}');}">
                <button type="button" onclick="addChip('${uid}')">
                    <i class="fa-solid fa-plus"></i> Añadir
                </button>
            </div>
        </div>`;
    document.getElementById('preguntas-'+secIdx).appendChild(row);
}
</script>
</body>
</html>