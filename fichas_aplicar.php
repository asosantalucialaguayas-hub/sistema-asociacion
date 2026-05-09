<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require __DIR__ . "/layout/bootstrap.php";

$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
if ($id_usuario && function_exists('tienePermiso') && isset($pdo)) {
    $puede_agregar = tienePermiso($pdo, $id_usuario, 'fairtrade', 'puede_agregar');
} else {
    $puede_agregar = false;
}
if (!$puede_agregar) {
    die('<div style="text-align:center;margin-top:80px;font-family:sans-serif;"><h2>⛔ Acceso denegado</h2><a href="fichas_lista.php">← Volver</a></div>');
}

$id_ficha = isset($_GET['ficha']) ? (int)$_GET['ficha'] : 0;
$id_socio = isset($_GET['socio']) ? (int)$_GET['socio'] : 0;

// Cargar fichas activas
$fichas_activas = $pdo->query("SELECT id_ficha,nombre FROM fichas WHERE activa=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

$ficha   = null;
$socio   = null;
$secciones = [];

if ($id_ficha) {
    $st = $pdo->prepare("SELECT * FROM fichas WHERE id_ficha=? AND activa=1");
    $st->execute([$id_ficha]);
    $ficha = $st->fetch(PDO::FETCH_ASSOC);

    if ($ficha) {
        $stS = $pdo->prepare("SELECT * FROM ficha_secciones WHERE id_ficha=? ORDER BY orden");
        $stS->execute([$id_ficha]);
        $secs = $stS->fetchAll(PDO::FETCH_ASSOC);
        foreach ($secs as $s) {
            $stP = $pdo->prepare("SELECT * FROM ficha_preguntas WHERE id_seccion=? ORDER BY orden");
            $stP->execute([$s['id_seccion']]);
            $s['preguntas'] = $stP->fetchAll(PDO::FETCH_ASSOC);
            $secciones[] = $s;
        }
    }
}

if ($id_socio) {
    $st = $pdo->prepare("SELECT * FROM socios WHERE id_socio=?");
    $st->execute([$id_socio]);
    $socio = $st->fetch(PDO::FETCH_ASSOC);
}

// GUARDAR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puede_agregar) {
    $id_f = (int)$_POST['id_ficha'];
    $id_s = (int)$_POST['id_socio'];
    $firma_inspector = $_POST['firma_inspector'] ?? null;
    $firma_productor = $_POST['firma_productor'] ?? null;

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO ficha_aplicaciones
                (id_ficha,id_socio,id_usuario,canton,parroquia,sector,
                 coord_hogar_x,coord_hogar_y,coord_hogar_z,
                 coord_finca_x,coord_finca_y,coord_finca_z,
                 cultivo,variedad,edad_cultivo,hectareas,
                 riego,fuente_agua,poda_semestre,
                 firma_inspector,firma_productor,fecha_aplicacion)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
        ")->execute([
            $id_f, $id_s, $id_usuario,
            $_POST['canton']??'', $_POST['parroquia']??'', $_POST['sector']??'',
            $_POST['coord_hogar_x']??'', $_POST['coord_hogar_y']??'', $_POST['coord_hogar_z']??'',
            $_POST['coord_finca_x']??'', $_POST['coord_finca_y']??'', $_POST['coord_finca_z']??'',
            $_POST['cultivo']??'', $_POST['variedad']??'', $_POST['edad_cultivo']??'', $_POST['hectareas']??'',
            isset($_POST['riego']) ? 1 : 0,
            $_POST['fuente_agua']??'',
            $_POST['poda_semestre']??'',
            $firma_inspector, $firma_productor
        ]);
        $id_aplicacion = $pdo->lastInsertId();

        // Guardar respuestas
        $stR = $pdo->prepare("
            INSERT INTO ficha_respuestas (id_aplicacion,id_pregunta,respuesta_sino,cumplimiento,observacion,respuesta_texto)
            VALUES (?,?,?,?,?,?)
        ");

        $preguntas_ids = $_POST['pregunta_id'] ?? [];
        foreach ($preguntas_ids as $id_pregunta) {
            $id_pregunta = (int)$id_pregunta;
            $cumpl  = $_POST["cumpl_$id_pregunta"]  ?? null;
            $sino   = $_POST["sino_$id_pregunta"]   ?? null;
            $obs    = $_POST["obs_$id_pregunta"]     ?? '';
            $txt    = $_POST["texto_$id_pregunta"]   ?? '';
            $num    = $_POST["numero_$id_pregunta"]  ?? '';

            $stR->execute([
                $id_aplicacion,
                $id_pregunta,
                $sino !== null ? (int)$sino : null,
                $cumpl ?: null,
                $obs,
                $txt ?: $num
            ]);
        }

        $pdo->commit();
        $_SESSION['flash'] = ['tipo'=>'success','msg'=>'✅ Ficha aplicada correctamente.'];
        header("Location: fichas_ver.php?id=$id_aplicacion"); exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash'] = ['tipo'=>'error','msg'=>'Error: '.$e->getMessage()];
    }
}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Aplicar Ficha</title>
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
.flash.success{background:#dcfce7;color:#166534;}
.flash.error{background:#fee2e2;color:#991b1b;}
.card{background:#fff;border-radius:14px;border:1.5px solid var(--borde);overflow:hidden;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.06);}
.card-head{background:linear-gradient(135deg,#1f3a5f,#2563eb);padding:14px 20px;color:#fff;}
.card-head h3{margin:0;font-size:.95rem;font-weight:700;display:flex;align-items:center;gap:8px;}
.card-body{padding:18px 20px;}
.fg{display:flex;flex-direction:column;gap:5px;margin-bottom:14px;}
.fg label{font-size:.8rem;font-weight:700;color:var(--azul);}
.fg input,.fg select,.fg textarea{border:1.5px solid var(--borde);border-radius:8px;padding:9px 12px;font-size:.875rem;font-family:inherit;outline:none;transition:.2s;width:100%;}
.fg input:focus,.fg select:focus{border-color:var(--azul2);}
.fgrid2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.fgrid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;}
@media(max-width:600px){.fgrid2,.fgrid3{grid-template-columns:1fr;}}
.sec-lbl{font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:var(--azul2);display:flex;align-items:center;gap:6px;border-bottom:2px solid var(--borde);padding-bottom:7px;margin:0 0 14px;}
.preg-table{width:100%;border-collapse:collapse;font-size:.85rem;}
.preg-table th{background:#f1f5f9;padding:8px 12px;text-align:left;font-size:.75rem;font-weight:700;color:#374151;border-bottom:1px solid var(--borde);}
.preg-table td{padding:8px 12px;border-bottom:1px solid #f9fafb;vertical-align:middle;}
.preg-table tr:hover td{background:#f8fafc;}
.cumpl-group{display:flex;gap:6px;}
.cumpl-btn{padding:5px 10px;border-radius:6px;font-size:.75rem;font-weight:700;cursor:pointer;border:1.5px solid transparent;transition:.15s;}
.cumpl-b{background:#e0f2fe;color:#0369a1;border-color:#bae6fd;}
.cumpl-r{background:#fef3c7;color:#92400e;border-color:#fde68a;}
.cumpl-m{background:#fee2e2;color:#b91c1c;border-color:#fecaca;}
.cumpl-btn.selected-B{background:#0369a1;color:#fff;}
.cumpl-btn.selected-R{background:#d97706;color:#fff;}
.cumpl-btn.selected-M{background:#dc2626;color:#fff;}
.sino-group{display:flex;gap:6px;}
.sino-btn{padding:5px 12px;border-radius:6px;font-size:.75rem;font-weight:700;cursor:pointer;border:1.5px solid #e5e7eb;background:#f9fafb;transition:.15s;}
.sino-btn.sel-si{background:#16a34a;color:#fff;border-color:#16a34a;}
.sino-btn.sel-no{background:#dc2626;color:#fff;border-color:#dc2626;}
/* Firma canvas */
.firma-wrap{border:2px dashed #cbd5e1;border-radius:10px;background:#fafafa;position:relative;}
.firma-wrap canvas{display:block;touch-action:none;}
.firma-lbl{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:#cbd5e1;font-size:.8rem;pointer-events:none;}
.firma-btns{display:flex;gap:8px;margin-top:6px;}
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
        <i class="fa-solid fa-file-pen" style="color:var(--azul2);"></i> Aplicar Ficha
    </h1>
</div>

<!-- Selector ficha y socio -->
<div class="card">
    <div class="card-head"><h3><i class="fa-solid fa-sliders"></i> Selección</h3></div>
    <div class="card-body">
        <div class="fgrid2">
            <div class="fg">
                <label>Ficha a aplicar *</label>
                <select onchange="window.location='fichas_aplicar.php?ficha='+this.value+'&socio=<?= $id_socio ?>'">
                    <option value="">— Selecciona ficha —</option>
                    <?php foreach ($fichas_activas as $fa): ?>
                    <option value="<?= $fa['id_ficha'] ?>" <?= $id_ficha==$fa['id_ficha']?'selected':'' ?>>
                        <?= htmlspecialchars($fa['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label>Buscar socio *</label>
                <input type="text" id="inputSocio"
                       value="<?= $socio ? htmlspecialchars($socio['nombre_completo'].' · '.$socio['identificacion']) : '' ?>"
                       placeholder="Nombre o cédula del socio..."
                       oninput="buscarSocio(this.value)" autocomplete="off">
                <div id="resultSocios"></div>
                <input type="hidden" id="socioSelId" value="<?= $id_socio ?>">
            </div>
        </div>
        <?php if ($socio): ?>
        <div style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:12px;">
            <i class="fa-solid fa-user-check" style="color:#166534;font-size:1.2rem;"></i>
            <div>
                <div style="font-weight:700;color:#166534;"><?= htmlspecialchars($socio['nombre_completo']) ?></div>
                <div style="font-size:.78rem;color:#64748b;">Cédula: <?= htmlspecialchars($socio['identificacion']) ?> · <?= htmlspecialchars($socio['direccion'] ?? '—') ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($ficha && $socio): ?>
<form method="POST" id="frmAplicar">
<input type="hidden" name="id_ficha" value="<?= $id_ficha ?>">
<input type="hidden" name="id_socio" value="<?= $id_socio ?>">
<input type="hidden" name="firma_inspector" id="firmaInspectorData">
<input type="hidden" name="firma_productor" id="firmaProductorData">

<!-- Datos generales de la visita -->
<div class="card">
    <div class="card-head"><h3><i class="fa-solid fa-location-dot"></i> Datos de Ubicación</h3></div>
    <div class="card-body">
        <div class="fgrid3">
            <div class="fg"><label>Cantón</label><input type="text" name="canton" placeholder="Cantón"></div>
            <div class="fg"><label>Parroquia</label><input type="text" name="parroquia" placeholder="Parroquia"></div>
            <div class="fg"><label>Sector</label><input type="text" name="sector" placeholder="Sector"></div>
        </div>
        <div class="sec-lbl"><i class="fa-solid fa-house"></i> Coordenadas Hogar (UTM)</div>
        <div class="fgrid3">
            <div class="fg"><label>X</label><input type="text" name="coord_hogar_x" placeholder="X"></div>
            <div class="fg"><label>Y</label><input type="text" name="coord_hogar_y" placeholder="Y"></div>
            <div class="fg"><label>Z</label><input type="text" name="coord_hogar_z" placeholder="Z"></div>
        </div>
        <div class="sec-lbl"><i class="fa-solid fa-tree"></i> Coordenadas Finca (UTM)</div>
        <div class="fgrid3">
            <div class="fg"><label>X</label><input type="text" name="coord_finca_x" placeholder="X"></div>
            <div class="fg"><label>Y</label><input type="text" name="coord_finca_y" placeholder="Y"></div>
            <div class="fg"><label>Z</label><input type="text" name="coord_finca_z" placeholder="Z"></div>
        </div>
    </div>
</div>

<!-- Datos del cultivo -->
<div class="card">
    <div class="card-head"><h3><i class="fa-solid fa-seedling"></i> Datos del Cultivo</h3></div>
    <div class="card-body">
        <div class="fgrid2">
            <div class="fg"><label>Cultivo</label><input type="text" name="cultivo" placeholder="Ej: Cacao"></div>
            <div class="fg"><label>Variedad</label><input type="text" name="variedad" placeholder="Variedad"></div>
            <div class="fg"><label>Edad del Cultivo</label><input type="text" name="edad_cultivo" placeholder="Años"></div>
            <div class="fg"><label>Hectáreas</label><input type="number" step="0.01" name="hectareas" placeholder="Has"></div>
        </div>
        <div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:4px;">
            <label style="display:flex;align-items:center;gap:8px;font-size:.875rem;font-weight:600;cursor:pointer;">
                <input type="checkbox" name="riego"> Tiene riego
            </label>
            <div class="fg" style="margin:0;">
                <label>Fuente de agua</label>
                <select name="fuente_agua">
                    <option value="">— Selecciona —</option>
                    <option value="Pozo">Pozo</option>
                    <option value="Albarrada">Albarrada</option>
                </select>
            </div>
            <div class="fg" style="margin:0;">
                <label>Poda</label>
                <select name="poda_semestre">
                    <option value="">— Selecciona —</option>
                    <option value="1er semestre">1er semestre</option>
                    <option value="2do semestre">2do semestre</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Secciones de la ficha -->
<?php foreach ($secciones as $sec): ?>
<div class="card">
    <div class="card-head"><h3><i class="fa-solid fa-list-check"></i> <?= htmlspecialchars($sec['titulo']) ?></h3></div>
    <div class="card-body" style="padding:0;">
        <table class="preg-table">
            <thead>
                <tr>
                    <th style="width:45%;">Descripción de actividades</th>
                    <th style="width:8%;text-align:center;">No</th>
                    <th style="width:8%;text-align:center;">Sí</th>
                    <th style="width:20%;text-align:center;">Cumplimiento (B/R/M)</th>
                    <th>Observaciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sec['preguntas'] as $preg): ?>
            <tr>
                <td>
                    <input type="hidden" name="pregunta_id[]" value="<?= $preg['id_pregunta'] ?>">
                    <?= htmlspecialchars($preg['texto']) ?>
                </td>
                <?php if ($preg['tipo'] === 'cumplimiento'): ?>
                <td style="text-align:center;">
                    <input type="radio" name="sino_<?= $preg['id_pregunta'] ?>" value="0">
                </td>
                <td style="text-align:center;">
                    <input type="radio" name="sino_<?= $preg['id_pregunta'] ?>" value="1">
                </td>
                <td>
                    <div class="cumpl-group" style="justify-content:center;" id="cg-<?= $preg['id_pregunta'] ?>">
                        <input type="hidden" name="cumpl_<?= $preg['id_pregunta'] ?>" id="cv-<?= $preg['id_pregunta'] ?>">
                        <button type="button" class="cumpl-btn cumpl-b" onclick="setCumpl(<?= $preg['id_pregunta'] ?>,'B')">B</button>
                        <button type="button" class="cumpl-btn cumpl-r" onclick="setCumpl(<?= $preg['id_pregunta'] ?>,'R')">R</button>
                        <button type="button" class="cumpl-btn cumpl-m" onclick="setCumpl(<?= $preg['id_pregunta'] ?>,'M')">M</button>
                    </div>
                </td>
                <td><input type="text" name="obs_<?= $preg['id_pregunta'] ?>" placeholder="Observación..." style="border:1px solid var(--borde);border-radius:6px;padding:5px 8px;font-size:.8rem;width:100%;"></td>
                <?php elseif ($preg['tipo'] === 'si_no'): ?>
                <td style="text-align:center;">
                    <input type="radio" name="sino_<?= $preg['id_pregunta'] ?>" value="0">
                </td>
                <td style="text-align:center;">
                    <input type="radio" name="sino_<?= $preg['id_pregunta'] ?>" value="1">
                </td>
                <td></td>
                <td><input type="text" name="obs_<?= $preg['id_pregunta'] ?>" placeholder="Observación..." style="border:1px solid var(--borde);border-radius:6px;padding:5px 8px;font-size:.8rem;width:100%;"></td>
                <?php else: ?>
                <td colspan="3">
                    <input type="<?= $preg['tipo'] === 'numero' ? 'number' : 'text' ?>"
                           name="<?= $preg['tipo'] === 'numero' ? 'numero' : 'texto' ?>_<?= $preg['id_pregunta'] ?>"
                           placeholder="Respuesta..."
                           style="border:1px solid var(--borde);border-radius:6px;padding:5px 8px;font-size:.8rem;width:100%;">
                </td>
                <td></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<!-- Firmas -->
<div class="card">
    <div class="card-head"><h3><i class="fa-solid fa-signature"></i> Firmas</h3></div>
    <div class="card-body">
        <div class="fgrid2">
            <div>
                <div style="font-weight:700;font-size:.85rem;color:var(--azul);margin-bottom:8px;">Firma Inspector Interno</div>
                <div class="firma-wrap">
                    <canvas id="canvasInspector" width="280" height="120"></canvas>
                    <span class="firma-lbl" id="lblInspector">Firme aquí</span>
                </div>
                <div class="firma-btns">
                    <button type="button" class="btn-sec" style="font-size:.78rem;padding:5px 12px;" onclick="limpiarFirma('Inspector')">
                        <i class="fa-solid fa-eraser"></i> Limpiar
                    </button>
                </div>
            </div>
            <div>
                <div style="font-weight:700;font-size:.85rem;color:var(--azul);margin-bottom:8px;">Firma Productor</div>
                <div class="firma-wrap">
                    <canvas id="canvasProductor" width="280" height="120"></canvas>
                    <span class="firma-lbl" id="lblProductor">Firme aquí</span>
                </div>
                <div class="firma-btns">
                    <button type="button" class="btn-sec" style="font-size:.78rem;padding:5px 12px;" onclick="limpiarFirma('Productor')">
                        <i class="fa-solid fa-eraser"></i> Limpiar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;">
    <a href="fichas_lista.php" class="btn-sec"><i class="fa-solid fa-arrow-left"></i> Cancelar</a>
    <button type="submit" class="btn-prim" onclick="capturarFirmas()">
        <i class="fa-solid fa-floppy-disk"></i> Guardar Ficha Aplicada
    </button>
</div>
</form>
<?php endif; ?>

</section>
</main>
</div>

<script>
// Buscar socio
let timerSocio;
function buscarSocio(q) {
    clearTimeout(timerSocio);
    if (q.length < 2) { document.getElementById('resultSocios').innerHTML=''; return; }
    timerSocio = setTimeout(async () => {
        const r = await fetch(`api/socios.php?q=${encodeURIComponent(q)}`);
        const d = await r.json();
        if (!d.ok || !d.data.length) {
            document.getElementById('resultSocios').innerHTML = '<p style="font-size:.8rem;color:#94a3b8;padding:6px 0;">Sin resultados</p>';
            return;
        }
        document.getElementById('resultSocios').innerHTML = d.data.slice(0,8).map(s => `
            <div onclick="selSocio(${s.id_socio},'${s.nombre_completo} · ${s.identificacion}')"
                 style="padding:8px 12px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-top:4px;cursor:pointer;font-size:.85rem;">
                <b>${s.nombre_completo}</b><br>
                <span style="color:#64748b;font-size:.75rem;">${s.identificacion}</span>
            </div>`).join('');
    }, 300);
}

function selSocio(id, nombre) {
    document.getElementById('socioSelId').value = id;
    document.getElementById('inputSocio').value = nombre;
    document.getElementById('resultSocios').innerHTML = '';
    const ficha = <?= $id_ficha ?>;
    if (ficha) window.location = `fichas_aplicar.php?ficha=${ficha}&socio=${id}`;
}

// Cumplimiento B/R/M
function setCumpl(id, val) {
    document.getElementById('cv-'+id).value = val;
    document.querySelectorAll('#cg-'+id+' .cumpl-btn').forEach(b => {
        b.className = b.className.replace(/selected-[BRM]/,'').trim();
    });
    event.target.classList.add('selected-'+val);
}

// Firma canvas
function initCanvas(id) {
    const canvas = document.getElementById('canvas'+id);
    const lbl    = document.getElementById('lbl'+id);
    const ctx    = canvas.getContext('2d');
    ctx.strokeStyle = '#1f3a5f';
    ctx.lineWidth   = 2;
    ctx.lineCap     = 'round';
    let drawing = false;

    const getPos = e => {
        const r = canvas.getBoundingClientRect();
        const t = e.touches ? e.touches[0] : e;
        return { x: t.clientX - r.left, y: t.clientY - r.top };
    };

    canvas.addEventListener('mousedown',  e => { drawing=true; ctx.beginPath(); lbl.style.display='none'; const p=getPos(e); ctx.moveTo(p.x,p.y); });
    canvas.addEventListener('mousemove',  e => { if(!drawing) return; const p=getPos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); });
    canvas.addEventListener('mouseup',    () => drawing=false);
    canvas.addEventListener('mouseleave', () => drawing=false);
    canvas.addEventListener('touchstart', e => { e.preventDefault(); drawing=true; ctx.beginPath(); lbl.style.display='none'; const p=getPos(e); ctx.moveTo(p.x,p.y); }, {passive:false});
    canvas.addEventListener('touchmove',  e => { e.preventDefault(); if(!drawing) return; const p=getPos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); }, {passive:false});
    canvas.addEventListener('touchend',   () => drawing=false);
}

function limpiarFirma(id) {
    const canvas = document.getElementById('canvas'+id);
    canvas.getContext('2d').clearRect(0,0,canvas.width,canvas.height);
    document.getElementById('lbl'+id).style.display='block';
}

function capturarFirmas() {
    document.getElementById('firmaInspectorData').value = document.getElementById('canvasInspector').toDataURL();
    document.getElementById('firmaProductorData').value = document.getElementById('canvasProductor').toDataURL();
}

initCanvas('Inspector');
initCanvas('Productor');
</script>
</body>
</html>
