<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Asistencia en Vivo – Asociación Santa Lucía</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800;900&display=swap" rel="stylesheet">
<style>
:root {
    --azul: #1f3a5f;
    --azul2: #2563eb;
    --verde: #16a34a;
    --rojo: #dc2626;
    --amarillo: #d97706;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%);
    min-height: 100vh;
    color: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

/* Header */
.header {
    text-align: center;
    margin-bottom: 32px;
}
.logo {
    font-size: 1rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 8px;
}
.titulo-conv {
    font-size: clamp(1.4rem, 3vw, 2.2rem);
    font-weight: 900;
    color: #fff;
    margin-bottom: 6px;
}
.meta-conv {
    font-size: .9rem;
    color: #94a3b8;
    display: flex;
    gap: 18px;
    justify-content: center;
    flex-wrap: wrap;
}

/* Donut grande */
.donut-wrap {
    position: relative;
    width: clamp(220px, 30vw, 300px);
    height: clamp(220px, 30vw, 300px);
    margin: 0 auto 32px;
}
.donut-wrap svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}
.donut-centro {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}
.donut-pct {
    font-size: clamp(2.5rem, 6vw, 4rem);
    font-weight: 900;
    line-height: 1;
    transition: all .8s;
}
.donut-label {
    font-size: .75rem;
    color: #94a3b8;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* KPIs */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    width: 100%;
    max-width: 700px;
    margin-bottom: 28px;
}
.kpi {
    background: rgba(255,255,255,.07);
    border: 1.5px solid rgba(255,255,255,.12);
    border-radius: 20px;
    padding: 24px 16px;
    text-align: center;
    backdrop-filter: blur(10px);
    transition: transform .3s;
}
.kpi:hover { transform: translateY(-3px); }
.kpi .num {
    font-size: clamp(2rem, 5vw, 3.5rem);
    font-weight: 900;
    line-height: 1;
    transition: all .8s;
}
.kpi .lbl {
    font-size: .78rem;
    color: #94a3b8;
    font-weight: 700;
    margin-top: 6px;
    text-transform: uppercase;
    letter-spacing: .5px;
}

/* Barra progreso */
.barra-wrap {
    width: 100%;
    max-width: 700px;
    margin-bottom: 24px;
}
.barra-labels {
    display: flex;
    justify-content: space-between;
    font-size: .82rem;
    color: #94a3b8;
    margin-bottom: 8px;
    font-weight: 700;
}
.barra-bg {
    background: rgba(255,255,255,.1);
    border-radius: 50px;
    height: 28px;
    overflow: hidden;
}
.barra-fill {
    height: 100%;
    border-radius: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .85rem;
    font-weight: 800;
    color: #fff;
    transition: width 1.5s cubic-bezier(.4,0,.2,1);
    min-width: 44px;
}

/* Estado quórum */
.quorum-box {
    width: 100%;
    max-width: 700px;
    border-radius: 16px;
    padding: 16px 22px;
    display: flex;
    align-items: center;
    gap: 14px;
    font-weight: 700;
    font-size: 1rem;
    margin-bottom: 24px;
    transition: all .8s;
}
.quorum-ok   { background: rgba(22,163,74,.25);  border: 2px solid rgba(22,163,74,.5);  }
.quorum-warn { background: rgba(217,119,6,.2);   border: 2px solid rgba(217,119,6,.4);  }
.quorum-icon { font-size: 1.8rem; }

