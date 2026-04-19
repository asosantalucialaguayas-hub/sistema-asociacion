<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require "config/conexion.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Mini-QGIS — Conversor & Editor</title>
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/modal-message.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<?php include 'layout/modals.php'; ?>
<style>
/* ══════════════════════ RESET & BASE ══════════════════════ */
:root{
  --amber:#f59e0b;--red:#ef4444;--green:#10b981;--sky:#38bdf8;
  --gray900:#0f172a;--purple:#a78bfa;--orange:#fb923c;
  --navy:#0d1117;--border:rgba(255,255,255,.08);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--gray900);color:#e2e8f0;overflow:hidden;height:100vh}
.app{display:flex;height:100vh;overflow:hidden}

/* ══ SIDEBAR ══ */
.sidebar,nav.sidebar,aside.sidebar{
  position:fixed!important;top:0;left:0;height:100vh;overflow-y:auto;
  flex-shrink:0;z-index:99999!important;
  transform:translateX(-100%);transition:transform .3s cubic-bezier(.4,0,.2,1);
  box-shadow:4px 0 32px rgba(0,0,0,.5)
}
.sidebar.sb-open,nav.sidebar.sb-open,aside.sidebar.sb-open{transform:translateX(0)}
#sbOverlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99998;backdrop-filter:blur(2px)}
#sbOverlay.open{display:block}
#btnSidebar{
  position:fixed;top:14px;left:14px;z-index:99997;width:38px;height:38px;
  border-radius:10px;background:rgba(15,23,42,.88);border:1px solid rgba(255,255,255,.12);
  color:#94a3b8;cursor:pointer;display:flex;align-items:center;justify-content:center;
  font-size:15px;backdrop-filter:blur(8px);transition:all .18s;box-shadow:0 4px 14px rgba(0,0,0,.4)
}
#btnSidebar:hover{background:rgba(56,189,248,.2);border-color:#38bdf8;color:#38bdf8}

/* ══ WORKSPACE ══ */
.qgis-workspace{flex:1;display:flex;flex-direction:column;height:100vh;overflow:hidden;position:relative}

