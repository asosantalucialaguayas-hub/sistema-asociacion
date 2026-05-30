<?php
// ============================================================
// exportar_convocatoria.php – Vista imprimible de la convocatoria
// Diseño fiel al documento oficial de la asociación
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require __DIR__ . "/layout/bootstrap.php";

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: convocatorias.php'); exit; }

$st = $pdo->prepare("SELECT * FROM convocatorias WHERE id = ?");
$st->execute([$id]);
$c = $st->fetch(PDO::FETCH_ASSOC);
if (!$c) { header('Location: convocatorias.php'); exit; }

$stP = $pdo->prepare("SELECT * FROM convocatoria_puntos WHERE convocatoria_id = ? ORDER BY numero");
$stP->execute([$id]);
$puntos = $stP->fetchAll(PDO::FETCH_ASSOC);

$stF = $pdo->prepare("SELECT * FROM convocatoria_firmas WHERE convocatoria_id = ? ORDER BY orden");
$stF->execute([$id]);
$firmas = $stF->fetchAll(PDO::FETCH_ASSOC);

// --- Helpers ---
function formatFechaES(string $fecha): string {
    $meses = [
            1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
            7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'
    ];
    $dias = [0=>'Domingo',1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado'];
    $ts  = strtotime($fecha);
    $dia = (int)date('j', $ts);
    $mes = (int)date('n', $ts);
    $ano = date('Y', $ts);
    $dow = (int)date('w', $ts);
    return $dias[$dow] . ' ' . $dia . ' de ' . $meses[$mes] . ' del ' . $ano;
}

function formatHora(string $hora): string {
    [$h, $m] = explode(':', $hora);
    return sprintf('%02dH%02d', (int)$h, (int)$m);
}

$tipoMap  = ['ordinaria'=>'ORDINARIA','extraordinaria'=>'EXTRAORDINARIA','urgente'=>'URGENTE'];
$tipoLabel = $tipoMap[$c['tipo_reunion']] ?? strtoupper($c['tipo_reunion']);
$tipoLower = ucfirst(strtolower($tipoLabel));

// Condicional socios vs directivos
$esGeneral = ($c['tipo_asistentes'] === 'general');
$saludo    = $esGeneral ? 'Estimados socios:' : 'Estimados directivos:';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Convocatoria – <?= htmlspecialchars($c['titulo']) ?></title>
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: 'Times New Roman', Times, serif;
            background: #e0e0e0;
            color: #111;
            font-size: 12pt;
            line-height: 1.55;
        }

        /* Toolbar */
        .toolbar {
            background: #1c3557;
            color: #fff;
            padding: 9px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-family: Arial, sans-serif;
            font-size: 13px;
        }
        .toolbar-btns { display:flex; gap:8px; }
        .toolbar-btns button {
            background: rgba(255,255,255,.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,.35);
            padding: 6px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-family: Arial, sans-serif;
            font-weight: 600;
        }
        .toolbar-btns button.btn-print { background:#fff; color:#1c3557; }

        /* Hoja */
        .sheet-wrap { display:flex; justify-content:center; padding:28px 16px 50px; }
        .sheet {
            background: #fff;
            width: 740px;
            min-height: 1000px;
            padding: 44px 56px 48px;
            box-shadow: 0 4px 28px rgba(0,0,0,.20);
            position: relative;
        }

        /* Marca de agua */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%,-50%) rotate(-30deg);
            width: 500px;
            opacity: .055;
            pointer-events: none;
        }

        /* ====== CABECERA ====== */
        /* Igual al original: logo+nombre a la izquierda, tipo/fecha/hora a la derecha */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 14px;
        }
        .header-left {
            display: table-cell;
            vertical-align: middle;
            width: 46%;
        }
        .header-left-inner {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .header-logo img {
            width: 82px;
            height: 82px;
            object-fit: contain;
            flex-shrink: 0;
        }
        .org-name {
            font-size: 10.5pt;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1.35;
            color: #111;
        }
        .header-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            padding-left: 10px;
        }
        .conv-label  { font-size:12pt; font-weight:700; text-transform:uppercase; display:block; }
        .fecha-bold  { font-size:11.5pt; font-weight:700; display:block; }
        .hora-line   { font-size:11pt; display:block; }

        /* Divisor */
        .divider { border:none; border-top:2px solid #111; margin:12px 0 16px; }

        /* Saludos */
        .saludo { font-weight:700; margin-bottom:6px; font-size:12pt; }

        /* Intro */
        .intro-p { text-align:justify; margin: 10px 0 16px; font-size:12pt; }
        .intro-p strong { font-weight:700; }

        /* Orden del día */
        .oda-title { font-size:12pt; font-weight:700; text-decoration:underline; margin: 4px 0 10px; }
        .oda-table { width:100%; border-collapse:collapse; margin-bottom:18px; font-size:11.5pt; }
        .oda-table td { border:1px solid #333; padding:5px 10px; vertical-align:top; }
        .oda-table td:first-child { width:38px; text-align:center; font-weight:700; white-space:nowrap; }

        /* Cierre */
        .cierre-p { text-align:justify; font-size:11.5pt; margin-bottom:18px; }
        .atentamente { font-size:12pt; margin-bottom:54px; }

        /* Firmas */
        .firmas-wrap { display:flex; justify-content:space-between; gap:20px; flex-wrap:wrap; margin-top:8px; }
        .firma-bloque { flex:1; min-width:160px; }
        .firma-nombre { font-size:12pt; font-weight:700; border-top:1.5px solid #111; padding-top:4px; }
        .firma-cargo  { font-size:11.5pt; padding-left:26px; }

        /* Pie */
        .pie {
            text-align:center; font-size:8pt; color:#aaa;
            border-top:1px solid #ddd; margin-top:36px; padding-top:8px;
            font-family:Arial,sans-serif;
        }

        /* IMPRESIÓN */
        @media print {
            body { background:#fff; }
            .toolbar { display:none !important; }
            .sheet-wrap { padding:0; }
            .sheet { box-shadow:none; width:100%; padding:16mm 20mm 16mm; min-height:unset; }
            .pie { display:none; }
            @page { size:A4; margin:0; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <span>📄 Convocatoria #<?= $id ?> — <?= htmlspecialchars($c['titulo']) ?></span>
    <div class="toolbar-btns">
        <button onclick="window.close()">✕ Cerrar</button>
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
    </div>
</div>

<div class="sheet-wrap">
    <div class="sheet">

        <?php if (file_exists(__DIR__.'/img/logo.png')): ?>
            <img class="watermark" src="img/logo.png" alt="">
        <?php endif; ?>

        <!-- CABECERA -->
        <div class="header">
            <div class="header-left">
                <div class="header-left-inner">
                    <?php if (file_exists(__DIR__.'/img/logo.png')): ?>
                        <div class="header-logo">
                            <img src="img/logo.png" alt="Logo">
                        </div>
                    <?php endif; ?>
                    <div class="org-name">
                        Asociación de Trabajadores Agrícolas Autónomos<br>"Santa Lucia Corotú"
                    </div>
                </div>
            </div>
            <div class="header-right">
                <span class="conv-label">CONVOCATORIA A REUNION <?= $tipoLabel ?></span>
                <span class="fecha-bold"><?= formatFechaES($c['fecha']) ?></span>
                <span class="hora-line"><strong>Hora:</strong> <?= formatHora($c['hora']) ?></span>
            </div>
        </div>

        <hr class="divider">

        <!-- SALUDOS — cambia según tipo de asistentes -->
        <p class="saludo"><?= $saludo ?></p>
        <p class="saludo"><?= $saludo ?></p>

        <!-- INTRO -->
        <p class="intro-p">
            Se convoca a una reunión <strong><?= $tipoLower ?></strong> que se efectuará de manera presencial.<br>
            La cual se realizará el día <strong><?= formatFechaES($c['fecha']) ?></strong>
            del presente año para tratarse el siguiente orden del día.
        </p>

        <!-- ORDEN DEL DÍA -->
        <p class="oda-title">Orden del Día</p>
        <?php if ($puntos): ?>
            <table class="oda-table">
                <?php foreach ($puntos as $p): ?>
                    <tr>
                        <td><?= intval($p['numero']) ?>.</td>
                        <td><?= htmlspecialchars($p['descripcion']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p style="font-style:italic;color:#888;margin-bottom:18px;">Sin puntos registrados.</p>
        <?php endif; ?>

        <!-- CIERRE -->
        <p class="cierre-p">
            Agradecemos de antemano su puntualidad y compromiso, los cuales son esenciales para avanzar
            en los objetivos de nuestra organización y consolidar las acciones necesarias para el beneficio de
            todos los <?= $esGeneral ? 'socios' : 'directivos' ?>.
        </p>

        <p class="atentamente">Atentamente</p>

        <!-- FIRMAS -->
        <div class="firmas-wrap">
            <?php if ($firmas): ?>
                <?php foreach ($firmas as $f): ?>
                    <div class="firma-bloque">
                        <div class="firma-nombre"><?= htmlspecialchars($f['nombre']) ?></div>
                        <div class="firma-cargo"><?= htmlspecialchars($f['cargo']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="firma-bloque">
                    <div class="firma-nombre">Ing. Rosendo Muñoz</div>
                    <div class="firma-cargo">Presidente</div>
                </div>
                <div class="firma-bloque">
                    <div class="firma-nombre">Ing. Jean Carlos Ponce</div>
                    <div class="firma-cargo">Secretario/a</div>
                </div>
            <?php endif; ?>
        </div>

        <div class="pie">
            Generado por el Sistema de Gestión · Asociación Santa Lucía Corotú · <?= date('d/m/Y H:i') ?>
        </div>

    </div>
</div>
</body>
</html>