/* Últimos registros */
.ultimos {
    width: 100%;
    max-width: 700px;
    background: rgba(255,255,255,.05);
    border: 1.5px solid rgba(255,255,255,.1);
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 24px;
}
.ultimos-header {
    padding: 14px 20px;
    border-bottom: 1px solid rgba(255,255,255,.1);
    font-weight: 800;
    font-size: .9rem;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: .5px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.reg-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 20px;
    border-bottom: 1px solid rgba(255,255,255,.05);
    animation: slideIn .4s ease;
    transition: background .2s;
}
.reg-item:last-child { border-bottom: none; }
.reg-item:hover { background: rgba(255,255,255,.05); }
@keyframes slideIn {
    from { opacity: 0; transform: translateX(-20px); }
    to   { opacity: 1; transform: translateX(0); }
}
.av {
    width: 42px; height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb, #1f3a5f);
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: .85rem;
    flex-shrink: 0;
    border: 2px solid rgba(255,255,255,.2);
}
.reg-nombre { font-weight: 700; font-size: .9rem; }
.reg-hora   { font-size: .75rem; color: #94a3b8; margin-top: 2px; }
.reg-badge  {
    margin-left: auto;
    background: rgba(22,163,74,.3);
    border: 1px solid rgba(22,163,74,.5);
    color: #86efac;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: .72rem;
    font-weight: 700;
    flex-shrink: 0;
}

/* Footer */
.footer {
    font-size: .78rem;
    color: #475569;
    text-align: center;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dot-live {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #22c55e;
    animation: pulse 1.5s infinite;
    display: inline-block;
}
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }

/* Responsive */
@media (max-width: 480px) {
    .kpi-grid { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>

<div class="header">
    <div class="logo">🏛 Asociación Santa Lucía de las Guayas</div>
    <div class="titulo-conv" id="tituloConv">Cargando...</div>
    <div class="meta-conv" id="metaConv">
        <span>⏳ Conectando...</span>
    </div>
</div>

<!-- Donut -->
<div class="donut-wrap">
    <svg viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg">
        <circle cx="18" cy="18" r="15.9" fill="none" stroke="rgba(255,255,255,.1)" stroke-width="3.8"/>
        <circle id="donutArc" cx="18" cy="18" r="15.9" fill="none"
            stroke="#2563eb" stroke-width="3.8"
            stroke-dasharray="0 100" stroke-linecap="round"/>
    </svg>
    <div class="donut-centro">
        <div class="donut-pct" id="pctNum" style="color:#2563eb;">0%</div>
        <div class="donut-label">Asistencia</div>
    </div>
</div>

<!-- KPIs -->
<div class="kpi-grid">
    <div class="kpi">
        <div class="num" id="numTotal" style="color:#94a3b8;">—</div>
        <div class="lbl">👥 Total Socios</div>
    </div>
    <div class="kpi">
        <div class="num" id="numPresentes" style="color:#22c55e;">0</div>
        <div class="lbl">✅ Presentes</div>
    </div>
    <div class="kpi">
        <div class="num" id="numAusentes" style="color:#ef4444;">—</div>
        <div class="lbl">❌ Ausentes</div>
    </div>
</div>

<!-- Barra -->
<div class="barra-wrap">
    <div class="barra-labels">
        <span>Progreso hacia quórum (50%)</span>
        <span id="barraLabel">0 / 0</span>
    </div>
    <div class="barra-bg">
        <div class="barra-fill" id="barraFill" style="width:0%;background:linear-gradient(90deg,#2563eb,#16a34a);">0%</div>
    </div>
</div>

<!-- Quórum -->
<div class="quorum-box quorum-warn" id="quorumBox">
    <span class="quorum-icon">⏳</span>
    <span id="quorumTxt">Calculando quórum...</span>
</div>

<!-- Últimos registros -->
<div class="ultimos">
    <div class="ultimos-header">
        <span>🕐 Últimos registros</span>
        <span id="ultActualizado" style="font-weight:400;font-size:.75rem;">—</span>
    </div>
    <div id="listaRegistros">
        <div style="padding:24px;text-align:center;color:#475569;">Esperando registros...</div>
    </div>
</div>

<!-- Footer -->
<div class="footer">
    <span class="dot-live"></span>
    Actualizando en tiempo real · <span id="ultimaActualizacion">—</span>
</div>

<script>
// ─── Configuración ────────────────────────────────────────────
// Detectar conv_id desde la URL: resumen_publico.php?conv_id=2
const params  = new URLSearchParams(location.search);
const CONV_ID = params.get('conv_id') || 0;
const BASE    = location.origin + location.pathname.replace('resumen_publico.php', '');

// ─── Estado ───────────────────────────────────────────────────
let ultimosRegistros = [];
let prevPresentes    = 0;

// ─── Fetch datos ──────────────────────────────────────────────
async function actualizarDatos() {
    try {
        const r = await fetch(`${BASE}ajax_resumen_asistencia.php?conv_id=${CONV_ID}&t=${Date.now()}`);
        const d = await r.json();
        if (!d.ok) return;

        renderDatos(d);
        document.getElementById('ultimaActualizacion').textContent =
            new Date().toLocaleTimeString('es-EC', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
    } catch(e) {
        console.error('Error al actualizar:', e);
    }
}

function renderDatos(d) {
    // Títulos
    document.getElementById('tituloConv').textContent = d.titulo || 'Sesión en curso';
    document.getElementById('metaConv').innerHTML =
        `<span>📅 ${d.fecha || ''}</span><span>🕐 ${d.hora || ''}</span><span>📍 ${d.lugar || ''}</span>`;

    // Números
    const pct       = d.porcentaje || 0;
    const presentes = d.presentes  || 0;
    const total     = d.total      || 0;
    const ausentes  = Math.max(0, total - presentes);

    // Animación si hay nuevo registro
    if (presentes > prevPresentes && prevPresentes > 0) {
        document.getElementById('numPresentes').style.transform = 'scale(1.3)';
        setTimeout(() => document.getElementById('numPresentes').style.transform = 'scale(1)', 400);
        // Notificación sonora suave (opcional)
        try { new Audio('data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAA...').play(); } catch(e){}
    }
    prevPresentes = presentes;

    document.getElementById('numTotal').textContent    = total;
    document.getElementById('numPresentes').textContent = presentes;
    document.getElementById('numAusentes').textContent  = ausentes;

    // Donut
    const color = pct >= 50 ? '#16a34a' : pct >= 30 ? '#d97706' : '#2563eb';
    document.getElementById('donutArc').setAttribute('stroke-dasharray', `${pct} 100`);
    document.getElementById('donutArc').setAttribute('stroke', color);
    document.getElementById('pctNum').textContent  = pct + '%';
    document.getElementById('pctNum').style.color  = color;

    // Barra
    document.getElementById('barraFill').style.width = Math.min(pct, 100) + '%';
    document.getElementById('barraFill').textContent  = pct + '%';
    document.getElementById('barraFill').style.background = pct >= 50
        ? 'linear-gradient(90deg,#16a34a,#22c55e)'
        : 'linear-gradient(90deg,#1d4ed8,#2563eb)';
    document.getElementById('barraLabel').textContent = `${presentes} / ${total}`;

    // Quórum
    const qBox = document.getElementById('quorumBox');
    const qTxt = document.getElementById('quorumTxt');
    if (pct >= 50) {
        qBox.className = 'quorum-box quorum-ok';
        qBox.querySelector('.quorum-icon').textContent = '🎉';
        qTxt.textContent = `¡Quórum alcanzado! La sesión es válida (${pct}% de asistencia)`;
    } else {
        const faltan = Math.ceil(total * 0.5) - presentes;
        qBox.className = 'quorum-box quorum-warn';
        qBox.querySelector('.quorum-icon').textContent = '⚠️';
        qTxt.textContent = `Quórum incompleto — Faltan ${faltan} socio(s) para el 50%`;
    }

    // Últimos registros
    if (d.ultimos && d.ultimos.length > 0) {
        ultimosRegistros = d.ultimos;
        const lista = document.getElementById('listaRegistros');
        lista.innerHTML = d.ultimos.map(u => {
            const partes = (u.nombre_completo || '').split(' ');
            const ini = (partes[0]?.[0] || '') + (partes[1]?.[0] || '');
            return `
            <div class="reg-item">
                <div class="av">${ini.toUpperCase()}</div>
                <div>
                    <div class="reg-nombre">${u.nombre_completo}</div>
                    <div class="reg-hora">🕐 ${u.hora_registro} · 🪪 ${u.cedula}</div>
                </div>
                <div class="reg-badge">✓ Biométrico</div>
            </div>`;
        }).join('');
        document.getElementById('ultActualizado').textContent =
            'Actualizado ' + new Date().toLocaleTimeString('es-EC', {hour:'2-digit',minute:'2-digit'});
    }
}

// ─── Iniciar ──────────────────────────────────────────────────
if (!CONV_ID) {
    document.getElementById('tituloConv').textContent = '⚠️ Falta conv_id en la URL';
    document.getElementById('metaConv').innerHTML = '<span>Usa: resumen_publico.php?conv_id=2</span>';
} else {
    actualizarDatos();
    setInterval(actualizarDatos, 8000); // Cada 8 segundos
}
</script>
</body>
</html>
