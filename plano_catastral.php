<?php
/**
 * plano_catastral.php — Versión 2.0 REFORZADA
 * ─────────────────────────────────────────────
 * Mejoras:
 *  • Múltiples polígonos → hojas separadas automáticas
 *  • Mapa satelital de fondo via OpenStreetMap/ESRI (sin API key)
 *  • Tabla de vértices completa (todas las páginas que sean necesarias)
 *  • Opción A4 Horizontal / Vertical antes de exportar
 *  • Asociación: Santa Lucía Corotu, RUC: 1291721334001
 *  • Subtítulo: "PLANO DE UBICACIÓN" (solo eso, sin extra)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require "config/conexion.php";

$id_socio     = intval($_GET['id_socio'] ?? 0);
$id_ubicacion = intval($_GET['id_ubicacion'] ?? 0);

if (!$id_socio) die('Parámetro id_socio requerido');

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

// ── Todos los archivos del socio ─────────────────────────────────
$stU = $pdo->prepare("SELECT * FROM socio_ubicaciones WHERE id_socio = :id ORDER BY codigo_archivo ASC");
$stU->bindValue(':id', $id_socio, PDO::PARAM_INT);
$stU->execute();
$archivos = $stU->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Plano Catastral — <?= htmlspecialchars($socio['nombre_full']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<style>
:root{
  --navy:#0f2240;--blue:#1d4ed8;--sky:#0ea5e9;--green:#059669;
  --amber:#d97706;--red:#dc2626;--gray50:#f8fafc;--gray100:#f1f5f9;
  --gray200:#e2e8f0;--gray400:#94a3b8;--gray600:#475569;--gray900:#0f172a;
  --paper:#fffef7;--accent:#10b981;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:#1e293b;color:var(--gray900);min-height:100vh}

/* ── TOOLBAR ── */
.toolbar{background:var(--navy);color:#fff;padding:10px 18px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;position:sticky;top:0;z-index:200;box-shadow:0 2px 16px rgba(0,0,0,.5)}
.toolbar h1{font-family:'Space Mono',monospace;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--sky);display:flex;align-items:center;gap:7px;white-space:nowrap}
.toolbar-sep{width:1px;height:26px;background:rgba(255,255,255,.12);flex-shrink:0}
.tb-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:7px;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.07);color:#fff;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;font-family:'DM Sans',sans-serif;white-space:nowrap}
.tb-btn:hover{background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.4)}
.tb-btn.green{background:var(--green);border-color:var(--green)}
.tb-btn.green:hover{background:#047857}
.tb-btn.blue{background:var(--blue);border-color:var(--blue)}
.tb-btn.blue:hover{background:#1e40af}
.tb-btn.amber{background:var(--amber);border-color:var(--amber);color:#fff}
.tb-btn.amber:hover{background:#b45309}
.tb-select{padding:7px 10px;border-radius:7px;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.07);color:#fff;font-size:12px;font-family:'DM Sans',sans-serif;cursor:pointer;outline:none}
.tb-select option{background:#0f2240}
.orientation-toggle{display:flex;gap:4px}
.ori-btn{padding:5px 10px;border-radius:5px;border:1px solid rgba(255,255,255,.2);background:transparent;color:rgba(255,255,255,.6);font-size:11px;cursor:pointer;transition:all .15s;font-family:'DM Sans',sans-serif}
.ori-btn.active{background:var(--sky);border-color:var(--sky);color:#fff;font-weight:700}

/* ── PAGES PREVIEW AREA ── */
.pages-wrap{padding:24px;display:flex;flex-direction:column;gap:32px;align-items:center;min-height:calc(100vh - 52px);overflow-y:auto}
.page-group{display:flex;flex-direction:column;align-items:center;gap:12px;width:100%}
.page-label{color:#94a3b8;font-size:11px;font-family:'Space Mono',monospace;letter-spacing:1px;text-transform:uppercase}

/* ── PLANO SHEET ── */
.plano-sheet{background:var(--paper);box-shadow:0 12px 50px rgba(0,0,0,.5);position:relative;flex-shrink:0;overflow:hidden}
.plano-sheet.landscape{width:1056px;min-height:748px}
.plano-sheet.portrait{width:748px;min-height:1056px}

/* ── HEADER ── */
.ph{display:flex;align-items:stretch;border:2.5px solid var(--navy);border-bottom:none;background:#fff}
.ph-logo{width:76px;min-height:68px;display:flex;align-items:center;justify-content:center;border-right:1.5px solid var(--gray200);background:var(--gray50);flex-shrink:0;padding:6px;overflow:hidden}
.ph-logo img{max-width:100%;max-height:60px;object-fit:contain}
.ph-logo i{font-size:30px;color:var(--gray400)}
.ph-main{flex:1;min-width:0;padding:8px 12px;display:flex;flex-direction:column;justify-content:center;overflow:hidden}
.ph-assoc{font-size:13.5px;font-weight:700;color:var(--navy);letter-spacing:.2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.3}
.ph-ruc{font-size:9px;color:var(--gray600);margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ph-sub{font-size:9.5px;font-weight:700;color:var(--navy);margin-top:5px;letter-spacing:.5px;text-transform:uppercase;border-top:1px solid var(--gray200);padding-top:4px;white-space:nowrap}
.ph-right{padding:8px 10px;text-align:right;font-family:'Space Mono',monospace;border-left:1.5px solid var(--gray200);display:flex;flex-direction:column;justify-content:center;flex-shrink:0;min-width:110px;max-width:130px}
.ph-docnum{font-size:14px;font-weight:700;color:var(--navy)}
.ph-date{font-size:9px;color:var(--gray600);margin-top:2px}
.ph-sys{font-size:9px;color:var(--gray400);margin-top:2px;line-height:1.5}

/* ── TITLE BAR ── */
.title-bar{background:var(--navy);color:#fff;text-align:center;padding:6px;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;border-left:2.5px solid var(--navy);border-right:2.5px solid var(--navy)}

/* ── OWNER ROW ── */
.owner-row{border:2.5px solid var(--navy);border-top:none;padding:6px 12px;display:grid;gap:6px;background:#fff}
.owner-row.cols-6{grid-template-columns:repeat(6,1fr)}
.owner-row.cols-4{grid-template-columns:repeat(4,1fr)}
.of{display:flex;flex-direction:column;gap:2px}
.of-label{font-size:8px;font-weight:700;color:var(--gray400);text-transform:uppercase;letter-spacing:.4px}
.of-value{font-size:11px;font-weight:700;color:var(--navy)}
.of-value.mono{font-family:'Space Mono',monospace;font-size:10px}

/* ── BODY: MAP + TABLE ── */
.body-grid{display:grid;border:2.5px solid var(--navy);border-top:none}
.body-grid.with-table{grid-template-columns:1fr 230px}
.body-grid.full{grid-template-columns:1fr}

.map-area{position:relative;overflow:hidden;background:#e8f4e8;display:flex;align-items:center;justify-content:center;min-height:420px}
.map-bg-canvas{position:absolute;inset:0;width:100%;height:100%}
.poly-canvas{position:absolute;inset:0;width:100%;height:100%}
.north-badge{position:absolute;top:10px;right:10px;width:38px;height:38px;border-radius:50%;border:2px solid var(--navy);background:rgba(255,255,255,.95);display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:10}
.north-arrow{font-size:16px;color:var(--blue);line-height:1}
.north-n{font-size:8px;font-weight:700;font-family:'Space Mono',monospace;color:var(--navy)}
.scale-bar{position:absolute;bottom:10px;left:10px;z-index:10}
.scale-txt{font-size:8px;font-family:'Space Mono',monospace;color:var(--navy);background:rgba(255,255,255,.8);padding:1px 3px;border-radius:2px}
.scale-rule{height:7px;background:linear-gradient(90deg,var(--navy) 50%,#fff 50%);width:80px;border:1px solid var(--navy);margin-top:2px}
.map-attrib{position:absolute;bottom:2px;right:4px;font-size:7px;color:rgba(0,0,0,.5);z-index:10;background:rgba(255,255,255,.7);padding:1px 3px;border-radius:2px}

/* ── TABLE COL ── */
.table-col{border-left:1.5px solid var(--gray200);padding:8px;display:flex;flex-direction:column;gap:0;overflow:hidden}
.table-col .vtx-scroll{overflow-y:auto;flex:1;max-height:100%}
.tc-title{font-size:8.5px;font-weight:700;color:var(--navy);letter-spacing:.5px;text-transform:uppercase;border-bottom:1.5px solid var(--navy);padding-bottom:3px;margin-bottom:6px}
.vtx-table{width:100%;border-collapse:collapse;font-size:8.5px}
.vtx-table th{background:var(--navy);color:#fff;padding:3px 4px;text-align:center;font-weight:700;font-size:8px;letter-spacing:.3px}
.vtx-table td{padding:2.5px 4px;border-bottom:1px solid var(--gray100);text-align:center;font-family:'Space Mono',monospace;font-size:8px;color:var(--gray900)}
.vtx-table tr:nth-child(even) td{background:var(--gray50)}
.vtx-table td.vidx{font-weight:700;color:var(--navy)}
.info-box{background:var(--navy);color:#fff;padding:7px;border-radius:4px;margin-top:8px}
.ib-row{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:5px}
.ib-row:last-child{margin-bottom:0}
.ib-label{font-size:7.5px;text-transform:uppercase;letter-spacing:.3px;opacity:.65}
.ib-val{font-family:'Space Mono',monospace;font-size:10.5px;font-weight:700;color:var(--sky)}
.obs-box{margin-top:8px;font-size:8px;color:var(--gray600);line-height:1.7;border-top:1px solid var(--gray200);padding-top:6px}

/* ── FULL VERTICES TABLE PAGE ── */
.vtx-page-body{border:2.5px solid var(--navy);border-top:none;padding:14px}
.vtx-full-table{width:100%;border-collapse:collapse;font-size:9px}
.vtx-full-table th{background:var(--navy);color:#fff;padding:5px 8px;text-align:center;font-weight:700;font-size:8.5px;letter-spacing:.4px}
.vtx-full-table td{padding:3.5px 8px;border-bottom:1px solid var(--gray100);text-align:center;font-family:'Space Mono',monospace;font-size:9px}
.vtx-full-table tr:nth-child(even) td{background:var(--gray50)}
.vtx-full-table td.vi{font-weight:700;color:var(--navy)}
.vtx-cols-3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}

/* ── FOOTER ── */
.pf{border:2.5px solid var(--navy);border-top:1px solid var(--gray200);padding:6px 14px;display:flex;align-items:center;justify-content:space-between;background:#fff;gap:10px}
.pf-info{font-size:8.5px;color:var(--gray600);line-height:1.7}
.pf-seal{width:56px;height:56px;border:1.5px dashed var(--gray300);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:7px;color:var(--gray400);text-align:center;font-family:'Space Mono',monospace;line-height:1.4;flex-shrink:0}
.pf-doc{font-size:9px;color:var(--gray600);text-align:right;font-family:'Space Mono',monospace}

/* ── LOADING ── */
.loading-ov{display:none;position:fixed;inset:0;background:rgba(15,34,64,.85);z-index:9999;align-items:center;justify-content:center;flex-direction:column;gap:14px;color:#fff;font-family:'Space Mono',monospace;font-size:12px;backdrop-filter:blur(6px)}
.loading-ov.show{display:flex}
.spin{width:44px;height:44px;border:3px solid rgba(255,255,255,.15);border-top-color:#0ea5e9;border-radius:50%;animation:spin .75s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.progress-bar{width:280px;height:4px;background:rgba(255,255,255,.15);border-radius:4px;overflow:hidden}
.progress-fill{height:100%;background:var(--sky);border-radius:4px;transition:width .3s}

/* ── CONFIG MODAL ── */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
.modal-bg.show{display:flex}
.modal{background:#fff;border-radius:12px;padding:24px;width:480px;max-width:90vw;box-shadow:0 24px 64px rgba(0,0,0,.4)}
.modal h3{font-size:15px;font-weight:700;color:var(--navy);margin-bottom:16px;display:flex;align-items:center;gap:8px}
.modal-row{margin-bottom:12px}
.modal-label{display:block;font-size:11px;font-weight:600;color:var(--gray600);margin-bottom:4px}
.modal-input{width:100%;padding:8px 10px;border:1.5px solid var(--gray200);border-radius:7px;font-size:13px;font-family:'DM Sans',sans-serif;outline:none;transition:border-color .15s}
.modal-input:focus{border-color:var(--blue)}
.modal-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:20px}
.modal-btn{padding:8px 18px;border-radius:7px;border:none;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;font-family:'DM Sans',sans-serif}
.modal-btn.cancel{background:var(--gray100);color:var(--gray600)}
.modal-btn.cancel:hover{background:var(--gray200)}
.modal-btn.confirm{background:var(--blue);color:#fff}
.modal-btn.confirm:hover{background:#1e40af}
.logo-drop{border:2px dashed var(--gray200);border-radius:8px;padding:16px;text-align:center;cursor:pointer;transition:all .2s}
.logo-drop:hover{border-color:var(--blue);background:var(--gray50)}
.logo-drop i{font-size:28px;color:var(--gray400);display:block;margin-bottom:6px}
.logo-drop p{font-size:11px;color:var(--gray400)}
#logoPreviewModal{max-width:100%;max-height:70px;margin-top:8px;border-radius:6px;display:none}
</style>
</head>
<body>

<!-- TOOLBAR -->
<div class="toolbar">
  <h1><i class="fa fa-map"></i> PLANO CATASTRAL</h1>
  <div class="toolbar-sep"></div>
  <div style="display:flex;flex-direction:column;gap:2px">
    <span style="font-size:10px;color:#94a3b8;letter-spacing:.5px;text-transform:uppercase">Orientación PDF</span>
    <div class="orientation-toggle">
      <button class="ori-btn active" id="btnLand" onclick="setOrientation('landscape')"><i class="fa fa-rectangle-landscape" style="font-size:10px"></i> Horizontal</button>
      <button class="ori-btn" id="btnPort" onclick="setOrientation('portrait')"><i class="fa fa-rectangle-portrait" style="font-size:10px"></i> Vertical</button>
    </div>
  </div>
  <div class="toolbar-sep"></div>
  <button class="tb-btn" onclick="abrirConfig()"><i class="fa fa-sliders"></i> Configurar</button>
  <button class="tb-btn green" onclick="exportarPDF()"><i class="fa fa-file-pdf"></i> Descargar PDF</button>
  <button class="tb-btn amber" onclick="window.print()"><i class="fa fa-print"></i> Imprimir</button>
  <button class="tb-btn" onclick="window.close()"><i class="fa fa-times"></i> Cerrar</button>
</div>

<!-- PAGES PREVIEW -->
<div class="pages-wrap" id="pagesWrap">
  <!-- Generado dinámicamente por JS -->
  <div style="color:#94a3b8;font-family:'Space Mono',monospace;font-size:12px;padding:40px;">Cargando planos...</div>
</div>

<!-- CONFIG MODAL -->
<div class="modal-bg" id="modalBg">
  <div class="modal">
    <h3><i class="fa fa-sliders" style="color:var(--blue)"></i> Configuración del Plano</h3>
    <div class="modal-row">
      <label class="modal-label">Nombre de la Asociación</label>
      <input class="modal-input" id="cfgNombreAsoc" value="ASOCIACIÓN SANTA LUCÍA COROTU">
    </div>
    <div class="modal-row">
      <label class="modal-label">RUC</label>
      <input class="modal-input" id="cfgRuc" value="1291721334001">
    </div>
    <div class="modal-row">
      <label class="modal-label">Escala referencial</label>
      <input class="modal-input" id="cfgEscala" value="1:5000">
    </div>
    <div class="modal-row">
      <label class="modal-label">Observaciones</label>
      <input class="modal-input" id="cfgObs" value="Coordenadas en sistema WGS84. Área calculada desde geometría KML.">
    </div>
    <div class="modal-row">
      <label class="modal-label">Logo de la Asociación</label>
      <div class="logo-drop" onclick="document.getElementById('logoFileInput').click()">
        <i class="fa fa-image"></i>
        <p>Clic para subir logo (PNG/JPG)</p>
        <img id="logoPreviewModal" src="" alt="Logo">
      </div>
      <input type="file" id="logoFileInput" accept="image/*" style="display:none" onchange="cargarLogo(this)">
    </div>
    <div class="modal-actions">
      <button class="modal-btn cancel" onclick="cerrarConfig()">Cancelar</button>
      <button class="modal-btn confirm" onclick="aplicarConfig()"><i class="fa fa-check"></i> Aplicar</button>
    </div>
  </div>
</div>

<!-- LOADING -->
<div class="loading-ov" id="loadingOv">
  <div class="spin"></div>
  <span id="loadingTxt">Preparando planos...</span>
  <div class="progress-bar"><div class="progress-fill" id="progressFill" style="width:0%"></div></div>
</div>

<script>
/* ════════════════════════════════════════════
   DATOS DEL SERVIDOR (PHP → JS)
════════════════════════════════════════════ */
const SOCIO = <?= json_encode([
  'nombre'    => $socio['nombre_full'],
  'cedula'    => $socio['identificacion'] ?? '',
  'zona'      => $socio['zona'] ?? '',
  'comunidad' => $socio['comunidad_grupo'] ?? '',
], JSON_UNESCAPED_UNICODE) ?>;

const ARCHIVOS = <?= json_encode(array_map(fn($a) => [
  'id'     => $a['id_ubicacion'],
  'codigo' => $a['codigo_archivo'] ?: $a['nombre_archivo'],
  'ruta'   => $a['ruta_archivo'],
], $archivos), JSON_UNESCAPED_UNICODE) ?>;

/* ════════════════════════════════════════════
   ESTADO GLOBAL
════════════════════════════════════════════ */
let orientation = 'landscape'; // landscape | portrait
let logoDataUrl = null;
let todosLosPlanos = []; // Array de { archivo, coords, geo }

let CFG = {
  nombreAsoc: 'ASOCIACIÓN SANTA LUCÍA COROTU',
  ruc:        '1291721334001',
  escala:     '1:5000',
  obs:        'Coordenadas en sistema WGS84. Área calculada desde geometría KML.',
};

/* ════════════════════════════════════════════
   INIT
════════════════════════════════════════════ */
window.addEventListener('load', async () => {
  setLoading(true, 'Cargando datos KML...', 5);
  await cargarTodosLosKML();
  setLoading(false);
  renderTodas();
});

/* ════════════════════════════════════════════
   CARGAR KML DE TODOS LOS ARCHIVOS
════════════════════════════════════════════ */
async function cargarTodosLosKML() {
  todosLosPlanos = [];
  for (let i = 0; i < ARCHIVOS.length; i++) {
    const arch = ARCHIVOS[i];
    const pct  = Math.round(((i + 1) / ARCHIVOS.length) * 80);
    setLoading(true, `Cargando polígono ${i+1} de ${ARCHIVOS.length}...`, pct);
    try {
      const r = await fetch(`ubicaciones_api.php?accion=leer_kml&id_ubicacion=${arch.id}`);
      const j = await r.json();
      if (!j.success) continue;
      const kml    = atob(j.kml);
      const coords = extraerCoordsKML(kml);
      if (!coords.length) continue;
      const geo = calcularGeo(coords);
      todosLosPlanos.push({ arch, coords, geo });
    } catch(e) { console.error('Error KML', arch.id, e); }
  }
}

/* ════════════════════════════════════════════
   EXTRAER COORDENADAS KML
════════════════════════════════════════════ */
function extraerCoordsKML(kmlStr) {
  const doc    = new DOMParser().parseFromString(kmlStr, 'text/xml');
  const coordEls = doc.querySelectorAll('outerBoundaryIs coordinates, Polygon > coordinates, coordinates');
  if (!coordEls.length) return [];
  const raw  = coordEls[0].textContent || '';
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

/* ════════════════════════════════════════════
   CÁLCULOS GEOGRÁFICOS
════════════════════════════════════════════ */
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
  area = Math.abs(area) * R * R / 2 / 10000;
  for (let i = 0; i < n - 1; i++) {
    const dLat = (coords[i+1].lat - coords[i].lat) * Math.PI/180;
    const dLon = (coords[i+1].lon - coords[i].lon) * Math.PI/180;
    const a = Math.sin(dLat/2)**2 + Math.cos(coords[i].lat*Math.PI/180)*Math.cos(coords[i+1].lat*Math.PI/180)*Math.sin(dLon/2)**2;
    perim += R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  }
  const lat = coords.reduce((s,c) => s + c.lat, 0) / n;
  const lon = coords.reduce((s,c) => s + c.lon, 0) / n;
  return { area: parseFloat(area.toFixed(4)), perim: parseFloat((perim/1000).toFixed(4)), lat, lon };
}

/* ════════════════════════════════════════════
   RENDER TODAS LAS HOJAS EN PANTALLA
════════════════════════════════════════════ */
function renderTodas() {
  const wrap = document.getElementById('pagesWrap');
  wrap.innerHTML = '';
  const fecha = hoy();

  todosLosPlanos.forEach((p, idx) => {
    // ── Hoja 1: Plano principal ──────────────────────
    const grp1 = document.createElement('div');
    grp1.className = 'page-group';
    grp1.innerHTML = `<div class="page-label">Lote ${idx+1} / ${todosLosPlanos.length} — ${p.arch.codigo} — PLANO PRINCIPAL</div>`;
    const sheet1 = crearHojaPlano(p, fecha, idx + 1);
    grp1.appendChild(sheet1);
    wrap.appendChild(grp1);

    // ── Hoja 2+: Tabla completa de vértices — SIEMPRE, para todos los lotes ──
    const vtxPages = crearHojasVertices(p, fecha, idx + 1);
    vtxPages.forEach((vp, vi) => {
      const grp2 = document.createElement('div');
      grp2.className = 'page-group';
      grp2.innerHTML = `<div class="page-label">Lote ${idx+1} — ${p.arch.codigo} — TABLA DE VÉRTICES (${vi+1}/${vtxPages.length})</div>`;
      grp2.appendChild(vp);
      wrap.appendChild(grp2);
    });
  });

  // Dibujar polígonos en todos los canvas
  setTimeout(() => {
    todosLosPlanos.forEach((p, idx) => {
      const canvas = document.getElementById(`polyCanvas_${idx}`);
      if (canvas) dibujarPoligono(canvas, p.coords, p.geo, '#0f2240');
      // Intentar cargar mapa sat
      cargarMapaSatelital(idx, p.coords, p.geo);
    });
  }, 100);
}

/* ════════════════════════════════════════════
   CREAR HOJA PRINCIPAL
════════════════════════════════════════════ */
function crearHojaPlano(p, fecha, num) {
  const isLand = orientation === 'landscape';
  const sheet  = document.createElement('div');
  sheet.className = `plano-sheet ${orientation}`;
  sheet.id = `sheet_${num-1}`;

  const zona = [SOCIO.zona, SOCIO.comunidad].filter(Boolean).join(' / ') || '—';
  // Mostrar TODOS los vértices — sin límite, sin "... más"
  const vtxMuestra = p.coords;

  sheet.innerHTML = `
    <!-- HEADER -->
    <div class="ph">
      <div class="ph-logo" id="logoBox_${num-1}">
        ${logoDataUrl ? `<img src="${logoDataUrl}" alt="Logo">` : '<i class="fa fa-leaf"></i>'}
      </div>
      <div class="ph-main">
        <div class="ph-assoc">${CFG.nombreAsoc}</div>
        <div class="ph-ruc">RUC: ${CFG.ruc} &nbsp;·&nbsp; Guayas, Ecuador</div>
        <div class="ph-sub">PLANO DE UBICACIÓN</div>
      </div>
      <div class="ph-right">
        <div class="ph-docnum">${p.arch.codigo}</div>
        <div class="ph-date">${fecha.corta}</div>
        <div class="ph-sys">Sistema: WGS84<br>Proyección: UTM</div>
      </div>
    </div>

    <!-- TITLE BAR -->
    <div class="title-bar">PLANO CATASTRAL DE LOTE — ${CFG.nombreAsoc}</div>

    <!-- DATOS PROPIETARIO -->
    <div class="owner-row cols-6">
      <div class="of"><span class="of-label">Propietario / Productor</span><span class="of-value">${SOCIO.nombre}</span></div>
      <div class="of"><span class="of-label">Cédula / RUC</span><span class="of-value mono">${SOCIO.cedula || '—'}</span></div>
      <div class="of"><span class="of-label">Zona / Comunidad</span><span class="of-value">${zona}</span></div>
      <div class="of"><span class="of-label">Código de Lote</span><span class="of-value mono">${p.arch.codigo}</span></div>
      <div class="of"><span class="of-label">Área Total</span><span class="of-value mono">${p.geo.area} ha</span></div>
      <div class="of"><span class="of-label">Perímetro</span><span class="of-value mono">${p.geo.perim} km</span></div>
    </div>

    <!-- CUERPO -->
    <div class="body-grid with-table" style="flex:1;min-height:${isLand ? '420' : '600'}px">

      <!-- MAPA + POLÍGONO -->
      <div class="map-area" id="mapArea_${num-1}">
        <canvas class="map-bg-canvas" id="mapBgCanvas_${num-1}"></canvas>
        <canvas class="poly-canvas" id="polyCanvas_${num-1}"></canvas>
        <div class="north-badge">
          <span class="north-arrow">↑</span>
          <span class="north-n">N</span>
        </div>
        <div class="scale-bar">
          <span class="scale-txt">ESC: ${CFG.escala}</span>
          <div class="scale-rule"></div>
        </div>
        <div class="map-attrib" id="mapAttrib_${num-1}">© OpenStreetMap</div>
      </div>

      <!-- TABLA LATERAL -->
      <div class="table-col">
        <div class="tc-title">Tabla de Vértices (${p.coords.length})</div>
        <div class="vtx-scroll">
        <table class="vtx-table">
          <thead><tr><th>V#</th><th>Latitud</th><th>Longitud</th></tr></thead>
          <tbody>
            ${vtxMuestra.map((c,i) => `
              <tr>
                <td class="vidx">${i+1}</td>
                <td>${c.lat.toFixed(6)}</td>
                <td>${c.lon.toFixed(6)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
        </div>

        <div style="margin-top:auto">
          <div class="tc-title" style="margin-top:8px">Resumen</div>
          <div class="info-box">
            <div class="ib-row"><span class="ib-label">Área (ha)</span><span class="ib-val">${p.geo.area}</span></div>
            <div class="ib-row"><span class="ib-label">Perímetro (km)</span><span class="ib-val">${p.geo.perim}</span></div>
            <div class="ib-row"><span class="ib-label">Lat. Centro</span><span class="ib-val">${p.geo.lat.toFixed(6)}</span></div>
            <div class="ib-row"><span class="ib-label">Lon. Centro</span><span class="ib-val">${p.geo.lon.toFixed(6)}</span></div>
            <div class="ib-row"><span class="ib-label">N° Vértices</span><span class="ib-val">${p.coords.length}</span></div>
          </div>
          <div class="obs-box">${CFG.obs}</div>
        </div>
      </div>
    </div>

    <!-- FOOTER -->
    <div class="pf">
      <div class="pf-info">
        <strong>Elaborado por:</strong> Sistema de Gestión — ${CFG.nombreAsoc}<br>
        <strong>Fecha:</strong> ${fecha.larga} &nbsp;·&nbsp; <strong>Usuario:</strong> <?= htmlspecialchars($_SESSION['usuario']) ?>
      </div>
      <div class="pf-seal">SELLO<br>OFICIAL</div>
      <div class="pf-doc">${p.arch.codigo}<br>Documento generado<br>automáticamente</div>
    </div>
  `;
  return sheet;
}

/* ════════════════════════════════════════════
   CREAR HOJAS EXTRA DE VÉRTICES
════════════════════════════════════════════ */
function crearHojasVertices(p, fecha, loteNum) {
  const isLand   = orientation === 'landscape';
  const cols     = 3;
  const rowsPerCol = isLand ? 40 : 55;
  const rowsPerPage = cols * rowsPerCol;
  const pages = [];
  const total = p.coords.length;

  for (let start = 0; start < total; start += rowsPerPage) {
    const chunk = p.coords.slice(start, start + rowsPerPage);
    const pageIdx = pages.length;
    const sheet   = document.createElement('div');
    sheet.className = `plano-sheet ${orientation}`;

    // Dividir chunk en 3 columnas
    const colData = [[], [], []];
    chunk.forEach((c, i) => {
      colData[Math.floor(i / rowsPerCol)].push({ idx: start + i + 1, c });
    });

    sheet.innerHTML = `
      <div class="ph">
        <div class="ph-logo">${logoDataUrl ? `<img src="${logoDataUrl}" alt="Logo">` : '<i class="fa fa-leaf"></i>'}</div>
        <div class="ph-main">
          <div class="ph-assoc">${CFG.nombreAsoc}</div>
          <div class="ph-ruc">RUC: ${CFG.ruc} · Guayas, Ecuador</div>
          <div class="ph-sub">PLANO DE UBICACIÓN — TABLA DE VÉRTICES</div>
        </div>
        <div class="ph-right">
          <div class="ph-docnum">${p.arch.codigo}</div>
          <div class="ph-date">${fecha.corta}</div>
          <div class="ph-sys">Vértices ${start+1}–${Math.min(start+rowsPerPage, total)} de ${total}</div>
        </div>
      </div>
      <div class="title-bar">TABLA COMPLETA DE VÉRTICES — LOTE ${loteNum}: ${p.arch.codigo}</div>
      <div class="owner-row cols-4">
        <div class="of"><span class="of-label">Propietario</span><span class="of-value">${SOCIO.nombre}</span></div>
        <div class="of"><span class="of-label">Código de Lote</span><span class="of-value mono">${p.arch.codigo}</span></div>
        <div class="of"><span class="of-label">Total Vértices</span><span class="of-value mono">${total}</span></div>
        <div class="of"><span class="of-label">Sistema</span><span class="of-value mono">WGS84 / UTM</span></div>
      </div>
      <div class="vtx-page-body" style="flex:1">
        <div class="vtx-cols-3">
          ${colData.map(col => col.length ? `
            <table class="vtx-full-table">
              <thead><tr><th>V#</th><th>Latitud</th><th>Longitud</th></tr></thead>
              <tbody>
                ${col.map(({idx, c}) => `
                  <tr>
                    <td class="vi">${idx}</td>
                    <td>${c.lat.toFixed(6)}</td>
                    <td>${c.lon.toFixed(6)}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          ` : '<div></div>').join('')}
        </div>
      </div>
      <div class="pf">
        <div class="pf-info">
          <strong>Elaborado:</strong> Sistema de Gestión — ${CFG.nombreAsoc}<br>
          <strong>Fecha:</strong> ${fecha.larga} &nbsp;·&nbsp; <strong>Usuario:</strong> <?= htmlspecialchars($_SESSION['usuario']) ?>
        </div>
        <div class="pf-seal">SELLO<br>OFICIAL</div>
        <div class="pf-doc">${p.arch.codigo}<br>Página ${pageIdx+2}<br>Vért. ${start+1}–${Math.min(start+rowsPerPage,total)}</div>
      </div>
    `;
    pages.push(sheet);
  }
  return pages;
}

/* ════════════════════════════════════════════
   DIBUJAR POLÍGONO EN CANVAS
════════════════════════════════════════════ */
function dibujarPoligono(canvas, coords, geo, color) {
  const ctx = canvas.getContext('2d');
  // Ajustar tamaño al contenedor
  const parent = canvas.parentElement;
  canvas.width  = parent.clientWidth  || 540;
  canvas.height = parent.clientHeight || 420;
  const W = canvas.width, H = canvas.height;
  const PAD = 55;

  ctx.clearRect(0, 0, W, H);
  if (!coords.length) return;

  const lats = coords.map(c => c.lat);
  const lons = coords.map(c => c.lon);
  const minLat = Math.min(...lats), maxLat = Math.max(...lats);
  const minLon = Math.min(...lons), maxLon = Math.max(...lons);
  const ranLat = maxLat - minLat || 0.0001;
  const ranLon = maxLon - minLon || 0.0001;
  const scaleX = (W - PAD*2) / ranLon;
  const scaleY = (H - PAD*2) / ranLat;
  const scale  = Math.min(scaleX, scaleY);
  const offX   = (W - ranLon * scale) / 2;
  const offY   = (H - ranLat * scale) / 2;
  const toX    = lon => offX + (lon - minLon) * scale;
  const toY    = lat => H - offY - (lat - minLat) * scale;

  // Polígono relleno
  ctx.beginPath();
  coords.forEach((c, i) => i === 0 ? ctx.moveTo(toX(c.lon), toY(c.lat)) : ctx.lineTo(toX(c.lon), toY(c.lat)));
  ctx.closePath();
  ctx.fillStyle   = color + '30';
  ctx.strokeStyle = color;
  ctx.lineWidth   = 2.5;
  ctx.fill();
  ctx.stroke();

  // Vértices (limitado a 50 etiquetas)
  const step = coords.length > 50 ? Math.ceil(coords.length / 50) : 1;
  coords.forEach((c, i) => {
    if (i % step !== 0 && i !== coords.length - 1) return;
    const x = toX(c.lon), y = toY(c.lat);
    ctx.beginPath(); ctx.arc(x, y, 3.5, 0, Math.PI*2);
    ctx.fillStyle = color; ctx.fill();
    ctx.strokeStyle = '#fff'; ctx.lineWidth = 1.5; ctx.stroke();
    ctx.fillStyle = color; ctx.font = 'bold 8px monospace'; ctx.textAlign = 'center';
    ctx.fillText(i + 1, x, y - 6);
  });

  // Centroide
  const cx = toX(geo.lon), cy = toY(geo.lat);
  ctx.beginPath(); ctx.arc(cx, cy, 5, 0, Math.PI*2);
  ctx.fillStyle = '#dc2626'; ctx.fill();
  ctx.strokeStyle = '#fff'; ctx.lineWidth = 1.5; ctx.stroke();
}

/* ════════════════════════════════════════════
   MAPA SATELITAL — OpenStreetMap tiles o ESRI
   (sin API Key, capa pública)
════════════════════════════════════════════ */
function deg2tile(lat, lon, zoom) {
  const n = Math.pow(2, zoom);
  const x = Math.floor((lon + 180) / 360 * n);
  const latR = lat * Math.PI / 180;
  const y = Math.floor((1 - Math.log(Math.tan(latR) + 1/Math.cos(latR)) / Math.PI) / 2 * n);
  return { x, y };
}

async function cargarMapaSatelital(idx, coords, geo) {
  const bgCanvas = document.getElementById(`mapBgCanvas_${idx}`);
  const mapArea  = document.getElementById(`mapArea_${idx}`);
  if (!bgCanvas || !mapArea) return;

  bgCanvas.width  = mapArea.clientWidth  || 540;
  bgCanvas.height = mapArea.clientHeight || 420;

  const W = bgCanvas.width, H = bgCanvas.height;
  const ctx = bgCanvas.getContext('2d');

  const lats = coords.map(c => c.lat);
  const lons = coords.map(c => c.lon);
  const minLat = Math.min(...lats), maxLat = Math.max(...lats);
  const minLon = Math.min(...lons), maxLon = Math.max(...lons);
  const cLat = (minLat + maxLat) / 2;
  const cLon = (minLon + maxLon) / 2;

  // Calcular zoom óptimo
  let zoom = 16;
  for (let z = 18; z >= 10; z--) {
    const t1 = deg2tile(maxLat, minLon, z);
    const t2 = deg2tile(minLat, maxLon, z);
    if (Math.abs(t2.x - t1.x) <= 4 && Math.abs(t2.y - t1.y) <= 4) { zoom = z; break; }
  }

  // Usar ESRI WorldImagery (sin API key, público)
  // URL: https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}
  const tileBase = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile';
  const TILE_SIZE = 256;

  const centerTile = deg2tile(cLat, cLon, zoom);
  // Calcular cuántos tiles necesitamos
  const tilesX = Math.ceil(W / TILE_SIZE) + 2;
  const tilesY = Math.ceil(H / TILE_SIZE) + 2;

  // Posición en píxeles del tile central
  const tileCenterPixX = centerTile.x * TILE_SIZE;
  const tileCenterPixY = centerTile.y * TILE_SIZE;

  // Coordenadas geográficas de la esquina superior izquierda del tile central
  function tile2deg(x, y, z) {
    const n = Math.pow(2, z);
    const lon = x / n * 360 - 180;
    const latR = Math.atan(Math.sinh(Math.PI * (1 - 2 * y / n)));
    return { lat: latR * 180 / Math.PI, lon };
  }

  const canvasCenter = { x: W/2, y: H/2 };
  const geoCenter    = { lat: cLat, lon: cLon };
  const tileCenterGeo = tile2deg(centerTile.x, centerTile.y, zoom);
  const nextTileGeo   = tile2deg(centerTile.x + 1, centerTile.y + 1, zoom);
  const pixPerDegLon  = TILE_SIZE / (nextTileGeo.lon - tileCenterGeo.lon);
  const pixPerDegLat  = TILE_SIZE / (tileCenterGeo.lat - nextTileGeo.lat);

  const geoToCanvas = (lat, lon) => ({
    x: canvasCenter.x + (lon - geoCenter.lon) * pixPerDegLon,
    y: canvasCenter.y - (lat - geoCenter.lat) * pixPerDegLat,
  });

  const startTileX = centerTile.x - Math.ceil(tilesX/2);
  const startTileY = centerTile.y - Math.ceil(tilesY/2);

  let loaded = 0;
  const totalTiles = tilesX * tilesY;

  // Fondo gris mientras carga
  ctx.fillStyle = '#e2e8f0';
  ctx.fillRect(0, 0, W, H);
  ctx.fillStyle = '#94a3b8';
  ctx.font = '12px monospace';
  ctx.textAlign = 'center';
  ctx.fillText('Cargando mapa satelital...', W/2, H/2);

  const loadPromises = [];
  for (let tx = 0; tx < tilesX; tx++) {
    for (let ty = 0; ty < tilesY; ty++) {
      const tileX = startTileX + tx;
      const tileY = startTileY + ty;

      const tileGeo = tile2deg(tileX, tileY, zoom);
      const cp = geoToCanvas(tileGeo.lat, tileGeo.lon);

      const url = `${tileBase}/${zoom}/${tileY}/${tileX}`;
      const p = new Promise((res) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
          ctx.drawImage(img, Math.round(cp.x), Math.round(cp.y), TILE_SIZE, TILE_SIZE);
          loaded++;
          res();
        };
        img.onerror = () => { loaded++; res(); };
        img.src = url;
      });
      loadPromises.push(p);
    }
  }

  await Promise.all(loadPromises);

  // Redibujar polígono encima del mapa
  const polyCanvas = document.getElementById(`polyCanvas_${idx}`);
  if (polyCanvas) {
    polyCanvas.width  = W;
    polyCanvas.height = H;
    const pctx = polyCanvas.getContext('2d');
    pctx.clearRect(0, 0, W, H);

    // Polígono sobre mapa
    pctx.beginPath();
    coords.forEach((c, i) => {
      const p = geoToCanvas(c.lat, c.lon);
      i === 0 ? pctx.moveTo(p.x, p.y) : pctx.lineTo(p.x, p.y);
    });
    pctx.closePath();
    pctx.fillStyle   = 'rgba(37,99,235,0.25)';
    pctx.strokeStyle = '#1d4ed8';
    pctx.lineWidth   = 3;
    pctx.fill();
    pctx.stroke();

    // Vértices sobre mapa
    const step = coords.length > 40 ? Math.ceil(coords.length / 40) : 1;
    coords.forEach((c, i) => {
      if (i % step !== 0 && i !== coords.length - 1) return;
      const p = geoToCanvas(c.lat, c.lon);
      pctx.beginPath(); pctx.arc(p.x, p.y, 4, 0, Math.PI*2);
      pctx.fillStyle = '#1d4ed8'; pctx.fill();
      pctx.strokeStyle = '#fff'; pctx.lineWidth = 1.5; pctx.stroke();
      pctx.fillStyle = '#0f2240'; pctx.font = 'bold 8px monospace'; pctx.textAlign = 'center';
      // Sombra para legibilidad
      pctx.strokeStyle = 'rgba(255,255,255,0.8)'; pctx.lineWidth = 3;
      pctx.strokeText(i+1, p.x, p.y - 7);
      pctx.fillText(i+1, p.x, p.y - 7);
    });

    // Centroide
    const cc = geoToCanvas(geo.lat, geo.lon);
    pctx.beginPath(); pctx.arc(cc.x, cc.y, 6, 0, Math.PI*2);
    pctx.fillStyle = '#dc2626'; pctx.fill();
    pctx.strokeStyle = '#fff'; pctx.lineWidth = 2; pctx.stroke();
  }

  // Actualizar atribución
  const att = document.getElementById(`mapAttrib_${idx}`);
  if (att) att.textContent = '© Esri, WorldImagery';
}

/* ════════════════════════════════════════════
   ORIENTACIÓN
════════════════════════════════════════════ */
function setOrientation(ori) {
  orientation = ori;
  document.getElementById('btnLand').classList.toggle('active', ori === 'landscape');
  document.getElementById('btnPort').classList.toggle('active', ori === 'portrait');
  renderTodas();
}

/* ════════════════════════════════════════════
   CONFIG MODAL
════════════════════════════════════════════ */
function abrirConfig() { document.getElementById('modalBg').classList.add('show'); }
function cerrarConfig() { document.getElementById('modalBg').classList.remove('show'); }

function aplicarConfig() {
  CFG.nombreAsoc = document.getElementById('cfgNombreAsoc').value || CFG.nombreAsoc;
  CFG.ruc        = document.getElementById('cfgRuc').value || CFG.ruc;
  CFG.escala     = document.getElementById('cfgEscala').value || CFG.escala;
  CFG.obs        = document.getElementById('cfgObs').value || CFG.obs;
  cerrarConfig();
  renderTodas();
}

function cargarLogo(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    logoDataUrl = e.target.result;
    const prev = document.getElementById('logoPreviewModal');
    prev.src = logoDataUrl; prev.style.display = 'block';
  };
  reader.readAsDataURL(file);
}

/* ════════════════════════════════════════════
   HELPERS
════════════════════════════════════════════ */
function hoy() {
  const d = new Date();
  return {
    corta: d.toLocaleDateString('es-EC', {day:'2-digit',month:'2-digit',year:'numeric'}),
    larga: d.toLocaleDateString('es-EC', {day:'2-digit',month:'long',year:'numeric'}),
  };
}

function setLoading(show, txt = '', pct = null) {
  document.getElementById('loadingOv').classList.toggle('show', show);
  if (txt) document.getElementById('loadingTxt').textContent = txt;
  if (pct !== null) document.getElementById('progressFill').style.width = pct + '%';
}

/* ════════════════════════════════════════════
   EXPORTAR PDF (jsPDF — captura canvas por canvas)
════════════════════════════════════════════ */
async function exportarPDF() {
  setLoading(true, 'Preparando hojas...', 5);
  await new Promise(r => setTimeout(r, 300));

  const { jsPDF } = window.jspdf;
  const isLand = orientation === 'landscape';
  const fmt    = isLand ? [297, 210] : [210, 297];
  const pdf    = new jsPDF({ orientation: isLand ? 'landscape' : 'portrait', unit: 'mm', format: 'a4' });
  const PW = fmt[0], PH = fmt[1];
  const M  = 8;

  const fecha  = hoy();
  const sheets = document.querySelectorAll('.plano-sheet');
  const total  = sheets.length;

  for (let i = 0; i < total; i++) {
    setLoading(true, `Exportando hoja ${i+1} de ${total}...`, Math.round(5 + ((i+1)/total)*90));

    if (i > 0) pdf.addPage('a4', isLand ? 'landscape' : 'portrait');

    await new Promise(r => setTimeout(r, 50));

    // Datos del plano correspondiente (si es hoja de polígono)
    const planoIdx = i < todosLosPlanos.length ? i : -1;
    const p = planoIdx >= 0 ? todosLosPlanos[planoIdx] : null;

    // ── Fondo ──
    pdf.setFillColor(255, 254, 247);
    pdf.rect(0, 0, PW, PH, 'F');

    // ── CABECERA ──
    const hH = 20;
    pdf.setFillColor(15, 34, 64);
    pdf.rect(M, M, PW-M*2, hH, 'F');

    if (logoDataUrl) {
      try { pdf.addImage(logoDataUrl, 'PNG', M+2, M+2, 16, 16); } catch(e) {}
    }

    pdf.setTextColor(255,255,255);
    pdf.setFontSize(12); pdf.setFont('helvetica','bold');
    pdf.text(CFG.nombreAsoc, M+22, M+8);
    pdf.setFontSize(7.5); pdf.setFont('helvetica','normal');
    pdf.setTextColor(147,197,253);
    pdf.text(`PLANO DE UBICACIÓN — RUC: ${CFG.ruc} | Guayas, Ecuador | WGS84`, M+22, M+13);

    const codigo = p ? p.arch.codigo : '—';
    pdf.setTextColor(255,255,255); pdf.setFontSize(11); pdf.setFont('helvetica','bold');
    pdf.text(codigo, PW-M-4, M+8, {align:'right'});
    pdf.setFontSize(7); pdf.setFont('helvetica','normal'); pdf.setTextColor(147,197,253);
    pdf.text(fecha.corta, PW-M-4, M+13, {align:'right'});
    pdf.text('Sistema: WGS84 / UTM', PW-M-4, M+17, {align:'right'});

    if (!p) {
      // Hoja de vértices extra — imprimir tabla
      await renderHojaVerticesPDF(pdf, i, PW, PH, M, hH, fecha, codigo);
      continue;
    }

    // ── OWNER ROW ──
    const owY = M + hH + 1;
    const owH = 13;
    pdf.setFillColor(241,245,249);
    pdf.rect(M, owY, PW-M*2, owH, 'F');
    pdf.setDrawColor(15,34,64); pdf.setLineWidth(0.3);
    pdf.rect(M, owY, PW-M*2, owH);

    const zona = [SOCIO.zona, SOCIO.comunidad].filter(Boolean).join(' / ') || '—';
    const campos = [
      {l:'PROPIETARIO', v: SOCIO.nombre},
      {l:'CÉDULA / RUC', v: SOCIO.cedula},
      {l:'ZONA / COMUNIDAD', v: zona},
      {l:'CÓDIGO DE LOTE', v: p.arch.codigo},
      {l:'ÁREA TOTAL', v: p.geo.area + ' ha'},
      {l:'PERÍMETRO', v: p.geo.perim + ' km'},
    ];
    const cw = (PW-M*2) / campos.length;
    campos.forEach((f, ci) => {
      const cx = M + cw*ci;
      if (ci > 0) { pdf.setDrawColor(200,210,220); pdf.setLineWidth(0.15); pdf.line(cx, owY, cx, owY+owH); }
      pdf.setTextColor(100,116,139); pdf.setFontSize(5.5); pdf.setFont('helvetica','bold');
      pdf.text(f.l, cx+2, owY+4.5);
      pdf.setTextColor(15,34,64); pdf.setFontSize(8); pdf.setFont('helvetica','bold');
      pdf.text(f.v || '—', cx+2, owY+10);
    });

    // ── CUERPO: MAPA + TABLA ──
    const bodyY   = owY + owH + 1;
    const tableW  = 68;
    const drawW   = PW - M*2 - tableW - 2;
    const bodyH   = PH - M - bodyY - 14;

    // Canvas del mapa (combinar bgCanvas + polyCanvas)
    const bgCanvas   = document.getElementById(`mapBgCanvas_${planoIdx}`);
    const polyCanvas = document.getElementById(`polyCanvas_${planoIdx}`);
    const mapArea    = document.getElementById(`mapArea_${planoIdx}`);

    if (bgCanvas && mapArea) {
      // Crear canvas temporal combinado
      const combo = document.createElement('canvas');
      combo.width  = bgCanvas.width;
      combo.height = bgCanvas.height;
      const cctx = combo.getContext('2d');
      cctx.drawImage(bgCanvas, 0, 0);
      if (polyCanvas) cctx.drawImage(polyCanvas, 0, 0);
      try {
        const img = combo.toDataURL('image/jpeg', 0.92);
        pdf.addImage(img, 'JPEG', M, bodyY, drawW, bodyH);
      } catch(e) {
        // Fallback si CORS bloquea
        pdf.setFillColor(220, 230, 220);
        pdf.rect(M, bodyY, drawW, bodyH, 'F');
        if (polyCanvas) {
          try {
            const polyImg = polyCanvas.toDataURL('image/png');
            pdf.addImage(polyImg, 'PNG', M, bodyY, drawW, bodyH);
          } catch(e2) {}
        }
      }
    }

    pdf.setDrawColor(15,34,64); pdf.setLineWidth(0.4);
    pdf.rect(M, bodyY, drawW, bodyH);

    // Norte
    pdf.setFillColor(255,255,255);
    pdf.circle(M+drawW-8, bodyY+8, 6, 'FD');
    pdf.setDrawColor(15,34,64); pdf.setLineWidth(0.3);
    pdf.setTextColor(15,34,64); pdf.setFontSize(8); pdf.setFont('helvetica','bold');
    pdf.text('↑N', M+drawW-8, bodyY+9.5, {align:'center'});

    // Escala
    pdf.setTextColor(50,70,90); pdf.setFontSize(6.5);
    pdf.text('ESC: '+CFG.escala, M+3, bodyY+bodyH-4);
    pdf.setFillColor(15,34,64); pdf.rect(M+3, bodyY+bodyH-8, 10, 2.5, 'F');
    pdf.setFillColor(255,255,255); pdf.rect(M+13, bodyY+bodyH-8, 10, 2.5, 'F');
    pdf.setDrawColor(15,34,64); pdf.setLineWidth(0.3);
    pdf.rect(M+3, bodyY+bodyH-8, 20, 2.5);
    pdf.setTextColor(100,100,100); pdf.setFontSize(5.5);
    pdf.text('© Esri WorldImagery', M+3, bodyY+bodyH-1);

    // ── TABLA LATERAL ──
    const tX = M + drawW + 2;
    const tW = tableW;

    pdf.setFillColor(15,34,64); pdf.rect(tX, bodyY, tW, 6.5, 'F');
    pdf.setTextColor(255,255,255); pdf.setFontSize(6.5); pdf.setFont('helvetica','bold');
    pdf.text('TABLA DE VÉRTICES', tX+tW/2, bodyY+4.5, {align:'center'});

    const cws = [8, 30, 30];
    const cxs = [tX, tX+8, tX+38];
    pdf.setFillColor(29,78,216); pdf.rect(tX, bodyY+6.5, tW, 5, 'F');
    pdf.setTextColor(255,255,255); pdf.setFontSize(6);
    ['V#','LATITUD','LONGITUD'].forEach((h,ci) => pdf.text(h, cxs[ci]+cws[ci]/2, bodyY+10.2, {align:'center'}));

    const maxR = Math.floor((bodyH - 50) / 4);
    const vtxMuestra = p.coords.slice(0, maxR);
    vtxMuestra.forEach((c, ri) => {
      const ry = bodyY + 11.5 + ri*4;
      if (ri%2===0) { pdf.setFillColor(248,250,252); pdf.rect(tX, ry-0.3, tW, 4, 'F'); }
      pdf.setTextColor(15,34,64); pdf.setFontSize(5.5); pdf.setFont('courier','normal');
      pdf.text(String(ri+1), cxs[0]+4, ry+2.5, {align:'center'});
      pdf.text(c.lat.toFixed(6), cxs[1]+15, ry+2.5, {align:'center'});
      pdf.text(c.lon.toFixed(6), cxs[2]+15, ry+2.5, {align:'center'});
      pdf.setDrawColor(230,235,240); pdf.setLineWidth(0.08);
      pdf.line(tX, ry+3.8, tX+tW, ry+3.8);
    });
    if (p.coords.length > maxR) {
      const mY = bodyY + 11.5 + vtxMuestra.length*4 + 3;
      pdf.setTextColor(150,150,150); pdf.setFontSize(5.5);
      pdf.text(`... ${p.coords.length - maxR} más (ver hoja adjunta)`, tX+tW/2, mY, {align:'center'});
    }

    // Resumen box
    const sY = bodyY + bodyH - 38;
    pdf.setFillColor(15,34,64); pdf.rect(tX, sY, tW, 38, 'F');
    pdf.setTextColor(147,197,253); pdf.setFontSize(6); pdf.setFont('helvetica','bold');
    pdf.text('RESUMEN', tX+tW/2, sY+5, {align:'center'});
    [
      ['Área (ha)',    p.geo.area.toString()],
      ['Perímetro km', p.geo.perim.toString()],
      ['Lat. Centro',  p.geo.lat.toFixed(6)],
      ['Lon. Centro',  p.geo.lon.toFixed(6)],
      ['N° Vértices',  p.coords.length.toString()],
    ].forEach(([lbl,val],ri) => {
      const ry = sY + 10 + ri*5.5;
      pdf.setTextColor(148,163,184); pdf.setFontSize(5); pdf.setFont('helvetica','normal');
      pdf.text(lbl, tX+2, ry);
      pdf.setTextColor(56,189,248); pdf.setFontSize(6.5); pdf.setFont('courier','bold');
      pdf.text(val, tX+tW-2, ry+3, {align:'right'});
    });

    // Observaciones bajo tabla
    pdf.setTextColor(70,90,110); pdf.setFontSize(5.5); pdf.setFont('helvetica','normal');
    const obsLines = pdf.splitTextToSize(CFG.obs, tW-4);
    pdf.text(obsLines.slice(0,3), tX+2, sY+39);

    // ── FOOTER ──
    const fY = PH - M - 12;
    pdf.setFillColor(241,245,249); pdf.rect(M, fY, PW-M*2, 12, 'F');
    pdf.setDrawColor(15,34,64); pdf.setLineWidth(0.3); pdf.rect(M, fY, PW-M*2, 12);
    pdf.setTextColor(70,85,105); pdf.setFontSize(6.5); pdf.setFont('helvetica','normal');
    pdf.text(`Elaborado: Sistema de Gestión — ${CFG.nombreAsoc}`, M+3, fY+4);
    pdf.text(`Usuario: <?= htmlspecialchars($_SESSION['usuario']) ?> | Fecha: ${fecha.corta}`, M+3, fY+8.5);
    pdf.setFont('courier','bold'); pdf.setTextColor(15,34,64); pdf.setFontSize(10);
    pdf.text(codigo, PW-M-4, fY+5, {align:'right'});
    pdf.setFont('helvetica','normal'); pdf.setTextColor(100,116,139); pdf.setFontSize(6);
    pdf.text('Documento generado automáticamente', PW-M-4, fY+9, {align:'right'});

    // Borde exterior
    pdf.setDrawColor(15,34,64); pdf.setLineWidth(0.8);
    pdf.rect(M, M, PW-M*2, PH-M*2);
  }

  setLoading(true, 'Guardando PDF...', 98);
  await new Promise(r => setTimeout(r, 100));
  const nomArchivo = todosLosPlanos.length ? todosLosPlanos[0].arch.codigo : 'lote';
  pdf.save(`Plano_Catastral_${nomArchivo}_${fecha.corta.replace(/\//g,'-')}.pdf`);
  setLoading(false);
}

async function renderHojaVerticesPDF(pdf, pageIdx, PW, PH, M, hH, fecha, codigo) {
  // Determinar qué set de vértices corresponde
  // Las hojas extra comienzan después de todosLosPlanos.length
  // Aquí simplificamos: si es página extra, buscar en p.coords
  // La lógica de páginas extra se puede mejorar si se necesita
  const p = todosLosPlanos.find(pl => pageIdx >= todosLosPlanos.length) || todosLosPlanos[0];
  if (!p) return;

  // Título tabla
  const owY = M + hH + 1;
  pdf.setFillColor(15,34,64); pdf.rect(M, owY, PW-M*2, 8, 'F');
  pdf.setTextColor(255,255,255); pdf.setFontSize(9); pdf.setFont('helvetica','bold');
  pdf.text(`TABLA COMPLETA DE VÉRTICES — ${p.arch.codigo}`, PW/2, owY+5.5, {align:'center'});

  const bodyY = owY + 9;
  const colW  = (PW-M*2-10) / 3;

  for (let col = 0; col < 3; col++) {
    const cX = M + col*(colW+5);
    pdf.setFillColor(29,78,216); pdf.rect(cX, bodyY, colW, 5, 'F');
    pdf.setTextColor(255,255,255); pdf.setFontSize(6); pdf.setFont('helvetica','bold');
    ['V#','LATITUD','LONGITUD'].forEach((h, hi) => {
      pdf.text(h, cX + [5,20,40][hi], bodyY+3.5);
    });
    const maxRows = Math.floor((PH-M-bodyY-20) / 4);
    const startRow = col * maxRows;
    p.coords.slice(startRow, startRow+maxRows).forEach((c, ri) => {
      const ry = bodyY + 5 + ri*4;
      if (ri%2===0) { pdf.setFillColor(248,250,252); pdf.rect(cX, ry, colW, 4, 'F'); }
      pdf.setTextColor(15,34,64); pdf.setFontSize(5.5); pdf.setFont('courier','normal');
      pdf.text(String(startRow+ri+1), cX+3, ry+2.8);
      pdf.text(c.lat.toFixed(6), cX+12, ry+2.8);
      pdf.text(c.lon.toFixed(6), cX+38, ry+2.8);
    });
  }
}
</script>
</body>
</html>