/* ══ TOPBAR ══ */
.map-topbar{
  display:flex;align-items:center;gap:8px;padding:7px 14px 7px 60px;
  background:rgba(15,23,42,.97);backdrop-filter:blur(12px);
  border-bottom:1px solid var(--border);z-index:1000;flex-shrink:0;flex-wrap:wrap
}
.map-title{
  font-family:'Space Mono',monospace;font-size:12px;font-weight:700;
  color:#38bdf8;letter-spacing:1px;text-transform:uppercase;
  white-space:nowrap;display:flex;align-items:center;gap:8px
}
.map-title .dot{width:8px;height:8px;border-radius:50%;background:var(--amber);animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.4)}}
.topbar-sep{width:1px;height:26px;background:rgba(255,255,255,.1);flex-shrink:0}
.tool-btn{
  display:inline-flex;align-items:center;gap:5px;padding:5px 11px;
  border-radius:7px;border:1px solid rgba(255,255,255,.12);
  background:rgba(255,255,255,.07);color:#cbd5e1;font-size:11px;font-weight:600;
  cursor:pointer;transition:all .18s;white-space:nowrap;font-family:'DM Sans',sans-serif
}
.tool-btn:hover{background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.25);color:#fff}
.tool-btn.active{background:rgba(56,189,248,.18);border-color:#38bdf8;color:#38bdf8}
.tool-btn.green{background:rgba(16,185,129,.15);border-color:#10b981;color:#10b981}
.tool-btn.green:hover{background:rgba(16,185,129,.25)}
.tool-btn.amber{background:rgba(245,158,11,.15);border-color:var(--amber);color:var(--amber)}
.tool-btn.amber:hover{background:rgba(245,158,11,.25)}
.tool-btn.red{background:rgba(239,68,68,.15);border-color:var(--red);color:#f87171}
.tool-btn.red:hover{background:rgba(239,68,68,.25)}
.tool-btn.purple{background:rgba(167,139,250,.15);border-color:var(--purple);color:var(--purple)}
.tool-btn.purple:hover{background:rgba(167,139,250,.25)}
.tool-btn.sky{background:rgba(56,189,248,.15);border-color:#38bdf8;color:#38bdf8}
.tool-btn.sky:hover{background:rgba(56,189,248,.25)}
.tool-btn.orange{background:rgba(251,146,60,.15);border-color:var(--orange);color:var(--orange)}
.tool-btn.orange:hover{background:rgba(251,146,60,.25)}

/* ══ MAIN LAYOUT ══ */
.qgis-main{flex:1;display:flex;overflow:hidden;position:relative}

/* ══ PANEL IZQUIERDO — CAPAS ══ */
.layer-panel{
  width:280px;flex-shrink:0;background:rgba(13,17,28,.97);
  border-right:1px solid var(--border);display:flex;flex-direction:column;
  overflow:hidden;transition:width .3s ease
}
.layer-panel.collapsed{width:0;border:none}
.lp-head{padding:10px 12px 8px;border-bottom:1px solid var(--border);flex-shrink:0;display:flex;align-items:center;justify-content:space-between}
.lp-head h3{font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:#94a3b8;display:flex;align-items:center;gap:7px}
.lp-head h3 i{color:#38bdf8}

.drop-zone{
  margin:10px;border:2px dashed rgba(56,189,248,.25);border-radius:10px;
  padding:18px 10px;text-align:center;cursor:pointer;transition:all .2s;
  background:rgba(56,189,248,.03);flex-shrink:0
}
.drop-zone:hover,.drop-zone.drag{border-color:#38bdf8;background:rgba(56,189,248,.08)}
.drop-zone i{font-size:24px;color:#38bdf8;display:block;margin-bottom:6px}
.drop-zone p{font-size:10px;color:#475569;margin:2px 0;line-height:1.5}
.drop-zone strong{color:#94a3b8;font-size:11px}
.dz-formats{display:flex;gap:4px;justify-content:center;flex-wrap:wrap;margin-top:6px}
.dz-fmt{background:rgba(56,189,248,.1);color:#38bdf8;font-size:9px;font-weight:700;padding:2px 6px;border-radius:4px;font-family:'Space Mono',monospace}

.layers-list{flex:1;overflow-y:auto;padding:5px}
.layers-list::-webkit-scrollbar{width:3px}
.layers-list::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:4px}
.layer-item{
  display:flex;align-items:center;gap:6px;padding:6px 8px;
  border-radius:8px;cursor:pointer;border:1px solid transparent;
  margin-bottom:3px;transition:all .15s
}
.layer-item:hover{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.08)}
.layer-item.selected{background:rgba(56,189,248,.1);border-color:rgba(56,189,248,.3)}
.layer-item.editing{background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.4)!important}
.l-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;border:2px solid rgba(255,255,255,.2)}
.l-info{flex:1;min-width:0}
.l-name{font-size:11px;font-weight:600;color:#e2e8f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.l-sub{font-size:9px;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px}
.l-acts{display:flex;gap:2px;flex-shrink:0}
.lbtn{width:20px;height:20px;border-radius:4px;border:none;background:rgba(255,255,255,.06);color:#94a3b8;cursor:pointer;font-size:9px;display:flex;align-items:center;justify-content:center;transition:all .15s}
.lbtn:hover{background:rgba(255,255,255,.15);color:#fff}
.lbtn.eb:hover{background:rgba(245,158,11,.2);color:var(--amber)}
.lbtn.zb:hover{background:rgba(56,189,248,.2);color:#38bdf8}
.lbtn.rb:hover{background:rgba(239,68,68,.2);color:#f87171}
.lbtn.pb:hover{background:rgba(167,139,250,.2);color:var(--purple)}
.lbtn.sb:hover{background:rgba(16,185,129,.2);color:#10b981}
.tswitch{width:22px;height:13px;background:#1e293b;border-radius:999px;cursor:pointer;transition:background .2s;position:relative;flex-shrink:0;border:1px solid rgba(255,255,255,.1)}
.tswitch.on{background:#10b981;border-color:#10b981}
.tswitch::after{content:'';position:absolute;top:1px;left:1px;width:9px;height:9px;border-radius:50%;background:#fff;transition:transform .2s}
.tswitch.on::after{transform:translateX(9px)}

/* ══ MAPA ══ */
.map-area{flex:1;position:relative;overflow:hidden}
#mapaQgis{width:100%;height:100%}

/* ══ PANEL DERECHO — ATRIBUTOS ══ */
.attr-panel{
  width:300px;flex-shrink:0;background:rgba(13,17,28,.97);
  border-left:1px solid var(--border);display:flex;flex-direction:column;
  overflow:hidden;transition:width .3s ease
}
.attr-panel.collapsed{width:0;border:none}
.ap-head{padding:10px 12px 8px;border-bottom:1px solid var(--border);flex-shrink:0;display:flex;align-items:center;justify-content:space-between}
.ap-head h3{font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:#94a3b8;display:flex;align-items:center;gap:7px}
.ap-head h3 i{color:var(--amber)}
.ap-body{flex:1;overflow-y:auto;padding:10px}
.ap-body::-webkit-scrollbar{width:3px}
.ap-body::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:4px}
.ap-placeholder{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#334155;font-size:12px;gap:10px;text-align:center;padding:20px}
.ap-placeholder i{font-size:28px;color:#1e293b}
.afl{display:block;font-size:9.5px;font-weight:700;color:#475569;letter-spacing:.6px;text-transform:uppercase;margin-bottom:4px;margin-top:10px}
.afl:first-child{margin-top:0}
.afi{width:100%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:7px;padding:6px 9px;color:#e2e8f0;font-size:12px;font-family:'DM Sans',sans-serif;outline:none;transition:border-color .15s}
.afi:focus{border-color:#38bdf8;background:rgba(56,189,248,.04)}
.afi[readonly]{opacity:.5;cursor:not-allowed}
.sdiv{display:flex;align-items:center;gap:8px;margin:13px 0 9px;font-size:9.5px;font-weight:700;color:#334155;letter-spacing:.8px;text-transform:uppercase}
.sdiv::before,.sdiv::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.07)}
.at-wrap{background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.07);border-radius:8px;overflow:hidden;margin-bottom:8px}
.at-head-mini{display:grid;grid-template-columns:1fr 1fr 22px;background:rgba(56,189,248,.08);border-bottom:1px solid rgba(255,255,255,.07)}
.at-head-mini span{font-size:9px;font-weight:700;color:#38bdf8;letter-spacing:.5px;text-transform:uppercase;padding:5px 7px}
.at-row-mini{display:grid;grid-template-columns:1fr 1fr 22px;border-bottom:1px solid rgba(255,255,255,.04)}
.at-row-mini:last-child{border-bottom:none}
.at-row-mini:nth-child(even){background:rgba(255,255,255,.02)}
.at-cell-mini{padding:3px 4px;display:flex;align-items:center}
.at-cell-mini input{width:100%;background:transparent;border:none;border-radius:4px;padding:3px 4px;color:#e2e8f0;font-size:11px;font-family:'DM Sans',sans-serif;outline:none}
.at-cell-mini input:focus{background:rgba(56,189,248,.07)}
.at-del-mini{width:18px;height:18px;border-radius:3px;border:none;background:transparent;color:#334155;cursor:pointer;font-size:9px;display:flex;align-items:center;justify-content:center;transition:all .15s}
.at-del-mini:hover{background:rgba(239,68,68,.15);color:#f87171}
.at-add-mini{display:flex;align-items:center;justify-content:center;gap:5px;width:100%;padding:6px;background:rgba(56,189,248,.04);border:1px dashed rgba(56,189,248,.18);border-radius:0 0 7px 7px;color:#38bdf8;font-size:10px;font-weight:600;cursor:pointer;transition:all .15s;font-family:'DM Sans',sans-serif}
.at-add-mini:hover{background:rgba(56,189,248,.1)}
.ap-actions{padding:10px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:6px;flex-shrink:0}
.ap-btn{display:flex;align-items:center;justify-content:center;gap:6px;padding:8px;border-radius:8px;border:1px solid;font-size:11px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s;width:100%}
.ap-btn.kml{background:rgba(16,185,129,.12);border-color:#10b981;color:#10b981}
.ap-btn.kml:hover{background:rgba(16,185,129,.24)}
.ap-btn.geo{background:rgba(56,189,248,.12);border-color:#38bdf8;color:#38bdf8}
.ap-btn.geo:hover{background:rgba(56,189,248,.24)}
.ap-btn.bd{background:rgba(167,139,250,.12);border-color:var(--purple);color:var(--purple)}
.ap-btn.bd:hover{background:rgba(167,139,250,.24)}
.ap-btn.amber{background:rgba(245,158,11,.12);border-color:var(--amber);color:var(--amber)}
.ap-btn.amber:hover{background:rgba(245,158,11,.24)}
.ap-btn.orange{background:rgba(251,146,60,.12);border-color:var(--orange);color:var(--orange)}
.ap-btn.orange:hover{background:rgba(251,146,60,.24)}
.ap-btn.red{background:rgba(239,68,68,.12);border-color:var(--red);color:#f87171}
.ap-btn.red:hover{background:rgba(239,68,68,.24)}

/* ══ BARRA EDITOR POLÍGONO ══ */
#polyEditBar{
  display:none;position:absolute;bottom:18px;left:50%;transform:translateX(-50%);z-index:1100;
  background:rgba(10,14,26,.97);border:1px solid rgba(245,158,11,.4);border-radius:14px;
  padding:10px 16px;box-shadow:0 8px 40px rgba(0,0,0,.7);backdrop-filter:blur(16px);
  flex-direction:column;align-items:center;gap:8px;min-width:480px
}
#polyEditBar.show{display:flex}
.peb-title{font-family:'Space Mono',monospace;font-size:11px;color:var(--amber);font-weight:700;letter-spacing:.5px;display:flex;align-items:center;gap:8px}
.peb-tools{display:flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:center}
.peb-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:8px;border:1px solid;font-size:11px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s;white-space:nowrap}
.peb-btn.mode-btn{background:rgba(255,255,255,.07);border-color:rgba(255,255,255,.15);color:#cbd5e1}
.peb-btn.mode-btn:hover,.peb-btn.mode-btn.on{background:rgba(245,158,11,.18);border-color:var(--amber);color:var(--amber)}
.peb-btn.save-kml{background:rgba(16,185,129,.15);border-color:#10b981;color:#10b981}
.peb-btn.save-kml:hover{background:rgba(16,185,129,.28)}
.peb-btn.cancel{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.12);color:#94a3b8}
.peb-btn.cancel:hover{background:rgba(255,255,255,.12);color:#fff}
.peb-area{font-family:'Space Mono',monospace;font-size:12px;color:#10b981;font-weight:700;padding:4px 12px;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);border-radius:6px;min-width:140px;text-align:center}
.peb-sep{width:1px;height:28px;background:rgba(255,255,255,.1)}
.peb-hint{font-size:10px;color:#475569;font-family:'Space Mono',monospace;text-align:center}
.vtx-tip{background:#0f172a!important;border:1px solid rgba(56,189,248,.3)!important;color:#e2e8f0!important;font-family:'Space Mono',monospace!important;font-size:10px!important;padding:2px 7px!important;border-radius:5px!important;box-shadow:none!important;white-space:nowrap}
.vtx-tip::before{display:none!important}

/* ══ STATUS BAR ══ */
.stbar{position:absolute;bottom:12px;right:12px;display:flex;gap:6px;z-index:900;flex-direction:column;align-items:flex-end}
.stchip{background:rgba(13,17,28,.92);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.1);border-radius:7px;padding:4px 10px;font-size:10.5px;color:#94a3b8;font-family:'Space Mono',monospace}
.stchip b{color:#38bdf8}

/* ══ POPUP MAPA ══ */
.leaflet-popup-content-wrapper{background:#0d1117!important;border:1px solid rgba(56,189,248,.25)!important;border-radius:12px!important;box-shadow:0 16px 48px rgba(0,0,0,.7)!important;color:#e2e8f0!important;padding:0!important;overflow:hidden!important}
.leaflet-popup-tip{background:#0d1117!important}
.leaflet-popup-close-button{color:#475569!important;font-size:20px!important;top:8px!important;right:10px!important;z-index:10!important}
.leaflet-popup-close-button:hover{color:#fff!important}
.leaflet-popup-content{margin:0!important;font-family:'DM Sans',sans-serif!important;min-width:260px;max-width:340px}
.pu-wrap{overflow:hidden}
.pu-head{background:linear-gradient(135deg,rgba(31,58,95,.9),rgba(13,148,136,.3));padding:10px 32px 8px 12px;border-bottom:1px solid rgba(255,255,255,.08)}
.pu-title{font-weight:700;font-size:13px;color:#38bdf8;line-height:1.3}
.pu-sub{font-size:10px;color:#475569;margin-top:2px;font-family:'Space Mono',monospace}
.pu-body{padding:8px 11px;max-height:200px;overflow-y:auto}
.pu-body::-webkit-scrollbar{width:3px}
.pu-body::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1)}
.pu-table{width:100%;border-collapse:collapse;font-size:11px}
.pu-table tr{border-bottom:1px solid rgba(255,255,255,.05)}
.pu-table tr:last-child{border-bottom:none}
.pu-table td{padding:3px 5px;vertical-align:top}
.pu-table td:first-child{color:#64748b;font-weight:700;text-transform:uppercase;font-size:9.5px;white-space:nowrap;width:36%}
.pu-table td:last-child{color:#cbd5e1;word-break:break-word}
.pu-foot{display:flex;gap:5px;padding:7px 11px;background:rgba(0,0,0,.25);border-top:1px solid rgba(255,255,255,.06);flex-wrap:wrap}
.pu-btn{display:inline-flex;align-items:center;gap:4px;padding:4px 9px;border-radius:6px;font-size:10px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;border:1px solid;transition:all .15s}
.pu-btn-edit{background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.35);color:var(--amber)}
.pu-btn-edit:hover{background:rgba(245,158,11,.24)}
.pu-btn-poly{background:rgba(167,139,250,.12);border-color:rgba(167,139,250,.35);color:var(--purple)}
.pu-btn-poly:hover{background:rgba(167,139,250,.24)}
.pu-btn-zoom{background:rgba(56,189,248,.1);border-color:rgba(56,189,248,.3);color:#38bdf8}
.pu-btn-zoom:hover{background:rgba(56,189,248,.2)}
.pu-btn-split{background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#10b981}
.pu-btn-split:hover{background:rgba(16,185,129,.2)}

/* ══ MODALES GENERICOS ══ */
.bd-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:99999;align-items:center;justify-content:center;backdrop-filter:blur(6px)}
.bd-ov.open{display:flex}
.bd-box{background:#0a0e1a;border:1px solid rgba(255,255,255,.1);border-radius:16px;width:95%;max-width:580px;max-height:90vh;overflow:hidden;box-shadow:0 40px 100px rgba(0,0,0,.7);display:flex;flex-direction:column}
.bd-box.wide{max-width:780px}
.bd-head{padding:14px 20px 12px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.bd-head h2{font-size:14px;font-weight:700;color:#f1f5f9;display:flex;align-items:center;gap:9px}
.bd-head h2 i{color:var(--purple)}
.bd-body{flex:1;overflow-y:auto;padding:16px 20px}
.bd-foot{padding:12px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;flex-shrink:0;align-items:center}
.bd-fl{display:block;font-size:10px;font-weight:700;color:#64748b;letter-spacing:.6px;text-transform:uppercase;margin-bottom:5px}
.bd-fi{width:100%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:7px 11px;color:#e2e8f0;font-size:13px;font-family:'DM Sans',sans-serif;outline:none;transition:border-color .15s}
.bd-fi:focus{border-color:#38bdf8}
.bd-fi option{background:#1e293b}
.bd-fr{margin-bottom:12px}
.bd-fg{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px}
.bd-info{background:rgba(56,189,248,.06);border:1px solid rgba(56,189,248,.15);border-radius:8px;padding:10px 12px;margin-bottom:12px;font-size:11px;color:#94a3b8;line-height:1.6}
.bd-info b{color:#38bdf8}
.bd-codes{display:flex;flex-wrap:wrap;gap:5px;margin-top:6px;max-height:90px;overflow-y:auto}
.bd-code-chip{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);color:#10b981;font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;font-family:'Space Mono',monospace;cursor:pointer;transition:all .15s}
.bd-code-chip:hover{background:rgba(16,185,129,.25)}
.btn-cancel{padding:8px 16px;border-radius:8px;border:1px solid rgba(255,255,255,.1);background:transparent;color:#94a3b8;cursor:pointer;font-size:13px;font-family:'DM Sans',sans-serif;font-weight:600}
.btn-cancel:hover{background:rgba(255,255,255,.06);color:#e2e8f0}
.btn-save-bd{padding:8px 22px;border-radius:8px;border:none;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;cursor:pointer;font-size:13px;font-family:'DM Sans',sans-serif;font-weight:700;box-shadow:0 4px 14px rgba(124,58,237,.35);display:flex;align-items:center;gap:7px}
.btn-save-bd:hover{opacity:.88}
.btn-save-bd:disabled{opacity:.45;cursor:not-allowed}
.sind{font-size:11px;color:#94a3b8;font-family:'Space Mono',monospace;margin-right:auto}
.sind.ok{color:#10b981}.sind.err{color:#f87171}

/* ══ SEPARADOR DE POLÍGONOS ══ */
.split-grid{display:grid;grid-template-columns:220px 1fr;gap:0;height:100%;min-height:340px}
.split-list{border-right:1px solid var(--border);overflow-y:auto;padding:6px}
.split-list::-webkit-scrollbar{width:3px}
.split-list::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1)}
.split-item{display:flex;align-items:center;gap:6px;padding:6px 8px;border-radius:7px;margin-bottom:3px;border:1px solid transparent;transition:all .15s;cursor:pointer}
.split-item:hover{background:rgba(255,255,255,.04)}
.split-item.sp-sel{background:rgba(56,189,248,.08);border-color:rgba(56,189,248,.25)}
.split-chk{width:14px;height:14px;border-radius:3px;border:1px solid rgba(255,255,255,.15);background:transparent;display:flex;align-items:center;justify-content:center;font-size:8px;color:transparent;cursor:pointer;flex-shrink:0;transition:all .15s}
.split-chk.on{background:#10b981;border-color:#10b981;color:#fff}
.split-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
.split-info{flex:1;min-width:0}
.split-name{font-size:10.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.split-area{font-size:9px;color:#475569;font-family:'Space Mono',monospace;margin-top:1px}
.split-detail{padding:12px;overflow-y:auto;display:flex;flex-direction:column;gap:8px}
.split-stat{background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px;padding:10px 12px}
.split-stat-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#475569;margin-bottom:3px}
.split-stat-val{font-family:'Space Mono',monospace;font-size:14px;font-weight:700;color:#10b981}
.split-stat-val.sky{color:#38bdf8}
.split-rename{width:100%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:7px;padding:5px 9px;color:#e2e8f0;font-size:12px;font-family:'DM Sans',sans-serif;outline:none;transition:border-color .15s;margin-top:4px}
.split-rename:focus{border-color:#38bdf8}
.sel-all-row{display:flex;align-items:center;gap:6px;padding:5px 8px;border-bottom:1px solid var(--border);font-size:10px;color:#475569;flex-shrink:0}
.sel-all-row label{cursor:pointer;display:flex;align-items:center;gap:5px}
.split-export-row{display:flex;gap:5px;flex-wrap:wrap}
.sp-ebtn{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border-radius:6px;border:1px solid;font-size:10px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s;white-space:nowrap}
.sp-ebtn.kml{background:rgba(16,185,129,.1);border-color:#10b981;color:#10b981}
.sp-ebtn.kml:hover{background:rgba(16,185,129,.22)}
.sp-ebtn.geo{background:rgba(56,189,248,.1);border-color:#38bdf8;color:#38bdf8}
.sp-ebtn.geo:hover{background:rgba(56,189,248,.22)}
.sp-ebtn.all{background:rgba(245,158,11,.1);border-color:var(--amber);color:var(--amber)}
.sp-ebtn.all:hover{background:rgba(245,158,11,.22)}

/* ══ HERRAMIENTA MEDICIÓN ══ */
#measureBar{
  display:none;position:absolute;top:14px;left:50%;transform:translateX(-50%);z-index:1100;
  background:rgba(10,14,26,.97);border:1px solid rgba(56,189,248,.35);border-radius:12px;
  padding:8px 14px;box-shadow:0 6px 28px rgba(0,0,0,.6);backdrop-filter:blur(14px);
  flex-direction:row;align-items:center;gap:10px
}
#measureBar.show{display:flex}
.mb-stat{font-family:'Space Mono',monospace;font-size:12px;color:#38bdf8;font-weight:700;padding:3px 10px;background:rgba(56,189,248,.1);border:1px solid rgba(56,189,248,.2);border-radius:6px;min-width:120px;text-align:center}
.mb-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border-radius:7px;border:1px solid;font-size:10px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s}
.mb-btn.active{background:rgba(56,189,248,.18);border-color:#38bdf8;color:#38bdf8}
.mb-btn.clear{background:rgba(239,68,68,.1);border-color:var(--red);color:#f87171}
.mb-btn.clear:hover{background:rgba(239,68,68,.22)}

/* ══ HERRAMIENTA COLOR ══ */
.color-swatches{display:flex;gap:5px;flex-wrap:wrap;margin-top:6px}
.cswatch{width:22px;height:22px;border-radius:5px;cursor:pointer;border:2px solid transparent;transition:all .15s;flex-shrink:0}
.cswatch:hover,.cswatch.sel{border-color:#fff;transform:scale(1.15)}

/* ══ HERRAMIENTA BUSCAR COORDS ══ */
.coord-result{background:rgba(56,189,248,.05);border:1px solid rgba(56,189,248,.15);border-radius:8px;padding:8px 12px;font-size:11px;color:#94a3b8;line-height:1.7;margin-top:8px}
.coord-result b{color:#38bdf8}

/* ══ LOADING OVERLAY ══ */
.lov{position:absolute;inset:0;background:rgba(10,14,26,.93);backdrop-filter:blur(10px);z-index:9999;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;transition:opacity .5s}
.lov.done{opacity:0;pointer-events:none}
.lbar{width:260px;height:5px;background:rgba(255,255,255,.1);border-radius:4px;overflow:hidden}
.lbar-inner{height:100%;background:linear-gradient(90deg,#38bdf8,#10b981);border-radius:4px;width:0%;transition:width .3s ease}
.ltxt{font-family:'Space Mono',monospace;font-size:11px;color:#94a3b8;letter-spacing:.5px}

/* ══ EMPTY STATE ══ */
.empty-map{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;pointer-events:none;z-index:2}
.empty-map.hidden{display:none}
.empty-card{background:rgba(13,17,28,.92);backdrop-filter:blur(12px);border:1px solid var(--border);border-radius:16px;padding:32px 40px;text-align:center;pointer-events:auto}
.empty-card i{font-size:40px;color:#1e293b;display:block;margin-bottom:12px}
.empty-card h3{font-size:14px;font-weight:700;color:#475569;margin-bottom:6px}
.empty-card p{font-size:12px;color:#334155;line-height:1.6;max-width:280px}
.empty-formats{display:flex;gap:6px;justify-content:center;flex-wrap:wrap;margin-top:12px}
.ef{background:rgba(56,189,248,.08);border:1px solid rgba(56,189,248,.2);color:#38bdf8;font-size:10px;font-weight:700;padding:3px 10px;border-radius:5px;font-family:'Space Mono',monospace}

/* ══ TOAST ══ */
#toast-q{position:fixed;bottom:18px;left:50%;transform:translateX(-50%) translateY(80px);background:#1e293b;border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:10px 22px;font-size:13px;font-weight:600;color:#fff;z-index:999999;transition:transform .3s,opacity .3s;opacity:0;white-space:nowrap;box-shadow:0 8px 32px rgba(0,0,0,.4);pointer-events:none}
#toast-q.show{transform:translateX(-50%) translateY(0);opacity:1}

/* CSV mapper */
.csv-map-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:10px 0}
.csv-map-grid select{width:100%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:7px;padding:6px 9px;color:#e2e8f0;font-size:12px;font-family:'DM Sans',sans-serif;outline:none}
.csv-map-grid select:focus{border-color:#38bdf8}
.csv-map-grid select option{background:#1e293b}

/* ══ TABLA ATRIBUTOS GRANDE ══ */
.tabla-atrib{width:100%;border-collapse:collapse;font-size:11px}
.tabla-atrib th{background:rgba(56,189,248,.1);color:#38bdf8;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:6px 8px;text-align:left;border-bottom:1px solid rgba(255,255,255,.08)}
.tabla-atrib td{padding:5px 8px;border-bottom:1px solid rgba(255,255,255,.04);color:#cbd5e1;vertical-align:middle}
.tabla-atrib tr:nth-child(even) td{background:rgba(255,255,255,.02)}
.tabla-atrib tr:hover td{background:rgba(56,189,248,.04)}
.tabla-atrib td input{width:100%;background:transparent;border:none;border-radius:4px;padding:2px 4px;color:#e2e8f0;font-size:11px;font-family:'DM Sans',sans-serif;outline:none}
.tabla-atrib td input:focus{background:rgba(56,189,248,.07)}
</style>
</head>
<body>
<script src="layout/modal-message.js"></script>
<div class="app">
  <div id="sbOverlay" onclick="cerrarSidebar()"></div>
  <button id="btnSidebar" onclick="abrirSidebar()"><i class="fa fa-bars"></i></button>
  <?php include __DIR__ . "/layout/sidebar.php"; ?>

  <div class="qgis-workspace">
    <!-- ══ TOPBAR ══ -->
    <div class="map-topbar">
      <div class="map-title">
        <span class="dot"></span>
        <i class="fa fa-map"></i> MINI-QGIS — CONVERSOR & EDITOR
      </div>
      <div class="topbar-sep"></div>
      <button class="tool-btn active" id="btnToggleCapas" onclick="togglePanel('layer')">
        <i class="fa fa-layer-group"></i> Capas
      </button>
      <button class="tool-btn active" id="btnToggleAttr" onclick="togglePanel('attr')">
        <i class="fa fa-table"></i> Atributos
      </button>
      <div class="topbar-sep"></div>
      <button class="tool-btn" onclick="ciclarCapa()">
        <i class="fa fa-satellite"></i> <span id="lblCapa">Satélite</span>
      </button>
      <button class="tool-btn" onclick="mapa&&mapa.zoomIn()"><i class="fa fa-plus"></i></button>
      <button class="tool-btn" onclick="mapa&&mapa.zoomOut()"><i class="fa fa-minus"></i></button>
      <button class="tool-btn" onclick="centrarTodo()"><i class="fa fa-crosshairs"></i> Centrar</button>
      <div class="topbar-sep"></div>
      <!-- NUEVAS HERRAMIENTAS -->
      <button class="tool-btn orange" id="btnMedicion" onclick="toggleMedicion()">
        <i class="fa fa-ruler"></i> Medir
      </button>
      <button class="tool-btn green" onclick="abrirSeparador()">
        <i class="fa fa-scissors"></i> Separar
      </button>
      <button class="tool-btn purple" onclick="abrirBuscarCoordenadas()">
        <i class="fa fa-search-location"></i> Ir a coords
      </button>
      <div class="topbar-sep"></div>
      <button class="tool-btn amber" onclick="document.getElementById('fileInputHidden').click()">
        <i class="fa fa-folder-open"></i> Abrir archivo
      </button>
      <button class="tool-btn red" onclick="limpiarTodo()">
        <i class="fa fa-trash"></i> Limpiar todo
      </button>
      <input type="file" id="fileInputHidden" accept=".kml,.kmz,.geojson,.json,.csv,.shp,.dxf" multiple style="display:none" onchange="onFilesSelected(this.files)">
    </div>

    <!-- ══ MAIN ══ -->
    <div class="qgis-main">

      <!-- Panel Capas -->
      <div class="layer-panel" id="layerPanel">
        <div class="lp-head">
          <h3><i class="fa fa-layer-group"></i> Capas cargadas</h3>
          <span id="badgeCapas" style="background:rgba(56,189,248,.2);color:#38bdf8;font-size:9px;font-weight:800;padding:2px 8px;border-radius:999px;font-family:'Space Mono',monospace;">0</span>
        </div>
        <div class="drop-zone" id="dropZone"
             onclick="document.getElementById('fileInputHidden').click()"
             ondragover="dzOver(event)" ondragleave="dzLeave(event)" ondrop="dzDrop(event)">
          <i class="fa fa-cloud-arrow-up"></i>
          <strong>Arrastra archivos aquí</strong>
          <p>o haz clic para seleccionar</p>
          <div class="dz-formats">
            <span class="dz-fmt">KML</span><span class="dz-fmt">KMZ</span>
            <span class="dz-fmt">GeoJSON</span><span class="dz-fmt">CSV</span>
            <span class="dz-fmt">SHP</span><span class="dz-fmt">DXF</span>
          </div>
        </div>
        <div class="layers-list" id="listaCapas">
          <div style="text-align:center;padding:16px;color:#334155;font-size:11px;">Sin capas cargadas</div>
        </div>
      </div>

      <!-- Mapa -->
      <div class="map-area">
        <div id="mapaQgis"></div>

        <!-- Empty state -->
        <div class="empty-map" id="emptyMap">
          <div class="empty-card">
            <i class="fa fa-map"></i>
            <h3>Mini-QGIS listo</h3>
            <p>Carga un archivo geoespacial para comenzar a editar y convertir</p>
            <div class="empty-formats">
              <span class="ef">KML</span><span class="ef">KMZ</span><span class="ef">GeoJSON</span>
              <span class="ef">CSV</span><span class="ef">SHP</span><span class="ef">DXF</span>
            </div>
          </div>
        </div>

        <!-- Barra editor polígono -->
        <div id="polyEditBar">
          <div class="peb-title">
            <i class="fa fa-vector-square" style="color:var(--amber)"></i>
            EDITOR DE VÉRTICES —
            <span id="pebNombre" style="color:#fff;font-size:10px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
          </div>
          <div class="peb-tools">
            <button class="peb-btn mode-btn on" id="btnModoVertices" onclick="setModoEdit('vertices')">
              <i class="fa fa-circle-dot"></i> Editar vértices
            </button>
            <button class="peb-btn mode-btn" id="btnModoEliminar" onclick="setModoEdit('eliminar')">
              <i class="fa fa-trash-can"></i> Eliminar polígono
            </button>
            <div class="peb-sep"></div>
            <div class="peb-area" id="pebArea"><i class="fa fa-ruler-combined"></i> — ha</div>
          </div>
          <div class="peb-hint" id="pebHint">🟡 Arrastra vértice → mover &nbsp;|&nbsp; 🟢 Clic punto verde → agregar &nbsp;|&nbsp; Clic derecho → eliminar</div>
          <div class="peb-tools">
            <button class="peb-btn save-kml" onclick="finalizarEdicion()">
              <i class="fa fa-check"></i> Aplicar cambios
            </button>
            <button class="peb-btn cancel" onclick="cancelarEdicion()">
              <i class="fa fa-times"></i> Cancelar
            </button>
          </div>
        </div>

        <!-- Barra medición -->
        <div id="measureBar">
          <span style="font-family:'Space Mono',monospace;font-size:10px;color:var(--orange);font-weight:700;"><i class="fa fa-ruler"></i> MEDICIÓN</span>
          <div class="mb-stat" id="mbDist">0.000 km</div>
          <div class="mb-stat" id="mbArea" style="color:#10b981;border-color:rgba(16,185,129,.25);background:rgba(16,185,129,.1);">0.000 ha</div>
          <span style="font-size:10px;color:#475569;font-family:'Space Mono',monospace;">Clic → punto &nbsp;|&nbsp; Doble clic → cerrar</span>
          <button class="mb-btn clear" onclick="limpiarMedicion()"><i class="fa fa-times"></i> Limpiar</button>
        </div>

        <!-- Status bar -->
        <div class="stbar">
          <div class="stchip">Capas: <b id="stCapas">0</b></div>
          <div class="stchip" id="stZoom">Zoom: <b>—</b></div>
          <div class="stchip" id="stCoords" style="display:none">—</div>
        </div>

        <!-- Loading overlay -->
        <div class="lov done" id="loadingOverlay">
          <i class="fa fa-map" style="font-size:32px;color:#38bdf8;margin-bottom:4px;"></i>
          <div class="ltxt" id="loadingText">Procesando...</div>
          <div class="lbar"><div class="lbar-inner" id="loadingBar"></div></div>
        </div>
      </div>

      <!-- Panel Atributos -->
      <div class="attr-panel" id="attrPanel">
        <div class="ap-head">
          <h3><i class="fa fa-table"></i> Atributos</h3>
          <span id="apLayerName" style="font-size:10px;color:#475569;font-family:'Space Mono',monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:120px;"></span>
        </div>
        <div class="ap-body" id="apBody">
          <div class="ap-placeholder">
            <i class="fa fa-hand-pointer"></i>
            <span>Selecciona una capa o haz clic en una geometría para ver y editar sus atributos</span>
          </div>
        </div>
        <div class="ap-actions" id="apActions" style="display:none">
          <button class="ap-btn amber" onclick="iniciarEdicionPoligono()">
            <i class="fa fa-vector-square"></i> Editar vértices
          </button>
          <!-- ▶ NUEVA: Separar polígonos desde panel -->
          <button class="ap-btn orange" onclick="abrirSeparadorCapa(capaSeleccionada)">
            <i class="fa fa-scissors"></i> Separar polígonos
          </button>
          <button class="ap-btn kml" onclick="exportarCapaKML()">
            <i class="fa fa-download"></i> Exportar KML
          </button>
          <button class="ap-btn geo" onclick="exportarCapaGeoJSON()">
            <i class="fa fa-download"></i> Exportar GeoJSON
          </button>
          <button class="ap-btn bd" onclick="abrirGuardarBD()">
            <i class="fa fa-database"></i> Guardar en BD
          </button>
          <!-- ▶ NUEVA: Cambiar color capa -->
          <button class="ap-btn" style="background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.12);color:#94a3b8;" onclick="abrirCambiarColor()">
            <i class="fa fa-palette"></i> Cambiar color
          </button>
        </div>
      </div>

    </div><!-- /qgis-main -->
  </div><!-- /qgis-workspace -->
</div><!-- /app -->

<!-- ══════════════════════════════════════════════════════
     MODAL SEPARADOR DE POLÍGONOS
══════════════════════════════════════════════════════ -->
<div class="bd-ov" id="splitOv">
  <div class="bd-box wide" style="max-height:85vh">
    <div class="bd-head">
      <h2><i class="fa fa-scissors" style="color:#10b981"></i> Separador de Polígonos</h2>
      <button onclick="cerrarSeparador()" style="width:28px;height:28px;border-radius:50%;border:none;background:rgba(255,255,255,.07);color:#94a3b8;cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;"><i class="fa fa-times"></i></button>
    </div>
    <div class="bd-body" style="padding:0">
      <div class="bd-info" style="margin:12px 16px 0;border-color:rgba(16,185,129,.2);background:rgba(16,185,129,.04)">
        <i class="fa fa-circle-info" style="color:#10b981"></i>
        Detecta todos los polígonos del archivo (incluyendo MultiGeometry) y te permite exportarlos <b>uno por uno</b> con su área real calculada correctamente.
      </div>
      <!-- Drop zona para archivo en modal -->
      <div id="splitDzWrap" style="padding:10px 16px">
        <div class="drop-zone" id="splitDz" style="margin:0;padding:14px 10px;"
             onclick="document.getElementById('splitFileInput').click()"
             ondragover="splitDzOver(event)" ondragleave="splitDzLeave(event)" ondrop="splitDzDrop(event)">
          <i class="fa fa-cloud-arrow-up"></i>
          <strong>Arrastra KML / KMZ / GeoJSON aquí</strong>
          <p>o haz clic para seleccionar</p>
        </div>
        <input type="file" id="splitFileInput" accept=".kml,.kmz,.geojson,.json" style="display:none" onchange="splitOnFile(this.files)">
      </div>
      <div class="split-grid" id="splitGrid" style="display:none;height:360px;">
        <div style="display:flex;flex-direction:column;overflow:hidden;border-right:1px solid var(--border)">
          <div class="sel-all-row">
            <label>
              <div class="split-chk" id="splitChkAll" onclick="splitToggleAll(this)"><i class="fa fa-check"></i></div>
              Todos
            </label>
            <span style="margin-left:auto;font-family:'Space Mono',monospace;font-size:9px;" id="splitSelCount">0 / 0</span>
          </div>
          <div class="split-list" id="splitList"></div>
        </div>
        <div class="split-detail" id="splitDetail">
          <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#334155;gap:8px;font-size:11px;">
            <i class="fa fa-hand-pointer" style="font-size:24px;color:#1e293b"></i>
            <span>Selecciona un polígono</span>
          </div>
        </div>
      </div>
    </div>
    <div class="bd-foot" id="splitFoot" style="display:none">
      <div style="margin-right:auto;display:flex;gap:5px;flex-wrap:wrap">
        <button class="sp-ebtn kml" onclick="splitExportarSelKML()"><i class="fa fa-download"></i> KML seleccionados</button>
        <button class="sp-ebtn geo" onclick="splitExportarSelGeoJSON()"><i class="fa fa-download"></i> GeoJSON seleccionados</button>
        <button class="sp-ebtn all" onclick="splitExportarTodosKML()"><i class="fa fa-layer-group"></i> Exportar TODOS (KML)</button>
      </div>
      <button class="btn-cancel" onclick="cerrarSeparador()">Cerrar</button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL IR A COORDENADAS
══════════════════════════════════════════════════════ -->
<div class="bd-ov" id="coordsOv">
  <div class="bd-box" style="max-width:420px">
    <div class="bd-head">
      <h2><i class="fa fa-location-crosshairs" style="color:#38bdf8"></i> Ir a Coordenadas</h2>
      <button onclick="document.getElementById('coordsOv').classList.remove('open')" style="width:28px;height:28px;border-radius:50%;border:none;background:rgba(255,255,255,.07);color:#94a3b8;cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;"><i class="fa fa-times"></i></button>
    </div>
    <div class="bd-body">
      <div class="bd-fr">
        <label class="bd-fl">Latitud</label>
        <input type="number" step="any" class="bd-fi" id="gotoLat" placeholder="-1.831667" style="font-family:'Space Mono',monospace">
      </div>
      <div class="bd-fr">
        <label class="bd-fl">Longitud</label>
        <input type="number" step="any" class="bd-fi" id="gotoLng" placeholder="-79.968889" style="font-family:'Space Mono',monospace">
      </div>
      <div class="bd-fr">
        <label class="bd-fl">Zoom</label>
        <input type="number" class="bd-fi" id="gotoZoom" value="16" min="1" max="22">
      </div>
      <div class="bd-fr">
        <label class="bd-fl">— o pega coordenadas en formato libre —</label>
        <input type="text" class="bd-fi" id="gotoFree" placeholder="-1.831667, -79.968889 | DMS: 1°49'54&quot;S 79°58'8&quot;W" oninput="parsearCoordLibre(this.value)">
      </div>
      <div id="coordParseResult" class="coord-result" style="display:none"></div>
    </div>
    <div class="bd-foot">
      <button class="btn-cancel" onclick="document.getElementById('coordsOv').classList.remove('open')">Cancelar</button>
      <button class="btn-save-bd" onclick="irACoordenadas()" style="background:linear-gradient(135deg,#0d9488,#0891b2)">
        <i class="fa fa-location-dot"></i> Ir al punto
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL CAMBIAR COLOR
══════════════════════════════════════════════════════ -->
<div class="bd-ov" id="colorOv">
  <div class="bd-box" style="max-width:340px">
    <div class="bd-head">
      <h2><i class="fa fa-palette" style="color:var(--purple)"></i> Cambiar Color de Capa</h2>
      <button onclick="document.getElementById('colorOv').classList.remove('open')" style="width:28px;height:28px;border-radius:50%;border:none;background:rgba(255,255,255,.07);color:#94a3b8;cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;"><i class="fa fa-times"></i></button>
    </div>
    <div class="bd-body">
      <div class="bd-info" style="margin-bottom:10px">Selecciona un color predefinido o usa el selector personalizado.</div>
      <label class="bd-fl">Colores predefinidos</label>
      <div class="color-swatches" id="colorSwatches"></div>
      <div style="height:10px"></div>
      <label class="bd-fl">Color personalizado</label>
      <input type="color" id="colorPicker" style="width:100%;height:38px;border-radius:8px;border:1px solid rgba(255,255,255,.1);background:transparent;cursor:pointer;padding:2px" oninput="previewColor(this.value)">
      <div style="height:8px"></div>
      <label class="bd-fl">Opacidad de relleno</label>
      <input type="range" id="opacitySlider" min="0" max="100" value="22" oninput="document.getElementById('opacityVal').textContent=this.value+'%';previewOpacity(this.value)" style="width:100%;accent-color:#38bdf8">
      <span id="opacityVal" style="font-size:11px;color:#94a3b8;font-family:'Space Mono',monospace">22%</span>
    </div>
    <div class="bd-foot">
      <button class="btn-cancel" onclick="document.getElementById('colorOv').classList.remove('open')">Cancelar</button>
      <button class="btn-save-bd" onclick="aplicarColor()" style="background:linear-gradient(135deg,#7c3aed,#a21caf)">
        <i class="fa fa-check"></i> Aplicar
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL GUARDAR EN BASE DE DATOS
══════════════════════════════════════════════════════ -->
<div class="bd-ov" id="bdOv">
  <div class="bd-box">
    <div class="bd-head">
      <h2><i class="fa fa-database"></i> Guardar en Base de Datos</h2>
      <button onclick="cerrarBdModal()" style="width:28px;height:28px;border-radius:50%;border:none;background:rgba(255,255,255,.07);color:#94a3b8;cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;"><i class="fa fa-times"></i></button>
    </div>
    <div class="bd-body">
      <div class="bd-fr">
        <label class="bd-fl">1. Buscar y seleccionar productor</label>
        <input type="text" class="bd-fi" id="bdBuscarSocio" placeholder="Escribe cédula o nombre..." oninput="buscarSociosBD()">
      </div>
      <div id="bdListaSocios" style="max-height:140px;overflow-y:auto;margin-bottom:12px;border-radius:8px;border:1px solid rgba(255,255,255,.08);display:none;"></div>
      <div id="bdSocioInfo" style="display:none;">
        <div class="bd-info">
          <b><i class="fa fa-user"></i> <span id="bdSocioNombre">—</span></b><br>
          Cédula: <span id="bdSocioCedula">—</span> &nbsp;·&nbsp; Zona: <span id="bdSocioZona">—</span><br>
          Código base: <b id="bdSocioBase">—</b>
        </div>
        <div class="bd-fg">
          <div>
            <label class="bd-fl">2. Código del archivo</label>
            <input type="text" class="bd-fi" id="bdCodigo" placeholder="SLC-001_1" oninput="validarCodigoBD()" style="font-family:'Space Mono',monospace;font-weight:700;text-transform:uppercase;">
            <div style="font-size:10px;color:#475569;margin-top:3px;" id="bdCodigoHint">Formato: SLC-NNN_L</div>
          </div>
          <div>
            <label class="bd-fl">3. Descripción (opcional)</label>
            <input type="text" class="bd-fi" id="bdDescripcion" placeholder="Lote 1, Finca principal...">
          </div>
        </div>
        <div class="bd-fr">
          <label class="bd-fl"><i class="fa fa-circle-check" style="color:#10b981;"></i> Códigos disponibles</label>
          <div class="bd-codes" id="bdCodesDisp"><span style="color:#334155;font-size:11px;">Cargando...</span></div>
        </div>
        <div style="background:rgba(16,185,129,.05);border:1px solid rgba(16,185,129,.15);border-radius:8px;padding:10px 12px;">
          <div style="font-size:10px;font-weight:700;color:#10b981;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px;"><i class="fa fa-eye"></i> Vista previa del registro</div>
          <div style="font-size:11px;color:#94a3b8;line-height:1.8;">
            Archivo: <b id="pvNombre" style="color:#e2e8f0;">—</b><br>
            Código: <b id="pvCodigo" style="color:#10b981;font-family:'Space Mono',monospace;">—</b><br>
            Socio: <b id="pvSocio" style="color:#e2e8f0;">—</b><br>
            Formato origen: <b id="pvFormato" style="color:#38bdf8;">—</b>
          </div>
        </div>
      </div>
    </div>
    <div class="bd-foot">
      <span class="sind" id="bdSind"></span>
      <button class="btn-cancel" onclick="cerrarBdModal()">Cancelar</button>
      <button class="btn-save-bd" id="btnGuardarBD" onclick="guardarEnBD()" disabled>
        <i class="fa fa-floppy-disk"></i> Guardar en BD
      </button>
    </div>
  </div>
</div>

<!-- ══ MODAL CSV COLUMN MAPPER ══ -->
<div class="bd-ov" id="csvMapOv">
  <div class="bd-box">
    <div class="bd-head">
      <h2><i class="fa fa-table"></i> Mapear columnas CSV</h2>
      <button onclick="cerrarCsvMap()" style="width:28px;height:28px;border-radius:50%;border:none;background:rgba(255,255,255,.07);color:#94a3b8;cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;"><i class="fa fa-times"></i></button>
    </div>
    <div class="bd-body">
      <div class="bd-info">Indica qué columnas contienen las coordenadas y el nombre de las geometrías.</div>
      <div class="bd-fr"><label class="bd-fl">Columna de Latitud</label><select class="bd-fi" id="csvLat"></select></div>
      <div class="bd-fr"><label class="bd-fl">Columna de Longitud</label><select class="bd-fi" id="csvLng"></select></div>
      <div class="bd-fr"><label class="bd-fl">Columna de Nombre (opcional)</label><select class="bd-fi" id="csvName"><option value="">— Sin nombre —</option></select></div>
      <div id="csvPreview" style="margin-top:10px;font-size:11px;color:#475569;"></div>
    </div>
    <div class="bd-foot">
      <button class="btn-cancel" onclick="cerrarCsvMap()">Cancelar</button>
      <button class="btn-save-bd" onclick="procesarCsvConMapping()" style="background:linear-gradient(135deg,#0d9488,#0891b2);">
        <i class="fa fa-check"></i> Importar CSV
      </button>
    </div>
  </div>
</div>

<div id="toast-q"></div>

<!-- ══ SCRIPTS ══ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-omnivore/0.3.4/leaflet-omnivore.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/shpjs/3.6.3/shp.min.js"></script>

<script>
/* ═══════════════════════════════════════════════════
   CONSTANTES & ESTADO GLOBAL
═══════════════════════════════════════════════════ */
const COLORES = ['#38bdf8','#10b981','#f59e0b','#ef4444','#a78bfa','#fb923c','#34d399','#e879f9','#facc15','#60a5fa'];
const TILES = [
  {url:'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',label:'Satélite',att:'© Esri',maxNative:18,maxZoom:22},
  {url:'https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',label:'Google Sat',att:'© Google',maxNative:20,maxZoom:22},
  {url:'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',label:'OSM',att:'© OpenStreetMap',maxNative:19,maxZoom:22},
  {url:'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',label:'Topográfico',att:'© OpenTopoMap',maxNative:17,maxZoom:22},
];

let mapa = null, tileLayer = null, tiloActual = 0;
let capas = {};
let capaSeleccionada = null;
let colorIdx = 0;
let panelCapasVisible = true, panelAttrVisible = true;

// Editor polígono
let editState = {
  activo:false,idCapa:null,modoEdit:'vertices',
  poligonosEditados:[],layersEdicion:[],markerVertices:[],midMarkers:[]
};
let _dragState = null;

// CSV temporal
let _csvPendiente = { rows:[], cols:[], nombre:'' };

// BD
let bdSocioSel = null, bdCodigoOk = false, _bdSearchTimer = null;

// Medición
let measureActive = false;
let measurePoints = [];
let measurePolyline = null, measurePolygon = null, measureMarkers = [];

// Separador
let splitPoligonos = [];
let splitSelId = null;

// Color
let _colorTarget = null;

/* ═══════════════════════════════════════════════════
   INIT
═══════════════════════════════════════════════════ */
window.addEventListener('load', () => { inicializarMapa(); });

function inicializarMapa() {
  mapa = L.map('mapaQgis', { center: [-1.8, -78.5], zoom: 8, zoomControl: false });
  const t = TILES[0];
  tileLayer = L.tileLayer(t.url, { attribution: t.att, maxNativeZoom: t.maxNative, maxZoom: t.maxZoom }).addTo(mapa);
  L.control.zoom({ position: 'bottomright' }).addTo(mapa);
  mapa.on('zoomend', () => { document.getElementById('stZoom').innerHTML = 'Zoom: <b>' + mapa.getZoom() + '</b>'; });
  mapa.on('mousemove', e => {
    const c = document.getElementById('stCoords');
    c.style.display = 'block';
    c.textContent = e.latlng.lat.toFixed(5) + ', ' + e.latlng.lng.toFixed(5);
  });
  mapa.on('click', e => {
    if (measureActive) agregarPuntoMedicion(e.latlng);
  });
  mapa.on('dblclick', e => {
    if (measureActive) { L.DomEvent.stopPropagation(e); cerrarMedicion(); }
  });
}

/* ═══════════════════════════════════════════════════
   CARGA DE ARCHIVOS
═══════════════════════════════════════════════════ */
function onFilesSelected(files) {
  Array.from(files).forEach(f => procesarArchivo(f));
  document.getElementById('fileInputHidden').value = '';
}

async function procesarArchivo(file) {
  const ext = file.name.split('.').pop().toLowerCase();
  const nombre = file.name.replace(/\.[^/.]+$/, '');
  setLoading(true, 'Procesando ' + file.name + '...', 30);
  try {
    if (ext === 'kml') { const text = await readText(file); agregarCapaKML(text, nombre, file.name); }
    else if (ext === 'kmz') { await procesarKMZ(file, nombre); }
    else if (ext === 'geojson' || ext === 'json') { const text = await readText(file); agregarCapaGeoJSON(JSON.parse(text), nombre); }
    else if (ext === 'csv') { const text = await readText(file); abrirCsvMapper(text, nombre); setLoading(false); return; }
    else if (ext === 'shp') { await procesarSHP(file, nombre); }
    else if (ext === 'dxf') { await procesarDXF(file, nombre); }
    else { toast('⚠ Formato no soportado: .' + ext, '#f59e0b'); }
  } catch(e) { console.error(e); toast('❌ Error al procesar ' + file.name, '#ef4444'); }
  setLoading(false);
}

function readText(file) { return new Promise((r,j) => { const rd=new FileReader(); rd.onload=e=>r(e.target.result); rd.onerror=j; rd.readAsText(file); }); }
function readArrayBuffer(file) { return new Promise((r,j) => { const rd=new FileReader(); rd.onload=e=>r(e.target.result); rd.onerror=j; rd.readAsArrayBuffer(file); }); }

async function procesarKMZ(file, nombre) {
  const buf = await readArrayBuffer(file);
  const zip = await JSZip.loadAsync(buf);
  const kmlFile = Object.keys(zip.files).find(n => n.toLowerCase().endsWith('.kml'));
  if (!kmlFile) { toast('❌ KMZ sin KML interno', '#ef4444'); return; }
  const kmlText = await zip.files[kmlFile].async('text');
  agregarCapaKML(kmlText, nombre, file.name);
}

async function procesarSHP(file, nombre) {
  try { const buf = await readArrayBuffer(file); const geojson = await shp(buf); agregarCapaGeoJSON(geojson, nombre); }
  catch(e) { toast('⚠ Para Shapefile arrastra el .zip con todos los archivos', '#f59e0b'); }
}

async function procesarDXF(file, nombre) {
  const text = await readText(file);
  const geojson = parseDXFbasico(text);
  if (!geojson.features.length) { toast('⚠ DXF sin geometrías reconocidas', '#f59e0b'); return; }
  agregarCapaGeoJSON(geojson, nombre);
}

function parseDXFbasico(dxf) {
  const features = [];
  const lines = dxf.split('\n').map(l => l.trim());
  let i = 0;
  while (i < lines.length) {
    if (lines[i] === '0' && (lines[i+1] === 'LWPOLYLINE' || lines[i+1] === 'LINE' || lines[i+1] === 'POINT')) {
      const type = lines[i+1]; const coords = []; let j = i + 2;
      while (j < lines.length && !(lines[j] === '0')) {
        const code = parseInt(lines[j]); const val = parseFloat(lines[j+1]);
        if (code === 10) coords.push([val]);
        else if (code === 20 && coords.length) coords[coords.length-1].push(val);
        j += 2;
      }
      const validCoords = coords.filter(c => c.length === 2 && !isNaN(c[0]) && !isNaN(c[1]));
      if (validCoords.length) {
        const lnglat = validCoords.map(c => [c[0], c[1]]);
        if (type === 'POINT' && lnglat.length >= 1) {
          features.push({ type:'Feature', geometry:{ type:'Point', coordinates:lnglat[0] }, properties:{ tipo:'POINT' } });
        } else if (lnglat.length >= 2) {
          const closed = lnglat.length > 2;
          if (closed) lnglat.push(lnglat[0]);
          features.push({ type:'Feature', geometry:{ type:closed?'Polygon':'LineString', coordinates:closed?[lnglat]:lnglat }, properties:{ tipo:type } });
        }
      }
      i = j;
    } else { i++; }
  }
  return { type:'FeatureCollection', features };
}

function abrirCsvMapper(text, nombre) {
  const rows = text.trim().split('\n').map(r => r.split(',').map(c => c.trim().replace(/^"|"$/g,'')));
  if (rows.length < 2) { toast('❌ CSV vacío o sin datos', '#ef4444'); return; }
  const cols = rows[0];
  _csvPendiente = { rows: rows.slice(1), cols, nombre };
  const sel = (id) => { const s=document.getElementById(id); s.innerHTML=''; cols.forEach((c,i) => { const o=document.createElement('option'); o.value=i; o.textContent=c; s.appendChild(o); }); };
  sel('csvLat'); sel('csvLng');
  const sn = document.getElementById('csvName'); sn.innerHTML = '<option value="">— Sin nombre —</option>';
  cols.forEach((c,i) => { const o=document.createElement('option'); o.value=i; o.textContent=c; sn.appendChild(o); });
  const latIdx = cols.findIndex(c => /lat/i.test(c));
  const lngIdx = cols.findIndex(c => /lon|lng|long/i.test(c));
  if (latIdx >= 0) document.getElementById('csvLat').value = latIdx;
  if (lngIdx >= 0) document.getElementById('csvLng').value = lngIdx;
  document.getElementById('csvPreview').textContent = `${rows.length - 1} filas. Columnas: ${cols.join(', ')}`;
  document.getElementById('csvMapOv').classList.add('open');
}
function cerrarCsvMap() { document.getElementById('csvMapOv').classList.remove('open'); }

function procesarCsvConMapping() {
  const latIdx = parseInt(document.getElementById('csvLat').value);
  const lngIdx = parseInt(document.getElementById('csvLng').value);
  const nameIdx = document.getElementById('csvName').value;
  const { rows, cols, nombre } = _csvPendiente;
  const features = rows.map((row, ri) => {
    const lat=parseFloat(row[latIdx]); const lng=parseFloat(row[lngIdx]);
    if (isNaN(lat)||isNaN(lng)) return null;
    const props = {};
    cols.forEach((c,i) => { if (i!==latIdx&&i!==lngIdx) props[c]=row[i]||''; });
    if (nameIdx !== '') props['_nombre'] = row[parseInt(nameIdx)] || ('Fila '+(ri+1));
    return { type:'Feature', geometry:{ type:'Point', coordinates:[lng,lat] }, properties:props };
  }).filter(Boolean);
  cerrarCsvMap();
  if (!features.length) { toast('❌ Sin coordenadas válidas en el CSV', '#ef4444'); return; }
  agregarCapaGeoJSON({ type:'FeatureCollection', features }, nombre);
  toast(`✅ CSV importado: ${features.length} puntos`, '#10b981');
}

/* ═══════════════════════════════════════════════════
   AGREGAR CAPAS
═══════════════════════════════════════════════════ */
function agregarCapaKML(kmlStr, nombre, nombreArchivo) {
  const id = 'capa_' + Date.now() + '_' + Math.random().toString(36).slice(2,6);
  const color = COLORES[colorIdx++ % COLORES.length];
  const atributos = extraerAtributosKML(kmlStr);
  const geoInfo = calcularGeoKML(kmlStr);
  const layer = omnivore.kml.parse(kmlStr, null, L.geoJson(null, {
    style: { color, weight:2.5, fillOpacity:0.22, fillColor:color },
    pointToLayer: (f,ll) => L.circleMarker(ll, { radius:7, fillColor:color, color:'#fff', weight:2, fillOpacity:.9 }),
    onEachFeature: (feature, lyr) => {
      lyr.on('click', e => {
        L.DomEvent.stopPropagation(e);
        if (editState.activo && editState.modoEdit === 'eliminar') { manejarClickEliminar(id, lyr); return; }
        if (!editState.activo && !measureActive) abrirPopup(feature, lyr, id);
      });
    }
  })).addTo(mapa);
  const atrsConCalcs = rellenarCalcs(atributos, geoInfo);
  capas[id] = { layer, nombre:nombreArchivo||nombre, displayName:nombre, formato:'KML', color, activa:true, atributos:atrsConCalcs, geoInfo, kmlOriginal:kmlStr };
  seleccionarCapa(id); renderCapas(); actualizarStats(); ocultarEmpty(); centrarTodo();
  toast(`✅ KML cargado: ${nombre}`, '#10b981');
}

function agregarCapaGeoJSON(geojson, nombre) {
  const id = 'capa_' + Date.now() + '_' + Math.random().toString(36).slice(2,6);
  const color = COLORES[colorIdx++ % COLORES.length];
  let atributos = [];
  if (geojson.features && geojson.features.length) {
    const props = geojson.features[0].properties || {};
    atributos = Object.entries(props).map(([k,v]) => ({ k, v:String(v||'') }));
  }
  const layer = L.geoJson(geojson, {
    style: { color, weight:2.5, fillOpacity:0.22, fillColor:color },
    pointToLayer: (f,ll) => L.circleMarker(ll, { radius:7, fillColor:color, color:'#fff', weight:2, fillOpacity:.9 }),
    onEachFeature: (feature, lyr) => {
      lyr.on('click', e => {
        L.DomEvent.stopPropagation(e);
        if (!editState.activo && !measureActive) abrirPopup(feature, lyr, id);
      });
    }
  }).addTo(mapa);
  capas[id] = { layer, nombre, displayName:nombre, formato:'GeoJSON', color, activa:true, atributos, geoInfo:{}, kmlOriginal:null, geojson };
  seleccionarCapa(id); renderCapas(); actualizarStats(); ocultarEmpty();
  try { const b=layer.getBounds(); if(b.isValid()) mapa.fitBounds(b,{padding:[40,40]}); } catch(e) {}
  toast(`✅ Cargado: ${nombre}`, '#10b981');
}

/* ═══════════════════════════════════════════════════
   RENDER CAPAS
═══════════════════════════════════════════════════ */
function renderCapas() {
  const lista = document.getElementById('listaCapas');
  const ids = Object.keys(capas);
  if (!ids.length) {
    lista.innerHTML = '<div style="text-align:center;padding:16px;color:#334155;font-size:11px;">Sin capas cargadas</div>';
    document.getElementById('badgeCapas').textContent = '0'; return;
  }
  document.getElementById('badgeCapas').textContent = ids.length;
  lista.innerHTML = ids.reverse().map(id => {
    const c = capas[id];
    const fmtColor = c.formato==='KML'?'#10b981':c.formato==='GeoJSON'?'#38bdf8':c.formato==='CSV'?'#f59e0b':'#a78bfa';
    return `<div class="layer-item${capaSeleccionada===id?' selected':''}" id="li_${id}" onclick="seleccionarCapa('${id}')">
      <div class="l-dot" style="background:${c.color};border-color:${c.color}80"></div>
      <div class="l-info">
        <div class="l-name" title="${esc(c.nombre)}">${esc(c.displayName.length>22?c.displayName.slice(0,22)+'…':c.displayName)}</div>
        <div class="l-sub" style="color:${fmtColor}">${c.formato}</div>
      </div>
      <div class="l-acts">
        <button class="lbtn zb" onclick="event.stopPropagation();centrarCapa('${id}')" title="Centrar"><i class="fa fa-crosshairs"></i></button>
        <button class="lbtn sb" onclick="event.stopPropagation();abrirSeparadorCapa('${id}')" title="Separar polígonos"><i class="fa fa-scissors"></i></button>
        <button class="lbtn pb" onclick="event.stopPropagation();iniciarEdicionPoligono('${id}')" title="Editar vértices"><i class="fa fa-vector-square"></i></button>
        <button class="lbtn rb" onclick="event.stopPropagation();eliminarCapa('${id}')" title="Eliminar capa"><i class="fa fa-trash"></i></button>
        <div class="tswitch${c.activa?' on':''}" onclick="event.stopPropagation();toggleCapa('${id}',this)" title="Mostrar/ocultar"></div>
      </div>
    </div>`;
  }).join('');
}

function seleccionarCapa(id) {
  capaSeleccionada = id; renderCapas(); renderAtributos(id);
  document.getElementById('apActions').style.display = 'flex';
  document.getElementById('apLayerName').textContent = capas[id]?.displayName || '';
}

/* ═══════════════════════════════════════════════════
   ATRIBUTOS
═══════════════════════════════════════════════════ */
function renderAtributos(id) {
  const c = capas[id]; if (!c) return;
  const body = document.getElementById('apBody');
  const atrs = c.atributos || [];
  let html = `
    <label class="afl">Nombre del archivo</label>
    <input class="afi" type="text" value="${esc(c.displayName)}" oninput="capas['${id}'].displayName=this.value;capas['${id}'].nombre=this.value;renderCapas()">
    <label class="afl">Formato origen</label>
    <input class="afi" type="text" value="${esc(c.formato)}" readonly>`;
  if (c.geoInfo && c.geoInfo.area !== null) {
    html += `<label class="afl">Área calculada</label>
    <input class="afi" type="text" value="${c.geoInfo.area ? c.geoInfo.area.toFixed(4)+' ha' : '—'}" readonly>`;
  }
  html += `<div class="sdiv">Atributos / Etiquetas</div>
    <div class="at-wrap">
      <div class="at-head-mini"><span>Campo</span><span>Valor</span><span></span></div>
      <div id="atRowsMini">`;
  atrs.forEach((a,i) => {
    html += `<div class="at-row-mini">
      <div class="at-cell-mini"><input type="text" value="${esc(a.k)}" placeholder="Nombre" oninput="capas['${id}'].atributos[${i}].k=this.value"></div>
      <div class="at-cell-mini"><input type="text" value="${esc(a.v)}" placeholder="Valor" oninput="capas['${id}'].atributos[${i}].v=this.value"></div>
      <div class="at-cell-mini" style="justify-content:center"><button class="at-del-mini" onclick="elimAtributo('${id}',${i})"><i class="fa fa-times"></i></button></div>
    </div>`;
  });
  html += `</div></div>
    <button class="at-add-mini" onclick="agregarAtributo('${id}')"><i class="fa fa-plus"></i> Agregar campo</button>
    <div style="margin-top:8px;padding:8px 10px;background:rgba(56,189,248,.04);border:1px solid rgba(56,189,248,.1);border-radius:7px;">
      <div style="font-size:9.5px;font-weight:700;color:#38bdf8;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px;"><i class="fa fa-calculator"></i> Campos calculados</div>
      <div style="display:flex;gap:5px;flex-wrap:wrap;">
        <span onclick="insertarCalcAtrib('${id}','Area','area')" style="cursor:pointer;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);color:#10b981;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:700;">+ Área ha</span>
        <span onclick="insertarCalcAtrib('${id}','Latitud','latitud')" style="cursor:pointer;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);color:#10b981;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:700;">+ Latitud</span>
        <span onclick="insertarCalcAtrib('${id}','Longitud','longitud')" style="cursor:pointer;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);color:#10b981;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:700;">+ Longitud</span>
        <span onclick="insertarCalcAtrib('${id}','Perimetro','perimetro')" style="cursor:pointer;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);color:#10b981;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:700;">+ Perímetro</span>
      </div>
    </div>`;
  body.innerHTML = html;
}

function elimAtributo(id, idx) { capas[id].atributos.splice(idx,1); renderAtributos(id); }
function agregarAtributo(id) { if(!capas[id].atributos) capas[id].atributos=[]; capas[id].atributos.push({k:'',v:''}); renderAtributos(id); }
function insertarCalcAtrib(id, nombre, tipo) {
  const c = capas[id]; if (!c) return;
  const geo = c.geoInfo || {}; let v = '';
  if (tipo==='area') v=geo.area!==null?String(geo.area):'0';
  else if (tipo==='latitud') v=geo.latitud!==null?String(parseFloat((geo.latitud||0).toFixed(6))):'0';
  else if (tipo==='longitud') v=geo.longitud!==null?String(parseFloat((geo.longitud||0).toFixed(6))):'0';
  else if (tipo==='perimetro') v=geo.perimetro!==null?String(geo.perimetro):'0';
  if (!c.atributos) c.atributos = [];
  c.atributos.push({ k:nombre, v, tipo });
  renderAtributos(id);
}

/* ═══════════════════════════════════════════════════
   POPUP
═══════════════════════════════════════════════════ */
function abrirPopup(feature, lyr, idCapa) {
  const c = capas[idCapa]; if (!c) return;
  const atrs = c.atributos || [];
  const fn = feature?.properties?.name || feature?.properties?.Name || feature?.properties?._nombre || '';
  let tabla = '';
  if (atrs.length) {
    tabla = '<table class="pu-table">'; atrs.forEach(a => { tabla+=`<tr><td>${esc(a.k)}</td><td>${esc(a.v)}</td></tr>`; }); tabla+='</table>';
  } else if (feature?.properties) {
    const props = feature.properties; tabla='<table class="pu-table">';
    Object.entries(props).slice(0,8).forEach(([k,v]) => { tabla+=`<tr><td>${esc(k)}</td><td>${esc(String(v||''))}</td></tr>`; });
    tabla+='</table>';
  } else { tabla='<p style="color:#334155;font-size:11px;font-style:italic;padding:4px 0;">Sin atributos</p>'; }
  const html = `<div class="pu-wrap">
    <div class="pu-head">
      <div class="pu-title">${esc(fn||c.displayName)}</div>
      <div class="pu-sub">${esc(c.formato)} · <span style="color:${c.color}">●</span> ${esc(c.displayName)}</div>
    </div>
    <div class="pu-body">${tabla}</div>
    <div class="pu-foot">
      <button class="pu-btn pu-btn-edit" onclick="seleccionarCapa('${idCapa}');mapa.closePopup()"><i class="fa fa-pen"></i> Editar</button>
      <button class="pu-btn pu-btn-split" onclick="abrirSeparadorCapa('${idCapa}');mapa.closePopup()"><i class="fa fa-scissors"></i> Separar</button>
      <button class="pu-btn pu-btn-poly" onclick="iniciarEdicionPoligono('${idCapa}');mapa.closePopup()"><i class="fa fa-vector-square"></i> Vértices</button>
      <button class="pu-btn pu-btn-zoom" onclick="centrarCapa('${idCapa}')"><i class="fa fa-crosshairs"></i></button>
    </div>
  </div>`;
  let ll;
  try { const b=lyr.getBounds?.(); ll=b?b.getCenter():(lyr.getLatLng?.()||mapa.getCenter()); } catch(e) { ll=mapa.getCenter(); }
  L.popup({ maxWidth:360 }).setLatLng(ll).setContent(html).openOn(mapa);
}

/* ═══════════════════════════════════════════════════
   CONTROLES MAPA
═══════════════════════════════════════════════════ */
function toggleCapa(id, el) {
  const c=capas[id]; if(!c) return;
  if(c.activa){mapa.removeLayer(c.layer);c.activa=false;el.classList.remove('on');}
  else{c.layer.addTo(mapa);c.activa=true;el.classList.add('on');}
  actualizarStats();
}
function centrarCapa(id) {
  const c=capas[id]; if(!c) return;
  if(!c.activa){c.layer.addTo(mapa);c.activa=true;}
  try{const b=c.layer.getBounds();if(b.isValid())mapa.fitBounds(b,{padding:[40,40],maxZoom:19});}catch(e){}
}
function centrarTodo() {
  const act=Object.values(capas).filter(c=>c.activa);
  if(!act.length) return;
  try{
    const bs=act.map(c=>c.layer.getBounds()).filter(b=>{try{return b.isValid();}catch(e){return false;}});
    if(!bs.length) return;
    let cb=bs[0]; bs.forEach(b=>{cb=cb.extend(b);});
    mapa.fitBounds(cb,{padding:[30,30]});
  }catch(e){}
}
function eliminarCapa(id) {
  const c=capas[id]; if(!c) return;
  try{mapa.removeLayer(c.layer);}catch(e){}
  delete capas[id];
  if(capaSeleccionada===id){
    capaSeleccionada=null;
    document.getElementById('apBody').innerHTML='<div class="ap-placeholder"><i class="fa fa-hand-pointer"></i><span>Selecciona una capa</span></div>';
    document.getElementById('apActions').style.display='none';
    document.getElementById('apLayerName').textContent='';
  }
  renderCapas(); actualizarStats();
  if(!Object.keys(capas).length) document.getElementById('emptyMap').classList.remove('hidden');
}
function limpiarTodo() {
  if(!Object.keys(capas).length) return;
  Object.values(capas).forEach(c=>{try{mapa.removeLayer(c.layer);}catch(e){}});
  capas={}; capaSeleccionada=null; colorIdx=0;
  cancelarEdicion(true); renderCapas(); actualizarStats();
  document.getElementById('apBody').innerHTML='<div class="ap-placeholder"><i class="fa fa-hand-pointer"></i><span>Selecciona una capa</span></div>';
  document.getElementById('apActions').style.display='none';
  document.getElementById('apLayerName').textContent='';
  document.getElementById('emptyMap').classList.remove('hidden');
  toast('🗑 Todo limpiado','#94a3b8');
}
function ciclarCapa() {
  tiloActual=(tiloActual+1)%TILES.length;
  if(tileLayer) mapa.removeLayer(tileLayer);
  const t=TILES[tiloActual];
  tileLayer=L.tileLayer(t.url,{attribution:t.att,maxNativeZoom:t.maxNative,maxZoom:t.maxZoom}).addTo(mapa);
  document.getElementById('lblCapa').textContent=t.label;
}
function togglePanel(tipo) {
  if(tipo==='layer'){
    panelCapasVisible=!panelCapasVisible;
    document.getElementById('layerPanel').classList.toggle('collapsed',!panelCapasVisible);
    document.getElementById('btnToggleCapas').classList.toggle('active',panelCapasVisible);
  }else{
    panelAttrVisible=!panelAttrVisible;
    document.getElementById('attrPanel').classList.toggle('collapsed',!panelAttrVisible);
    document.getElementById('btnToggleAttr').classList.toggle('active',panelAttrVisible);
  }
  setTimeout(()=>mapa.invalidateSize(),350);
}
function actualizarStats(){document.getElementById('stCapas').textContent=Object.keys(capas).length;}
function ocultarEmpty(){document.getElementById('emptyMap').classList.add('hidden');}

/* ═══════════════════════════════════════════════════
   EXTRACCIÓN KML / GEO
═══════════════════════════════════════════════════ */
function extraerAtributosKML(kmlStr) {
  const CAMPOS_CALC=['area','área','area_ha','hectarea','hectárea','ha','latitud','lat','longitud','lon','lng','perimetro','perímetro','perimetro_km'];
  const esCalc=k=>CAMPOS_CALC.some(c=>k.toLowerCase().includes(c));
  const atrs=[]; const doc=new DOMParser().parseFromString(kmlStr,'text/xml');
  doc.querySelectorAll('SimpleData').forEach(sd=>{const k=sd.getAttribute('name')||'';const v=(sd.textContent||'').trim();if(k&&!esCalc(k))atrs.push({k,v,tipo:'texto'});});
  if(!atrs.length){doc.querySelectorAll('ExtendedData > Data').forEach(d=>{const k=d.getAttribute('name')||'';const vEl=d.querySelector('value');const v=vEl?(vEl.textContent||'').trim():'';if(k&&!esCalc(k))atrs.push({k,v,tipo:'texto'});});}
  if(!atrs.length){const descEl=doc.querySelector('description');if(descEl){const dd=new DOMParser().parseFromString(descEl.textContent||'','text/html');dd.querySelectorAll('tr').forEach(row=>{const tds=row.querySelectorAll('td');if(tds.length>=2){const k=(tds[0].textContent||'').trim();const v=(tds[1].textContent||'').trim();if(k&&!esCalc(k))atrs.push({k,v,tipo:'texto'});}});}}
  return atrs;
}
function calcularGeoKML(kmlStr) {
  const info={area:null,latitud:null,longitud:null,perimetro:null};
  try{
    const doc=new DOMParser().parseFromString(kmlStr,'text/xml');
    const coordEls=doc.querySelectorAll('coordinates');if(!coordEls.length)return info;
    let all=[];
    coordEls.forEach(el=>{(el.textContent||'').trim().split(/\s+/).forEach(c=>{const p=c.split(',');if(p.length>=2){const lon=parseFloat(p[0]),lat=parseFloat(p[1]);if(!isNaN(lon)&&!isNaN(lat))all.push([lat,lon]);}});});
    if(!all.length)return info;
    info.latitud=all.reduce((s,c)=>s+c[0],0)/all.length;
    info.longitud=all.reduce((s,c)=>s+c[1],0)/all.length;
    if(all.length>3){info.area=calcArea(all);info.perimetro=calcPerim(all);}
  }catch(e){}
  return info;
}
function calcArea(coords){let area=0;const n=coords.length,R=6371000;for(let i=0;i<n-1;i++){const lat1=coords[i][0]*Math.PI/180,lat2=coords[i+1][0]*Math.PI/180;const dlon=(coords[i+1][1]-coords[i][1])*Math.PI/180;area+=dlon*(2+Math.sin(lat1)+Math.sin(lat2));}return parseFloat((Math.abs(area)*R*R/2/10000).toFixed(6));}
function calcPerim(coords){let p=0;for(let i=0;i<coords.length-1;i++)p+=haversine(coords[i],coords[i+1]);return parseFloat((p/1000).toFixed(4));}
function haversine([lat1,lon1],[lat2,lon2]){const R=6371000,dLat=(lat2-lat1)*Math.PI/180,dLon=(lon2-lon1)*Math.PI/180;const a=Math.sin(dLat/2)**2+Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;return R*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));}

/* ═══════════════════════════════════════════════════
   ★ SEPARADOR DE POLÍGONOS
═══════════════════════════════════════════════════ */
const SPLIT_COLORS=['#38bdf8','#10b981','#f59e0b','#ef4444','#a78bfa','#fb923c','#34d399','#e879f9','#facc15','#60a5fa','#f472b6','#4ade80'];

function abrirSeparador() {
  splitPoligonos=[]; splitSelId=null;
  document.getElementById('splitGrid').style.display='none';
  document.getElementById('splitFoot').style.display='none';
  document.getElementById('splitDzWrap').style.display='block';
  document.getElementById('splitList').innerHTML='';
  document.getElementById('splitDetail').innerHTML='<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#334155;gap:8px;font-size:11px;"><i class="fa fa-hand-pointer" style="font-size:24px;color:#1e293b"></i><span>Selecciona un polígono</span></div>';
  document.getElementById('splitOv').classList.add('open');
}

function abrirSeparadorCapa(id) {
  abrirSeparador();
  const c = capas[id]; if (!c) return;
  if (c.kmlOriginal) {
    splitExtraerKML(c.kmlOriginal, c.displayName, c.atributos);
  } else {
    const gj = c.geojson || (c.layer ? (() => { try { return c.layer.toGeoJSON(); } catch(e) { return null; } })() : null);
    if (gj) splitExtraerGeoJSON(gj, c.displayName);
    else toast('⚠ No se pudo acceder a la geometría de la capa', '#f59e0b');
  }
}

function cerrarSeparador() { document.getElementById('splitOv').classList.remove('open'); }

function splitDzOver(e){e.preventDefault();document.getElementById('splitDz').classList.add('drag');}
function splitDzLeave(e){document.getElementById('splitDz').classList.remove('drag');}
function splitDzDrop(e){e.preventDefault();document.getElementById('splitDz').classList.remove('drag');if(e.dataTransfer.files.length)splitOnFile(e.dataTransfer.files);}

async function splitOnFile(files) {
  const file = files[0]; if (!file) return;
  const ext = file.name.split('.').pop().toLowerCase();
  splitPoligonos = []; splitSelId = null;
  try {
    if (ext === 'kml') { const t=await readText(file); splitExtraerKML(t, file.name, []); }
    else if (ext === 'kmz') {
      const buf=await readArrayBuffer(file); const zip=await JSZip.loadAsync(buf);
      const kn=Object.keys(zip.files).find(n=>n.toLowerCase().endsWith('.kml'));
      if(!kn){toast('❌ KMZ sin KML','#ef4444');return;}
      const t=await zip.files[kn].async('text'); splitExtraerKML(t, file.name, []);
    } else if (ext === 'geojson' || ext === 'json') {
      const t=await readText(file); splitExtraerGeoJSON(JSON.parse(t), file.name);
    } else { toast('⚠ Solo KML, KMZ, GeoJSON','#f59e0b'); }
  } catch(e) { toast('❌ Error procesando archivo','#ef4444'); console.error(e); }
  document.getElementById('splitFileInput').value = '';
}

function splitExtraerKML(kmlStr, archivo, atrsBase) {
  const doc = new DOMParser().parseFromString(kmlStr, 'text/xml');
  let count = 0;
  doc.querySelectorAll('Placemark').forEach(pm => {
    const nombre = (pm.querySelector('name')?.textContent || '').trim() || ('Polígono ' + (splitPoligonos.length+1));
    const atrs = [...(atrsBase||[])];
    pm.querySelectorAll('SimpleData').forEach(sd=>{const k=sd.getAttribute('name')||'';const v=(sd.textContent||'').trim();if(k)atrs.push({k,v});});
    if(!atrs.length) pm.querySelectorAll('ExtendedData > Data').forEach(d=>{const k=d.getAttribute('name')||'';const vEl=d.querySelector('value');const v=vEl?(vEl.textContent||'').trim():'';if(k)atrs.push({k,v});});

    const procesarPoly = (poly, sufijo) => {
      const outerEl=poly.querySelector('outerBoundaryIs coordinates')||poly.querySelector('outerBoundaryIs LinearRing coordinates');
      if(!outerEl)return;
      const outerCoords=splitParsarCoords(outerEl.textContent||'');
      if(outerCoords.length<3)return;
      const huecos=[];
      poly.querySelectorAll('innerBoundaryIs').forEach(inner=>{const el=inner.querySelector('coordinates')||inner.querySelector('LinearRing coordinates');if(el){const c=splitParsarCoords(el.textContent||'');if(c.length>=3)huecos.push(c);}});
      splitAgregar(nombre+(sufijo?` — ${sufijo}`:''), outerCoords, huecos, atrs.map(a=>({...a})));
      count++;
    };

    pm.querySelectorAll('Polygon').forEach((poly,pi)=>procesarPoly(poly, pm.querySelectorAll('Polygon').length>1?`Parte ${pi+1}`:''));
    pm.querySelectorAll('MultiGeometry > Polygon').forEach((poly,pi)=>procesarPoly(poly,`Parte ${pi+1}`));
  });

  if(!count){toast('⚠ No se encontraron polígonos','#f59e0b');return;}
  splitRenderizar(archivo, count);
}

function splitExtraerGeoJSON(gj, archivo) {
  let count=0;
  const features=gj.type==='FeatureCollection'?gj.features:(gj.type==='Feature'?[gj]:[]);
  features.forEach((feat,fi)=>{
    const geo=feat.geometry;
    const nombre=feat.properties?.name||feat.properties?.Name||feat.properties?.nombre||`Polígono ${splitPoligonos.length+1}`;
    const atrs=Object.entries(feat.properties||{}).filter(([k])=>k!=='name'&&k!=='Name'&&k!=='nombre').map(([k,v])=>({k,v:String(v||'')}));
    if(geo?.type==='Polygon'){
      const outer=geo.coordinates[0].map(c=>[c[1],c[0]]);
      const huecos=geo.coordinates.slice(1).map(h=>h.map(c=>[c[1],c[0]]));
      if(outer.length>=3){splitAgregar(nombre,outer,huecos,atrs);count++;}
    } else if(geo?.type==='MultiPolygon'){
      geo.coordinates.forEach((poly,pi)=>{
        const outer=poly[0].map(c=>[c[1],c[0]]);
        const huecos=poly.slice(1).map(h=>h.map(c=>[c[1],c[0]]));
        if(outer.length>=3){splitAgregar(`${nombre} — Parte ${pi+1}`,outer,huecos,atrs.map(a=>({...a})));count++;}
      });
    }
  });
  if(!count){toast('⚠ No se encontraron polígonos','#f59e0b');return;}
  splitRenderizar(archivo, count);
}

function splitParsarCoords(raw) {
  const coords=[];
  raw.trim().split(/\s+/).forEach(c=>{const p=c.split(',');if(p.length>=2){const lon=parseFloat(p[0]),lat=parseFloat(p[1]);if(!isNaN(lon)&&!isNaN(lat))coords.push([lat,lon]);}});
  return coords;
}

function splitAgregar(nombre, coords, huecos, atrs) {
  const id='sp_'+Date.now()+'_'+Math.random().toString(36).slice(2,5);
  const color=SPLIT_COLORS[splitPoligonos.length%SPLIT_COLORS.length];
  const area=calcArea(coords);
  splitPoligonos.push({id,nombre,coords,huecos:huecos||[],area,color,atrs:atrs||[],seleccionado:false});
}

function splitRenderizar(archivo, count) {
  document.getElementById('splitDzWrap').style.display='none';
  document.getElementById('splitGrid').style.display='grid';
  document.getElementById('splitFoot').style.display='flex';
  splitRenderLista();
  toast(`✅ ${count} polígono(s) separados de "${archivo}"`, '#10b981');
}

function splitRenderLista() {
  const lista=document.getElementById('splitList');
  const sel=splitPoligonos.filter(p=>p.seleccionado).length;
  document.getElementById('splitSelCount').textContent=`${sel} / ${splitPoligonos.length}`;
  if(!splitPoligonos.length){lista.innerHTML='<div style="text-align:center;padding:16px;color:#334155;font-size:11px;">Sin polígonos</div>';return;}
  lista.innerHTML=splitPoligonos.map(p=>`
    <div class="split-item${splitSelId===p.id?' sp-sel':''}" onclick="splitSelectPoli('${p.id}')">
      <div class="split-chk${p.seleccionado?' on':''}" onclick="event.stopPropagation();splitToggleSel('${p.id}',this)" title="Seleccionar"><i class="fa fa-check"></i></div>
      <div class="split-dot" style="background:${p.color};box-shadow:0 0 4px ${p.color}55"></div>
      <div class="split-info">
        <div class="split-name">${esc(p.nombre)}</div>
        <div class="split-area">${p.area.toFixed(4)} ha · ${p.coords.length} vért.</div>
      </div>
    </div>`).join('');
}

function splitToggleSel(id, el) {
  const p=splitPoligonos.find(x=>x.id===id); if(!p)return;
  p.seleccionado=!p.seleccionado; el.classList.toggle('on',p.seleccionado);
  splitRenderLista();
  const allChk=document.getElementById('splitChkAll');
  allChk.classList.toggle('on',splitPoligonos.every(x=>x.seleccionado));
}

function splitToggleAll(el) {
  const allSel=splitPoligonos.every(p=>p.seleccionado);
  splitPoligonos.forEach(p=>{p.seleccionado=!allSel;}); el.classList.toggle('on',!allSel);
  splitRenderLista();
}

function splitSelectPoli(id) {
  splitSelId=id;
  const p=splitPoligonos.find(x=>x.id===id); if(!p)return;
  splitRenderLista();
  // Mostrar detalle
  const centLat=p.coords.reduce((s,c)=>s+c[0],0)/p.coords.length;
  const centLng=p.coords.reduce((s,c)=>s+c[1],0)/p.coords.length;
  const perim=calcPerim(p.coords);
  let atrsHtml='';
  if(p.atrs&&p.atrs.length){
    atrsHtml=`<div style="font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#475569;margin-bottom:5px;"><i class="fa fa-table" style="color:var(--amber)"></i> Atributos</div>`;
    p.atrs.forEach((a,i)=>{
      atrsHtml+=`<div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-bottom:3px;">
        <input style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);border-radius:5px;padding:3px 6px;color:#e2e8f0;font-size:10px;outline:none;" value="${esc(a.k)}" oninput="splitPoligonos.find(x=>x.id==='${id}').atrs[${i}].k=this.value">
        <input style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);border-radius:5px;padding:3px 6px;color:#e2e8f0;font-size:10px;outline:none;" value="${esc(a.v)}" oninput="splitPoligonos.find(x=>x.id==='${id}').atrs[${i}].v=this.value">
      </div>`;
    });
  }
  document.getElementById('splitDetail').innerHTML=`
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#475569;"><i class="fa fa-tag" style="color:#38bdf8"></i> Nombre</div>
    <input class="split-rename" type="text" value="${esc(p.nombre)}" oninput="splitPoligonos.find(x=>x.id==='${id}').nombre=this.value;splitRenderLista()">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
      <div class="split-stat">
        <div class="split-stat-label"><i class="fa fa-ruler-combined"></i> Área</div>
        <div class="split-stat-val">${p.area.toFixed(4)}</div>
        <div style="font-size:9px;color:#475569;">hectáreas</div>
      </div>
      <div class="split-stat">
        <div class="split-stat-label"><i class="fa fa-draw-polygon"></i> Vértices</div>
        <div class="split-stat-val sky">${p.coords.length}</div>
      </div>
    </div>
    <div class="split-stat">
      <div class="split-stat-label"><i class="fa fa-arrows-left-right-to-line"></i> Perímetro</div>
      <div class="split-stat-val" style="color:var(--amber);font-size:13px">${perim.toFixed(4)} km</div>
    </div>
    <div class="split-stat">
      <div class="split-stat-label"><i class="fa fa-location-dot"></i> Centroide</div>
      <div style="font-family:'Space Mono',monospace;font-size:10px;color:#475569;">${centLat.toFixed(6)}, ${centLng.toFixed(6)}</div>
    </div>
    <div>${atrsHtml}</div>
    <button onclick="splitAgregarAtr('${id}')" style="display:flex;align-items:center;justify-content:center;gap:5px;width:100%;padding:5px;background:rgba(56,189,248,.04);border:1px dashed rgba(56,189,248,.15);border-radius:5px;color:#38bdf8;font-size:10px;font-weight:600;cursor:pointer;"><i class="fa fa-plus"></i> Agregar atributo</button>
    <div class="split-export-row">
      <button class="sp-ebtn kml" onclick="splitExportarUnoKML('${id}')"><i class="fa fa-download"></i> KML</button>
      <button class="sp-ebtn geo" onclick="splitExportarUnoGeoJSON('${id}')"><i class="fa fa-download"></i> GeoJSON</button>
      <button class="sp-ebtn" style="background:rgba(167,139,250,.1);border-color:var(--purple);color:var(--purple);" onclick="splitCargarEnMapa('${id}')"><i class="fa fa-layer-group"></i> Al mapa</button>
    </div>`;
}

function splitAgregarAtr(id) {
  const p=splitPoligonos.find(x=>x.id===id); if(!p)return;
  if(!p.atrs)p.atrs=[];
  p.atrs.push({k:'',v:''});
  splitSelectPoli(id);
}

function splitCargarEnMapa(id) {
  const p=splitPoligonos.find(x=>x.id===id); if(!p)return;
  const kml=splitGenerarKML(p);
  agregarCapaKML(kml, p.nombre, p.nombre+'.kml');
  toast(`✅ "${p.nombre}" cargado en el mapa`,'#10b981');
}

function splitGenerarKML(p) {
  const titulo=p.nombre||'Sin nombre';
  const colorHex=(p.color||'#38bdf8').replace('#','');
  const atrs=(p.atrs||[]).filter(a=>a.k?.trim());
  let rows=atrs.map((a,i)=>`<tr${i%2?' bgcolor="#D4E4F3"':''}><td>${escH(a.k)}</td><td>${escH(a.v)}</td></tr>`).join('');
  rows+=`<tr><td>Area_ha</td><td>${p.area.toFixed(6)}</td></tr>`;
  rows+=`<tr><td>Perimetro_km</td><td>${calcPerim(p.coords).toFixed(4)}</td></tr>`;
  const htmlDesc=`<table style="font-family:Arial;font-size:12px;width:100%;border-collapse:collapse;"><tr style="background:#9CBCE2;font-weight:bold;text-align:center;"><td colspan="2">${escH(titulo)}</td></tr>${rows}</table>`;
  let ext='<ExtendedData>';
  atrs.forEach(a=>{ext+=`<Data name="${escH(a.k)}"><value>${escH(a.v)}</value></Data>`;});
  ext+=`<Data name="Area_ha"><value>${p.area.toFixed(6)}</value></Data>`;
  ext+=`<Data name="Perimetro_km"><value>${calcPerim(p.coords).toFixed(4)}</value></Data>`;
  ext+='</ExtendedData>';
  const outerStr=p.coords.map(c=>`${c[1]},${c[0]},0`).join(' ');
  let polyXml=`<Polygon><outerBoundaryIs><LinearRing><coordinates>${outerStr}</coordinates></LinearRing></outerBoundaryIs>`;
  (p.huecos||[]).forEach(h=>{const hs=h.map(c=>`${c[1]},${c[0]},0`).join(' ');polyXml+=`<innerBoundaryIs><LinearRing><coordinates>${hs}</coordinates></LinearRing></innerBoundaryIs>`;});
  polyXml+='</Polygon>';
  return `<?xml version="1.0" encoding="UTF-8"?>\n<kml xmlns="http://www.opengis.net/kml/2.2">\n<Document>\n<name>${escH(titulo)}</name>\n<Style id="sty"><LineStyle><color>ff${colorHex}</color><width>2.5</width></LineStyle><PolyStyle><color>4f${colorHex}</color></PolyStyle></Style>\n<Placemark>\n<name>${escH(titulo)}</name>\n<description><![CDATA[${htmlDesc}]]></description>\n${ext}\n<styleUrl>#sty</styleUrl>\n${polyXml}\n</Placemark>\n</Document>\n</kml>`;
}

function splitGenerarGeoJSON(p) {
  const outer=p.coords.map(c=>[c[1],c[0]]);
  if(outer.length&&(outer[0][0]!==outer[outer.length-1][0]||outer[0][1]!==outer[outer.length-1][1]))outer.push(outer[0]);
  const rings=[outer];
  (p.huecos||[]).forEach(h=>{const hc=h.map(c=>[c[1],c[0]]);if(hc.length&&(hc[0][0]!==hc[hc.length-1][0]||hc[0][1]!==hc[hc.length-1][1]))hc.push(hc[0]);rings.push(hc);});
  const props={nombre:p.nombre,Area_ha:p.area,Perimetro_km:calcPerim(p.coords)};
  (p.atrs||[]).forEach(a=>{if(a.k?.trim())props[a.k]=a.v;});
  return {type:'FeatureCollection',features:[{type:'Feature',geometry:{type:'Polygon',coordinates:rings},properties:props}]};
}

function splitExportarUnoKML(id) {
  const p=splitPoligonos.find(x=>x.id===id); if(!p)return;
  dlBlob(splitGenerarKML(p),'application/vnd.google-earth.kml+xml',sanit(p.nombre)+'.kml');
  toast(`✅ KML: ${p.nombre}`,'#10b981');
}
function splitExportarUnoGeoJSON(id) {
  const p=splitPoligonos.find(x=>x.id===id); if(!p)return;
  dlBlob(JSON.stringify(splitGenerarGeoJSON(p),null,2),'application/json',sanit(p.nombre)+'.geojson');
  toast(`✅ GeoJSON: ${p.nombre}`,'#38bdf8');
}
function splitExportarSelKML() {
  const sel=splitPoligonos.filter(p=>p.seleccionado);
  if(!sel.length){toast('⚠ Selecciona al menos uno','#f59e0b');return;}
  sel.forEach(p=>dlBlob(splitGenerarKML(p),'application/vnd.google-earth.kml+xml',sanit(p.nombre)+'.kml'));
  toast(`✅ ${sel.length} KML exportados`,'#10b981');
}
function splitExportarSelGeoJSON() {
  const sel=splitPoligonos.filter(p=>p.seleccionado);
  if(!sel.length){toast('⚠ Selecciona al menos uno','#f59e0b');return;}
  sel.forEach(p=>dlBlob(JSON.stringify(splitGenerarGeoJSON(p),null,2),'application/json',sanit(p.nombre)+'.geojson'));
  toast(`✅ ${sel.length} GeoJSON exportados`,'#38bdf8');
}
function splitExportarTodosKML() {
  if(!splitPoligonos.length){toast('⚠ Sin polígonos','#f59e0b');return;}
  splitPoligonos.forEach(p=>dlBlob(splitGenerarKML(p),'application/vnd.google-earth.kml+xml',sanit(p.nombre)+'.kml'));
  toast(`✅ ${splitPoligonos.length} KML exportados`,'#10b981');
}

/* ═══════════════════════════════════════════════════
   ★ HERRAMIENTA MEDICIÓN
═══════════════════════════════════════════════════ */
function toggleMedicion() {
  measureActive = !measureActive;
  document.getElementById('btnMedicion').classList.toggle('active', measureActive);
  if (measureActive) {
    mapa.getContainer().style.cursor = 'crosshair';
    document.getElementById('measureBar').classList.add('show');
    toast('📏 Clic para medir · Doble clic para cerrar polígono', '#fb923c');
  } else {
    limpiarMedicion();
    mapa.getContainer().style.cursor = '';
    document.getElementById('measureBar').classList.remove('show');
  }
}

function agregarPuntoMedicion(latlng) {
  measurePoints.push(latlng);
  const marker = L.circleMarker(latlng, { radius:5, color:'#fff', weight:2, fillColor:'#fb923c', fillOpacity:1 }).addTo(mapa);
  measureMarkers.push(marker);
  actualizarMedicion();
}

function actualizarMedicion() {
  if (measurePolyline) mapa.removeLayer(measurePolyline);
  if (measurePolygon) mapa.removeLayer(measurePolygon);
  if (measurePoints.length < 2) { document.getElementById('mbDist').textContent='0.000 km'; document.getElementById('mbArea').textContent='0.000 ha'; return; }
  measurePolyline = L.polyline(measurePoints, { color:'#fb923c', weight:2.5, dashArray:'6,4' }).addTo(mapa);
  // Distancia total
  let dist = 0;
  for (let i = 0; i < measurePoints.length - 1; i++) {
    dist += haversine([measurePoints[i].lat, measurePoints[i].lng], [measurePoints[i+1].lat, measurePoints[i+1].lng]);
  }
  document.getElementById('mbDist').textContent = (dist/1000).toFixed(3) + ' km';
  if (measurePoints.length >= 3) {
    const coords = measurePoints.map(p => [p.lat, p.lng]);
    measurePolygon = L.polygon(coords, { color:'#fb923c', weight:1.5, fillOpacity:0.12, dashArray:'4,3' }).addTo(mapa);
    document.getElementById('mbArea').textContent = calcArea(coords).toFixed(4) + ' ha';
  }
}

function cerrarMedicion() {
  if (measurePoints.length >= 3) {
    const coords = measurePoints.map(p => [p.lat, p.lng]);
    const area = calcArea(coords);
    const dist = measurePoints.reduce((s, p, i) => {
      if (i === 0) return 0;
      return s + haversine([measurePoints[i-1].lat, measurePoints[i-1].lng], [p.lat, p.lng]);
    }, 0);
    toast(`📐 Área: ${area.toFixed(4)} ha | Perímetro: ${(dist/1000).toFixed(3)} km`, '#10b981');
  }
}

function limpiarMedicion() {
  if (measurePolyline) { mapa.removeLayer(measurePolyline); measurePolyline = null; }
  if (measurePolygon) { mapa.removeLayer(measurePolygon); measurePolygon = null; }
  measureMarkers.forEach(m => { try { mapa.removeLayer(m); } catch(e) {} });
  measureMarkers = []; measurePoints = [];
  document.getElementById('mbDist').textContent = '0.000 km';
  document.getElementById('mbArea').textContent = '0.000 ha';
}

/* ═══════════════════════════════════════════════════
   ★ IR A COORDENADAS
═══════════════════════════════════════════════════ */
function abrirBuscarCoordenadas() { document.getElementById('coordsOv').classList.add('open'); }

function parsearCoordLibre(val) {
  const res = document.getElementById('coordParseResult');
  if (!val.trim()) { res.style.display='none'; return; }
  // Intentar decimal simple: -1.83, -79.96
  let m = val.match(/([-]?\d+\.?\d*)[,\s]+([-]?\d+\.?\d*)/);
  if (m) {
    const lat=parseFloat(m[1]), lng=parseFloat(m[2]);
    if(lat>=-90&&lat<=90&&lng>=-180&&lng<=180){
      document.getElementById('gotoLat').value=lat;
      document.getElementById('gotoLng').value=lng;
      res.innerHTML=`<b>Lat:</b> ${lat} &nbsp; <b>Lng:</b> ${lng}`;
      res.style.display='block'; return;
    }
  }
  // DMS: 1°49'54"S 79°58'8"W
  m = val.match(/(\d+)[°º](\d+)[\'′](\d+(?:\.\d+)?)[\"″]?\s*([NSns])[,\s]+(\d+)[°º](\d+)[\'′](\d+(?:\.\d+)?)[\"″]?\s*([EWew])/);
  if (m) {
    let lat=parseFloat(m[1])+parseFloat(m[2])/60+parseFloat(m[3])/3600;
    if(/[Ss]/.test(m[4]))lat=-lat;
    let lng=parseFloat(m[5])+parseFloat(m[6])/60+parseFloat(m[7])/3600;
    if(/[Ww]/.test(m[8]))lng=-lng;
    document.getElementById('gotoLat').value=lat.toFixed(6);
    document.getElementById('gotoLng').value=lng.toFixed(6);
    res.innerHTML=`<b>Lat:</b> ${lat.toFixed(6)} &nbsp; <b>Lng:</b> ${lng.toFixed(6)}`;
    res.style.display='block'; return;
  }
  res.innerHTML='<span style="color:#f87171">No se pudo parsear. Usa: -1.83, -79.96 o DMS</span>';
  res.style.display='block';
}

let _gotoMarker = null;
function irACoordenadas() {
  const lat=parseFloat(document.getElementById('gotoLat').value);
  const lng=parseFloat(document.getElementById('gotoLng').value);
  const zoom=parseInt(document.getElementById('gotoZoom').value)||16;
  if(isNaN(lat)||isNaN(lng)){toast('⚠ Coordenadas inválidas','#f59e0b');return;}
  mapa.setView([lat,lng],zoom);
  if(_gotoMarker)mapa.removeLayer(_gotoMarker);
  _gotoMarker=L.circleMarker([lat,lng],{radius:10,color:'#fff',weight:2.5,fillColor:'#38bdf8',fillOpacity:.9}).addTo(mapa);
  _gotoMarker.bindPopup(`<div style="font-family:'Space Mono',monospace;font-size:11px;color:#38bdf8;padding:6px 10px">${lat.toFixed(6)}, ${lng.toFixed(6)}</div>`).openPopup();
  setTimeout(()=>{if(_gotoMarker){mapa.removeLayer(_gotoMarker);_gotoMarker=null;}},8000);
  document.getElementById('coordsOv').classList.remove('open');
  toast(`📍 Navegando a ${lat.toFixed(4)}, ${lng.toFixed(4)}`,'#38bdf8');
}

/* ═══════════════════════════════════════════════════
   ★ CAMBIAR COLOR DE CAPA
═══════════════════════════════════════════════════ */
const PALETTE=['#38bdf8','#10b981','#f59e0b','#ef4444','#a78bfa','#fb923c','#34d399','#e879f9','#facc15','#60a5fa','#f472b6','#4ade80','#fff','#94a3b8'];

function abrirCambiarColor() {
  if(!capaSeleccionada){toast('⚠ Selecciona una capa','#f59e0b');return;}
  _colorTarget = capaSeleccionada;
  const c=capas[_colorTarget];
  document.getElementById('colorPicker').value = c?.color||'#38bdf8';
  const sw=document.getElementById('colorSwatches');
  sw.innerHTML=PALETTE.map(col=>`<div class="cswatch${c?.color===col?' sel':''}" style="background:${col}" onclick="document.getElementById('colorPicker').value='${col}';previewColor('${col}');document.querySelectorAll('.cswatch').forEach(x=>x.classList.remove('sel'));this.classList.add('sel')" title="${col}"></div>`).join('');
  document.getElementById('colorOv').classList.add('open');
}

function previewColor(color) {
  if(!_colorTarget||!capas[_colorTarget])return;
  capas[_colorTarget].layer.setStyle({color,fillColor:color});
}

function previewOpacity(val) {
  if(!_colorTarget||!capas[_colorTarget])return;
  capas[_colorTarget].layer.setStyle({fillOpacity:val/100});
}

function aplicarColor() {
  if(!_colorTarget||!capas[_colorTarget])return;
  const color=document.getElementById('colorPicker').value;
  const opacity=parseInt(document.getElementById('opacitySlider').value)/100;
  capas[_colorTarget].color=color;
  capas[_colorTarget].layer.setStyle({color,fillColor:color,fillOpacity:opacity});
  renderCapas(); renderAtributos(_colorTarget);
  document.getElementById('colorOv').classList.remove('open');
  toast('🎨 Color actualizado','#a78bfa');
}

/* ═══════════════════════════════════════════════════
   EDITOR DE POLÍGONOS
═══════════════════════════════════════════════════ */
function iniciarEdicionPoligono(id) {
  id = id || capaSeleccionada;
  if (!id) { toast('⚠ Selecciona una capa primero', '#f59e0b'); return; }
  const c = capas[id]; if (!c) return;
  mapa.closePopup();
  const poligonos = extraerPoligonosKML(c.kmlOriginal || generarKMLdesdeGeojson(c));
  if (!poligonos.length) { toast('⚠ No hay polígonos editables en esta capa', '#f59e0b'); return; }
  cancelarEdicion(true);
  editState.activo=true; editState.idCapa=id; editState.modoEdit='vertices';
  editState.poligonosEditados=poligonos.map(p=>({coords:[...p.coords.map(c=>[...c])],huecos:(p.huecos||[]).map(h=>[...h.map(c=>[...c])]),nombre:p.nombre,eliminado:false}));
  editState.layersEdicion=[]; editState.markerVertices=[]; editState.midMarkers=[];
  try{mapa.removeLayer(c.layer);c.activa=false;}catch(e){}
  renderizarPoligonosEdicion(); actualizarAreaEdicion();
  document.getElementById('pebNombre').textContent=c.displayName;
  document.getElementById('polyEditBar').classList.add('show');
  setModoEdit('vertices');
  document.querySelectorAll('.layer-item').forEach(el=>el.classList.remove('editing'));
  document.getElementById('li_'+id)?.classList.add('editing');
  toast('✏ Editor activado — arrastra vértices para modificar','#f59e0b');
}

function extraerPoligonosKML(kmlStr) {
  if(!kmlStr)return[];
  const doc=new DOMParser().parseFromString(kmlStr,'text/xml');
  const result=[];
  doc.querySelectorAll('Placemark').forEach(pm=>{
    const nombre=(pm.querySelector('name')?.textContent||'').trim();
    pm.querySelectorAll('Polygon').forEach(poly=>{
      const outerEl=poly.querySelector('outerBoundaryIs coordinates')||poly.querySelector('outerBoundaryIs LinearRing coordinates');
      if(!outerEl)return;
      const outerCoords=parsearCoords(outerEl.textContent||'');
      if(outerCoords.length<3)return;
      const huecos=[];
      poly.querySelectorAll('innerBoundaryIs').forEach(inner=>{const el=inner.querySelector('coordinates')||inner.querySelector('LinearRing coordinates');if(!el)return;const c=parsearCoords(el.textContent||'');if(c.length>=3)huecos.push(c);});
      result.push({coords:outerCoords,huecos,nombre});
    });
  });
  return result;
}

function parsearCoords(raw) {
  const coords=[];
  raw.trim().split(/\s+/).forEach(c=>{const p=c.split(',');if(p.length>=2){const lon=parseFloat(p[0]),lat=parseFloat(p[1]);if(!isNaN(lon)&&!isNaN(lat))coords.push([lat,lon]);}});
  return coords;
}

function renderizarPoligonosEdicion() {
  editState.layersEdicion.forEach(l=>{try{mapa.removeLayer(l);}catch(e){}});
  editState.markerVertices.forEach(m=>{try{mapa.removeLayer(m);}catch(e){}});
  (editState.midMarkers||[]).forEach(m=>{try{mapa.removeLayer(m);}catch(e){}});
  editState.layersEdicion=[]; editState.markerVertices=[]; editState.midMarkers=[];
  const c=capas[editState.idCapa];
  const baseColor=c?c.color:'#f59e0b';
  editState.poligonosEditados.forEach((poli,idx)=>{
    if(poli.eliminado)return;
    const latlngs=[poli.coords];
    if(poli.huecos?.length)poli.huecos.forEach(h=>latlngs.push(h));
    const layer=L.polygon(latlngs,{color:baseColor,weight:2.5,fillOpacity:0.2,fillColor:baseColor,dashArray:editState.modoEdit==='eliminar'?'8,4':null}).addTo(mapa);
    layer._poliIdx=idx;
    if(editState.modoEdit==='eliminar'){layer.on('click',function(e){L.DomEvent.stopPropagation(e);editState.poligonosEditados[idx].eliminado=true;renderizarPoligonosEdicion();actualizarAreaEdicion();toast('🗑 Polígono eliminado','#f59e0b');});}
    editState.layersEdicion.push(layer);
    if(editState.modoEdit==='vertices')renderizarVertices(poli,idx,layer,baseColor);
  });
}

function renderizarVertices(poli,poliIdx,polyLayer,color){
  const coords=poli.coords;
  coords.forEach((coord,vIdx)=>{
    const m=L.circleMarker([coord[0],coord[1]],{radius:8,color:'#fff',weight:2.5,fillColor:'#f59e0b',fillOpacity:1,bubblingMouseEvents:false}).addTo(mapa);
    m.bindTooltip(`V${vIdx+1}`,{permanent:false,direction:'top',offset:[0,-10],className:'vtx-tip'});
    m.on('mousedown',e=>{L.DomEvent.stopPropagation(e);if(e.originalEvent.button!==0)return;iniciarDrag(m,poliIdx,vIdx,polyLayer);});
    m.on('contextmenu',e=>{L.DomEvent.stopPropagation(e);eliminarVertice(poliIdx,vIdx);});
    editState.markerVertices.push(m);
  });
  const n=coords.length;
  for(let i=0;i<n;i++){
    const a=coords[i],b=coords[(i+1)%n];
    const midLat=(a[0]+b[0])/2,midLng=(a[1]+b[1])/2;
    const insertIdx=i+1;
    const mid=L.circleMarker([midLat,midLng],{radius:5,color:'#fff',weight:1.5,fillColor:'#10b981',fillOpacity:.65,bubblingMouseEvents:false}).addTo(mapa);
    mid.bindTooltip('+ agregar',{permanent:false,direction:'top',offset:[0,-8],className:'vtx-tip'});
    mid.on('click',e=>{L.DomEvent.stopPropagation(e);agregarVertice(poliIdx,insertIdx,midLat,midLng);});
    editState.midMarkers.push(mid);
  }
}

function iniciarDrag(marker,poliIdx,vIdx,polyLayer){
  mapa.dragging.disable();
  _dragState={marker,poliIdx,vIdx,polyLayer};
  mapa.on('mousemove',_onMove); mapa.on('mouseup',_onUp);
  document.addEventListener('mouseup',_onUp);
}
function _onMove(e){
  if(!_dragState)return;
  const{marker,poliIdx,vIdx}=_dragState;
  marker.setLatLng(e.latlng);
  editState.poligonosEditados[poliIdx].coords[vIdx]=[e.latlng.lat,e.latlng.lng];
  try{const p=editState.poligonosEditados[poliIdx];const ll=[p.coords];if(p.huecos?.length)p.huecos.forEach(h=>ll.push(h));_dragState.polyLayer.setLatLngs(ll);}catch(ex){}
  actualizarAreaEdicion();
}
function _onUp(){
  if(!_dragState)return;
  mapa.dragging.enable();mapa.off('mousemove',_onMove);mapa.off('mouseup',_onUp);
  document.removeEventListener('mouseup',_onUp);_dragState=null;
  renderizarPoligonosEdicion();actualizarAreaEdicion();
}
function agregarVertice(poliIdx,insertIdx,lat,lng){editState.poligonosEditados[poliIdx].coords.splice(insertIdx,0,[lat,lng]);renderizarPoligonosEdicion();actualizarAreaEdicion();}
function eliminarVertice(poliIdx,vIdx){
  const coords=editState.poligonosEditados[poliIdx].coords;
  if(coords.length<=3){toast('⚠ Mínimo 3 vértices','#f59e0b');return;}
  coords.splice(vIdx,1);renderizarPoligonosEdicion();actualizarAreaEdicion();
}
function manejarClickEliminar(idCapa,lyr){}
function setModoEdit(modo){
  editState.modoEdit=modo;
  document.querySelectorAll('.peb-btn.mode-btn').forEach(b=>b.classList.remove('on'));
  document.getElementById('btnModo'+modo.charAt(0).toUpperCase()+modo.slice(1))?.classList.add('on');
  const hints={vertices:'🟡 Arrastra → mover &nbsp;|&nbsp; 🟢 Clic verde → agregar &nbsp;|&nbsp; Clic derecho → eliminar',eliminar:'Haz clic sobre un polígono para eliminarlo'};
  document.getElementById('pebHint').innerHTML=hints[modo]||'';
  renderizarPoligonosEdicion();
}
function actualizarAreaEdicion(){
  let ha=0;
  editState.poligonosEditados.forEach(p=>{if(!p.eliminado&&p.coords.length>3)ha+=calcArea(p.coords);});
  document.getElementById('pebArea').innerHTML=`<i class="fa fa-ruler-combined"></i> ${ha.toFixed(4)} ha`;
  const c=capas[editState.idCapa];
  if(c?.geoInfo)c.geoInfo.area=ha;
}

function finalizarEdicion(){
  const id=editState.idCapa; const c=capas[id]; if(!c)return;
  const newKml=reconstruirKMLEditor();
  if(newKml){c.kmlOriginal=newKml;c.geoInfo=calcularGeoKML(newKml);c.atributos=rellenarCalcs(c.atributos||[],c.geoInfo);}
  cancelarEdicion(true);
  try{mapa.removeLayer(c.layer);}catch(e){}
  c.layer=omnivore.kml.parse(newKml||c.kmlOriginal,null,L.geoJson(null,{
    style:{color:c.color,weight:2.5,fillOpacity:0.22,fillColor:c.color},
    pointToLayer:(f,ll)=>L.circleMarker(ll,{radius:7,fillColor:c.color,color:'#fff',weight:2,fillOpacity:.9}),
    onEachFeature:(feature,lyr)=>{lyr.on('click',e=>{L.DomEvent.stopPropagation(e);if(!editState.activo)abrirPopup(feature,lyr,id);});}
  })).addTo(mapa);
  c.activa=true; renderCapas(); renderAtributos(id); toast('✅ Edición aplicada','#10b981');
}

function reconstruirKMLEditor(){
  const c=capas[editState.idCapa]; if(!c)return null;
  const atrs=(c.atributos||[]).filter(a=>a.k?.trim());
  const titulo=c.displayName||c.nombre||'';
  let rows=''; atrs.forEach((a,i)=>{const bg=i%2!==0?' bgcolor="#D4E4F3"':'';rows+=`<tr${bg}><td>${escH(a.k)}</td><td>${escH(a.v)}</td></tr>`;});
  const htmlDesc=`<table style="font-family:Arial;font-size:12px;width:100%;border-collapse:collapse;"><tr style="background:#9CBCE2;font-weight:bold;text-align:center;"><td colspan="2">${escH(titulo)}</td></tr>${rows}</table>`;
  const activos=editState.poligonosEditados.filter(p=>!p.eliminado);
  if(!activos.length)return null;
  const buildPoly=(poli)=>{
    const outerStr=poli.coords.map(c=>c[1]+','+c[0]+',0').join(' ');
    let xml=`<Polygon><outerBoundaryIs><LinearRing><coordinates>${outerStr}</coordinates></LinearRing></outerBoundaryIs>`;
    (poli.huecos||[]).forEach(h=>{const s=h.map(c=>c[1]+','+c[0]+',0').join(' ');xml+=`<innerBoundaryIs><LinearRing><coordinates>${s}</coordinates></LinearRing></innerBoundaryIs>`;});
    return xml+'</Polygon>';
  };
  const body=activos.length===1?buildPoly(activos[0]):'<MultiGeometry>'+activos.map(buildPoly).join('')+'</MultiGeometry>';
  const colorHex=(c.color||'#38bdf8').replace('#','');
  let extData='<ExtendedData>'; atrs.forEach(a=>{extData+=`<Data name="${escH(a.k)}"><value>${escH(a.v)}</value></Data>`;}); extData+='</ExtendedData>';
  return `<?xml version="1.0" encoding="UTF-8"?>\n<kml xmlns="http://www.opengis.net/kml/2.2">\n<Document>\n<name>${escH(titulo)}</name>\n<Style id="sty"><LineStyle><color>ff${colorHex}</color><width>2</width></LineStyle><PolyStyle><color>4f${colorHex}</color></PolyStyle></Style>\n<Placemark>\n  <name>${escH(titulo)}</name>\n  <description><![CDATA[${htmlDesc}]]></description>\n  ${extData}\n  <styleUrl>#sty</styleUrl>\n  ${body}\n</Placemark>\n</Document>\n</kml>`;
}

function cancelarEdicion(silencioso=false){
  editState.layersEdicion.forEach(l=>{try{mapa.removeLayer(l);}catch(e){}});
  editState.markerVertices.forEach(m=>{try{mapa.removeLayer(m);}catch(e){}});
  (editState.midMarkers||[]).forEach(m=>{try{mapa.removeLayer(m);}catch(e){}});
  if(_dragState){mapa.dragging.enable();mapa.off('mousemove',_onMove);mapa.off('mouseup',_onUp);_dragState=null;}
  if(editState.idCapa&&capas[editState.idCapa]){const c=capas[editState.idCapa];try{c.layer.addTo(mapa);c.activa=true;}catch(e){}}
  editState={activo:false,idCapa:null,modoEdit:'vertices',poligonosEditados:[],layersEdicion:[],markerVertices:[],midMarkers:[]};
  document.getElementById('polyEditBar').classList.remove('show');
  document.querySelectorAll('.layer-item').forEach(el=>el.classList.remove('editing'));
  if(!silencioso)toast('✖ Edición cancelada','#94a3b8');
}

/* ═══════════════════════════════════════════════════
   EXPORTAR KML / GeoJSON
═══════════════════════════════════════════════════ */
function exportarCapaKML(id){
  id=id||capaSeleccionada;if(!id){toast('⚠ Selecciona una capa','#f59e0b');return;}
  const c=capas[id];if(!c)return;
  let kml=c.kmlOriginal;
  if(!kml){kml=generarKMLdesdeAtributos(c);}
  dlBlob(kml,'application/vnd.google-earth.kml+xml',(c.displayName||'exportado')+'.kml');
  toast('✅ KML exportado','#10b981');
}

function generarKMLdesdeAtributos(c){
  const titulo=c.displayName||'Sin nombre';
  const atrs=(c.atributos||[]).filter(a=>a.k?.trim());
  let rows=''; atrs.forEach((a,i)=>{const bg=i%2!==0?' bgcolor="#D4E4F3"':'';rows+=`<tr${bg}><td>${escH(a.k)}</td><td>${escH(a.v)}</td></tr>`;});
  const htmlDesc=`<table style="font-family:Arial;font-size:12px;width:100%;border-collapse:collapse;"><tr style="background:#9CBCE2;font-weight:bold;text-align:center;"><td colspan="2">${escH(titulo)}</td></tr>${rows}</table>`;
  let extData='<ExtendedData>'; atrs.forEach(a=>{extData+=`<Data name="${escH(a.k)}"><value>${escH(a.v)}</value></Data>`;}); extData+='</ExtendedData>';
  const colorHex=(c.color||'#38bdf8').replace('#','');
  let geomStr='<Point><coordinates>0,0,0</coordinates></Point>';
  if(c.geojson?.features?.length){const feat=c.geojson.features[0];const geo=feat.geometry;if(geo?.type==='Polygon'){const coords=geo.coordinates[0].map(p=>p[0]+','+p[1]+',0').join(' ');geomStr=`<Polygon><outerBoundaryIs><LinearRing><coordinates>${coords}</coordinates></LinearRing></outerBoundaryIs></Polygon>`;}else if(geo?.type==='Point'){geomStr=`<Point><coordinates>${geo.coordinates[0]},${geo.coordinates[1]},0</coordinates></Point>`;}}
  return `<?xml version="1.0" encoding="UTF-8"?>\n<kml xmlns="http://www.opengis.net/kml/2.2">\n<Document>\n<name>${escH(titulo)}</name>\n<Style id="sty"><LineStyle><color>ff${colorHex}</color><width>2</width></LineStyle><PolyStyle><color>4f${colorHex}</color></PolyStyle></Style>\n<Placemark>\n  <name>${escH(titulo)}</name>\n  <description><![CDATA[${htmlDesc}]]></description>\n  ${extData}\n  <styleUrl>#sty</styleUrl>\n  ${geomStr}\n</Placemark>\n</Document>\n</kml>`;
}

function generarKMLdesdeGeojson(c){return c.kmlOriginal||generarKMLdesdeAtributos(c);}

function exportarCapaGeoJSON(id){
  id=id||capaSeleccionada;if(!id){toast('⚠ Selecciona una capa','#f59e0b');return;}
  const c=capas[id];if(!c)return;
  let geojson;
  if(c.geojson){geojson=c.geojson;}else{geojson=capasAGeoJSON(id);}
  if(!geojson){toast('❌ No se pudo generar GeoJSON','#ef4444');return;}
  dlBlob(JSON.stringify(geojson,null,2),'application/json',(c.displayName||'exportado')+'.geojson');
  toast('✅ GeoJSON exportado','#38bdf8');
}

function capasAGeoJSON(id){const c=capas[id];if(!c)return null;try{return c.layer.toGeoJSON();}catch(e){return null;}}

function rellenarCalcs(atrs,geo){
  return atrs.map(a=>{
    const t=a.tipo||'texto';
    if(t==='area'&&geo.area!==null)return{...a,v:String(geo.area)};
    if(t==='latitud'&&geo.latitud!==null)return{...a,v:String(parseFloat(geo.latitud.toFixed(6)))};
    if(t==='longitud'&&geo.longitud!==null)return{...a,v:String(parseFloat(geo.longitud.toFixed(6)))};
    if(t==='perimetro'&&geo.perimetro!==null)return{...a,v:String(geo.perimetro)};
    return a;
  });
}

/* ═══════════════════════════════════════════════════
   GUARDAR EN BD
═══════════════════════════════════════════════════ */
function abrirGuardarBD(id){
  id=id||capaSeleccionada;if(!id){toast('⚠ Selecciona una capa','#f59e0b');return;}
  bdSocioSel=null;bdCodigoOk=false;
  document.getElementById('bdBuscarSocio').value='';
  document.getElementById('bdListaSocios').style.display='none';
  document.getElementById('bdListaSocios').innerHTML='';
  document.getElementById('bdSocioInfo').style.display='none';
  document.getElementById('bdCodigo').value='';
  document.getElementById('bdDescripcion').value='';
  document.getElementById('bdSind').textContent='';
  document.getElementById('btnGuardarBD').disabled=true;
  document.getElementById('pvNombre').textContent=capas[id]?.displayName||'—';
  document.getElementById('pvFormato').textContent=capas[id]?.formato||'—';
  document.getElementById('pvCodigo').textContent='—';
  document.getElementById('pvSocio').textContent='—';
  document.getElementById('bdOv').classList.add('open');
  document.getElementById('bdOv').dataset.idCapa=id;
}
function cerrarBdModal(){document.getElementById('bdOv').classList.remove('open');}

let _bdSearch;
function buscarSociosBD(){
  const q=document.getElementById('bdBuscarSocio').value.trim();
  clearTimeout(_bdSearch);
  if(q.length<2){document.getElementById('bdListaSocios').style.display='none';return;}
  _bdSearch=setTimeout(async()=>{
    try{
      const r=await fetch(`ubicaciones_api.php?accion=buscar_socios&pagina=1&porPagina=10&q=${encodeURIComponent(q)}`);
      const j=await r.json();
      const lista=document.getElementById('bdListaSocios');
      if(!j.success||!j.datos?.length){lista.innerHTML='<div style="padding:8px 12px;font-size:12px;color:#475569;">Sin resultados</div>';lista.style.display='block';return;}
      lista.innerHTML=j.datos.map(s=>`<div onclick="seleccionarSocioBD(${s.id_socio},'${escJ(s.nombre_completo||s.identificacion)}','${escJ(s.identificacion)}','${escJ(s.zona||'')}','${escJ(s.codigo_slc||'')}')" style="padding:9px 12px;cursor:pointer;border-bottom:1px solid rgba(255,255,255,.06);transition:background .12s;font-size:12px;" onmouseover="this.style.background='rgba(56,189,248,.08)'" onmouseout="this.style.background='transparent'"><b style="color:#e2e8f0;">${esc(s.nombre_completo||s.identificacion)}</b><span style="color:#475569;margin-left:8px;font-family:'Space Mono',monospace;font-size:10px;">${esc(s.identificacion)}</span>${s.zona?'<span style="color:#334155;margin-left:6px;font-size:10px;">· '+esc(s.zona)+'</span>':''}</div>`).join('');
      lista.style.display='block';
    }catch(e){}
  },350);
}

async function seleccionarSocioBD(id,nombre,cedula,zona,codigoBase){
  bdSocioSel={id,nombre,cedula,zona,codigoBase};
  document.getElementById('bdListaSocios').style.display='none';
  document.getElementById('bdBuscarSocio').value=nombre;
  document.getElementById('bdSocioNombre').textContent=nombre;
  document.getElementById('bdSocioCedula').textContent=cedula;
  document.getElementById('bdSocioZona').textContent=zona||'—';
  document.getElementById('bdSocioBase').textContent=codigoBase||'—';
  document.getElementById('pvSocio').textContent=nombre;
  document.getElementById('bdSocioInfo').style.display='block';
  try{
    const r=await fetch(`ubicaciones_api.php?accion=listar&id_socio=${id}`);
    const j=await r.json();
    const existentes=j.success?j.datos:[];
    const siguienteLote=existentes.length+1;
    const sugerido=codigoBase?`${codigoBase}_${siguienteLote}`:'';
    document.getElementById('bdCodigo').value=sugerido.toUpperCase();
    const r2=await fetch('ubicaciones_api.php?accion=buscar_socios&pagina=1&porPagina=9999');
    const j2=await r2.json();
    const usados=new Set();
    if(j2.success){for(const s of j2.datos){const r3=await fetch(`ubicaciones_api.php?accion=listar&id_socio=${s.id_socio}`);const j3=await r3.json();if(j3.success)j3.datos.forEach(a=>{const m=(a.codigo_archivo||'').match(/^SLC-(\d+)/i);if(m)usados.add(parseInt(m[1]));});}}
    let maxNum=0; usados.forEach(n=>{if(n>maxNum)maxNum=n;});
    const libres=[]; for(let i=1;i<=maxNum+5;i++){if(!usados.has(i))libres.push(`SLC-${String(i).padStart(3,'0')}`);}
    const sugerencias=[];
    for(let l=1;l<=5;l++){const cod=codigoBase?`${codigoBase}_${existentes.length+l}`:null;if(cod)sugerencias.push(cod.toUpperCase());}
    document.getElementById('bdCodesDisp').innerHTML=[
      ...sugerencias.map(c=>`<span class="bd-code-chip" onclick="usarCodigo('${c}')">${c}</span>`),
      ...libres.slice(0,8).map(c=>`<span class="bd-code-chip" style="opacity:.6" onclick="usarCodigo('${c}')">+ ${c}</span>`)
    ].join('')||'<span style="color:#334155;font-size:11px;">Sin sugerencias</span>';
    if(sugerido)validarCodigoBD();
  }catch(e){console.error(e);}
}

function usarCodigo(cod){document.getElementById('bdCodigo').value=cod;validarCodigoBD();}

let _vTimer;
function validarCodigoBD(){
  const val=(document.getElementById('bdCodigo').value||'').trim().toUpperCase();
  document.getElementById('bdCodigo').value=val;
  document.getElementById('pvCodigo').textContent=val||'—';
  const hint=document.getElementById('bdCodigoHint');
  const formatOk=/^SLC-\d{3}_\d+$/.test(val);
  if(!val){hint.innerHTML='<span style="color:#f87171;">⚠ Requerido</span>';bdCodigoOk=false;actualizarBtnBD();return;}
  if(!formatOk){hint.innerHTML='<span style="color:#f87171;">⚠ Formato inválido (SLC-NNN_L)</span>';bdCodigoOk=false;actualizarBtnBD();return;}
  hint.innerHTML='<i class="fa fa-spinner fa-spin" style="color:#94a3b8;font-size:10px;"></i> Verificando...';
  clearTimeout(_vTimer);
  _vTimer=setTimeout(async()=>{
    try{
      const r=await fetch(`ubicaciones_api.php?accion=validar_codigo&codigo=${encodeURIComponent(val)}`);
      const j=await r.json();
      if(j.existe){hint.innerHTML=`<span style="color:#f87171;">⚠ Ya existe (${j.socio||'otro socio'})</span>`;bdCodigoOk=false;}
      else{hint.innerHTML='<span style="color:#10b981;">✓ Código disponible</span>';bdCodigoOk=true;}
    }catch(e){hint.innerHTML='<span style="color:#94a3b8;">No verificado</span>';bdCodigoOk=true;}
    actualizarBtnBD();
  },500);
}

function actualizarBtnBD(){document.getElementById('btnGuardarBD').disabled=!(bdSocioSel&&bdCodigoOk);}

async function guardarEnBD(){
  const id=document.getElementById('bdOv').dataset.idCapa;
  const c=capas[id];if(!c||!bdSocioSel)return;
  const codigo=document.getElementById('bdCodigo').value.trim().toUpperCase();
  const descripcion=document.getElementById('bdDescripcion').value.trim();
  const sind=document.getElementById('bdSind'); const btn=document.getElementById('btnGuardarBD');
  btn.disabled=true;sind.textContent='Guardando...';sind.className='sind';
  let kml=c.kmlOriginal||generarKMLdesdeAtributos(c);
  const fd=new FormData();
  fd.append('accion','subir_desde_conversor');fd.append('id_socio',bdSocioSel.id);
  fd.append('codigo_archivo',codigo);fd.append('descripcion',descripcion);
  fd.append('atributos',JSON.stringify(c.atributos||[]));fd.append('color',c.color||'#38bdf8');
  fd.append('titulo_aviso',c.displayName||'');fd.append('kml_content',kml);
  fd.append('formato_origen',c.formato||'KML');
  try{
    const r=await fetch('ubicaciones_api.php',{method:'POST',body:fd});
    const j=await r.json();
    if(j.success){sind.textContent='✓ Guardado en BD';sind.className='sind ok';toast('✅ Guardado en base de datos','#10b981');setTimeout(()=>cerrarBdModal(),1200);}
    else{sind.textContent='✗ '+(j.message||'Error');sind.className='sind err';toast('❌ '+(j.message||'Error al guardar'),'#ef4444');btn.disabled=false;}
  }catch(e){sind.textContent='✗ Error de red';sind.className='sind err';btn.disabled=false;}
}

/* ═══════════════════════════════════════════════════
   DRAG & DROP
═══════════════════════════════════════════════════ */
function dzOver(e){e.preventDefault();document.getElementById('dropZone').classList.add('drag');}
function dzLeave(e){document.getElementById('dropZone').classList.remove('drag');}
function dzDrop(e){e.preventDefault();document.getElementById('dropZone').classList.remove('drag');if(e.dataTransfer.files.length)onFilesSelected(e.dataTransfer.files);}

document.addEventListener('DOMContentLoaded',()=>{
  const mapEl=document.getElementById('mapaQgis');
  if(mapEl){
    mapEl.addEventListener('dragover',e=>e.preventDefault());
    mapEl.addEventListener('drop',e=>{e.preventDefault();if(e.dataTransfer.files.length)onFilesSelected(e.dataTransfer.files);});
  }
});

/* ═══════════════════════════════════════════════════
   SIDEBAR / LOADING / UTILS
═══════════════════════════════════════════════════ */
function abrirSidebar(){document.querySelector('.sidebar,nav.sidebar,aside.sidebar')?.classList.add('sb-open');document.getElementById('sbOverlay').classList.add('open');}
function cerrarSidebar(){document.querySelector('.sidebar,nav.sidebar,aside.sidebar')?.classList.remove('sb-open');document.getElementById('sbOverlay').classList.remove('open');}

function setLoading(show,text,pct){
  const ov=document.getElementById('loadingOverlay');const bar=document.getElementById('loadingBar');const txt=document.getElementById('loadingText');
  if(show){ov.classList.remove('done');if(text)txt.textContent=text;if(pct!==undefined)bar.style.width=pct+'%';}
  else{bar.style.width='100%';setTimeout(()=>ov.classList.add('done'),300);}
}

function esc(s){return(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function escH(s){return(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
function escJ(s){return(s||'').replace(/'/g,"\\'").replace(/"/g,'\\"');}
function sanit(s){return(s||'poligono').replace(/[^a-zA-Z0-9_\-\.áéíóúÁÉÍÓÚñÑ ]/g,'_').replace(/\s+/g,'_');}

let _tt;
function toast(msg,bg){
  const el=document.getElementById('toast-q');
  el.textContent=msg;el.style.borderColor=bg||'rgba(255,255,255,.12)';
  el.classList.add('show');clearTimeout(_tt);
  _tt=setTimeout(()=>el.classList.remove('show'),3500);
}

function dlBlob(content,mime,filename){
  const blob=new Blob([content],{type:mime});
  const url=URL.createObjectURL(blob);
  const a=document.createElement('a');a.href=url;a.download=filename;a.click();
  URL.revokeObjectURL(url);
}
</script>
</body>
</html>