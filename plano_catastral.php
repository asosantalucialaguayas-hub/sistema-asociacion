<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require "config/conexion.php";

$id_socio     = intval($_GET['id_socio'] ?? 0);
$id_ubicacion = intval($_GET['id_ubicacion'] ?? 0);

if (!$id_socio) { die('Parámetro id_socio requerido'); }

// ── Datos del socio ──────────────────────────────────────────────
$stS = $pdo->prepare("
    SELECT s.*,
        COALESCE(NULLIF(TRIM(s.nombre_completo),''),
            TRIM(CONCAT(COALESCE(s.nombres,''),' ',COALESCE(s.apellidos,'')))) AS nombre_full,
        l.zona, l.comunidad_grupo
    FROM socios s
    LEFT JOIN (
        SELECT lj.id_socio, lj.zona, lj.comunidad_grupo
        FROM tabla_lpa lj
        INNER JOIN (SELECT id_socio, MAX(id_lpa) AS mx FROM tabla_lpa WHERE id_socio IS NOT NULL GROUP BY id_socio) m
            ON lj.id_socio = m.id_socio AND lj.id_lpa = m.mx
    ) l ON l.id_socio = s.id_socio
    WHERE s.id_socio = :id
");
$stS->bindValue(':id', $id_socio, PDO::PARAM_INT);
$stS->execute();
$socio = $stS->fetch(PDO::FETCH_ASSOC);
if (!$socio) die('Socio no encontrado');

// ── Archivos del socio ───────────────────────────────────────────
$stU = $pdo->prepare("SELECT * FROM socio_ubicaciones WHERE id_socio = :id ORDER BY codigo_archivo ASC");
$stU->bindValue(':id', $id_socio, PDO::PARAM_INT);
$stU->execute();
$archivos = $stU->fetchAll(PDO::FETCH_ASSOC);

// Si viene id_ubicacion específico, preseleccionar
$archivoSel = null;
if ($id_ubicacion) {
    foreach ($archivos as $a) {
        if ($a['id_ubicacion'] == $id_ubicacion) { $archivoSel = $a; break; }
    }
}
if (!$archivoSel && count($archivos)) $archivoSel = $archivos[0];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Plano Catastral — <?= htmlspecialchars($socio['nombre_full']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<!-- jsPDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<style>
:root {
  --navy:#1a2744;--blue:#2563eb;--sky:#38bdf8;--green:#10b981;
  --amber:#f59e0b;--red:#ef4444;--gray50:#f8fafc;--gray100:#f1f5f9;
  --gray200:#e2e8f0;--gray400:#94a3b8;--gray600:#475569;--gray900:#0f172a;
  --paper:#fffef7;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--gray100);color:var(--gray900);min-height:100vh}

/* ── TOOLBAR ── */
.toolbar{background:var(--navy);color:#fff;padding:10px 20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,.3)}
.toolbar h1{font-family:'Space Mono',monospace;font-size:13px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--sky);display:flex;align-items:center;gap:8px}
.toolbar-sep{width:1px;height:28px;background:rgba(255,255,255,.15);flex-shrink:0}
.tb-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:#fff;font-size:12px;font-weight:600;cursor:pointer;transition:all .18s;font-family:'DM Sans',sans-serif;white-space:nowrap}
.tb-btn:hover{background:rgba(255,255,255,.18);border-color:rgba(255,255,255,.4)}
.tb-btn.primary{background:var(--blue);border-color:var(--blue)}
.tb-btn.primary:hover{background:#1d4ed8}
.tb-btn.green{background:var(--green);border-color:var(--green)}
.tb-btn.green:hover{background:#059669}
.tb-btn.amber{background:var(--amber);border-color:var(--amber);color:#000}
.tb-btn.amber:hover{background:#d97706}
.sel-archivo{padding:7px 11px;border-radius:8px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:#fff;font-size:12px;font-family:'DM Sans',sans-serif;cursor:pointer;outline:none}
.sel-archivo option{background:var(--navy);color:#fff}

/* ── MAIN ── */
.main{display:grid;grid-template-columns:280px 1fr;gap:0;height:calc(100vh - 52px)}

/* ── SIDEBAR CONFIG ── */
.config-panel{background:#fff;border-right:1px solid var(--gray200);overflow-y:auto;padding:16px}
.config-panel::-webkit-scrollbar{width:4px}
.config-panel::-webkit-scrollbar-thumb{background:var(--gray200);border-radius:4px}
.cfg-section{margin-bottom:18px}
.cfg-title{font-size:10px;font-weight:700;color:var(--gray400);letter-spacing:.8px;text-transform:uppercase;margin-bottom:10px;display:flex;align-items:center;gap:6px}
.cfg-title i{color:var(--blue)}
.cfg-row{margin-bottom:10px}
.cfg-label{display:block;font-size:11px;font-weight:600;color:var(--gray600);margin-bottom:4px}
.cfg-input{width:100%;padding:7px 10px;border:1px solid var(--gray200);border-radius:7px;font-size:12px;font-family:'DM Sans',sans-serif;outline:none;transition:border-color .15s}
.cfg-input:focus{border-color:var(--blue)}
.cfg-textarea{width:100%;padding:7px 10px;border:1px solid var(--gray200);border-radius:7px;font-size:12px;font-family:'DM Sans',sans-serif;outline:none;resize:vertical;min-height:60px;transition:border-color .15s}
.cfg-textarea:focus{border-color:var(--blue)}
.logo-upload{border:2px dashed var(--gray200);border-radius:8px;padding:14px;text-align:center;cursor:pointer;transition:all .2s}
.logo-upload:hover{border-color:var(--blue);background:var(--gray50)}
.logo-upload i{font-size:24px;color:var(--gray400);display:block;margin-bottom:6px}
.logo-upload p{font-size:11px;color:var(--gray400)}
.logo-preview{max-width:100%;max-height:60px;border-radius:6px;margin-top:8px;display:none}
.color-row{display:flex;gap:6px;flex-wrap:wrap}
.color-sw{width:22px;height:22px;border-radius:50%;cursor:pointer;border:3px solid transparent;transition:all .15s}
.color-sw:hover,.color-sw.sel{border-color:var(--gray900);transform:scale(1.1)}
.cfg-divider{height:1px;background:var(--gray100);margin:14px 0}
.stat-chip{background:var(--gray50);border:1px solid var(--gray200);border-radius:6px;padding:6px 10px;font-size:11px;margin-bottom:6px;display:flex;align-items:center;justify-content:space-between}
.stat-chip b{color:var(--navy);font-family:'Space Mono',monospace}

/* ── CANVAS AREA ── */
.canvas-area{background:var(--gray100);display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:24px;overflow:auto;gap:16px}

/* ── PLANO SHEET ── */
.plano-sheet{background:var(--paper);border:1px solid var(--gray200);box-shadow:0 8px 40px rgba(0,0,0,.15);width:794px;min-height:1123px;position:relative;font-family:'DM Sans',sans-serif;flex-shrink:0}

/* Estructura interna del plano */
.plano-inner{padding:18px;display:flex;flex-direction:column;gap:0;height:100%}

/* Cabecera */
.plano-header{display:flex;align-items:center;gap:14px;border:2px solid var(--navy);border-bottom:none;padding:10px 14px;background:#fff}
.plano-logo-box{width:70px;height:70px;display:flex;align-items:center;justify-content:center;border:1px solid var(--gray200);border-radius:6px;flex-shrink:0;overflow:hidden;background:var(--gray50)}
.plano-logo-box img{max-width:100%;max-height:100%;object-fit:contain}
.plano-logo-box i{font-size:28px;color:var(--gray400)}
.plano-header-text{flex:1}
.plano-assoc{font-family:'Playfair Display',serif;font-size:15px;font-weight:700;color:var(--navy);line-height:1.2}
.plano-subtitle{font-size:10px;color:var(--gray600);margin-top:2px;letter-spacing:.3px}
.plano-doc-num{text-align:right;font-family:'Space Mono',monospace;font-size:10px;color:var(--gray600);flex-shrink:0;line-height:1.8}
.plano-doc-num b{color:var(--navy);display:block;font-size:13px}

/* Título del plano */
.plano-title-bar{background:var(--navy);color:#fff;text-align:center;padding:7px;font-family:'Space Mono',monospace;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;border-left:2px solid var(--navy);border-right:2px solid var(--navy)}

/* Datos del propietario */
.plano-owner{border:2px solid var(--navy);border-top:none;padding:7px 14px;display:grid;grid-template-columns:repeat(3,1fr);gap:6px;background:#fff}
.plano-field{display:flex;flex-direction:column;gap:2px}
.plano-field-label{font-size:8.5px;font-weight:700;color:var(--gray400);text-transform:uppercase;letter-spacing:.5px}
.plano-field-value{font-size:11px;font-weight:600;color:var(--navy)}
.plano-field-value.mono{font-family:'Space Mono',monospace;font-size:10px}

/* Área de dibujo + tabla */
.plano-body{display:grid;grid-template-columns:1fr 220px;border:2px solid var(--navy);border-top:none;flex:1}
.plano-draw{border-right:1px solid var(--gray200);position:relative;display:flex;align-items:center;justify-content:center;background:var(--gray50);min-height:480px;overflow:hidden}
#planCanvas{display:block}
.norte-indicator{position:absolute;top:10px;right:10px;width:36px;height:36px;border-radius:50%;border:2px solid var(--navy);background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:8px;font-weight:700;font-family:'Space Mono',monospace;color:var(--navy)}
.norte-indicator .n-arrow{font-size:14px;color:var(--blue);line-height:1}
.escala-bar{position:absolute;bottom:10px;left:10px;display:flex;flex-direction:column;gap:3px}
.escala-label{font-size:8px;font-family:'Space Mono',monospace;color:var(--gray600)}
.escala-rule{height:8px;background:linear-gradient(90deg,var(--navy) 50%,#fff 50%);width:80px;border:1px solid var(--navy)}

/* Tabla lateral */
.plano-table-col{padding:8px;display:flex;flex-direction:column;gap:8px}
.pt-section-title{font-size:8.5px;font-weight:700;color:var(--navy);letter-spacing:.5px;text-transform:uppercase;border-bottom:1px solid var(--navy);padding-bottom:3px;margin-bottom:4px}
.pt-table{width:100%;border-collapse:collapse;font-size:9px}
.pt-table th{background:var(--navy);color:#fff;padding:3px 4px;text-align:center;font-weight:700;letter-spacing:.3px}
.pt-table td{padding:3px 4px;border-bottom:1px solid var(--gray100);text-align:center;font-family:'Space Mono',monospace;font-size:8.5px;color:var(--gray900)}
.pt-table tr:nth-child(even) td{background:var(--gray50)}
.pt-info-box{background:var(--navy);color:#fff;padding:7px;border-radius:4px}
.pt-info-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px}
.pt-info-row:last-child{margin-bottom:0}
.pt-info-label{font-size:8px;text-transform:uppercase;letter-spacing:.4px;opacity:.7}
.pt-info-val{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:var(--sky)}

/* Footer */
.plano-footer{border:2px solid var(--navy);border-top:1px solid var(--gray200);padding:6px 14px;display:flex;align-items:center;justify-content:space-between;background:#fff}
.pf-left{font-size:9px;color:var(--gray600);line-height:1.6}
.pf-right{font-size:9px;color:var(--gray600);text-align:right;font-family:'Space Mono',monospace}
.pf-sello{width:60px;height:60px;border:1.5px dashed var(--gray200);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:7px;color:var(--gray400);text-align:center;font-family:'Space Mono',monospace;line-height:1.4}

/* Loading overlay */
.loading-ov{display:none;position:fixed;inset:0;background:rgba(26,39,68,.8);z-index:9999;align-items:center;justify-content:center;flex-direction:column;gap:12px;color:#fff;font-family:'Space Mono',monospace;font-size:12px;backdrop-filter:blur(4px)}
.loading-ov.show{display:flex}
.spin{width:40px;height:40px;border:3px solid rgba(255,255,255,.2);border-top-color:#38bdf8;border-radius:50%;animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* Responsive */
@media(max-width:900px){.main{grid-template-columns:1fr}.config-panel{display:none}}
</style>
</head>
<body>

<!-- ══ TOOLBAR ══ -->
<div class="toolbar">
  <h1><i class="fa fa-map"></i> PLANO CATASTRAL</h1>
  <div class="toolbar-sep"></div>
  <?php if (count($archivos) > 1): ?>
  <select class="sel-archivo" id="selArchivo" onchange="cambiarArchivo(this.value)">
    <?php foreach ($archivos as $a): ?>
    <option value="<?= $a['id_ubicacion'] ?>" <?= ($archivoSel && $a['id_ubicacion'] == $archivoSel['id_ubicacion']) ? 'selected' : '' ?>>
      <?= htmlspecialchars($a['codigo_archivo'] ?: $a['nombre_archivo']) ?>
    </option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>
  <div class="toolbar-sep"></div>
  <button class="tb-btn green" onclick="exportarPDF()">
    <i class="fa fa-file-pdf"></i> Descargar PDF
  </button>
  <button class="tb-btn amber" onclick="window.print()">
    <i class="fa fa-print"></i> Imprimir
  </button>
  <button class="tb-btn" onclick="window.close()">
    <i class="fa fa-times"></i> Cerrar
  </button>
</div>

<!-- ══ MAIN ══ -->
<div class="main">

  <!-- Panel de configuración -->
  <div class="config-panel">

    <div class="cfg-section">
      <div class="cfg-title"><i class="fa fa-building"></i> Asociación</div>
      <div class="cfg-row">
        <label class="cfg-label">Nombre de la Asociación</label>
        <input class="cfg-input" id="cfgNombreAsoc" value="Asociación de Productores Santa Lucía" oninput="actualizarPlano()">
      </div>
      <div class="cfg-row">
        <label class="cfg-label">RUC / Identificación</label>
        <input class="cfg-input" id="cfgRucAsoc" value="0968500210001" oninput="actualizarPlano()">
      </div>
      <div class="cfg-row">
        <label class="cfg-label">Logo de la Asociación</label>
        <div class="logo-upload" onclick="document.getElementById('logoInput').click()">
          <i class="fa fa-image"></i>
          <p>Clic para subir logo</p>
          <img id="logoPreview" class="logo-preview" src="" alt="Logo">
        </div>
        <input type="file" id="logoInput" accept="image/*" style="display:none" onchange="cargarLogo(this)">
      </div>
    </div>

    <div class="cfg-divider"></div>

    <div class="cfg-section">
      <div class="cfg-title"><i class="fa fa-palette"></i> Apariencia</div>
      <div class="cfg-row">
        <label class="cfg-label">Color del polígono</label>
        <div class="color-row" id="colorRow">
          <?php
          $colores = ['#1a2744','#2563eb','#10b981','#ef4444','#f59e0b','#7c3aed','#0891b2','#dc2626'];
          foreach ($colores as $col):
          ?>
          <div class="color-sw<?= $col==='#1a2744'?' sel':'' ?>" style="background:<?= $col ?>" onclick="selColor('<?= $col ?>',this)" title="<?= $col ?>"></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="cfg-row">
        <label class="cfg-label">Escala referencial</label>
        <input class="cfg-input" id="cfgEscala" value="1:5000" oninput="actualizarPlano()">
      </div>
      <div class="cfg-row">
        <label class="cfg-label">Observaciones</label>
        <textarea class="cfg-textarea" id="cfgObs" oninput="actualizarPlano()" placeholder="Ej: Lote en zona rural, coordenadas WGS84...">Coordenadas en sistema WGS84. Área calculada desde geometría KML.</textarea>
      </div>
    </div>

    <div class="cfg-divider"></div>

    <div class="cfg-section">
      <div class="cfg-title"><i class="fa fa-chart-area"></i> Datos calculados</div>
      <div class="stat-chip">Área <b id="statArea">—</b></div>
      <div class="stat-chip">Perímetro <b id="statPerim">—</b></div>
      <div class="stat-chip">Vértices <b id="statVtx">—</b></div>
      <div class="stat-chip">Centroide Lat <b id="statLat">—</b></div>
      <div class="stat-chip">Centroide Lon <b id="statLon">—</b></div>
    </div>

  </div>

  <!-- Área del plano -->
  <div class="canvas-area" id="canvasArea">
    <div class="plano-sheet" id="planoSheet">
      <div class="plano-inner" id="planoInner">

        <!-- Cabecera -->
        <div class="plano-header" id="planoHeader">
          <div class="plano-logo-box" id="logoBox">
            <i class="fa fa-leaf"></i>
          </div>
          <div class="plano-header-text">
            <div class="plano-assoc" id="planoAsocNombre">Asociación de Productores Santa Lucía</div>
            <div class="plano-subtitle" id="planoAsocRuc">RUC: 0968500210001 &nbsp;·&nbsp; Guayas, Ecuador</div>
            <div class="plano-subtitle" style="margin-top:2px;color:#1a2744;font-weight:600;">PLANO DE UBICACIÓN CATASTRAL</div>
          </div>
          <div class="plano-doc-num">
            <b id="planoDocNum">—</b>
            <span id="planoFecha">—</span><br>
            Sistema: WGS84<br>
            Proyección: UTM
          </div>
        </div>

        <!-- Título -->
        <div class="plano-title-bar">PLANO CATASTRAL DE LOTE — ASOCIACIÓN SANTA LUCÍA</div>

        <!-- Datos propietario -->
        <div class="plano-owner" id="planoOwner">
          <div class="plano-field">
            <span class="plano-field-label">Propietario / Productor</span>
            <span class="plano-field-value" id="pfNombre">—</span>
          </div>
          <div class="plano-field">
            <span class="plano-field-label">Cédula / RUC</span>
            <span class="plano-field-value mono" id="pfCedula">—</span>
          </div>
          <div class="plano-field">
            <span class="plano-field-label">Zona / Comunidad</span>
            <span class="plano-field-value" id="pfZona">—</span>
          </div>
          <div class="plano-field">
            <span class="plano-field-label">Código de Lote</span>
            <span class="plano-field-value mono" id="pfCodigo">—</span>
          </div>
          <div class="plano-field">
            <span class="plano-field-label">Área Total</span>
            <span class="plano-field-value mono" id="pfArea">—</span>
          </div>
          <div class="plano-field">
            <span class="plano-field-label">Perímetro</span>
            <span class="plano-field-value mono" id="pfPerim">—</span>
          </div>
        </div>

        <!-- Cuerpo: dibujo + tabla -->
        <div class="plano-body">
          <!-- Canvas de dibujo -->
          <div class="plano-draw" id="planoDraw">
            <canvas id="planCanvas" width="540" height="480"></canvas>
            <div class="norte-indicator">
              <span class="n-arrow">↑</span>
              <span>N</span>
            </div>
            <div class="escala-bar">
              <span class="escala-label" id="escalaLabel">ESC: 1:5000</span>
              <div class="escala-rule"></div>
            </div>
          </div>

          <!-- Tabla de vértices -->
          <div class="plano-table-col">
            <div>
              <div class="pt-section-title">Tabla de Vértices</div>
              <table class="pt-table" id="tablaVertices">
                <thead>
                  <tr>
                    <th>V#</th>
                    <th>Latitud</th>
                    <th>Longitud</th>
                  </tr>
                </thead>
                <tbody id="tbodyVertices">
                  <tr><td colspan="3" style="color:#94a3b8;font-size:9px;padding:8px;">Cargando...</td></tr>
                </tbody>
              </table>
            </div>

            <div style="margin-top:auto">
              <div class="pt-section-title">Resumen</div>
              <div class="pt-info-box">
                <div class="pt-info-row">
                  <span class="pt-info-label">Área (ha)</span>
                  <span class="pt-info-val" id="ptArea">—</span>
                </div>
                <div class="pt-info-row">
                  <span class="pt-info-label">Perímetro (km)</span>
                  <span class="pt-info-val" id="ptPerim">—</span>
                </div>
                <div class="pt-info-row">
                  <span class="pt-info-label">Lat. Centro</span>
                  <span class="pt-info-val" id="ptLat">—</span>
                </div>
                <div class="pt-info-row">
                  <span class="pt-info-label">Lon. Centro</span>
                  <span class="pt-info-val" id="ptLon">—</span>
                </div>
              </div>

              <div style="margin-top:8px">
                <div class="pt-section-title">Observaciones</div>
                <div style="font-size:8.5px;color:#475569;line-height:1.6" id="ptObs">
                  Coordenadas en sistema WGS84. Área calculada desde geometría KML.
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="plano-footer">
          <div class="pf-left">
            <strong>Elaborado por:</strong> Sistema de Gestión — Asociación Santa Lucía<br>
            <strong>Fecha:</strong> <span id="pfFechaFull">—</span><br>
            <strong>Usuario:</strong> <?= htmlspecialchars($_SESSION['usuario']) ?>
          </div>
          <div class="pf-sello">SELLO<br>OFICIAL</div>
          <div class="pf-right">
            Documento generado<br>automáticamente<br>
            <span id="pfDocNum2">—</span>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- Loading overlay -->
<div class="loading-ov" id="loadingOv">
  <div class="spin"></div>
  <span id="loadingTxt">Generando PDF...</span>
</div>

<script>
/* ═══════════════════════════════════════
   DATOS DEL SERVIDOR
═══════════════════════════════════════ */
const SOCIO = <?= json_encode([
  'nombre'    => $socio['nombre_full'],
  'cedula'    => $socio['identificacion'],
  'zona'      => $socio['zona'] ?? '',
  'comunidad' => $socio['comunidad_grupo'] ?? '',
], JSON_UNESCAPED_UNICODE) ?>;

const ARCHIVOS = <?= json_encode(array_map(function($a) {
  return [
    'id'          => $a['id_ubicacion'],
    'codigo'      => $a['codigo_archivo'] ?: $a['nombre_archivo'],
    'ruta'        => $a['ruta_archivo'],
    'tipo'        => $a['tipo_archivo'],
    'atributos'   => $a['atributos'],
    'titulo_aviso'=> $a['titulo_aviso'] ?? '',
  ];
}, $archivos), JSON_UNESCAPED_UNICODE) ?>;

const ID_SEL = <?= $archivoSel ? $archivoSel['id_ubicacion'] : 'null' ?>;

/* ═══════════════════════════════════════
   ESTADO
═══════════════════════════════════════ */
let colorPoligono = '#1a2744';
let logoDataUrl   = null;
let coordsActuales = [];
let geoInfoActual  = {};

/* ═══════════════════════════════════════
   INIT
═══════════════════════════════════════ */
window.addEventListener('load', () => {
  poblarDatosSocio();
  if (ID_SEL) cargarKML(ID_SEL);
  else if (ARCHIVOS.length) cargarKML(ARCHIVOS[0].id);
  actualizarFecha();
});

function poblarDatosSocio() {
  document.getElementById('pfNombre').textContent  = SOCIO.nombre;
  document.getElementById('pfCedula').textContent  = SOCIO.cedula;
  document.getElementById('pfZona').textContent    = [SOCIO.zona, SOCIO.comunidad].filter(Boolean).join(' / ') || '—';
}

function actualizarFecha() {
  const now = new Date();
  const fmt = now.toLocaleDateString('es-EC', {day:'2-digit',month:'long',year:'numeric'});
  const fmtShort = now.toLocaleDateString('es-EC', {day:'2-digit',month:'2-digit',year:'numeric'});
  document.getElementById('planoFecha').textContent  = fmtShort;
  document.getElementById('pfFechaFull').textContent = fmt;
}

/* ═══════════════════════════════════════
   CARGAR KML
═══════════════════════════════════════ */
async function cargarKML(idUbicacion) {
  try {
    const r = await fetch(`ubicaciones_api.php?accion=leer_kml&id_ubicacion=${idUbicacion}`);
    const j = await r.json();
    if (!j.success) return;

    const arch = ARCHIVOS.find(a => a.id == idUbicacion);
    document.getElementById('pfCodigo').textContent   = arch?.codigo || '—';
    document.getElementById('planoDocNum').textContent = arch?.codigo || '—';
    document.getElementById('pfDocNum2').textContent   = arch?.codigo || '—';

    const kmlStr = atob(j.kml);
    const coords = extraerCoordsKML(kmlStr);
    coordsActuales = coords;

    if (!coords.length) return;

    // Calcular geo
    const geo = calcularGeo(coords);
    geoInfoActual = geo;

    // Poblar datos
    document.getElementById('pfArea').textContent  = geo.area !== null ? geo.area.toFixed(4) + ' ha' : '—';
    document.getElementById('pfPerim').textContent = geo.perim !== null ? geo.perim.toFixed(4) + ' km' : '—';
    document.getElementById('ptArea').textContent  = geo.area !== null ? geo.area.toFixed(4) : '—';
    document.getElementById('ptPerim').textContent = geo.perim !== null ? geo.perim.toFixed(4) : '—';
    document.getElementById('ptLat').textContent   = geo.lat !== null ? geo.lat.toFixed(6) : '—';
    document.getElementById('ptLon').textContent   = geo.lon !== null ? geo.lon.toFixed(6) : '—';

    // Stats sidebar
    document.getElementById('statArea').textContent  = geo.area !== null ? geo.area.toFixed(4) + ' ha' : '—';
    document.getElementById('statPerim').textContent = geo.perim !== null ? geo.perim.toFixed(4) + ' km' : '—';
    document.getElementById('statVtx').textContent   = coords.length;
    document.getElementById('statLat').textContent   = geo.lat !== null ? geo.lat.toFixed(6) : '—';
    document.getElementById('statLon').textContent   = geo.lon !== null ? geo.lon.toFixed(6) : '—';

    // Tabla de vértices
    renderTablaVertices(coords);

    // Dibujar polígono
    dibujarPoligono(coords);

  } catch(e) { console.error(e); }
}

/* ═══════════════════════════════════════
   EXTRAER COORDENADAS KML
═══════════════════════════════════════ */
function extraerCoordsKML(kmlStr) {
  const doc = new DOMParser().parseFromString(kmlStr, 'text/xml');
  const coordEls = doc.querySelectorAll('outerBoundaryIs coordinates, Polygon > coordinates, coordinates');
  if (!coordEls.length) return [];
  const raw = coordEls[0].textContent || '';
  const coords = [];
  raw.trim().split(/\s+/).forEach(c => {
    const p = c.split(',');
    if (p.length >= 2) {
      const lon = parseFloat(p[0]), lat = parseFloat(p[1]);
      if (!isNaN(lon) && !isNaN(lat)) coords.push({ lat, lon });
    }
  });
  return coords;
}

/* ═══════════════════════════════════════
   CÁLCULOS GEOGRÁFICOS
═══════════════════════════════════════ */
function calcularGeo(coords) {
  const R = 6371000;
  let area = 0, perim = 0;
  const n = coords.length;

  for (let i = 0; i < n - 1; i++) {
    const lat1 = coords[i].lat * Math.PI/180;
    const lat2 = coords[i+1].lat * Math.PI/180;
    const dlon = (coords[i+1].lon - coords[i].lon) * Math.PI/180;
    area += dlon * (2 + Math.sin(lat1) + Math.sin(lat2));
  }
  area = parseFloat((Math.abs(area) * R * R / 2 / 10000).toFixed(4));

  for (let i = 0; i < n - 1; i++) {
    const dLat = (coords[i+1].lat - coords[i].lat) * Math.PI/180;
    const dLon = (coords[i+1].lon - coords[i].lon) * Math.PI/180;
    const a = Math.sin(dLat/2)**2 + Math.cos(coords[i].lat*Math.PI/180)*Math.cos(coords[i+1].lat*Math.PI/180)*Math.sin(dLon/2)**2;
    perim += R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  }
  perim = parseFloat((perim / 1000).toFixed(4));

  const lat = coords.reduce((s,c) => s + c.lat, 0) / n;
  const lon = coords.reduce((s,c) => s + c.lon, 0) / n;

  return { area, perim, lat, lon };
}

/* ═══════════════════════════════════════
   TABLA DE VÉRTICES
═══════════════════════════════════════ */
function renderTablaVertices(coords) {
  const tbody = document.getElementById('tbodyVertices');
  // Limitar a 20 vértices en la tabla para no desbordar
  const muestra = coords.length > 20 ? coords.slice(0, 20) : coords;
  tbody.innerHTML = muestra.map((c, i) => `
    <tr>
      <td style="font-weight:700;color:#1a2744;">${i+1}</td>
      <td>${c.lat.toFixed(6)}</td>
      <td>${c.lon.toFixed(6)}</td>
    </tr>
  `).join('');
  if (coords.length > 20) {
    tbody.innerHTML += `<tr><td colspan="3" style="color:#94a3b8;font-size:8px;padding:4px;">... y ${coords.length - 20} más</td></tr>`;
  }
}

/* ═══════════════════════════════════════
   DIBUJAR POLÍGONO EN CANVAS
═══════════════════════════════════════ */
function dibujarPoligono(coords) {
  const canvas = document.getElementById('planCanvas');
  const ctx    = canvas.getContext('2d');
  const W = canvas.width, H = canvas.height;
  const PAD = 60;

  ctx.clearRect(0, 0, W, H);

  if (!coords.length) return;

  // Calcular bounds
  const lats = coords.map(c => c.lat);
  const lons = coords.map(c => c.lon);
  const minLat = Math.min(...lats), maxLat = Math.max(...lats);
  const minLon = Math.min(...lons), maxLon = Math.max(...lons);

  const ranLat = maxLat - minLat || 0.0001;
  const ranLon = maxLon - minLon || 0.0001;

  const scaleX = (W - PAD*2) / ranLon;
  const scaleY = (H - PAD*2) / ranLat;
  const scale  = Math.min(scaleX, scaleY);

  const offX = (W - ranLon * scale) / 2;
  const offY = (H - ranLat * scale) / 2;

  const toX = lon => offX + (lon - minLon) * scale;
  const toY = lat => H - offY - (lat - minLat) * scale;

  // Fondo cuadrícula
  ctx.strokeStyle = '#e2e8f0';
  ctx.lineWidth = 0.5;
  for (let i = 0; i <= 10; i++) {
    const x = (W / 10) * i;
    const y = (H / 10) * i;
    ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, H); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke();
  }

  // Polígono relleno
  ctx.beginPath();
  coords.forEach((c, i) => {
    const x = toX(c.lon), y = toY(c.lat);
    i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
  });
  ctx.closePath();
  ctx.fillStyle   = colorPoligono + '33';
  ctx.strokeStyle = colorPoligono;
  ctx.lineWidth   = 2.5;
  ctx.fill();
  ctx.stroke();

  // Vértices numerados
  const muestra = coords.length > 30 ? coords.filter((_, i) => i % Math.ceil(coords.length/30) === 0) : coords;
  muestra.forEach((c, i) => {
    const x = toX(c.lon), y = toY(c.lat);
    const realIdx = coords.indexOf(c);

    // Punto
    ctx.beginPath();
    ctx.arc(x, y, 4, 0, Math.PI*2);
    ctx.fillStyle = colorPoligono;
    ctx.fill();
    ctx.strokeStyle = '#fff';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    // Número
    ctx.fillStyle = colorPoligono;
    ctx.font = 'bold 8px Space Mono, monospace';
    ctx.textAlign = 'center';
    ctx.fillText(realIdx + 1, x, y - 7);
  });

  // Centroide
  if (geoInfoActual.lat) {
    const cx = toX(geoInfoActual.lon);
    const cy = toY(geoInfoActual.lat);
    ctx.beginPath();
    ctx.arc(cx, cy, 5, 0, Math.PI*2);
    ctx.fillStyle = '#ef4444';
    ctx.fill();
  }
}

/* ═══════════════════════════════════════
   CONTROLES UI
═══════════════════════════════════════ */
function selColor(color, el) {
  colorPoligono = color;
  document.querySelectorAll('.color-sw').forEach(e => e.classList.remove('sel'));
  el.classList.add('sel');
  if (coordsActuales.length) dibujarPoligono(coordsActuales);
}

function cargarLogo(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    logoDataUrl = e.target.result;
    const prev = document.getElementById('logoPreview');
    prev.src = logoDataUrl;
    prev.style.display = 'block';
    // Actualizar logo en plano
    const logoBox = document.getElementById('logoBox');
    logoBox.innerHTML = `<img src="${logoDataUrl}" style="max-width:100%;max-height:100%;object-fit:contain;">`;
  };
  reader.readAsDataURL(file);
}

function actualizarPlano() {
  document.getElementById('planoAsocNombre').textContent = document.getElementById('cfgNombreAsoc').value;
  document.getElementById('planoAsocRuc').textContent    = 'RUC: ' + document.getElementById('cfgRucAsoc').value + ' · Guayas, Ecuador';
  document.getElementById('escalaLabel').textContent     = 'ESC: ' + document.getElementById('cfgEscala').value;
  document.getElementById('ptObs').textContent           = document.getElementById('cfgObs').value;
}

function cambiarArchivo(id) {
  coordsActuales = [];
  geoInfoActual  = {};
  cargarKML(parseInt(id));
}

/* ═══════════════════════════════════════
   EXPORTAR PDF
═══════════════════════════════════════ */
async function exportarPDF() {
  const { jsPDF } = window.jspdf;
  setLoading(true, 'Preparando plano...');

  await new Promise(r => setTimeout(r, 200));

  try {
    const pdf = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
    const PW = 297, PH = 210; // A4 landscape mm
    const M = 10; // margen

    // ── Fondo y borde exterior ──
    pdf.setFillColor(255, 254, 247);
    pdf.rect(0, 0, PW, PH, 'F');
    pdf.setDrawColor(26, 39, 68);
    pdf.setLineWidth(0.8);
    pdf.rect(M, M, PW - M*2, PH - M*2);

    // ── CABECERA ──────────────────────────────────────────
    const headerH = 22;
    pdf.setFillColor(26, 39, 68);
    pdf.rect(M, M, PW - M*2, headerH, 'F');

    // Logo (si existe)
    if (logoDataUrl) {
      try { pdf.addImage(logoDataUrl, 'PNG', M+2, M+2, 18, 18); } catch(e) {}
    }

    // Nombre asociación
    const nombreAsoc = document.getElementById('cfgNombreAsoc').value;
    pdf.setTextColor(255, 255, 255);
    pdf.setFontSize(13);
    pdf.setFont('helvetica', 'bold');
    pdf.text(nombreAsoc.toUpperCase(), M + 24, M + 9);

    pdf.setFontSize(8);
    pdf.setFont('helvetica', 'normal');
    pdf.setTextColor(147, 197, 253);
    pdf.text('PLANO CATASTRAL DE LOTE — SISTEMA DE GESTIÓN', M + 24, M + 14);
    pdf.text('RUC: ' + document.getElementById('cfgRucAsoc').value + '   |   Guayas, Ecuador   |   Sistema WGS84', M + 24, M + 19);

    // Código y fecha
    const codigo = document.getElementById('pfCodigo').textContent;
    const fecha  = new Date().toLocaleDateString('es-EC', {day:'2-digit',month:'2-digit',year:'numeric'});
    pdf.setTextColor(255, 255, 255);
    pdf.setFontSize(10);
    pdf.setFont('helvetica', 'bold');
    pdf.text(codigo, PW - M - 5, M + 8, { align: 'right' });
    pdf.setFontSize(7);
    pdf.setFont('helvetica', 'normal');
    pdf.setTextColor(147, 197, 253);
    pdf.text(fecha, PW - M - 5, M + 13, { align: 'right' });

    // ── DATOS DEL PROPIETARIO ─────────────────────────────
    const ownerY = M + headerH + 2;
    const ownerH = 16;
    pdf.setFillColor(241, 245, 249);
    pdf.rect(M, ownerY, PW - M*2, ownerH, 'F');
    pdf.setDrawColor(26, 39, 68);
    pdf.setLineWidth(0.3);
    pdf.rect(M, ownerY, PW - M*2, ownerH);

    const campos = [
      { label: 'PROPIETARIO / PRODUCTOR', value: SOCIO.nombre },
      { label: 'CÉDULA / RUC',            value: SOCIO.cedula },
      { label: 'ZONA / COMUNIDAD',         value: [SOCIO.zona, SOCIO.comunidad].filter(Boolean).join(' / ') || '—' },
      { label: 'CÓDIGO DE LOTE',           value: codigo },
      { label: 'ÁREA TOTAL',               value: document.getElementById('pfArea').textContent },
      { label: 'PERÍMETRO',                value: document.getElementById('pfPerim').textContent },
    ];

    const colW = (PW - M*2) / campos.length;
    campos.forEach((f, i) => {
      const x = M + colW * i;
      pdf.setDrawColor(200, 210, 220);
      if (i > 0) { pdf.setLineWidth(0.2); pdf.line(x, ownerY, x, ownerY + ownerH); }
      pdf.setTextColor(100, 116, 139);
      pdf.setFontSize(6);
      pdf.setFont('helvetica', 'bold');
      pdf.text(f.label, x + 2, ownerY + 5);
      pdf.setTextColor(26, 39, 68);
      pdf.setFontSize(8);
      pdf.setFont('helvetica', 'bold');
      pdf.text(f.value || '—', x + 2, ownerY + 11);
    });

    // ── CUERPO: CANVAS + TABLA ────────────────────────────
    const bodyY  = ownerY + ownerH + 2;
    const bodyH  = PH - M - bodyY - 14;
    const tableW = 75;
    const drawW  = PW - M*2 - tableW;

    // Dibujar el canvas del polígono como imagen
    const canvas = document.getElementById('planCanvas');
    const imgData = canvas.toDataURL('image/png');
    pdf.addImage(imgData, 'PNG', M, bodyY, drawW, bodyH);
    pdf.setDrawColor(26, 39, 68);
    pdf.setLineWidth(0.3);
    pdf.rect(M, bodyY, drawW, bodyH);

    // Norte
    pdf.setFillColor(255, 255, 255);
    pdf.circle(M + drawW - 8, bodyY + 8, 6, 'FD');
    pdf.setTextColor(26, 39, 68);
    pdf.setFontSize(7);
    pdf.setFont('helvetica', 'bold');
    pdf.text('↑N', M + drawW - 8, bodyY + 9, { align: 'center' });

    // Escala
    pdf.setTextColor(70, 85, 105);
    pdf.setFontSize(7);
    pdf.setFont('helvetica', 'normal');
    pdf.text('ESC: ' + document.getElementById('cfgEscala').value, M + 3, bodyY + bodyH - 3);
    pdf.setFillColor(26, 39, 68);
    pdf.rect(M + 3, bodyY + bodyH - 6, 20, 2, 'F');
    pdf.setFillColor(255, 255, 255);
    pdf.rect(M + 13, bodyY + bodyH - 6, 10, 2, 'F');
    pdf.setDrawColor(26, 39, 68);
    pdf.rect(M + 3, bodyY + bodyH - 6, 20, 2);

    // ── TABLA LATERAL ─────────────────────────────────────
    const tX = M + drawW + 2;
    const tW = tableW - 2;

    // Título tabla
    pdf.setFillColor(26, 39, 68);
    pdf.rect(tX, bodyY, tW, 7, 'F');
    pdf.setTextColor(255, 255, 255);
    pdf.setFontSize(7);
    pdf.setFont('helvetica', 'bold');
    pdf.text('TABLA DE VÉRTICES', tX + tW/2, bodyY + 4.5, { align: 'center' });

    // Headers
    const colsT = [8, 33, 34]; // anchos
    const colsX = [tX, tX+8, tX+41];
    pdf.setFillColor(37, 99, 235);
    pdf.rect(tX, bodyY+7, tW, 5, 'F');
    pdf.setTextColor(255,255,255);
    pdf.setFontSize(6);
    ['V#','LATITUD','LONGITUD'].forEach((h,i) => {
      pdf.text(h, colsX[i] + colsT[i]/2, bodyY + 10.5, { align: 'center' });
    });

    // Filas
    const maxRows = Math.floor((bodyH - 60) / 4.5);
    const vtxMuestra = coordsActuales.slice(0, maxRows);
    vtxMuestra.forEach((c, i) => {
      const rowY = bodyY + 12 + i * 4.5;
      if (i % 2 === 0) { pdf.setFillColor(248,250,252); pdf.rect(tX, rowY-0.5, tW, 4.5, 'F'); }
      pdf.setTextColor(26,39,68);
      pdf.setFontSize(5.5);
      pdf.setFont('helvetica', i%2===0?'normal':'normal');
      pdf.text(String(i+1), colsX[0]+4, rowY+2.8, {align:'center'});
      pdf.setFont('courier', 'normal');
      pdf.text(c.lat.toFixed(6), colsX[1]+colsT[1]/2, rowY+2.8, {align:'center'});
      pdf.text(c.lon.toFixed(6), colsX[2]+colsT[2]/2, rowY+2.8, {align:'center'});
      pdf.setDrawColor(230,230,230); pdf.setLineWidth(0.1);
      pdf.line(tX, rowY+4, tX+tW, rowY+4);
    });
    if (coordsActuales.length > maxRows) {
      const moreY = bodyY + 12 + vtxMuestra.length * 4.5 + 3;
      pdf.setTextColor(150,150,150); pdf.setFontSize(5.5);
      pdf.text(`... y ${coordsActuales.length - maxRows} vértices más`, tX + tW/2, moreY, {align:'center'});
    }

    // Resumen box
    const sumY = bodyY + bodyH - 42;
    pdf.setFillColor(26,39,68);
    pdf.rect(tX, sumY, tW, 42, 'F');
    pdf.setTextColor(147,197,253);
    pdf.setFontSize(6);
    pdf.setFont('helvetica','bold');
    pdf.text('RESUMEN', tX+tW/2, sumY+5, {align:'center'});
    const resumen = [
      ['Área (ha)',    document.getElementById('ptArea').textContent],
      ['Perímetro(km)',document.getElementById('ptPerim').textContent],
      ['Lat. Centro',  document.getElementById('ptLat').textContent],
      ['Lon. Centro',  document.getElementById('ptLon').textContent],
    ];
    resumen.forEach(([lbl, val], i) => {
      const ry = sumY + 10 + i * 8;
      pdf.setTextColor(148,163,184); pdf.setFontSize(5.5); pdf.setFont('helvetica','normal');
      pdf.text(lbl, tX+2, ry);
      pdf.setTextColor(56,189,248); pdf.setFontSize(7); pdf.setFont('courier','bold');
      pdf.text(val, tX+tW-2, ry+4, {align:'right'});
    });

    // ── OBSERVACIONES ─────────────────────────────────────
    const obsY = bodyY + bodyH - 0;
    // (incluidas en footer)

    // ── FOOTER ───────────────────────────────────────────
    const footY = PH - M - 12;
    pdf.setFillColor(241,245,249);
    pdf.rect(M, footY, PW-M*2, 12, 'F');
    pdf.setDrawColor(26,39,68); pdf.setLineWidth(0.3);
    pdf.rect(M, footY, PW-M*2, 12);

    pdf.setTextColor(70,85,105); pdf.setFontSize(6.5); pdf.setFont('helvetica','normal');
    pdf.text(`Elaborado: Sistema de Gestión — ${nombreAsoc}`, M+3, footY+4);
    pdf.text(`Usuario: <?= htmlspecialchars($_SESSION['usuario']) ?>   |   Fecha: ${fecha}`, M+3, footY+8.5);

    const obs = document.getElementById('cfgObs').value;
    pdf.text('Obs: ' + obs.substring(0, 80), M+3, footY+12);

    pdf.setFont('courier','bold'); pdf.setTextColor(26,39,68);
    pdf.text(codigo, PW-M-3, footY+4, {align:'right'});
    pdf.setFont('helvetica','normal'); pdf.setTextColor(100,116,139); pdf.setFontSize(6);
    pdf.text('Documento generado automáticamente', PW-M-3, footY+8.5, {align:'right'});

    // Borde exterior final
    pdf.setDrawColor(26,39,68); pdf.setLineWidth(1);
    pdf.rect(M, M, PW-M*2, PH-M*2);

    setLoading(false);
    pdf.save(`Plano_Catastral_${codigo}_${fecha.replace(/\//g,'-')}.pdf`);

  } catch(e) {
    setLoading(false);
    console.error(e);
    alert('Error al generar PDF: ' + e.message);
  }
}

function setLoading(show, txt='') {
  const ov = document.getElementById('loadingOv');
  ov.classList.toggle('show', show);
  if (txt) document.getElementById('loadingTxt').textContent = txt;
}
</script>
</body>
</html>
