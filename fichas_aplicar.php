<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require __DIR__ . "/layout/bootstrap.php";

$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
if ($id_usuario && function_exists('tienePermiso') && isset($pdo)) {
    $puede_agregar = tienePermiso($pdo, $id_usuario, 'fichas_aplicar', 'puede_agregar');
} else {
    $puede_agregar = false;
}
if (!$puede_agregar) {
    die('<div style="text-align:center;margin-top:80px;font-family:sans-serif;"><h2>⛔ Acceso denegado</h2><a href="fichas_lista.php">← Volver</a></div>');
}

$id_ficha = isset($_GET['ficha']) ? (int)$_GET['ficha'] : 0;
$id_socio = isset($_GET['socio']) ? (int)$_GET['socio'] : 0;

$fichas_activas = $pdo->query("SELECT id_ficha,nombre FROM fichas WHERE activa=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

$ficha     = null;
$socio     = null;
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

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO ficha_aplicaciones
                (id_ficha, id_socio, id_usuario,
                 canton, parroquia, sector,
                 coord_hogar_x, coord_hogar_y, coord_hogar_z,
                 coord_finca_x, coord_finca_y, coord_finca_z,
                 firma_inspector, firma_productor, fecha_aplicacion)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
        ")->execute([
            $id_f, $id_s, $id_usuario,
            $_POST['canton']??'', $_POST['parroquia']??'', $_POST['sector']??'',
            $_POST['coord_hogar_x']??'', $_POST['coord_hogar_y']??'', $_POST['coord_hogar_z']??'',
            $_POST['coord_finca_x']??'', $_POST['coord_finca_y']??'', $_POST['coord_finca_z']??'',
            $_POST['firma_inspector']??null, $_POST['firma_productor']??null
        ]);
        $id_aplicacion = $pdo->lastInsertId();

        $stR = $pdo->prepare("
            INSERT INTO ficha_respuestas
                (id_aplicacion, id_pregunta, respuesta_sino, cumplimiento, observacion, respuesta_texto)
            VALUES (?,?,?,?,?,?)
        ");

        foreach ($_POST['pregunta_id'] ?? [] as $id_pregunta) {
            $id_pregunta = (int)$id_pregunta;
            $tipo  = $_POST["tipo_$id_pregunta"]   ?? 'texto';
            $cumpl = $_POST["cumpl_$id_pregunta"]  ?? null;
            $sino  = $_POST["sino_$id_pregunta"]   ?? null;
            $obs      = $_POST["obs_$id_pregunta"]    ?? '';
            $resp_raw = $_POST["resp_$id_pregunta"] ?? '';
            if (is_array($resp_raw)) {
                $txt = implode(', ', $resp_raw);
            } else {
                $txt = $resp_raw;
            }

            // sino: 0=No, 1=Sí, 2=Aplica (para si_no_aplica), null=no aplica
            $sino_val = ($sino !== null && $sino !== '') ? (int)$sino : null;

            $stR->execute([
                $id_aplicacion,
                $id_pregunta,
                $sino_val,
                $cumpl ?: null,
                $obs,
                $txt
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

/* ── Tabla de preguntas ── */
.preg-table{width:100%;border-collapse:collapse;font-size:.855rem;}
.preg-table th{
    background:#f1f5f9;padding:9px 12px;text-align:left;
    font-size:.75rem;font-weight:700;color:#374151;
    border-bottom:2px solid var(--borde);white-space:nowrap;
}
.preg-table td{padding:9px 12px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
.preg-table tr:last-child td{border-bottom:none;}
.preg-table tr:hover td{background:#f8fafc;}
.preg-table td.txt-pregunta{font-size:.855rem;color:#1e293b;line-height:1.4;}

/* Cumplimiento B/R/M */
.cumpl-group{display:flex;gap:5px;justify-content:center;}
.cumpl-btn{padding:4px 10px;border-radius:6px;font-size:.75rem;font-weight:700;cursor:pointer;border:1.5px solid transparent;transition:.15s;background:#f1f5f9;color:#374151;}
.cumpl-btn:hover{opacity:.85;}
.cumpl-btn.sel-B{background:#0369a1;color:#fff;border-color:#0369a1;}
.cumpl-btn.sel-R{background:#d97706;color:#fff;border-color:#d97706;}
.cumpl-btn.sel-M{background:#dc2626;color:#fff;border-color:#dc2626;}

/* Sí / No radios */
.sino-wrap{display:flex;justify-content:center;}
.sino-wrap input[type=radio]{width:18px;height:18px;accent-color:var(--azul2);cursor:pointer;}

/* Inputs en tabla */
.inp-tabla{border:1.5px solid var(--borde);border-radius:7px;padding:6px 9px;font-size:.82rem;width:100%;font-family:inherit;outline:none;transition:.2s;}
.inp-tabla:focus{border-color:var(--azul2);box-shadow:0 0 0 2px rgba(37,99,235,.1);}
.inp-obs{width:100%;}

/* Opciones personalizadas (checkboxes) */
.opciones-checks{display:flex;flex-wrap:wrap;gap:6px;}
.opciones-checks label{display:inline-flex;align-items:center;gap:5px;background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:20px;padding:4px 11px;font-size:.78rem;font-weight:600;color:#1e40af;cursor:pointer;transition:.15s;}
.opciones-checks label:hover{background:#dbeafe;}
.opciones-checks input[type=checkbox]{accent-color:#2563eb;width:14px;height:14px;}

/* Coordenadas hint */
.coord-badge{display:inline-flex;align-items:center;gap:5px;background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;padding:3px 8px;font-size:.72rem;color:#92400e;margin-bottom:4px;}

/* Firma */
.firma-wrap{border:2px dashed #cbd5e1;border-radius:10px;background:#fafafa;position:relative;}
.firma-wrap canvas{display:block;touch-action:none;}
.firma-lbl{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:#cbd5e1;font-size:.8rem;pointer-events:none;white-space:nowrap;}

/* Socio results dropdown */
#resultSocios{position:relative;z-index:10;}
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

<!-- Datos de ubicación -->
<div class="card">
    <div class="card-head"><h3><i class="fa-solid fa-location-dot"></i> Datos de Ubicación</h3></div>
    <div class="card-body">
        <div class="fgrid3" style="margin-bottom:16px;">
            <div class="fg"><label>Cantón</label><input type="text" name="canton" placeholder="Cantón"></div>
            <div class="fg"><label>Parroquia</label><input type="text" name="parroquia" placeholder="Parroquia"></div>
            <div class="fg"><label>Sector</label><input type="text" name="sector" placeholder="Sector"></div>
        </div>

        <div style="font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--azul2);border-bottom:2px solid var(--borde);padding-bottom:6px;margin-bottom:12px;">
            <i class="fa-solid fa-house"></i> Coordenadas Hogar (UTM)
        </div>
        <div class="fgrid3" style="margin-bottom:16px;">
            <div class="fg"><label>X</label><input type="text" name="coord_hogar_x" placeholder="X"></div>
            <div class="fg"><label>Y</label><input type="text" name="coord_hogar_y" placeholder="Y"></div>
            <div class="fg"><label>Z (altitud)</label><input type="text" name="coord_hogar_z" placeholder="Z"></div>
        </div>

        <div style="font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--azul2);border-bottom:2px solid var(--borde);padding-bottom:6px;margin-bottom:12px;">
            <i class="fa-solid fa-tree"></i> Coordenadas Finca (UTM)
        </div>
        <div class="fgrid3">
            <div class="fg"><label>X</label><input type="text" name="coord_finca_x" placeholder="X"></div>
            <div class="fg"><label>Y</label><input type="text" name="coord_finca_y" placeholder="Y"></div>
            <div class="fg"><label>Z (altitud)</label><input type="text" name="coord_finca_z" placeholder="Z"></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     SECCIONES Y PREGUNTAS DINÁMICAS DE LA FICHA
     ══════════════════════════════════════════════ -->
<?php foreach ($secciones as $sec):

    // ─── Detectar qué tipos de columnas necesita esta sección ───
    $tiene_sino      = false; // si_no o si_no_aplica o cumplimiento → muestra col SI
    $tiene_aplica    = false; // si_no_aplica → muestra col APLICA
    $tiene_cumpl     = false; // cumplimiento → muestra col B/R/M
    foreach ($sec['preguntas'] as $p) {
        if (in_array($p['tipo'], ['si_no', 'si_no_aplica', 'cumplimiento'])) $tiene_sino   = true;
        if ($p['tipo'] === 'si_no_aplica')                                    $tiene_aplica = true;
        if ($p['tipo'] === 'cumplimiento')                                    $tiene_cumpl  = true;
    }
    // Número de columnas "radio" que ocupa esta sección
    // si_no → 2 cols (No, Sí) | si_no_aplica → 3 cols (SI, NO, APLICA) | cumplimiento → 2 cols (No, Sí) + BRM en Respuesta
    $num_radio_cols = $tiene_sino ? ($tiene_aplica ? 3 : 2) : 0;
    $ancho_preg     = $tiene_sino ? '38%' : '55%';
?>
<div class="card">
    <div class="card-head">
        <h3><i class="fa-solid fa-list-check"></i> <?= htmlspecialchars($sec['titulo']) ?></h3>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="preg-table">
            <thead>
                <tr>
                    <th style="width:<?= $ancho_preg ?>;">Criterio / Pregunta</th>
                    <?php if ($tiene_sino && !$tiene_aplica): ?>
                        <th style="width:6%;text-align:center;">No</th>
                        <th style="width:6%;text-align:center;">Sí</th>
                    <?php elseif ($tiene_aplica): ?>
                        <th style="width:6%;text-align:center;">SI</th>
                        <th style="width:6%;text-align:center;">NO</th>
                        <th style="width:6%;text-align:center;">APLICA</th>
                    <?php endif; ?>
                    <?php if ($tiene_cumpl): ?>
                        <th style="width:18%;text-align:center;">Cumplimiento</th>
                    <?php endif; ?>
                    <th>Observaciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sec['preguntas'] as $preg):
                $pid  = $preg['id_pregunta'];
                $tipo = $preg['tipo'];
                $opts = [];
                if ($tipo === 'opciones' && !empty($preg['opciones_json'])) {
                    $opts = json_decode($preg['opciones_json'], true) ?? [];
                }
            ?>
            <tr>
                <!-- Texto de la pregunta -->
                <td class="txt-pregunta">
                    <input type="hidden" name="pregunta_id[]" value="<?= $pid ?>">
                    <input type="hidden" name="tipo_<?= $pid ?>" value="<?= htmlspecialchars($tipo) ?>">
                    <?= htmlspecialchars($preg['texto']) ?>
                </td>

                <?php if ($tipo === 'si_no_aplica'): ?>
                <!-- ── SI / NO / APLICA ── -->
                <td class="sino-wrap">
                    <input type="radio" name="sino_<?= $pid ?>" value="1">
                </td>
                <td class="sino-wrap">
                    <input type="radio" name="sino_<?= $pid ?>" value="0">
                </td>
                <td class="sino-wrap">
                    <input type="radio" name="sino_<?= $pid ?>" value="2">
                </td>
                <?php if ($tiene_cumpl): ?><td></td><?php endif; ?>
                <td>
                    <input type="text" class="inp-tabla inp-obs" name="obs_<?= $pid ?>" placeholder="Observación...">
                </td>

                <?php elseif ($tipo === 'cumplimiento'): ?>
                <!-- ── Cumplimiento: No/Sí + BRM + obs ── -->
                <td class="sino-wrap">
                    <input type="radio" name="sino_<?= $pid ?>" value="0">
                </td>
                <?php if ($tiene_aplica): ?><td class="sino-wrap"><input type="radio" name="sino_<?= $pid ?>" value="1"></td><?php else: ?>
                <td class="sino-wrap">
                    <input type="radio" name="sino_<?= $pid ?>" value="1">
                </td><?php endif; ?>
                <?php if ($tiene_cumpl): ?>
                <td>
                    <input type="hidden" name="cumpl_<?= $pid ?>" id="cv-<?= $pid ?>">
                    <div class="cumpl-group">
                        <button type="button" class="cumpl-btn" onclick="setCumpl(<?= $pid ?>,'B',this)">B</button>
                        <button type="button" class="cumpl-btn" onclick="setCumpl(<?= $pid ?>,'R',this)">R</button>
                        <button type="button" class="cumpl-btn" onclick="setCumpl(<?= $pid ?>,'M',this)">M</button>
                    </div>
                </td>
                <?php endif; ?>
                <td>
                    <input type="text" class="inp-tabla inp-obs" name="obs_<?= $pid ?>" placeholder="Observación...">
                </td>

                <?php elseif ($tipo === 'si_no'): ?>
                <!-- ── Solo Sí/No ── -->
                <td class="sino-wrap">
                    <input type="radio" name="sino_<?= $pid ?>" value="0">
                </td>
                <td class="sino-wrap">
                    <input type="radio" name="sino_<?= $pid ?>" value="1">
                </td>
                <?php if ($tiene_aplica): ?><td></td><?php endif; ?>
                <?php if ($tiene_cumpl): ?><td></td><?php endif; ?>
                <td>
                    <input type="text" class="inp-tabla inp-obs" name="obs_<?= $pid ?>" placeholder="Observación...">
                </td>

                <?php elseif ($tipo === 'numero'): ?>
                <!-- ── Número ── -->
                <?php if ($num_radio_cols > 0): ?><td colspan="<?= $num_radio_cols ?>"></td><?php endif; ?>
                <?php if ($tiene_cumpl): ?><td></td><?php endif; ?>
                <td>
                    <input type="number" step="any" class="inp-tabla"
                           name="resp_<?= $pid ?>" placeholder="0">
                </td>
                <td>
                    <input type="text" class="inp-tabla inp-obs" name="obs_<?= $pid ?>" placeholder="Observación...">
                </td>

                <?php elseif ($tipo === 'coordenadas'): ?>
                <!-- ── Coordenadas ── -->
                <?php if ($num_radio_cols > 0): ?><td colspan="<?= $num_radio_cols ?>"></td><?php endif; ?>
                <?php if ($tiene_cumpl): ?><td></td><?php endif; ?>
                <td>
                    <div class="coord-badge"><i class="fa-solid fa-location-dot"></i> lat, lon</div>
                    <input type="text" class="inp-tabla"
                           name="resp_<?= $pid ?>"
                           placeholder="-2.1234, -79.5678"
                           pattern="^-?\d+(\.\d+)?,\s*-?\d+(\.\d+)?$">
                </td>
                <td>
                    <input type="text" class="inp-tabla inp-obs" name="obs_<?= $pid ?>" placeholder="Observación...">
                </td>

                <?php elseif ($tipo === 'opciones'): ?>
                <!-- ── Opciones (checkboxes) ── -->
                <?php if ($num_radio_cols > 0): ?><td colspan="<?= $num_radio_cols ?>"></td><?php endif; ?>
                <?php if ($tiene_cumpl): ?><td></td><?php endif; ?>
                <td colspan="2">
                    <?php if ($opts): ?>
                    <div class="opciones-checks">
                        <?php foreach ($opts as $opt): ?>
                        <label>
                            <input type="checkbox"
                                   name="resp_<?= $pid ?>[]"
                                   value="<?= htmlspecialchars($opt) ?>">
                            <?= htmlspecialchars($opt) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <span style="color:#94a3b8;font-size:.8rem;">Sin opciones configuradas</span>
                    <?php endif; ?>
                    <input type="text" class="inp-tabla inp-obs" name="obs_<?= $pid ?>"
                           placeholder="Observación..." style="margin-top:6px;">
                </td>

                <?php else: /* texto libre */ ?>
                <!-- ── Texto libre ── -->
                <?php if ($num_radio_cols > 0): ?><td colspan="<?= $num_radio_cols ?>"></td><?php endif; ?>
                <?php if ($tiene_cumpl): ?><td></td><?php endif; ?>
                <td colspan="2">
                    <input type="text" class="inp-tabla"
                           name="resp_<?= $pid ?>" placeholder="Respuesta...">
                </td>

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
                    <canvas id="canvasInspector" width="300" height="120"></canvas>
                    <span class="firma-lbl" id="lblInspector">Firme aquí</span>
                </div>
                <div style="margin-top:6px;">
                    <button type="button" class="btn-sec" style="font-size:.78rem;padding:5px 12px;" onclick="limpiarFirma('Inspector')">
                        <i class="fa-solid fa-eraser"></i> Limpiar
                    </button>
                </div>
            </div>
            <div>
                <div style="font-weight:700;font-size:.85rem;color:var(--azul);margin-bottom:8px;">Firma Productor</div>
                <div class="firma-wrap">
                    <canvas id="canvasProductor" width="300" height="120"></canvas>
                    <span class="firma-lbl" id="lblProductor">Firme aquí</span>
                </div>
                <div style="margin-top:6px;">
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
/* ── Buscar socio ── */
let timerSocio;
function buscarSocio(q) {
    clearTimeout(timerSocio);
    const box = document.getElementById('resultSocios');
    if (q.length < 2) { box.innerHTML = ''; return; }
    timerSocio = setTimeout(async () => {
        try {
            const r = await fetch(`api/socios.php?q=${encodeURIComponent(q)}`);
            const d = await r.json();
            if (!d.ok || !d.data.length) {
                box.innerHTML = '<p style="font-size:.8rem;color:#94a3b8;padding:6px 0;">Sin resultados</p>';
                return;
            }
            box.innerHTML = d.data.slice(0,8).map(s => `
                <div onclick="selSocio(${s.id_socio},'${s.nombre_completo} · ${s.identificacion}')"
                     style="padding:8px 12px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-top:4px;cursor:pointer;font-size:.85rem;">
                    <b>${s.nombre_completo}</b><br>
                    <span style="color:#64748b;font-size:.75rem;">${s.identificacion}</span>
                </div>`).join('');
        } catch(e) { box.innerHTML = ''; }
    }, 300);
}
function selSocio(id, nombre) {
    document.getElementById('socioSelId').value = id;
    document.getElementById('inputSocio').value = nombre;
    document.getElementById('resultSocios').innerHTML = '';
    const ficha = <?= $id_ficha ?>;
    if (ficha) window.location = `fichas_aplicar.php?ficha=${ficha}&socio=${id}`;
}

/* ── Cumplimiento B/R/M ── */
function setCumpl(pid, val, btn) {
    document.getElementById('cv-' + pid).value = val;
    btn.closest('.cumpl-group').querySelectorAll('.cumpl-btn').forEach(b => {
        b.classList.remove('sel-B','sel-R','sel-M');
    });
    btn.classList.add('sel-' + val);
}

/* ── Canvas de firma ── */
function initCanvas(id) {
    const canvas = document.getElementById('canvas' + id);
    const lbl    = document.getElementById('lbl' + id);
    const ctx    = canvas.getContext('2d');
    ctx.strokeStyle = '#1f3a5f';
    ctx.lineWidth   = 2;
    ctx.lineCap     = 'round';
    let drawing = false;

    const pos = e => {
        const r = canvas.getBoundingClientRect();
        const t = e.touches ? e.touches[0] : e;
        return { x: t.clientX - r.left, y: t.clientY - r.top };
    };
    canvas.addEventListener('mousedown',  e => { drawing=true; ctx.beginPath(); lbl.style.display='none'; const p=pos(e); ctx.moveTo(p.x,p.y); });
    canvas.addEventListener('mousemove',  e => { if(!drawing) return; const p=pos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); });
    canvas.addEventListener('mouseup',    () => drawing=false);
    canvas.addEventListener('mouseleave', () => drawing=false);
    canvas.addEventListener('touchstart', e => { e.preventDefault(); drawing=true; ctx.beginPath(); lbl.style.display='none'; const p=pos(e); ctx.moveTo(p.x,p.y); }, {passive:false});
    canvas.addEventListener('touchmove',  e => { e.preventDefault(); if(!drawing) return; const p=pos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); }, {passive:false});
    canvas.addEventListener('touchend',   () => drawing=false);
}
function limpiarFirma(id) {
    const c = document.getElementById('canvas'+id);
    c.getContext('2d').clearRect(0,0,c.width,c.height);
    document.getElementById('lbl'+id).style.display = 'block';
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