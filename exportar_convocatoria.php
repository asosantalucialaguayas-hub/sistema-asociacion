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

$st = $pdo->prepare("SELECT c.*, u.nombre AS nombre_real, u.apellido AS apellido_real
                     FROM convocatorias c
                     LEFT JOIN usuarios u ON u.id = c.creado_por
                     WHERE c.id = ?");
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
    $dias = [
            0=>'Domingo',1=>'Lunes',2=>'Martes',3=>'Miércoles',
            4=>'Jueves',5=>'Viernes',6=>'Sábado'
    ];
    $ts  = strtotime($fecha);
    $dia = (int)date('j', $ts);
    $mes = (int)date('n', $ts);
    $ano = date('Y', $ts);
    $dow = (int)date('w', $ts);
    return $dias[$dow] . ' ' . $dia . ' de ' . $meses[$mes] . ' del ' . $ano;
}

function formatHora(string $hora): string {
    // Convierte "09:00:00" -> "09H00"
    [$h, $m] = explode(':', $hora);
    return sprintf('%02dH%02d', (int)$h, (int)$m);
}

$tipoMap = [
        'ordinaria'      => 'ORDINARIA',
        'extraordinaria' => 'EXTRAORDINARIA',
        'urgente'        => 'URGENTE',
];
$tipoLabel   = $tipoMap[$c['tipo_reunion']] ?? strtoupper($c['tipo_reunion']);
$tipoLower   = ucfirst(strtolower($tipoLabel));
$esGeneral   = $c['tipo_asistentes'] === 'general';
$asistentes  = $esGeneral ? 'señores socios activos' : 'señores miembros de la Directiva';
$asambleaTxt = $esGeneral ? 'Asamblea General de Socios' : 'Reunión de Directivos';

