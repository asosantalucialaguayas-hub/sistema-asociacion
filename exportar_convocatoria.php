<?php
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

function formatFechaES(string $fecha): string {
    $meses = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
            7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
    $dias  = [0=>'Domingo',1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado'];
    $ts = strtotime($fecha);
    return $dias[(int)date('w',$ts)] . ' ' . (int)date('j',$ts) . ' de ' . $meses[(int)date('n',$ts)] . ' del ' . date('Y',$ts);
}

function formatHora(string $hora): string {
    [$h,$m] = explode(':', $hora);
    return sprintf('%02dH%02d', (int)$h, (int)$m);
}

$tipoMap   = ['ordinaria'=>'ORDINARIA','extraordinaria'=>'EXTRAORDINARIA','urgente'=>'URGENTE'];
$tipoLabel = $tipoMap[$c['tipo_reunion']] ?? strtoupper($c['tipo_reunion']);
$tipoLower = ucfirst(strtolower($tipoLabel));
$esGeneral = ($c['tipo_asistentes'] === 'general');
$saludo    = $esGeneral ? 'Estimados socios:' : 'Estimados directivos:';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Convocatoria – <?= htmlspecialchars($c['titulo']) ?></title>
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}

        body{
            font-family:'Times New Roman',Times,serif;
            background:#d8d8d8;
            color:#111;
            font-size:12pt;
            line-height:1.55;
        }

        /* Toolbar */
        .toolbar{
            background:#1c3557;color:#fff;
            padding:9px 24px;
            display:flex;align-items:center;justify-content:space-between;
            font-family:Arial,sans-serif;font-size:13px;
        }
        .toolbar-btns{display:flex;gap:8px;}
        .toolbar-btns button{
            background:rgba(255,255,255,.15);color:#fff;
            border:1px solid rgba(255,255,255,.35);
            padding:6px 18px;border-radius:6px;cursor:pointer;
            font-size:12px;font-family:Arial,sans-serif;font-weight:600;
        }
        .toolbar-btns button.btn-print{background:#fff;color:#1c3557;}

        /* Hoja */
        .sheet-wrap{display:flex;justify-content:center;padding:28px 16px 50px;}
        .sheet{
            background:#fff;
            width:760px;
            min-height:1020px;
            padding:44px 58px 50px;
            box-shadow:0 4px 28px rgba(0,0,0,.22);
            position:relative;
            overflow:hidden;
        }

        /* Marca de agua centrada */
        .watermark{
            position:absolute;
            top:50%;left:50%;
            transform:translate(-50%,-50%);
            width:520px;
            opacity:.10;
            pointer-events:none;
            z-index:0;
        }

        /* Todo el contenido por encima de la marca de agua */
        .content{position:relative;z-index:1;}

        /* ===== CABECERA ===== */
        /* Tabla de dos celdas: izquierda=logo+nombre, derecha=tipo+fecha+hora */
        .cab{
            display:table;
            width:100%;
            margin-bottom:14px;
        }
        .cab-izq{
            display:table-cell;
            vertical-align:middle;
            /* suficiente para logo+nombre sin partir palabras */
            width:50%;
            padding-right:16px;
        }
        .cab-izq-inner{
            display:flex;
            align-items:center;
            gap:12px;
        }
        .cab-logo img{
            width:82px;height:82px;
            object-fit:contain;
            flex-shrink:0;
            display:block;
        }
        .org-name{
            font-size:10.5pt;
            font-weight:700;
            text-transform:uppercase;
            line-height:1.38;
            color:#111;
        }
        .cab-der{
            display:table-cell;
            vertical-align:middle;
            text-align:right;
        }
        .cab-der .conv-label{font-size:12pt;font-weight:700;text-transform:uppercase;display:block;}
        .cab-der .fecha-bold{font-size:11.5pt;font-weight:700;display:block;}
        .cab-der .hora-line{font-size:11pt;display:block;}

        /* Divisor */
        .divider{border:none;border-top:2px solid #111;margin:12px 0 18px;}

        /* Saludo — solo uno */
        .saludo{font-weight:700;font-size:12pt;margin-bottom:14px;}

        /* Intro */
        .intro-p{text-align:justify;margin-bottom:16px;font-size:12pt;}
        .intro-p strong{font-weight:700;}

        /* Orden del día */
        .oda-title{font-size:12pt;font-weight:700;text-decoration:underline;margin:4px 0 10px;}
        .oda-table{width:100%;border-collapse:collapse;margin-bottom:18px;font-size:11.5pt;}
        .oda-table td{border:1px solid #333;padding:5px 10px;vertical-align:top;}
        .oda-table td:first-child{width:38px;text-align:center;font-weight:700;white-space:nowrap;}

        /* Cierre */
        .cierre-p{text-align:justify;font-size:11.5pt;margin-bottom:18px;}
        .atentamente{font-size:12pt;margin-bottom:56px;}

        /* Firmas */
        .firmas-wrap{display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap;}
        .firma-bloque{flex:1;min-width:160px;}
        .firma-nombre{font-size:12pt;font-weight:700;border-top:1.5px solid #111;padding-top:4px;}
        .firma-cargo{font-size:11.5pt;padding-left:26px;}

        /* IMPRESIÓN */
        @media print{
            body{background:#fff;}
            .toolbar{display:none!important;}
            .sheet-wrap{padding:0;}
            .sheet{box-shadow:none;width:100%;padding:14mm 20mm 16mm;min-height:unset;}
            @page{size:A4;margin:0;}
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

<div class="sheet-wrap"><div class="sheet">

        <!-- Marca de agua -->
        <?php if (file_exists(__DIR__.'/img/logo.png')): ?>
            <img class="watermark" src="img/logo.png" alt="">
        <?php endif; ?>

        <div class="content">

            <!-- CABECERA -->
            <div class="cab">
                <div class="cab-izq">
                    <div class="cab-izq-inner">
                        <?php if (file_exists(__DIR__.'/img/logo.png')): ?>
                            <div class="cab-logo"><img src="img/logo.png" alt="Logo"></div>
                        <?php endif; ?>
                        <div class="org-name">
                            Asociación de Trabajadores<br>Agrícolas Autónomos<br>"Santa Lucia Corotú"
                        </div>
                    </div>
                </div>
                <div class="cab-der">
                    <span class="conv-label">CONVOCATORIA A REUNION <?= $tipoLabel ?></span>
                    <span class="fecha-bold"><?= formatFechaES($c['fecha']) ?></span>
                    <span class="hora-line"><strong>Hora:</strong> <?= formatHora($c['hora']) ?></span>
                </div>
            </div>

            <hr class="divider">

            <!-- SALUDO — solo uno, cambia según tipo -->
            <p class="saludo"><?= $saludo ?></p>

            <!-- INTRO -->
            <p class="intro-p">
                Se convoca a una reunión <strong><?= $tipoLower ?></strong> que se efectuará de manera presencial.<br>
                La cual se realizará el día <strong><?= formatFechaES($c['fecha']) ?></strong>
                para tratarse el siguiente orden del día.
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

        </div><!-- /content -->
    </div></div><!-- /sheet /sheet-wrap -->

</body>
</html>