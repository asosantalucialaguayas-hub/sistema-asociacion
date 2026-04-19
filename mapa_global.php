<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require "config/conexion.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Mapa Global KML — Asociación</title>
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/modal-message.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<?php include 'layout/modals.php'; ?>
<style>
:root{--amber:#f59e0b;--red:#ef4444;--green:#10b981;--sky:#38bdf8;--gray900:#0f172a;--purple:#a78bfa;--orange:#fb923c}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--gray900);color:#e2e8f0;overflow:hidden}
.app{display:flex;height:100vh;overflow:hidden}
.sidebar,nav.sidebar,aside.sidebar{position:fixed!important;top:0;left:0;height:100vh;overflow-y:auto;flex-shrink:0;z-index:99999!important;transform:translateX(-100%);transition:transform .3s cubic-bezier(.4,0,.2,1);box-shadow:4px 0 32px rgba(0,0,0,.5)}
.sidebar.sb-open,nav.sidebar.sb-open,aside.sidebar.sb-open{transform:translateX(0)}
#sbOverlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99998;backdrop-filter:blur(2px)}
#sbOverlay.open{display:block}
#btnSidebar{position:fixed;top:14px;left:14px;z-index:99997;width:38px;height:38px;border-radius:10px;background:rgba(15,23,42,.88);border:1px solid rgba(255,255,255,.12);color:#94a3b8;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:15px;backdrop-filter:blur(8px);transition:all .18s;box-shadow:0 4px 14px rgba(0,0,0,.4)}
#btnSidebar:hover{background:rgba(56,189,248,.2);border-color:#38bdf8;color:#38bdf8}
.map-workspace{flex:1;display:flex;flex-direction:column;height:100vh;overflow:hidden;position:relative}
.map-topbar{display:flex;align-items:center;gap:8px;padding:7px 14px 7px 60px;background:rgba(15,23,42,.97);backdrop-filter:blur(12px);border-bottom:1px solid rgba(255,255,255,.07);z-index:1000;flex-shrink:0;flex-wrap:wrap}
.map-title{font-family:'Space Mono',monospace;font-size:12px;font-weight:700;color:#38bdf8;letter-spacing:1px;text-transform:uppercase;white-space:nowrap;display:flex;align-items:center;gap:8px}
.map-title .dot{width:8px;height:8px;border-radius:50%;background:#10b981;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.4)}}
.topbar-sep{width:1px;height:26px;background:rgba(255,255,255,.1);flex-shrink:0}
.tool-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:7px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.07);color:#cbd5e1;font-size:11px;font-weight:600;cursor:pointer;transition:all .18s;white-space:nowrap;font-family:'DM Sans',sans-serif}
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
.tool-btn.orange{background:rgba(251,146,60,.15);border-color:var(--orange);color:var(--orange)}
.tool-btn.orange:hover{background:rgba(251,146,60,.25)}
.badge-count{background:#38bdf8;color:#0f172a;font-size:10px;font-weight:800;padding:1px 6px;border-radius:999px;font-family:'Space Mono',monospace}
#mapaGlobal{flex:1;width:100%;z-index:1}

/* ── PANEL ── */
.side-panel{position:absolute;top:54px;left:8px;bottom:8px;width:308px;background:rgba(13,17,28,.96);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.08);border-radius:14px;z-index:900;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 16px 56px rgba(0,0,0,.5);transition:transform .3s ease,opacity .3s ease}
.side-panel.hidden{transform:translateX(-340px);opacity:0;pointer-events:none}
.panel-header{padding:10px 13px 8px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.panel-header h3{font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:#94a3b8;display:flex;align-items:center;gap:7px}
.panel-header h3 i{color:#38bdf8}
.panel-search{padding:7px 10px;border-bottom:1px solid rgba(255,255,255,.06);flex-shrink:0;position:relative}
.panel-search input{width:100%;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:7px;padding:6px 10px 6px 27px;color:#e2e8f0;font-size:12px;font-family:'DM Sans',sans-serif;outline:none}
.panel-search input:focus{border-color:#38bdf8}
.panel-search input::placeholder{color:#334155}
.ps-icon{position:absolute;left:19px;top:50%;transform:translateY(-50%);color:#475569;font-size:11px;pointer-events:none}
.layers-list{flex:1;overflow-y:auto;padding:5px}
.layers-list::-webkit-scrollbar{width:3px}
.layers-list::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:4px}
.socio-group{margin-bottom:5px;border-radius:9px;overflow:hidden;border:1px solid rgba(255,255,255,.06)}
.socio-group-header{background:rgba(56,189,248,.07);padding:7px 10px;cursor:pointer;display:flex;align-items:center;gap:6px;transition:background .15s}
.socio-group-header:hover{background:rgba(56,189,248,.13)}
.sg-chevron{font-size:9px;color:#475569;transition:transform .2s;flex-shrink:0}
.socio-group.collapsed .sg-chevron{transform:rotate(-90deg)}
.sg-title{font-size:11px;font-weight:700;color:#38bdf8;flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:flex;align-items:center;gap:5px}
.sg-badge{background:rgba(56,189,248,.2);color:#38bdf8;font-size:9px;font-weight:800;padding:2px 6px;border-radius:999px;flex-shrink:0}
.sg-body{background:rgba(0,0,0,.12);padding:3px;display:none}
.sg-body.open{display:block}
.layer-item{display:flex;align-items:center;gap:6px;padding:5px 7px;border-radius:7px;cursor:pointer;border:1px solid transparent;margin-bottom:2px;transition:all .15s}
.layer-item:hover{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.08)}
.layer-item.selected{background:rgba(56,189,248,.1);border-color:rgba(56,189,248,.3)}
.layer-item.editing{background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.4)!important}
.l-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;border:2px solid rgba(255,255,255,.15)}
.l-info{flex:1;min-width:0}
.l-name{font-size:11px;font-weight:600;color:#e2e8f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.l-sub{font-size:9px;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px}
.l-acts{display:flex;gap:2px;flex-shrink:0}
.lbtn{width:20px;height:20px;border-radius:4px;border:none;background:rgba(255,255,255,.06);color:#94a3b8;cursor:pointer;font-size:9px;display:flex;align-items:center;justify-content:center;transition:all .15s}
.lbtn:hover{background:rgba(255,255,255,.15);color:#fff}
.lbtn.eb:hover{background:rgba(245,158,11,.2);color:var(--amber)}
.lbtn.zb:hover{background:rgba(56,189,248,.2);color:#38bdf8}
.lbtn.editbtn:hover{background:rgba(167,139,250,.2);color:var(--purple)}
.tswitch{width:22px;height:13px;background:#1e293b;border-radius:999px;cursor:pointer;transition:background .2s;position:relative;flex-shrink:0;border:1px solid rgba(255,255,255,.1)}
.tswitch.on{background:#10b981;border-color:#10b981}
.tswitch::after{content:'';position:absolute;top:1px;left:1px;width:9px;height:9px;border-radius:50%;background:#fff;transition:transform .2s}
.tswitch.on::after{transform:translateX(9px)}
.ppag{padding:6px 10px;border-top:1px solid rgba(255,255,255,.06);display:flex;align-items:center;justify-content:center;gap:3px;flex-shrink:0}
.ppbtn{padding:3px 7px;border-radius:5px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:#94a3b8;font-size:10px;font-weight:600;cursor:pointer;transition:all .15s;font-family:'Space Mono',monospace;min-width:26px;text-align:center}
.ppbtn:hover{background:rgba(255,255,255,.12);color:#fff}
.ppbtn.active{background:#38bdf8;color:#0f172a;border-color:#38bdf8}
.ppbtn:disabled{opacity:.35;cursor:not-allowed}
.pstats{padding:4px 12px;border-top:1px solid rgba(255,255,255,.04);flex-shrink:0;font-size:10px;color:#334155;font-family:'Space Mono',monospace;text-align:center}

/* ── LOADING ── */
.lov{position:absolute;inset:0;background:rgba(10,14,26,.93);backdrop-filter:blur(10px);z-index:9999;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;transition:opacity .5s}
.lov.done{opacity:0;pointer-events:none}
.lbar{width:260px;height:5px;background:rgba(255,255,255,.1);border-radius:4px;overflow:hidden}
.lbar-inner{height:100%;background:linear-gradient(90deg,#38bdf8,#10b981);border-radius:4px;width:0%;transition:width .3s ease}
.ltxt{font-family:'Space Mono',monospace;font-size:11px;color:#94a3b8;letter-spacing:.5px}

/* ── STATUS ── */
.stbar{position:absolute;bottom:12px;right:12px;display:flex;gap:6px;z-index:900;flex-direction:column;align-items:flex-end}
.stchip{background:rgba(13,17,28,.92);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.1);border-radius:7px;padding:4px 10px;font-size:10.5px;color:#94a3b8;font-family:'Space Mono',monospace}
.stchip b{color:#38bdf8}

/* ══ POPUP ══ */
.leaflet-popup-content-wrapper{background:#0d1117!important;border:1px solid rgba(56,189,248,.25)!important;border-radius:12px!important;box-shadow:0 16px 48px rgba(0,0,0,.7)!important;color:#e2e8f0!important;padding:0!important;overflow:hidden!important}
.leaflet-popup-tip{background:#0d1117!important}
.leaflet-popup-close-button{color:#475569!important;font-size:20px!important;top:8px!important;right:10px!important;z-index:10!important}
.leaflet-popup-close-button:hover{color:#fff!important}
.leaflet-popup-content{margin:0!important;font-family:'DM Sans',sans-serif!important;min-width:280px;max-width:370px}
.pu-wrap{overflow:hidden}
.pu-head{background:linear-gradient(135deg,rgba(31,58,95,.9),rgba(13,148,136,.3));padding:11px 36px 9px 13px;border-bottom:1px solid rgba(255,255,255,.08)}
.pu-code{font-size:9px;color:#475569;font-family:'Space Mono',monospace;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}
.pu-title{font-weight:700;font-size:13px;color:#38bdf8;line-height:1.3}
.pu-socio{font-size:11px;color:#94a3b8;margin-top:3px;display:flex;align-items:center;gap:5px}
.pu-body{padding:9px 11px;max-height:230px;overflow-y:auto}
.pu-body::-webkit-scrollbar{width:3px}
.pu-body::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1)}
.pu-table{width:100%;border-collapse:collapse;font-size:11.5px}
.pu-table tr{border-bottom:1px solid rgba(255,255,255,.05)}
.pu-table tr:last-child{border-bottom:none}
.pu-table td{padding:4px 6px;vertical-align:top}
.pu-table td:first-child{color:#64748b;font-weight:700;text-transform:uppercase;font-size:10px;white-space:nowrap;width:36%;letter-spacing:.3px}
.pu-table td:last-child{color:#cbd5e1;word-break:break-word}
.pu-table tr:nth-child(even) td{background:rgba(255,255,255,.025)}
.pu-no-desc{color:#334155;font-size:11px;font-style:italic;padding:6px 0}
.pu-foot{display:flex;gap:5px;padding:7px 11px;background:rgba(0,0,0,.25);align-items:center;border-top:1px solid rgba(255,255,255,.06);flex-wrap:wrap}
.pu-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;border:1px solid;transition:all .15s}
.pu-btn-edit{background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.35);color:var(--amber)}
.pu-btn-edit:hover{background:rgba(245,158,11,.24)}
.pu-btn-poly{background:rgba(167,139,250,.12);border-color:rgba(167,139,250,.35);color:var(--purple)}
.pu-btn-poly:hover{background:rgba(167,139,250,.24)}
.pu-btn-zoom{background:rgba(56,189,248,.1);border-color:rgba(56,189,248,.3);color:#38bdf8}
.pu-btn-zoom:hover{background:rgba(56,189,248,.2)}
.pu-btn-dl{background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#10b981}
.pu-btn-dl:hover{background:rgba(16,185,129,.2)}

/* ══ BARRA EDITOR POLÍGONO ══ */
#polyEditBar{display:none;position:absolute;bottom:18px;left:50%;transform:translateX(-50%);z-index:1100;
  background:rgba(10,14,26,.97);border:1px solid rgba(245,158,11,.4);border-radius:14px;
  padding:10px 16px;box-shadow:0 8px 40px rgba(0,0,0,.7);backdrop-filter:blur(16px);
  flex-direction:column;align-items:center;gap:8px;min-width:520px}
#polyEditBar.show{display:flex}
.peb-title{font-family:'Space Mono',monospace;font-size:11px;color:var(--amber);font-weight:700;letter-spacing:.5px;display:flex;align-items:center;gap:8px}
.peb-tools{display:flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:center}
.peb-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:8px;border:1px solid;font-size:11px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s;white-space:nowrap}
.peb-btn.mode-btn{background:rgba(255,255,255,.07);border-color:rgba(255,255,255,.15);color:#cbd5e1}
.peb-btn.mode-btn:hover,.peb-btn.mode-btn.on{background:rgba(245,158,11,.18);border-color:var(--amber);color:var(--amber)}
.peb-btn.save-kml{background:rgba(16,185,129,.15);border-color:#10b981;color:#10b981}
.peb-btn.save-kml:hover{background:rgba(16,185,129,.28)}
.peb-btn.save-bd{background:rgba(56,189,248,.15);border-color:#38bdf8;color:#38bdf8}
.peb-btn.save-bd:hover{background:rgba(56,189,248,.28)}
.peb-btn.cancel{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.12);color:#94a3b8}
.peb-btn.cancel:hover{background:rgba(255,255,255,.12);color:#fff}

.peb-area{font-family:'Space Mono',monospace;font-size:12px;color:#10b981;font-weight:700;padding:4px 12px;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);border-radius:6px;min-width:150px;text-align:center}
.peb-sep{width:1px;height:28px;background:rgba(255,255,255,.1)}
.peb-hint{font-size:10px;color:#475569;font-family:'Space Mono',monospace;text-align:center;max-width:500px}

/* Tooltip de vértices */
.vtx-tip{background:#0f172a!important;border:1px solid rgba(56,189,248,.3)!important;color:#e2e8f0!important;font-family:'Space Mono',monospace!important;font-size:10px!important;padding:2px 7px!important;border-radius:5px!important;box-shadow:none!important;white-space:nowrap}
.vtx-tip::before{display:none!important}

/* ══ MODAL EDITOR ATRIBUTOS ══ */
.med-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.72);z-index:99999;align-items:center;justify-content:center;backdrop-filter:blur(6px)}
.med-ov.open{display:flex}
.med-box{background:#0a0e1a;border:1px solid rgba(255,255,255,.1);border-radius:16px;width:95%;max-width:730px;max-height:92vh;overflow:hidden;box-shadow:0 40px 100px rgba(0,0,0,.7);display:flex;flex-direction:column}
.med-head{padding:14px 20px 12px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.med-head h2{font-size:14px;font-weight:700;color:#f1f5f9;display:flex;align-items:center;gap:9px}
.med-head h2 i{color:var(--amber)}
.med-tabs{display:flex;gap:0;border-bottom:1px solid rgba(255,255,255,.08);flex-shrink:0;padding:0 20px}
.med-tab{padding:8px 16px;font-size:11px;font-weight:700;color:#475569;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;transition:all .15s;letter-spacing:.3px;text-transform:uppercase}
.med-tab.active{color:#38bdf8;border-bottom-color:#38bdf8}
.med-tab:hover:not(.active){color:#94a3b8}
.med-body{flex:1;overflow-y:auto;padding:16px 20px}
.med-body::-webkit-scrollbar{width:4px}
.med-body::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:4px}
.med-foot{padding:12px 20px;border-top:1px solid rgba(255,255,255,.08);display:flex;justify-content:flex-end;gap:10px;flex-shrink:0;align-items:center}
.tc{display:none}.tc.active{display:block}
.fl{display:block;font-size:10px;font-weight:700;color:#64748b;letter-spacing:.6px;text-transform:uppercase;margin-bottom:5px}
.fi{width:100%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:7px 11px;color:#e2e8f0;font-size:13px;font-family:'DM Sans',sans-serif;outline:none;transition:border-color .15s}
.fi:focus{border-color:#38bdf8;background:rgba(56,189,248,.04)}
.fi[readonly]{opacity:.55;cursor:not-allowed}
.fr{margin-bottom:12px}
.fg{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px}
.sdiv{display:flex;align-items:center;gap:8px;margin:15px 0 11px;font-size:10px;font-weight:700;color:#334155;letter-spacing:.8px;text-transform:uppercase}
.sdiv::before,.sdiv::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.07)}
.at-wrap{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:10px;overflow:hidden;margin-bottom:12px}
.at-head{display:grid;grid-template-columns:1fr 1.3fr 90px 28px;background:rgba(56,189,248,.1);border-bottom:1px solid rgba(255,255,255,.08)}
.at-head span{font-size:9.5px;font-weight:700;color:#38bdf8;letter-spacing:.5px;text-transform:uppercase;padding:6px 8px}
.at-row{display:grid;grid-template-columns:1fr 1.3fr 90px 28px;border-bottom:1px solid rgba(255,255,255,.05)}
.at-row:last-child{border-bottom:none}
.at-row:nth-child(even){background:rgba(255,255,255,.02)}
.at-cell{padding:3px 4px;display:flex;align-items:center}
.at-cell input,.at-cell select{width:100%;background:transparent;border:none;border-radius:5px;padding:3px 5px;color:#e2e8f0;font-size:11.5px;font-family:'DM Sans',sans-serif;outline:none;transition:background .15s}
.at-cell input:focus{background:rgba(56,189,248,.07)}
.at-cell select{color:#94a3b8;font-size:10.5px;cursor:pointer}
.at-cell select option{background:#1e293b;color:#e2e8f0}
.at-cell input.calc-val{color:#10b981;cursor:default!important}
.at-del{width:22px;height:22px;border-radius:4px;border:none;background:transparent;color:#334155;cursor:pointer;font-size:10px;display:flex;align-items:center;justify-content:center;transition:all .15s}
.at-del:hover{background:rgba(239,68,68,.15);color:#f87171}
.at-add{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:7px;background:rgba(56,189,248,.05);border:1px dashed rgba(56,189,248,.2);border-radius:0 0 9px 9px;color:#38bdf8;font-size:11px;font-weight:600;cursor:pointer;transition:all .15s;font-family:'DM Sans',sans-serif}
.at-add:hover{background:rgba(56,189,248,.1)}
.csrow{display:flex;gap:7px;flex-wrap:wrap}
.csw{width:24px;height:24px;border-radius:50%;cursor:pointer;border:3px solid transparent;transition:transform .15s,border-color .15s}
.csw:hover{transform:scale(1.15)}
.csw.sel{border-color:#fff;transform:scale(1.08)}
.prev-box{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:12px;font-family:'Space Mono',monospace;font-size:10.5px;color:#94a3b8;line-height:1.6;max-height:240px;overflow-y:auto;white-space:pre-wrap;word-break:break-all}
.btn-cancel{padding:8px 16px;border-radius:8px;border:1px solid rgba(255,255,255,.1);background:transparent;color:#94a3b8;cursor:pointer;font-size:13px;font-family:'DM Sans',sans-serif;font-weight:600}
.btn-cancel:hover{background:rgba(255,255,255,.06);color:#e2e8f0}
.btn-save{padding:8px 22px;border-radius:8px;border:none;background:linear-gradient(135deg,#0d9488,#0891b2);color:#fff;cursor:pointer;font-size:13px;font-family:'DM Sans',sans-serif;font-weight:700;box-shadow:0 4px 14px rgba(13,148,136,.35);display:flex;align-items:center;gap:7px}
.btn-save:hover{opacity:.88}
.btn-save:disabled{opacity:.45;cursor:not-allowed}
.sind{font-size:11px;color:#94a3b8;font-family:'Space Mono',monospace;margin-right:auto}
.sind.ok{color:#10b981}.sind.err{color:#f87171}
.btn-prev{padding:6px 13px;border-radius:7px;border:1px solid rgba(56,189,248,.3);background:rgba(56,189,248,.08);color:#38bdf8;cursor:pointer;font-size:11px;font-weight:700;font-family:'DM Sans',sans-serif;display:inline-flex;align-items:center;gap:5px}
.btn-prev:hover{background:rgba(56,189,248,.18)}
.btn-prev.green{border-color:rgba(16,185,129,.3);background:rgba(16,185,129,.08);color:#10b981}
.btn-prev.green:hover{background:rgba(16,185,129,.18)}
.calc-chip{cursor:pointer;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);color:#10b981;padding:3px 9px;border-radius:5px;font-size:10.5px;font-weight:700;transition:all .15s}
.calc-chip:hover{background:rgba(16,185,129,.2)}
#toast-g{position:fixed;bottom:18px;left:50%;transform:translateX(-50%) translateY(80px);background:#1e293b;border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:10px 22px;font-size:13px;font-weight:600;color:#fff;z-index:999999;transition:transform .3s,opacity .3s;opacity:0;white-space:nowrap;box-shadow:0 8px 32px rgba(0,0,0,.4);pointer-events:none}
#toast-g.show{transform:translateX(-50%) translateY(0);opacity:1}


</style>
</head>
<body>
<script src="layout/modal-message.js"></script>
<div class="app">
  <div id="sbOverlay" onclick="cerrarSidebar()"></div>
  <button id="btnSidebar" onclick="abrirSidebar()"><i class="fa fa-bars"></i></button>
  <?php include __DIR__ . "/layout/sidebar.php"; ?>

  <div class="map-workspace" id="mapWorkspace">
    <div class="map-topbar">
      <div class="map-title"><span class="dot"></span><i class="fa fa-globe"></i> MAPA GLOBAL KML</div>
      <div class="topbar-sep"></div>
      <button class="tool-btn active" id="btnCapa" onclick="ciclarCapa()"><i class="fa fa-layer-group"></i> <span id="lblCapa">Satélite</span></button>
      <div class="topbar-sep"></div>
      <button class="tool-btn" onclick="mapa&&mapa.zoomIn()"><i class="fa fa-plus"></i></button>
      <button class="tool-btn" onclick="mapa&&mapa.zoomOut()"><i class="fa fa-minus"></i></button>
      <button class="tool-btn" onclick="centrarTodo()"><i class="fa fa-crosshairs"></i> Centrar</button>
      <div class="topbar-sep"></div>
      <button class="tool-btn" id="btnPanel" onclick="togglePanel()">
        <i class="fa fa-list"></i> Capas <span class="badge-count" id="badgeCapas">0</span>
      </button>
      <button class="tool-btn" onclick="mostrarTodas()"><i class="fa fa-eye"></i> Todas</button>
      <button class="tool-btn red" onclick="ocultarTodas()"><i class="fa fa-eye-slash"></i></button>
      <div class="topbar-sep"></div>
      <button class="tool-btn amber" onclick="exportarKML()"><i class="fa fa-file-export"></i> Exportar ZIP</button>
      <button class="tool-btn green" onclick="recargar()"><i class="fa fa-rotate"></i> Recargar</button>
    </div>

    <div id="mapaGlobal"></div>

    <!-- Panel lateral -->
    <div class="side-panel" id="sidePanel">
      <div class="panel-header">
        <h3><i class="fa fa-layer-group"></i> Capas KML / KMZ</h3>
        <button onclick="togglePanel()" style="width:20px;height:20px;border-radius:4px;border:none;background:rgba(255,255,255,.06);color:#94a3b8;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:10px"><i class="fa fa-times"></i></button>
      </div>
      <div class="panel-search">
        <i class="fa fa-search ps-icon"></i>
        <input type="text" id="inputFiltro" placeholder="Buscar productor, código..." oninput="filtrarPanel()">
      </div>
      <div class="layers-list" id="listaCapas">
        <div style="text-align:center;padding:28px;color:#334155;font-size:12px;">
          <i class="fa fa-spinner fa-spin" style="font-size:20px;margin-bottom:8px;display:block;color:#38bdf8;"></i>Cargando capas...
        </div>
      </div>
      <div class="ppag" id="panelPag"></div>
      <div class="pstats" id="panelStats">—</div>
    </div>

    <!-- ══ BARRA EDITOR POLÍGONO ══ -->
    <div id="polyEditBar">
      <div class="peb-title">
        <i class="fa fa-vector-square" style="color:var(--amber)"></i>
        EDITOR DE POLÍGONO — <span id="pebCapaNombre" style="color:#fff;font-size:10px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
      </div>
      <div class="peb-tools">
        <!-- Modo 1: Eliminar polígono entero -->
        <button class="peb-btn mode-btn on" id="btnModoEliminar" onclick="setModoEdit('eliminar')">
          <i class="fa fa-trash-can"></i> Eliminar polígono
        </button>
        <!-- Modo 2: Editor de vértices completo -->
        <button class="peb-btn mode-btn" id="btnModoVertices" onclick="setModoEdit('vertices')">
          <i class="fa fa-circle-dot"></i> Editar vértices
        </button>
        <div class="peb-sep"></div>
        <div class="peb-area" id="pebAreaDisplay"><i class="fa fa-ruler-combined"></i> — ha</div>
      </div>
      <!-- Leyenda dinámica según el modo -->
      <div class="peb-hint" id="pebHint">Haz clic sobre un polígono para eliminarlo del KML</div>
      <!-- Leyenda de controles del editor de vértices (oculta por defecto) -->
      <div id="pebVertexLegend" style="display:none;gap:12px;align-items:center;flex-wrap:wrap;justify-content:center;font-size:10px;font-family:'Space Mono',monospace;">
        <span style="display:flex;align-items:center;gap:5px;">
          <span style="width:12px;height:12px;border-radius:50%;background:#f59e0b;border:2px solid #fff;display:inline-block;"></span>
          <span style="color:#f59e0b;font-weight:700;">Arrastrar</span> = mover
        </span>
        <span style="display:flex;align-items:center;gap:5px;">
          <span style="width:12px;height:12px;border-radius:50%;background:#10b981;border:2px solid #fff;display:inline-block;opacity:.7;"></span>
          <span style="color:#10b981;font-weight:700;">Clic en segmento</span> = agregar vértice
        </span>
        <span style="display:flex;align-items:center;gap:5px;">
          <span style="width:12px;height:12px;border-radius:50%;background:#ef4444;border:2px solid #fff;display:inline-block;"></span>
          <span style="color:#f87171;font-weight:700;">Clic derecho</span> = eliminar vértice
        </span>
      </div>
      <div class="peb-tools" id="pebAcciones">
        <button class="peb-btn save-kml" onclick="guardarEdicionKml()">
          <i class="fa fa-download"></i> Descargar KML editado
        </button>
        <button class="peb-btn save-bd" onclick="guardarEdicionBD()">
          <i class="fa fa-floppy-disk"></i> Guardar en Base de Datos
        </button>
        <button class="peb-btn cancel" onclick="cancelarEdicion()">
          <i class="fa fa-times"></i> Cancelar
        </button>
      </div>
    </div>

    <div class="stbar">
      <div class="stchip">Visibles: <b id="stVis">0</b> / <b id="stTotal">0</b></div>
      <div class="stchip" id="stZoom">Zoom: <b>—</b></div>
    </div>

    <div class="lov" id="loadingOverlay">
      <i class="fa fa-map" style="font-size:34px;color:#38bdf8;margin-bottom:4px;"></i>
      <div class="ltxt" id="loadingText">Iniciando visor global...</div>
      <div class="lbar"><div class="lbar-inner" id="loadingBar"></div></div>
      <div style="font-size:10px;color:#334155;font-family:'Space Mono',monospace;" id="loadingSub"></div>
    </div>
  </div>
</div>

<!-- ══ MODAL EDITOR ATRIBUTOS TIPO QGIS ══ -->
<div class="med-ov" id="medOv">
  <div class="med-box">
    <div class="med-head">
      <h2><i class="fa fa-pen-to-square"></i> Editor de Etiqueta KML &nbsp;<span id="edTit" style="color:#475569;font-weight:400;font-size:12px;font-family:'Space Mono',monospace;"></span></h2>
      <button onclick="cerrarEditor()" style="width:28px;height:28px;border-radius:50%;border:none;background:rgba(255,255,255,.07);color:#94a3b8;cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;"><i class="fa fa-times"></i></button>
    </div>
    <div class="med-tabs">
      <div class="med-tab active" onclick="cambiarTab('tCampos',this)"><i class="fa fa-table-columns"></i> Campos</div>
      <div class="med-tab" onclick="cambiarTab('tApar',this)"><i class="fa fa-palette"></i> Apariencia</div>
      <div class="med-tab" onclick="cambiarTab('tPrev',this)"><i class="fa fa-eye"></i> Vista Previa</div>
    </div>
    <div class="med-body">
      <!-- TAB CAMPOS -->
      <div id="tCampos" class="tc active">
        <div class="fg">
          <div class="fr"><label class="fl">Código (solo lectura)</label><input class="fi" id="edCodigo" type="text" readonly></div>
          <div class="fr"><label class="fl">Nombre del archivo</label><input class="fi" id="edNombre" type="text" oninput="EA.nombre=this.value" placeholder="archivo.kml"></div>
        </div>
        <div class="fr"><label class="fl">Descripción general</label><input class="fi" id="edDesc" type="text" oninput="EA.descripcion=this.value" placeholder="Descripción del lote..."></div>
        <div class="sdiv">Campos de datos <span style="font-size:9px;color:#38bdf8;background:rgba(56,189,248,.1);padding:2px 8px;border-radius:4px;margin-left:4px;">TIPO QGIS</span></div>
        <div class="at-wrap">
          <div class="at-head"><span>Campo</span><span>Valor / Expresión</span><span>Tipo</span><span></span></div>
          <div id="atRows"></div>
        </div>
        <button class="at-add" onclick="agregarAtributo()"><i class="fa fa-plus"></i> Agregar campo</button>
        <div style="margin-top:10px;padding:10px 12px;background:rgba(56,189,248,.04);border:1px solid rgba(56,189,248,.12);border-radius:8px;">
          <div style="font-size:10px;font-weight:700;color:#38bdf8;margin-bottom:7px;letter-spacing:.4px;text-transform:uppercase;"><i class="fa fa-calculator"></i> Insertar campo calculado desde geometría</div>
          <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <span onclick="insertarCalc('Area','area')" class="calc-chip">+ Área (ha)</span>
            <span onclick="insertarCalc('Latitud','latitud')" class="calc-chip">+ Latitud</span>
            <span onclick="insertarCalc('Longitud','longitud')" class="calc-chip">+ Longitud</span>
            <span onclick="insertarCalc('Perimetro','perimetro')" class="calc-chip">+ Perímetro (km)</span>
            <span onclick="insertarCalc('Canton','texto')" class="calc-chip" style="background:rgba(56,189,248,.1);border-color:rgba(56,189,248,.2);color:#38bdf8;">+ Cantón</span>
            <span onclick="insertarCalc('Provincia','texto')" class="calc-chip" style="background:rgba(56,189,248,.1);border-color:rgba(56,189,248,.2);color:#38bdf8;">+ Provincia</span>
          </div>
          <div style="font-size:10px;color:#334155;margin-top:6px;">Los campos Área, Latitud, Longitud y Perímetro se calculan automáticamente desde el KML</div>
        </div>
      </div>
      <!-- TAB APARIENCIA -->
      <div id="tApar" class="tc">
        <div class="fr"><label class="fl">Color de capa en el mapa</label><div class="csrow" id="colorSW"></div></div>
        <div class="sdiv">Aviso del mapa — QGIS HTML</div>
        <div class="fr"><label class="fl">Título del aviso</label><input class="fi" id="edTitAviso" type="text" placeholder="Ej: GARCIA DELGADO VICTOR" oninput="EA.tituloAviso=this.value;generarQgisHtml()"></div>
        <div style="padding:10px 12px;background:rgba(245,158,11,.04);border:1px solid rgba(245,158,11,.18);border-radius:8px;margin-bottom:12px;">
          <div style="font-size:10px;font-weight:700;color:var(--amber);margin-bottom:5px;letter-spacing:.4px;text-transform:uppercase;"><i class="fa fa-qrcode"></i> Cómo usar en QGIS</div>
          <div style="font-size:11px;color:#94a3b8;line-height:1.6;">En QGIS: <strong style="color:#e2e8f0;">Propiedades → Visualizar → Aviso del mapa en HTML</strong><br>Escribe: <code style="background:rgba(255,255,255,.08);padding:2px 7px;border-radius:3px;color:#38bdf8;font-family:'Space Mono',monospace;">[% "description" %]</code></div>
        </div>
        <div class="fr">
          <label class="fl">Expresión QGIS generada</label>
          <textarea class="fi" id="qgisOut" rows="9" readonly style="font-family:'Space Mono',monospace;font-size:10px;resize:vertical;"></textarea>
          <div style="margin-top:5px;"><button class="btn-prev" onclick="copiarQgis()"><i class="fa fa-copy"></i> Copiar expresión</button></div>
        </div>
      </div>
      <!-- TAB PREVIEW -->
      <div id="tPrev" class="tc">
        <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
          <button class="btn-prev" onclick="actualizarPreview()"><i class="fa fa-rotate"></i> Actualizar</button>
          <button class="btn-prev green" onclick="descargarKmlActualizado()"><i class="fa fa-download"></i> Descargar KML actualizado</button>
        </div>
        <div class="sdiv">Popup del visor</div>
        <div id="prevPopup" style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:10px;overflow:hidden;margin-bottom:14px;"></div>
        <div class="sdiv">Tabla HTML — Google Earth / QGIS</div>
        <div id="prevTabla" style="padding:8px;background:#fff;border-radius:8px;overflow:auto;margin-bottom:14px;"></div>
        <div class="sdiv">Fragmento &lt;description&gt; KML</div>
        <div class="prev-box" id="prevKml"></div>
      </div>
    </div>
    <div class="med-foot">
      <span class="sind" id="saveInd"></span>
      <button class="btn-cancel" onclick="cerrarEditor()">Cancelar</button>
      <button class="btn-save" id="btnGuardar" onclick="guardarEtiqueta()"><i class="fa fa-floppy-disk"></i> Guardar permanente</button>
    </div>
  </div>
</div>

<div id="toast-g"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-omnivore/0.3.4/leaflet-omnivore.min.js"></script>

<script>
/* ═══════════════════════════════════════
   CONSTANTES
═══════════════════════════════════════ */
const COLORES=['#38bdf8','#10b981','#f59e0b','#ef4444','#a78bfa','#fb923c','#34d399','#e879f9','#facc15','#60a5fa'];

const TILES=[
  {
    url:'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    label:'Satélite',att:'© Esri',
    maxNativeZoom:18, maxZoom:22
  },
  {
    url:'https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',
    label:'Google Sat',att:'© Google',
    maxNativeZoom:20, maxZoom:22
  },
  {
    url:'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    label:'Mapa OSM',att:'© OpenStreetMap',
    maxNativeZoom:19, maxZoom:22
  },
  {
    url:'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
    label:'Topográfico',att:'© OpenTopoMap',
    maxNativeZoom:17, maxZoom:22
  },
];
const TIPOS={texto:{label:'Texto',calc:false},area:{label:'Área (ha)',calc:true},latitud:{label:'Latitud',calc:true},longitud:{label:'Longitud',calc:true},perimetro:{label:'Perímetro',calc:true}};

let mapa=null,tileLayer=null,tiloActual=0;
let capas={},capaSeleccionada=null;
let panelVisible=true,panelPagina=1;
const PPP=8;
let panelFiltro='',todosSocios=[];
let EA=null;

/* ══════════════════════════════════════
   ESTADO EDITOR POLÍGONO
══════════════════════════════════════ */
let editState={
  activo:false, idCapa:null, modoEdit:'vertices',
  poligonosEditados:[],
  layersEdicion:[],
  markerVertices:[],
  midMarkers:[],
};

/* ═══════════════════════════════════════
   INIT
═══════════════════════════════════════ */
window.addEventListener('load',()=>{
  inicializarMapa();
  cargarTodasLasCapas();
});

function inicializarMapa(){
  mapa=L.map('mapaGlobal',{center:[-1.8,-78.5],zoom:8,zoomControl:false});
  const t=TILES[0];
  tileLayer=L.tileLayer(t.url,{attribution:t.att,maxNativeZoom:t.maxNativeZoom,maxZoom:t.maxZoom}).addTo(mapa);
  L.control.zoom({position:'bottomright'}).addTo(mapa);
  mapa.on('zoomend',()=>{document.getElementById('stZoom').innerHTML='Zoom: <b>'+mapa.getZoom()+'</b>';});
}

/* ═══════════════════════════════════════
   CARGA MASIVA
═══════════════════════════════════════ */
async function cargarTodasLasCapas(){
  setLoading(true,'Cargando datos del servidor...',10);
  let datos=[];
  try{
    const r=await fetch('mapa_global_datos.php');
    const txt=await r.text();
    let json;
    try{json=JSON.parse(txt);}
    catch(e){toast('❌ Error del servidor','#ef4444');setLoading(false);return;}
    if(!json.success||!json.datos.length){
      setLoading(false);
      document.getElementById('listaCapas').innerHTML='<div style="text-align:center;padding:30px;color:#334155;font-size:12px;">No hay KML cargados.</div>';
      return;
    }
    datos=json.datos;
  }catch(e){toast('❌ Error de conexión','#ef4444');setLoading(false);return;}

  // Agrupar por socio para el panel lateral
  const sociosMap={};
  datos.forEach(d=>{
    if(!sociosMap[d.id_socio]){
      sociosMap[d.id_socio]={
        socio:{id_socio:d.id_socio,nombre_completo:d.nombre_socio,identificacion:d.identificacion},
        archivos:[]
      };
    }
    sociosMap[d.id_socio].archivos.push({
      id_ubicacion:d.id_ubicacion,nombre_archivo:d.nombre_archivo,
      tipo_archivo:d.tipo_archivo,codigo_archivo:d.codigo_archivo,
      descripcion:d.descripcion,subido_por:d.subido_por,fecha_subida:d.fecha_subida,
    });
  });
  todosSocios=Object.values(sociosMap);

  const total=datos.length;
  let ci=0,loaded=0;
  setLoading(true,`Renderizando ${total} capas en el mapa...`,30);

  for(const d of datos){
    const col=COLORES[ci%COLORES.length];ci++;
    const color=(d.color_capa&&d.color_capa!=='#38bdf8')?d.color_capa:col;
    const socio={id_socio:d.id_socio,nombre_completo:d.nombre_socio,identificacion:d.identificacion};
    const arch={
      id_ubicacion:d.id_ubicacion,nombre_archivo:d.nombre_archivo,
      tipo_archivo:d.tipo_archivo,codigo_archivo:d.codigo_archivo,
      descripcion:d.descripcion,subido_por:d.subido_por,fecha_subida:d.fecha_subida,
    };
    try{
      const kmlStr=atob(d.kml);
      let atributos=[];
      if(d.atributos&&d.atributos.length){atributos=d.atributos;}
      else{atributos=extraerAtributosKML(kmlStr,d.descripcion);}
      const geoInfo=calcularGeoKML(kmlStr);
      atributos=rellenarCalcs(atributos,geoInfo);
      const layer=omnivore.kml.parse(kmlStr,null,L.geoJson(null,{
        style:{color,weight:2.5,fillOpacity:0.22,fillColor:color},
        pointToLayer:(f,ll)=>L.circleMarker(ll,{radius:7,fillColor:color,color:'#fff',weight:2,fillOpacity:.9}),
        onEachFeature:(feature,lyr)=>{
          lyr.on('click',function(e){
            L.DomEvent.stopPropagation(e);
            if(editState.activo&&editState.modoEdit==='vertices')return;
            if(editState.activo&&editState.modoEdit==='eliminar'){manejarClickEliminar(d.id_ubicacion,lyr);return;}
            if(!editState.activo)abrirPopup(feature,lyr,arch,socio,color,atributos);
          });
        }
      })).addTo(mapa);
      capas[d.id_ubicacion]={
        layer,nombre:d.nombre_archivo,
        codigo:d.codigo_archivo||d.nombre_archivo,
        socio:d.nombre_socio,id_socio:d.id_socio,
        color,activa:true,arch,atributos,
        descripcion:d.descripcion||'',
        geoInfo,kmlOriginal:kmlStr,
        tituloAviso:d.titulo_aviso||'',
      };
    }catch(e){console.warn('Error KML:',d.codigo_archivo,e);}
    loaded++;
    if(loaded%10===0||loaded===total){
      setLoading(true,`${loaded}/${total} capas renderizadas...`,30+Math.round((loaded/total)*65));
      await new Promise(r=>setTimeout(r,0));
    }
  }
  document.getElementById('badgeCapas').textContent=total;
  setLoading(false);renderPanel();actualizarStats();centrarTodo();
  toast(`✅ ${total} capa(s) cargadas`,'#10b981');
}

/* ═══════════════════════════════════════
   CARGAR UN KML
═══════════════════════════════════════ */
function cargarKmlEnMapa(arch,socio,colorDefault){
  return new Promise(async resolve=>{
    try{
      const r=await fetch(`ubicaciones_api.php?accion=leer_kml&id_ubicacion=${arch.id_ubicacion}`);
      const j=await r.json();
      if(!j.success){resolve();return;}
      const kmlStr=atob(j.kml);
      const color=(j.color_capa&&j.color_capa!=='#38bdf8')?j.color_capa:colorDefault;
      let atributos=[];
      if(j.atributos&&j.atributos.length){atributos=j.atributos;}
      else{atributos=extraerAtributosKML(kmlStr,arch.descripcion);}
      const geoInfo=calcularGeoKML(kmlStr);
      atributos=rellenarCalcs(atributos,geoInfo);
      const layer=omnivore.kml.parse(kmlStr,null,L.geoJson(null,{
        style:{color,weight:2.5,fillOpacity:0.22,fillColor:color},
        pointToLayer:(f,ll)=>L.circleMarker(ll,{radius:7,fillColor:color,color:'#fff',weight:2,fillOpacity:.9}),
        onEachFeature:(feature,lyr)=>{
          lyr.on('click',function(e){
            L.DomEvent.stopPropagation(e);
            if(editState.activo&&editState.modoEdit==='vertices') return;
            if(editState.activo&&editState.modoEdit==='eliminar'){
              manejarClickEliminar(arch.id_ubicacion,lyr);
              return;
            }
            if(!editState.activo){
              abrirPopup(feature,lyr,arch,socio,color,atributos);
            }
          });
        }
      })).addTo(mapa);
      capas[arch.id_ubicacion]={layer,nombre:arch.nombre_archivo,codigo:arch.codigo_archivo||arch.nombre_archivo,socio:socio.nombre_completo||socio.identificacion,id_socio:socio.id_socio,color,activa:true,arch,atributos,descripcion:arch.descripcion||'',geoInfo,kmlOriginal:kmlStr,tituloAviso:j.titulo_aviso||''};
    }catch(e){console.warn('Error KML:',e);}
    resolve();
  });
}

/* ═══════════════════════════════════════
   EXTRAER / CALCULAR ATRIBUTOS KML
═══════════════════════════════════════ */
function extraerAtributosKML(kmlStr,descExtra){
  const atrs=[];const doc=new DOMParser().parseFromString(kmlStr,'text/xml');
  doc.querySelectorAll('SimpleData').forEach(sd=>{const k=sd.getAttribute('name')||'';const v=(sd.textContent||'').trim();if(k&&v)atrs.push({k,v,tipo:detectarTipo(k)});});
  if(!atrs.length){doc.querySelectorAll('ExtendedData > Data').forEach(d=>{const k=d.getAttribute('name')||'';const vEl=d.querySelector('value');const v=vEl?(vEl.textContent||'').trim():'';if(k)atrs.push({k,v,tipo:detectarTipo(k)});});}
  if(!atrs.length){const descEl=doc.querySelector('description');if(descEl){const content=descEl.textContent||'';const dd=new DOMParser().parseFromString(content,'text/html');dd.querySelectorAll('tr').forEach(row=>{const tds=row.querySelectorAll('td');if(tds.length>=2){const k=(tds[0].textContent||'').trim();const v=(tds[1].textContent||'').trim();if(k)atrs.push({k,v,tipo:detectarTipo(k)});}});}}
  if(descExtra&&descExtra.trim())atrs.push({k:'Descripción',v:descExtra.trim(),tipo:'texto'});
  return atrs;
}
function detectarTipo(n){n=n.toLowerCase();if(n.includes('area')||n.includes('área')||n.includes('hectarea'))return 'area';if(n==='lat'||n.includes('latitud'))return 'latitud';if(n==='lon'||n==='lng'||n.includes('longitud'))return 'longitud';if(n.includes('perimetro')||n.includes('perímetro'))return 'perimetro';return 'texto';}
function calcularGeoKML(kmlStr){
  const info={area:null,latitud:null,longitud:null,perimetro:null};
  try{const doc=new DOMParser().parseFromString(kmlStr,'text/xml');const coordEls=doc.querySelectorAll('coordinates');if(!coordEls.length)return info;let allCoords=[];
  coordEls.forEach(el=>{(el.textContent||'').trim().split(/\s+/).forEach(c=>{const p=c.split(',');if(p.length>=2){const lon=parseFloat(p[0]),lat=parseFloat(p[1]);if(!isNaN(lon)&&!isNaN(lat))allCoords.push([lat,lon]);}});});
  if(!allCoords.length)return info;
  info.latitud=allCoords.reduce((s,c)=>s+c[0],0)/allCoords.length;info.longitud=allCoords.reduce((s,c)=>s+c[1],0)/allCoords.length;
  if(allCoords.length>3){info.area=calcArea(allCoords);info.perimetro=calcPerim(allCoords);}
  }catch(e){}return info;
}
function calcArea(coords){
  if(coords.length<3)return 0;
  const R=6371000;
  const lat0=coords[0][0]*Math.PI/180;
  const cosLat=Math.cos(lat0);
  let area=0;
  const n=coords.length;
  for(let i=0;i<n;i++){
    const j=(i+1)%n;
    const x1=coords[i][1]*Math.PI/180*R*cosLat;
    const y1=coords[i][0]*Math.PI/180*R;
    const x2=coords[j][1]*Math.PI/180*R*cosLat;
    const y2=coords[j][0]*Math.PI/180*R;
    area+=x1*y2-x2*y1;
  }
  return parseFloat((Math.abs(area)/2/10000).toFixed(6));
}
function calcPerim(coords){let p=0;for(let i=0;i<coords.length-1;i++)p+=haversine(coords[i],coords[i+1]);return parseFloat((p/1000).toFixed(4));}
function haversine([lat1,lon1],[lat2,lon2]){const R=6371000,dLat=(lat2-lat1)*Math.PI/180,dLon=(lon2-lon1)*Math.PI/180;const a=Math.sin(dLat/2)**2+Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;return R*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));}
function rellenarCalcs(atrs,geo){return atrs.map(a=>{const t=a.tipo||'texto';if(t==='area'&&geo.area!==null)return{...a,v:String(geo.area)};if(t==='latitud'&&geo.latitud!==null)return{...a,v:String(parseFloat(geo.latitud.toFixed(6)))};if(t==='longitud'&&geo.longitud!==null)return{...a,v:String(parseFloat(geo.longitud.toFixed(6)))};if(t==='perimetro'&&geo.perimetro!==null)return{...a,v:String(geo.perimetro)};return a;});}

/* ═══════════════════════════════════════
   POPUP
═══════════════════════════════════════ */
function abrirPopup(feature,lyr,arch,socio,color,atributos){
  const nombre=arch.codigo_archivo||arch.nombre_archivo;const nombreS=socio.nombre_completo||socio.identificacion;const cedula=socio.identificacion||'';
  const c=capas[arch.id_ubicacion];const atrs=(c&&c.atributos)?c.atributos:atributos;
  const fn=feature&&feature.properties?(feature.properties.name||feature.properties.Name||''):'';
  let tabla='';
  if(atrs&&atrs.length){tabla='<table class="pu-table">';atrs.forEach(a=>{tabla+=`<tr><td>${esc(a.k)}</td><td>${esc(a.v)}</td></tr>`;});tabla+='</table>';}
  else tabla='<p class="pu-no-desc"><i class="fa fa-circle-info"></i> Sin atributos.</p>';
  const html=`<div class="pu-wrap">
    <div class="pu-head"><div class="pu-code">${esc(cedula)} · ${esc((arch.tipo_archivo||'KML').toUpperCase())}</div><div class="pu-title">${esc(nombre)}${fn?' — '+esc(fn):''}</div><div class="pu-socio"><i class="fa fa-user" style="color:${color};font-size:10px;"></i>${esc(nombreS)}</div></div>
    <div class="pu-body">${tabla}</div>
    <div class="pu-foot">
      <button class="pu-btn pu-btn-edit" onclick="abrirEditor(${arch.id_ubicacion})"><i class="fa fa-pen"></i> Editar</button>
      <button class="pu-btn pu-btn-poly" onclick="iniciarEdicionPoligono(${arch.id_ubicacion})"><i class="fa fa-vector-square"></i> Editar polígono</button>
      <button class="pu-btn pu-btn-zoom" onclick="centrarCapa(${arch.id_ubicacion})"><i class="fa fa-crosshairs"></i></button>
      <button class="pu-btn pu-btn-dl" onclick="dlKmlPopup(${arch.id_ubicacion})"><i class="fa fa-download"></i> KML</button>
    </div>
  </div>`;
  let ll;
  try{const b=lyr.getBounds?lyr.getBounds():null;ll=b?b.getCenter():(lyr.getLatLng?lyr.getLatLng():mapa.getCenter());}catch(e){ll=lyr.getLatLng?lyr.getLatLng():mapa.getCenter();}
  L.popup({maxWidth:380}).setLatLng(ll).setContent(html).openOn(mapa);
}

/* ═══════════════════════════════════════════════════════════
   EDITOR DE POLÍGONOS
═══════════════════════════════════════════════════════════ */
function iniciarEdicionPoligono(idCapa){
  mapa.closePopup();
  const c=capas[idCapa];if(!c)return;
  // ── FIX: usar extraerPoligonosConHuecos que preserva relación outer/inner ──
  const poligonos=extraerPoligonosConHuecos(c.kmlOriginal);
  if(!poligonos.length){toast('⚠ No se encontraron polígonos en este KML','#f59e0b');return;}
  cancelarEdicion(true);
  editState.activo=true; editState.idCapa=idCapa; editState.modoEdit='eliminar';
  // Cada entrada: { coords, huecos:[], nombre, eliminado }
  editState.poligonosEditados=poligonos.map(p=>({
    coords:[...p.coords.map(c=>[...c])],
    huecos:(p.huecos||[]).map(h=>[...h.map(c=>[...c])]),
    nombre:p.nombre,
    eliminado:false
  }));
  editState.layersEdicion=[];editState.markerVertices=[];editState.midMarkers=[];
  try{mapa.removeLayer(c.layer);c.activa=false;}catch(e){}
  renderizarPoligonosEdicion();
  actualizarAreaEdicion();
  document.getElementById('pebCapaNombre').textContent=c.codigo;
  document.getElementById('polyEditBar').classList.add('show');
  setModoEdit('vertices');
  document.querySelectorAll('.layer-item').forEach(el=>el.classList.remove('editing'));
  document.getElementById('li_'+idCapa)?.classList.add('editing');
  toast('✏ Editor de vértices activado — arrastra, clic en punto verde para agregar, clic derecho para eliminar','#f59e0b');
}

/* ══════════════════════════════════════════════════════════════════
   FIX #1 — extraerPoligonosConHuecos
   Extrae polígonos preservando la relación outerBoundary / innerBoundary.
   Cada entrada devuelta: { coords:[...], huecos:[[...], ...], nombre }
══════════════════════════════════════════════════════════════════ */
function extraerPoligonosConHuecos(kmlStr){
  const doc=new DOMParser().parseFromString(kmlStr,'text/xml');
  const result=[];

  doc.querySelectorAll('Placemark').forEach(pm=>{
    const nameEl=pm.querySelector('name');
    const nombre=(nameEl?nameEl.textContent:'').trim();

    // Procesar cada <Polygon> dentro del Placemark (incluyendo MultiGeometry)
    pm.querySelectorAll('Polygon').forEach(poly=>{
      // Outer boundary
      const outerEl=poly.querySelector('outerBoundaryIs coordinates') ||
                    poly.querySelector('outerBoundaryIs LinearRing coordinates');
      if(!outerEl) return;
      const outerCoords=parsearCoordenadas(outerEl.textContent||'');
      if(outerCoords.length<3) return;

      // Inner boundaries (huecos)
      const huecos=[];
      poly.querySelectorAll('innerBoundaryIs').forEach(inner=>{
        const innerEl=inner.querySelector('coordinates') ||
                      inner.querySelector('LinearRing coordinates');
        if(!innerEl) return;
        const innerCoords=parsearCoordenadas(innerEl.textContent||'');
        if(innerCoords.length>=3) huecos.push(innerCoords);
      });

      result.push({coords:outerCoords, huecos, nombre});
    });
  });

  return result;
}

function parsearCoordenadas(raw){
  const coords=[];
  raw.trim().split(/\s+/).forEach(c=>{
    const p=c.split(',');
    if(p.length>=2){const lon=parseFloat(p[0]),lat=parseFloat(p[1]);if(!isNaN(lon)&&!isNaN(lat))coords.push([lat,lon]);}
  });
  return coords;
}

function renderizarPoligonosEdicion(){
  editState.layersEdicion.forEach(l=>{try{mapa.removeLayer(l);}catch(e){}});
  editState.markerVertices.forEach(m=>{try{mapa.removeLayer(m);}catch(e){}});
  (editState.midMarkers||[]).forEach(m=>{try{mapa.removeLayer(m);}catch(e){}});
  editState.layersEdicion=[];editState.markerVertices=[];editState.midMarkers=[];

  const c=capas[editState.idCapa];
  const baseColor=c?c.color:'#f59e0b';

  editState.poligonosEditados.forEach((poli,idx)=>{
    if(poli.eliminado)return;

    // Construir latlngs: outer + huecos (Leaflet acepta array de arrays para huecos)
    const latlngs=[poli.coords];
    if(poli.huecos&&poli.huecos.length){
      poli.huecos.forEach(h=>latlngs.push(h));
    }

    const layer=L.polygon(latlngs,{
      color:baseColor,weight:2.5,fillOpacity:0.2,fillColor:baseColor,
      dashArray:editState.modoEdit==='eliminar'?'8,4':null
    }).addTo(mapa);
    layer._poliIdx=idx;

    if(editState.modoEdit==='eliminar'){
      layer.on('click',function(e){
        L.DomEvent.stopPropagation(e);
        editState.poligonosEditados[idx].eliminado=true;
        renderizarPoligonosEdicion();
        actualizarAreaEdicion();
        const r=editState.poligonosEditados.filter(p=>!p.eliminado).length;
        toast(`🗑 Polígono eliminado — quedan ${r}`,'#f59e0b');
      });
    }

    editState.layersEdicion.push(layer);

    if(editState.modoEdit==='vertices'){
      // Solo editamos el outer boundary con vértices
      renderizarVerticesCompleto(poli,idx,layer,baseColor);
    }
  });
}

/* ══════════════════════════════════════════════════════════
   EDITOR DE VÉRTICES COMPLETO
   ● Puntos AMARILLOS = vértices reales → arrastrar para mover
   ● Puntos VERDES semitransparentes = puntos medios → clic para agregar
   ● Clic DERECHO en vértice amarillo → eliminar ese vértice
══════════════════════════════════════════════════════════ */
function renderizarVerticesCompleto(poli,poliIdx,polyLayer,color){
  const coords=poli.coords;

  coords.forEach((coord,vIdx)=>{
    const m=L.circleMarker([coord[0],coord[1]],{
      radius:8, color:'#fff', weight:2.5,
      fillColor:'#f59e0b', fillOpacity:1,
      bubblingMouseEvents:false
    }).addTo(mapa);

    m.bindTooltip(`V${vIdx+1}`,{permanent:false,direction:'top',offset:[0,-10],className:'vtx-tip'});

    m.on('mousedown',function(e){
      L.DomEvent.stopPropagation(e);
      if(e.originalEvent.button!==0)return;
      iniciarDragVertice(m,poliIdx,vIdx,polyLayer);
    });

    m.on('contextmenu',function(e){
      L.DomEvent.stopPropagation(e);
      eliminarVertice(poliIdx,vIdx);
    });

    editState.markerVertices.push(m);
  });

  const n=coords.length;
  for(let i=0;i<n;i++){
    const a=coords[i];
    const b=coords[(i+1)%n];
    const midLat=(a[0]+b[0])/2;
    const midLng=(a[1]+b[1])/2;
    const insertIdx=i+1;

    const mid=L.circleMarker([midLat,midLng],{
      radius:5, color:'#fff', weight:1.5,
      fillColor:'#10b981', fillOpacity:0.65,
      bubblingMouseEvents:false
    }).addTo(mapa);

    mid.bindTooltip('+ agregar',{permanent:false,direction:'top',offset:[0,-8],className:'vtx-tip'});

    mid.on('click',function(e){
      L.DomEvent.stopPropagation(e);
      agregarVertice(poliIdx,insertIdx,midLat,midLng);
    });

    editState.midMarkers.push(mid);
  }
}

/* ── Drag de vértice ── */
let _dragState=null;
function iniciarDragVertice(marker,poliIdx,vIdx,polyLayer){
  mapa.dragging.disable();
  _dragState={marker,poliIdx,vIdx,polyLayer};
  mapa.on('mousemove',_onDragMove);
  mapa.on('mouseup',_onDragEnd);
  document.addEventListener('mouseup',_onDragEnd);
}
function _onDragMove(e){
  if(!_dragState)return;
  const {marker,poliIdx,vIdx}=_dragState;
  marker.setLatLng(e.latlng);
  editState.poligonosEditados[poliIdx].coords[vIdx]=[e.latlng.lat,e.latlng.lng];
  // Actualizar visual del polígono incluyendo huecos
  try{
    const poli=editState.poligonosEditados[poliIdx];
    const latlngs=[poli.coords];
    if(poli.huecos&&poli.huecos.length)poli.huecos.forEach(h=>latlngs.push(h));
    _dragState.polyLayer.setLatLngs(latlngs);
  }catch(ex){}
  actualizarAreaEdicion();
}
function _onDragEnd(){
  if(!_dragState)return;
  mapa.dragging.enable();
  mapa.off('mousemove',_onDragMove);
  mapa.off('mouseup',_onDragEnd);
  document.removeEventListener('mouseup',_onDragEnd);
  _dragState=null;
  renderizarPoligonosEdicion();
  actualizarAreaEdicion();
}

function agregarVertice(poliIdx,insertIdx,lat,lng){
  const coords=editState.poligonosEditados[poliIdx].coords;
  coords.splice(insertIdx,0,[lat,lng]);
  renderizarPoligonosEdicion();
  actualizarAreaEdicion();
  toast('✅ Vértice agregado — arrástralo para ajustar','#10b981');
}

function eliminarVertice(poliIdx,vIdx){
  const coords=editState.poligonosEditados[poliIdx].coords;
  if(coords.length<=3){toast('⚠ El polígono necesita al menos 3 vértices','#f59e0b');return;}
  coords.splice(vIdx,1);
  renderizarPoligonosEdicion();
  actualizarAreaEdicion();
  toast('🗑 Vértice eliminado','#f59e0b');
}

function setModoEdit(modo){
  editState.modoEdit=modo;
  document.querySelectorAll('.peb-btn.mode-btn').forEach(b=>b.classList.remove('on'));
  document.getElementById('btnModo'+modo.charAt(0).toUpperCase()+modo.slice(1))?.classList.add('on');

  const hints={
    eliminar:'Haz clic sobre un polígono para eliminarlo del KML',
    vertices:'🟡 Arrastra vértice → mover   |   🟢 Clic en punto verde → agregar   |   Clic derecho en vértice → eliminar'
  };
  document.getElementById('pebHint').textContent=hints[modo]||'';

  const leg=document.getElementById('pebVertexLegend');
  if(leg) leg.style.display=modo==='vertices'?'flex':'none';

  renderizarPoligonosEdicion();
}

function actualizarAreaEdicion(){
  let totalHa=0;
  editState.poligonosEditados.forEach(p=>{
    if(!p.eliminado&&p.coords.length>3)totalHa+=calcArea(p.coords);
  });
  document.getElementById('pebAreaDisplay').innerHTML=`<i class="fa fa-ruler-combined"></i> ${totalHa.toFixed(4)} ha`;
  const c=capas[editState.idCapa];
  if(c&&c.atributos){
    c.atributos=c.atributos.map(a=>{
      if(a.tipo==='area')return{...a,v:String(totalHa)};
      return a;
    });
  }
}

/* ══════════════════════════════════════════════════════════════════
   FIX #2 — reconstruirKML
   Genera KML correcto con innerBoundaryIs para los huecos,
   eliminando la "sombra" del polígono interior.
══════════════════════════════════════════════════════════════════ */
function reconstruirKML(){
  const c=capas[editState.idCapa];if(!c)return null;
  const atrs=(c.atributos||[]).filter(a=>a.k&&a.k.trim());
  const titulo=c.tituloAviso||c.socio||c.codigo||'';
  let rows='';
  atrs.forEach((a,i)=>{const bg=i%2!==0?' bgcolor="#D4E4F3"':'';rows+=`<tr${bg}><td>${escH(a.k)}</td><td>${escH(a.v)}</td></tr>`;});
  const htmlDesc=`<table style="font-family:Arial;font-size:12px;width:100%;border-collapse:collapse;"><tr style="background:#9CBCE2;font-weight:bold;text-align:center;"><td colspan="2">${escH(titulo)}</td></tr>${rows}</table>`;

  const poliActivos=editState.poligonosEditados.filter(p=>!p.eliminado);
  if(!poliActivos.length)return null;

  // Calcular área total usando solo outer boundaries
  let totalHa=0;
  poliActivos.forEach(p=>{if(p.coords.length>3)totalHa+=calcArea(p.coords);});
  const atrsActualizados=atrs.map(a=>a.tipo==='area'?{...a,v:String(totalHa)}:a);

  // ── Función auxiliar: construir <Polygon> con outer + inner boundaries ──
  function buildPolygonXml(poli){
    const outerStr=poli.coords.map(c=>c[1]+','+c[0]+',0').join(' ');
    let xml=`<Polygon>`;
    xml+=`<outerBoundaryIs><LinearRing><coordinates>${outerStr}</coordinates></LinearRing></outerBoundaryIs>`;
    // Incluir huecos como innerBoundaryIs
    if(poli.huecos&&poli.huecos.length){
      poli.huecos.forEach(h=>{
        const innerStr=h.map(c=>c[1]+','+c[0]+',0').join(' ');
        xml+=`<innerBoundaryIs><LinearRing><coordinates>${innerStr}</coordinates></LinearRing></innerBoundaryIs>`;
      });
    }
    xml+=`</Polygon>`;
    return xml;
  }

  let poligBody='';
  if(poliActivos.length===1){
    poligBody=buildPolygonXml(poliActivos[0]);
  }else{
    poligBody='<MultiGeometry>';
    poliActivos.forEach(p=>{poligBody+=buildPolygonXml(p);});
    poligBody+='</MultiGeometry>';
  }

  let extData='<ExtendedData>';
  atrsActualizados.forEach(a=>{extData+=`<Data name="${escH(a.k)}"><value>${escH(a.v)}</value></Data>`;});
  extData+='</ExtendedData>';

  const colorHex=c.color.replace('#','');
  const newKml=`<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
<Document>
<name>${escH(c.codigo)}</name>
<Style id="sty"><LineStyle><color>ff${colorHex}</color><width>2</width></LineStyle><PolyStyle><color>4f${colorHex}</color></PolyStyle></Style>
<Placemark>
  <name>${escH(c.codigo)}</name>
  <description><![CDATA[${htmlDesc}]]></description>
  ${extData}
  <styleUrl>#sty</styleUrl>
  ${poligBody}
</Placemark>
</Document>
</kml>`;
  return {kml:newKml,totalHa};
}

function guardarEdicionKml(){
  const result=reconstruirKML();
  if(!result){toast('❌ No quedan polígonos para guardar','#ef4444');return;}
  const c=capas[editState.idCapa];
  dlBlob(result.kml,'application/vnd.google-earth.kml+xml',(c?c.codigo:'kml_editado')+'_editado.kml');
  toast(`✅ KML descargado — ${result.totalHa.toFixed(4)} ha`,'#10b981');
}

async function guardarEdicionBD(){
  const result=reconstruirKML();
  if(!result){toast('❌ No quedan polígonos para guardar','#ef4444');return;}
  const c=capas[editState.idCapa];if(!c)return;
  toast('⏳ Guardando en base de datos...','#38bdf8');
  const atrsActualizados=(c.atributos||[]).map(a=>a.tipo==='area'?{...a,v:String(result.totalHa)}:a);
  const fd=new FormData();
  fd.append('accion','actualizar_kml_editado');
  fd.append('id_ubicacion',editState.idCapa);
  fd.append('kml_content',result.kml);
  fd.append('atributos',JSON.stringify(atrsActualizados));
  try{
    const r=await fetch('ubicaciones_api.php',{method:'POST',body:fd});
    const j=await r.json();
    if(j.success){
      c.kmlOriginal=result.kml;c.atributos=atrsActualizados;
      if(c.geoInfo)c.geoInfo.area=result.totalHa;
      toast(`✅ Guardado — área: ${result.totalHa.toFixed(4)} ha`,'#10b981');
      const idGuardado=editState.idCapa;
      cancelarEdicion();
      setTimeout(()=>recargarCapa(idGuardado),400);
    }else{toast('❌ '+(j.message||'Error al guardar'),'#ef4444');}
  }catch(ex){toast('❌ Error de conexión','#ef4444');console.error(ex);}
}

async function recargarCapa(id){
  const c=capas[id];if(!c)return;
  try{mapa.removeLayer(c.layer);}catch(e){}
  delete capas[id];
  for(const g of todosSocios){
    const arch=g.archivos.find(a=>a.id_ubicacion==id);
    if(arch){await cargarKmlEnMapa(arch,g.socio,COLORES[Object.keys(capas).length%COLORES.length]);renderPanel();break;}
  }
}

function cancelarEdicion(silencioso=false){
  editState.layersEdicion.forEach(l=>{try{mapa.removeLayer(l);}catch(e){}});
  editState.markerVertices.forEach(m=>{try{mapa.removeLayer(m);}catch(e){}});
  (editState.midMarkers||[]).forEach(m=>{try{mapa.removeLayer(m);}catch(e){}});
  if(_dragState){mapa.dragging.enable();mapa.off('mousemove',_onDragMove);mapa.off('mouseup',_onDragEnd);_dragState=null;}
  if(editState.idCapa&&capas[editState.idCapa]){
    const c=capas[editState.idCapa];
    try{c.layer.addTo(mapa);c.activa=true;}catch(e){}
  }
  editState={activo:false,idCapa:null,modoEdit:'vertices',poligonosEditados:[],layersEdicion:[],markerVertices:[],midMarkers:[]};
  document.getElementById('polyEditBar').classList.remove('show');
  document.querySelectorAll('.layer-item').forEach(el=>el.classList.remove('editing'));
  const leg=document.getElementById('pebVertexLegend');
  if(leg)leg.style.display='none';
  if(!silencioso)toast('✖ Edición cancelada','#94a3b8');
}

/* ═══════════════════════════════════════
   PANEL
═══════════════════════════════════════ */
function renderPanel(){
  const lista=document.getElementById('listaCapas');
  const q=panelFiltro.toLowerCase();
  let sf=todosSocios;
  if(q)sf=todosSocios.filter(g=>{const s=g.socio;return(s.nombre_completo||'').toLowerCase().includes(q)||(s.identificacion||'').toLowerCase().includes(q)||g.archivos.some(a=>(a.codigo_archivo||'').toLowerCase().includes(q));});
  const tot=sf.length,totP=Math.max(1,Math.ceil(tot/PPP));
  if(panelPagina>totP)panelPagina=totP;
  const desde=(panelPagina-1)*PPP,hasta=Math.min(desde+PPP,tot);const pag=sf.slice(desde,hasta);
  if(!pag.length){lista.innerHTML='<div style="text-align:center;padding:24px;color:#334155;font-size:12px;">Sin resultados</div>';document.getElementById('panelPag').innerHTML='';document.getElementById('panelStats').textContent='0';return;}
  let html='';
  pag.forEach((g,gi)=>{
    const s=g.socio,nom=s.nombre_completo||s.identificacion;const gid=`sg_${gi}_${panelPagina}`;
    html+=`<div class="socio-group" id="${gid}"><div class="socio-group-header" onclick="tgGrupo('${gid}')"><div class="sg-title"><i class="fa fa-user"></i><span title="${esc(nom)}">${esc(nom.length>22?nom.slice(0,22)+'…':nom)}</span></div><span class="sg-badge">${g.archivos.length}</span><i class="fa fa-chevron-down sg-chevron"></i></div><div class="sg-body open" id="${gid}_body">`;
    g.archivos.forEach(arch=>{
      const c=capas[arch.id_ubicacion];const col=c?c.color:'#94a3b8',act=c?c.activa:false;const cod=arch.codigo_archivo||arch.nombre_archivo;const tipo=(arch.tipo_archivo||'kml').toUpperCase();
      html+=`<div class="layer-item${capaSeleccionada===arch.id_ubicacion?' selected':''}" id="li_${arch.id_ubicacion}">
        <div class="l-dot" style="background:${col};border-color:${col}80"></div>
        <div class="l-info" onclick="centrarCapa(${arch.id_ubicacion})" style="cursor:pointer"><div class="l-name" title="${esc(cod)}">${esc(cod.length>22?cod.slice(0,22)+'…':cod)}</div><div class="l-sub">${tipo} · ${esc(s.identificacion||'')}</div></div>
        <div class="l-acts">
          <button class="lbtn zb" onclick="centrarCapa(${arch.id_ubicacion})"><i class="fa fa-crosshairs"></i></button>
          <button class="lbtn editbtn" onclick="iniciarEdicionPoligono(${arch.id_ubicacion})" title="Editar polígono"><i class="fa fa-vector-square"></i></button>
          <button class="lbtn eb" onclick="abrirEditor(${arch.id_ubicacion})" title="Editar etiqueta"><i class="fa fa-pen"></i></button>
          <div class="tswitch${act?' on':''}" onclick="toggleCapa(${arch.id_ubicacion},this)"></div>
        </div>
      </div>`;
    });
    html+=`</div></div>`;
  });
  lista.innerHTML=html;
  renderPag(panelPagina,totP);
  document.getElementById('panelStats').textContent=`${desde+1}–${hasta} de ${tot} socios · ${Object.keys(capas).length} capas`;
}

function tgGrupo(id){document.getElementById(id)?.classList.toggle('collapsed');document.getElementById(id+'_body')?.classList.toggle('open');}
function renderPag(p,tot){const div=document.getElementById('panelPag');if(tot<=1){div.innerHTML='';return;}let h=`<button class="ppbtn" onclick="irPag(1)" ${p===1?'disabled':''}>«</button><button class="ppbtn" onclick="irPag(${p-1})" ${p===1?'disabled':''}>‹</button>`;for(let i=Math.max(1,p-2);i<=Math.min(tot,p+2);i++)h+=`<button class="ppbtn${i===p?' active':''}" onclick="irPag(${i})">${i}</button>`;h+=`<button class="ppbtn" onclick="irPag(${p+1})" ${p===tot?'disabled':''}>›</button><button class="ppbtn" onclick="irPag(${tot})" ${p===tot?'disabled':''}>»</button>`;div.innerHTML=h;}
function irPag(p){panelPagina=p;renderPanel();}
function filtrarPanel(){panelFiltro=document.getElementById('inputFiltro').value||'';panelPagina=1;renderPanel();}

/* ═══════════════════════════════════════
   CONTROLES MAPA
═══════════════════════════════════════ */
function toggleCapa(id,el){const c=capas[id];if(!c)return;if(c.activa){mapa.removeLayer(c.layer);c.activa=false;el.classList.remove('on');}else{c.layer.addTo(mapa);c.activa=true;el.classList.add('on');}actualizarStats();}
function centrarCapa(id){const c=capas[id];if(!c)return;capaSeleccionada=id;if(!c.activa){c.layer.addTo(mapa);c.activa=true;}try{const b=c.layer.getBounds();if(b.isValid())mapa.fitBounds(b,{padding:[40,40],maxZoom:19});}catch(e){}document.querySelectorAll('.layer-item').forEach(el=>el.classList.remove('selected'));document.getElementById('li_'+id)?.classList.add('selected');}
function centrarTodo(){const act=Object.values(capas).filter(c=>c.activa);if(!act.length)return;try{const bs=act.map(c=>c.layer.getBounds()).filter(b=>b.isValid());if(!bs.length)return;let cb=bs[0];bs.forEach(b=>{cb=cb.extend(b);});mapa.fitBounds(cb,{padding:[30,30]});}catch(e){}}
function mostrarTodas(){Object.values(capas).forEach(c=>{if(!c.activa){c.layer.addTo(mapa);c.activa=true;}});actualizarStats();renderPanel();toast('✅ Todas visibles','#10b981');}
function ocultarTodas(){Object.values(capas).forEach(c=>{if(c.activa){mapa.removeLayer(c.layer);c.activa=false;}});actualizarStats();renderPanel();toast('👁 Todas ocultas','#f59e0b');}

function ciclarCapa(){
  tiloActual=(tiloActual+1)%TILES.length;
  if(tileLayer)mapa.removeLayer(tileLayer);
  const t=TILES[tiloActual];
  tileLayer=L.tileLayer(t.url,{attribution:t.att,maxNativeZoom:t.maxNativeZoom,maxZoom:t.maxZoom}).addTo(mapa);
  document.getElementById('lblCapa').textContent=t.label;
  toast('🗺 '+t.label,'#38bdf8');
}

function recargar(){
  Object.values(capas).forEach(c=>{try{mapa.removeLayer(c.layer);}catch(e){}});
  capas={};todosSocios=[];capaSeleccionada=null;cancelarEdicion(true);
  document.getElementById('listaCapas').innerHTML='<div style="text-align:center;padding:28px;color:#334155;font-size:12px;"><i class="fa fa-spinner fa-spin" style="font-size:20px;margin-bottom:8px;display:block;color:#38bdf8;"></i>Recargando...</div>';
  cargarTodasLasCapas();
}
function togglePanel(){panelVisible=!panelVisible;document.getElementById('sidePanel').classList.toggle('hidden',!panelVisible);document.getElementById('btnPanel').classList.toggle('active',panelVisible);}
function actualizarStats(){const t=Object.keys(capas).length,v=Object.values(capas).filter(c=>c.activa).length;document.getElementById('stVis').textContent=v;document.getElementById('stTotal').textContent=t;}
function exportarKML(){window.open('ubicaciones_api.php?accion=exportar_todos','_blank');}

/* ═══════════════════════════════════════
   EDITOR ETIQUETAS TIPO QGIS
═══════════════════════════════════════ */
function abrirEditor(id){
  const c=capas[id];if(!c)return;
  mapa.closePopup();
  EA={id_ubicacion:id,nombre:c.arch.nombre_archivo||'',codigo:c.arch.codigo_archivo||'',descripcion:c.descripcion||'',color:c.color,tituloAviso:c.tituloAviso||c.socio||'',atributos:JSON.parse(JSON.stringify(c.atributos||[])),geoInfo:c.geoInfo||{},kmlOriginal:c.kmlOriginal||'',socioNombre:c.socio||''};
  EA.atributos=EA.atributos.map(a=>({...a,tipo:a.tipo||detectarTipo(a.k)}));
  document.getElementById('edTit').textContent=EA.codigo;document.getElementById('edCodigo').value=EA.codigo;document.getElementById('edNombre').value=EA.nombre;document.getElementById('edDesc').value=EA.descripcion;document.getElementById('edTitAviso').value=EA.tituloAviso;
  renderColorSW();renderAtRows();generarQgisHtml();
  document.getElementById('medOv').classList.add('open');document.getElementById('saveInd').textContent='';document.getElementById('saveInd').className='sind';
  cambiarTab('tCampos',document.querySelector('.med-tab'));
}
function renderColorSW(){document.getElementById('colorSW').innerHTML=COLORES.map(c=>`<div class="csw${c===EA.color?' sel':''}" style="background:${c}" onclick="selColor('${c}',this)"></div>`).join('');}
function selColor(color,el){EA.color=color;document.querySelectorAll('#colorSW .csw').forEach(s=>s.classList.remove('sel'));el.classList.add('sel');}
function renderAtRows(){
  const div=document.getElementById('atRows');if(!div)return;let html='';
  EA.atributos.forEach((a,i)=>{
    const tipo=a.tipo||'texto';const isCalc=TIPOS[tipo]&&TIPOS[tipo].calc;const vDisp=isCalc?fmtCalc(tipo,a.v):a.v;
    const opts=Object.entries(TIPOS).map(([k,t])=>`<option value="${k}"${tipo===k?' selected':''}>${t.label}</option>`).join('');
    html+=`<div class="at-row" id="ar_${i}">
      <div class="at-cell"><input type="text" value="${escH(a.k)}" oninput="EA.atributos[${i}].k=this.value;generarQgisHtml()" placeholder="Nombre campo"></div>
      <div class="at-cell">${isCalc?`<input type="text" class="calc-val" value="${escH(vDisp)}" readonly>`:`<input type="text" value="${escH(a.v)}" oninput="EA.atributos[${i}].v=this.value;generarQgisHtml()" placeholder="Valor">`}</div>
      <div class="at-cell"><select onchange="cambiartipo(${i},this.value)">${opts}</select></div>
      <div class="at-cell" style="justify-content:center"><button class="at-del" onclick="elimAtrib(${i})"><i class="fa fa-times"></i></button></div>
    </div>`;
  });div.innerHTML=html;
}
function fmtCalc(tipo,v){const n=parseFloat(v);if(isNaN(n))return v;return(tipo==='latitud'||tipo==='longitud')?n.toFixed(6):n.toFixed(4);}
function cambiartipo(i,nuevoTipo){EA.atributos[i].tipo=nuevoTipo;const g=EA.geoInfo;if(nuevoTipo==='area'&&g.area!==null)EA.atributos[i].v=String(g.area);else if(nuevoTipo==='latitud'&&g.latitud!==null)EA.atributos[i].v=String(parseFloat(g.latitud.toFixed(6)));else if(nuevoTipo==='longitud'&&g.longitud!==null)EA.atributos[i].v=String(parseFloat(g.longitud.toFixed(6)));else if(nuevoTipo==='perimetro'&&g.perimetro!==null)EA.atributos[i].v=String(g.perimetro);renderAtRows();generarQgisHtml();}
function agregarAtributo(){EA.atributos.push({k:'',v:'',tipo:'texto'});renderAtRows();}
function elimAtrib(i){EA.atributos.splice(i,1);renderAtRows();generarQgisHtml();}
function insertarCalc(nombre,tipo){if(EA.atributos.some(a=>a.tipo===tipo&&tipo!=='texto')){toast('⚠ Ya existe ese campo calculado','#f59e0b');return;}const g=EA.geoInfo;let v='';if(tipo==='area')v=g.area!==null?String(g.area):'0';if(tipo==='latitud')v=g.latitud!==null?String(parseFloat(g.latitud.toFixed(6))):'0';if(tipo==='longitud')v=g.longitud!==null?String(parseFloat(g.longitud.toFixed(6))):'0';if(tipo==='perimetro')v=g.perimetro!==null?String(g.perimetro):'0';EA.atributos.push({k:nombre,v,tipo});renderAtRows();generarQgisHtml();}
function generarQgisHtml(){if(!EA)return;const titulo=EA.tituloAviso||EA.socioNombre||EA.codigo;const atrs=EA.atributos.filter(a=>a.k&&a.k.trim());let rows='';atrs.forEach((a,i)=>{const bg=i%2!==0?' bgcolor="#D4E4F3"':'';const tipo=a.tipo||'texto';const isCalc=TIPOS[tipo]&&TIPOS[tipo].calc;const dec=tipo==='latitud'||tipo==='longitud'?6:4;const expr=isCalc?`format_number("${a.k}",${dec})`:`coalesce("${a.k}",'')`;rows+=`\n'<tr${bg}>' ||\n'<td>${escH(a.k)}</td>' ||\n'<td>' || ${expr} || '</td>' ||\n'</tr>' ||`;});document.getElementById('qgisOut').value=`'<table style="font-family:Arial;font-size:12px;width:100%;border-collapse:collapse;">' ||\n'<tr style="background:#9CBCE2;font-weight:bold;text-align:center;">' ||\n'<td colspan="2">${escH(titulo)}</td>' ||\n'</tr>' ||${rows}\n'</table>'`;}
function copiarQgis(){const ta=document.getElementById('qgisOut');ta.select();document.execCommand('copy');toast('✅ Expresión copiada','#10b981');}
function actualizarPreview(){if(!EA)return;const atrs=EA.atributos.filter(a=>a.k&&a.k.trim());const nombre=EA.codigo,col=EA.color,socio=EA.socioNombre;let tabla='<table class="pu-table">';atrs.forEach(a=>{tabla+=`<tr><td>${esc(a.k)}</td><td>${esc(a.v)}</td></tr>`;});tabla+='</table>';document.getElementById('prevPopup').innerHTML=`<div style="background:linear-gradient(135deg,rgba(31,58,95,.9),rgba(13,148,136,.3));padding:10px 13px 8px;border-bottom:1px solid rgba(255,255,255,.08);"><div style="font-size:9px;color:#475569;font-family:'Space Mono',monospace;text-transform:uppercase;margin-bottom:3px;">${esc(EA.codigo)}</div><div style="font-weight:700;font-size:13px;color:${col};">${esc(nombre)}</div><div style="font-size:11px;color:#94a3b8;margin-top:3px;"><i class="fa fa-user" style="color:${col};font-size:10px;"></i> ${esc(socio)}</div></div><div style="padding:9px 11px;">${tabla}</div>`;
const titulo=EA.tituloAviso||EA.socioNombre||nombre;let tbody='';atrs.forEach((a,i)=>{const bg=i%2!==0?'background:#D4E4F3;':'';tbody+=`<tr style="${bg}"><td style="padding:4px 8px;font-size:12px;font-family:Arial;border:1px solid #ccc;">${esc(a.k)}</td><td style="padding:4px 8px;font-size:12px;font-family:Arial;border:1px solid #ccc;">${esc(a.v)}</td></tr>`;});document.getElementById('prevTabla').innerHTML=`<table style="font-family:Arial;font-size:12px;width:100%;border-collapse:collapse;background:#fff;"><tr style="background:#9CBCE2;font-weight:bold;text-align:center;"><td colspan="2" style="padding:6px 8px;border:1px solid #ccc;">${esc(titulo)}</td></tr>${tbody}</table>`;
let kmlRows='';atrs.forEach((a,i)=>{const bg=i%2!==0?' bgcolor="#D4E4F3"':'';kmlRows+=`  <tr${bg}>\n    <td>${escH(a.k)}</td>\n    <td>${escH(a.v)}</td>\n  </tr>\n`;});document.getElementById('prevKml').textContent=`<description><![CDATA[\n<table style="font-family:Arial;font-size:12px;width:100%;border-collapse:collapse;">\n  <tr style="background:#9CBCE2;font-weight:bold;text-align:center;">\n    <td colspan="2">${escH(titulo)}</td>\n  </tr>\n${kmlRows}</table>\n]]></description>`;}
function generarKmlActualizado(editor){if(!editor.kmlOriginal)return null;const atrs=(editor.atributos||[]).filter(a=>a.k&&a.k.trim());const titulo=editor.tituloAviso||editor.socioNombre||editor.codigo||'';let rows='';atrs.forEach((a,i)=>{const bg=i%2!==0?' bgcolor="#D4E4F3"':'';rows+=`    <tr${bg}>\n      <td>${escH(a.k)}</td>\n      <td>${escH(a.v)}</td>\n    </tr>\n`;});const htmlDesc=`<table style="font-family:Arial;font-size:12px;width:100%;border-collapse:collapse;">\n  <tr style="background:#9CBCE2;font-weight:bold;text-align:center;">\n    <td colspan="2">${escH(titulo)}</td>\n  </tr>\n${rows}</table>`;const newDesc=`<description><![CDATA[\n${htmlDesc}\n]]></description>`;let ext='<ExtendedData>\n';atrs.forEach(a=>{ext+=`  <Data name="${escH(a.k)}"><value>${escH(a.v)}</value></Data>\n`;});ext+='</ExtendedData>';let kml=editor.kmlOriginal;if(/<description/i.test(kml))kml=kml.replace(/<description[\s\S]*?<\/description>/gi,newDesc);else kml=kml.replace(/<Placemark[^>]*>/i,m=>m+'\n    '+newDesc);if(/<ExtendedData/i.test(kml))kml=kml.replace(/<ExtendedData>[\s\S]*?<\/ExtendedData>/gi,ext);else kml=kml.replace(/<\/Placemark>/i,'\n  '+ext+'\n</Placemark>');return kml;}
function descargarKmlActualizado(){if(!EA)return;const kml=generarKmlActualizado(EA);if(!kml){toast('❌ Sin KML original','#ef4444');return;}dlBlob(kml,'application/vnd.google-earth.kml+xml',(EA.codigo||'archivo')+'_editado.kml');toast('✅ KML actualizado descargado','#10b981');}
function dlKmlPopup(id){const c=capas[id];if(!c)return;const tmp={codigo:c.codigo,tituloAviso:c.tituloAviso||c.socio,socioNombre:c.socio,atributos:c.atributos||[],kmlOriginal:c.kmlOriginal||''};const kml=generarKmlActualizado(tmp)||c.kmlOriginal||'';dlBlob(kml,'application/vnd.google-earth.kml+xml',c.codigo+'.kml');toast('✅ KML descargado','#10b981');}
function dlBlob(content,mime,filename){const blob=new Blob([content],{type:mime});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=filename;a.click();URL.revokeObjectURL(url);}

async function guardarEtiqueta(){
  if(!EA)return;const btn=document.getElementById('btnGuardar'),ind=document.getElementById('saveInd');
  btn.disabled=true;ind.textContent='Guardando...';ind.className='sind';
  const fd=new FormData();fd.append('accion','actualizar_etiqueta_global');fd.append('id_ubicacion',EA.id_ubicacion);fd.append('nombre',EA.nombre);fd.append('descripcion',EA.descripcion);fd.append('color',EA.color);fd.append('atributos',JSON.stringify(EA.atributos));fd.append('titulo_aviso',EA.tituloAviso);
  try{const r=await fetch('ubicaciones_api.php',{method:'POST',body:fd});const j=await r.json();
    if(j.success){const c=capas[EA.id_ubicacion];if(c){c.color=EA.color;c.descripcion=EA.descripcion;c.tituloAviso=EA.tituloAviso;c.atributos=JSON.parse(JSON.stringify(EA.atributos));c.arch.nombre_archivo=EA.nombre;try{c.layer.setStyle({color:EA.color,fillColor:EA.color});}catch(e){}const dot=document.querySelector(`#li_${EA.id_ubicacion} .l-dot`);if(dot){dot.style.background=EA.color;dot.style.borderColor=EA.color+'80';}}
    ind.textContent='✓ Guardado';ind.className='sind ok';toast('✅ Etiqueta guardada permanentemente','#10b981');setTimeout(()=>cerrarEditor(),700);}
    else{ind.textContent='✗ '+(j.message||'Error');ind.className='sind err';toast('❌ '+(j.message||'Error'),'#ef4444');}
  }catch(ex){ind.textContent='✗ Error de conexión';ind.className='sind err';toast('❌ Error','#ef4444');}
  btn.disabled=false;
}
function cerrarEditor(){document.getElementById('medOv').classList.remove('open');EA=null;}
function cambiarTab(id,btn){document.querySelectorAll('.tc').forEach(t=>t.classList.remove('active'));document.querySelectorAll('.med-tab').forEach(b=>b.classList.remove('active'));document.getElementById(id).classList.add('active');if(btn)btn.classList.add('active');if(id==='tPrev')actualizarPreview();if(id==='tApar')generarQgisHtml();}

/* ═══════════════════════════════════════
   SIDEBAR / LOADING / UTILS
═══════════════════════════════════════ */
function abrirSidebar(){document.querySelector('.sidebar,nav.sidebar,aside.sidebar')?.classList.add('sb-open');document.getElementById('sbOverlay').classList.add('open');}
function cerrarSidebar(){document.querySelector('.sidebar,nav.sidebar,aside.sidebar')?.classList.remove('sb-open');document.getElementById('sbOverlay').classList.remove('open');}
function setLoading(show,text,pct){const ov=document.getElementById('loadingOverlay'),bar=document.getElementById('loadingBar'),txt=document.getElementById('loadingText');if(show){ov.classList.remove('done');if(text)txt.textContent=text;if(pct!==undefined)bar.style.width=pct+'%';}else{bar.style.width='100%';setTimeout(()=>ov.classList.add('done'),300);}}
function esc(s){return(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function escH(s){return(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
let _tt;
function toast(msg,bg){const el=document.getElementById('toast-g');el.textContent=msg;el.style.borderColor=bg||'rgba(255,255,255,.12)';el.classList.add('show');clearTimeout(_tt);_tt=setTimeout(()=>el.classList.remove('show'),3200);}
</script>
</body>
</html>