$nombreCreador = trim(($c['nombre_real'] ?? '') . ' ' . ($c['apellido_real'] ?? ''));
if (!$nombreCreador) $nombreCreador = $c['nombre_creador'] ?? 'Secretaría';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convocatoria – <?= htmlspecialchars($c['titulo']) ?></title>
    <style>
        /* ---- Reset ---- */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Times New Roman', Times, serif;
            background: #e8e8e8;
            color: #111;
            font-size: 12pt;
            line-height: 1.55;
        }

        /* ---- Controles (no imprimir) ---- */
        .toolbar {
            background: #1f3a5f;
            color: #fff;
            padding: 10px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-family: Arial, sans-serif;
            font-size: 13px;
            gap: 12px;
        }
        .toolbar span { opacity: .75; }
        .toolbar-btns { display: flex; gap: 8px; }
        .toolbar-btns button {
            background: rgba(255,255,255,.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,.3);
            padding: 6px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-family: Arial, sans-serif;
            font-weight: 600;
            transition: background .2s;
        }
        .toolbar-btns button:hover { background: rgba(255,255,255,.28); }
        .toolbar-btns button.btn-print { background: #fff; color: #1f3a5f; }
        .toolbar-btns button.btn-print:hover { background: #e8f0fb; }

        /* ---- Hoja ---- */
        .sheet-wrap {
            display: flex;
            justify-content: center;
            padding: 30px 20px 50px;
        }
        .sheet {
            background: #fff;
            width: 720px;
            min-height: 980px;
            padding: 48px 60px 50px;
            box-shadow: 0 4px 30px rgba(0,0,0,.18);
            position: relative;
        }

        /* ---- Marca de agua ---- */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            width: 480px;
            opacity: .06;
            pointer-events: none;
            user-select: none;
        }

        /* ---- Cabecera ---- */
        .header {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 20px;
        }
        .header-logo {
            flex-shrink: 0;
            width: 88px;
            height: 88px;
        }
        .header-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .header-text {
            flex: 1;
        }
        .header-text .org-name {
            font-size: 12pt;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1.3;
            margin-bottom: 4px;
            color: #111;
        }
        .header-right {
            flex-shrink: 0;
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .header-right .conv-label {
            font-size: 12pt;
            font-weight: 700;
            text-transform: uppercase;
            color: #111;
        }
        .header-right .fecha-bold {
            font-size: 11.5pt;
            font-weight: 700;
            color: #111;
        }
        .header-right .hora-line {
            font-size: 11pt;
            color: #111;
        }

        /* ---- Línea divisoria ---- */
        .divider {
            border: none;
            border-top: 2px solid #111;
            margin: 12px 0 16px;
        }

        /* ---- Intro ---- */
        .saludo {
            font-weight: 700;
            margin-bottom: 4px;
        }
        .intro-p {
            text-align: justify;
            margin-bottom: 14px;
            font-size: 12pt;
        }
        .intro-p strong { font-weight: 700; }

        /* ---- Orden del día ---- */
        .oda-title {
            font-size: 12pt;
            font-weight: 700;
            margin: 6px 0 8px;
            text-decoration: underline;
        }
        .oda-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            font-size: 11.5pt;
        }
        .oda-table td {
            border: 1px solid #333;
            padding: 5px 10px;
            vertical-align: top;
        }
        .oda-table td:first-child {
            width: 36px;
            text-align: center;
            font-weight: 700;
            white-space: nowrap;
        }

        /* ---- Cierre ---- */
        .cierre-p {
            text-align: justify;
            font-size: 11.5pt;
            margin-bottom: 18px;
        }
        .atentamente {
            font-size: 12pt;
            margin-bottom: 52px;
        }

        /* ---- Firmas ---- */
        .firmas-wrap {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .firma-bloque {
            flex: 1;
            min-width: 160px;
        }
        .firma-nombre {
            font-size: 12pt;
            font-weight: 700;
            border-top: 1.5px solid #111;
            padding-top: 4px;
            text-align: left;
        }
        .firma-cargo {
            font-size: 11.5pt;
            font-weight: 400;
            text-align: left;
            padding-left: 28px;
        }

        /* ---- Pie ---- */
        .pie {
            text-align: center;
            font-size: 8pt;
            color: #aaa;
            border-top: 1px solid #ddd;
            margin-top: 38px;
            padding-top: 8px;
            font-family: Arial, sans-serif;
        }

        /* ---- IMPRESIÓN ---- */
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet-wrap { padding: 0; }
            .sheet {
                box-shadow: none;
                width: 100%;
                padding: 20mm 22mm 18mm;
                min-height: unset;
            }
            .pie { display: none; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>

<!-- Toolbar -->
<div class="toolbar no-print">
    <span>📄 Vista previa — Convocatoria #<?= $id ?></span>
    <div class="toolbar-btns">
        <button onclick="window.close()">✕ Cerrar</button>
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
    </div>
</div>

<!-- Hoja -->
<div class="sheet-wrap">
    <div class="sheet">

        <!-- Marca de agua -->
        <?php if (file_exists(__DIR__.'/img/logo.png')): ?>
            <img class="watermark" src="img/logo.png" alt="">
        <?php endif; ?>

        <!-- Cabecera: logo + nombre | tipo + fecha + hora -->
        <div class="header">
            <div class="header-logo">
                <?php if (file_exists(__DIR__.'/img/logo.png')): ?>
                    <img src="img/logo.png" alt="Logo">
                <?php endif; ?>
            </div>
            <div class="header-text">
                <div class="org-name">
                    Asociación de Trabajadores Agrícolas Autónomos<br>"Santa Lucia Corotú"
                </div>
            </div>
            <div class="header-right">
                <div class="conv-label">CONVOCATORIA A REUNION <?= $tipoLabel ?></div>
                <div class="fecha-bold"><?= formatFechaES($c['fecha']) ?></div>
                <div class="hora-line"><strong>Hora:</strong> <?= formatHora($c['hora']) ?></div>
            </div>
        </div>

        <hr class="divider">

        <!-- Saludo e intro -->
        <p class="saludo">Estimados socios:</p>
        <p class="saludo">Estimados socios:</p>
        <br>
        <p class="intro-p">
            Se convoca a una reunión <strong><?= $tipoLower ?></strong> que se efectuará de manera presencial.<br>
            La cual se realizará el día <strong><?= formatFechaES($c['fecha']) ?></strong>
            para tratarse el siguiente orden del día.
        </p>

        <!-- Orden del día -->
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

        <!-- Cierre -->
        <p class="cierre-p">
            Agradecemos de antemano su puntualidad y compromiso, los cuales son esenciales para avanzar
            en los objetivos de nuestra organización y consolidar las acciones necesarias para el beneficio de
            todos los socios.
        </p>

        <p class="atentamente">Atentamente</p>

        <!-- Firmas -->
        <div class="firmas-wrap">
            <?php if ($firmas): ?>
                <?php foreach ($firmas as $f): ?>
                    <div class="firma-bloque">
                        <div class="firma-nombre"><?= htmlspecialchars($f['nombre']) ?></div>
                        <div class="firma-cargo"><?= htmlspecialchars($f['cargo']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Firmas por defecto si no hay registradas -->
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

        <!-- Pie generado -->
        <div class="pie">
            Generado por el Sistema de Gestión · Asociación Santa Lucía Corotú · <?= date('d/m/Y H:i') ?>
        </div>

    </div><!-- /sheet -->
</div><!-- /sheet-wrap -->

</body>
</html>