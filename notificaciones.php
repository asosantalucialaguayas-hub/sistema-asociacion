<!-- ═══════════════════════════════════════════════════════════════
     NOTIFICACIONES — incluir en layout/topbar o donde esté el topbar
     Uso: <?php include __DIR__."/layout/notificaciones.php"; ?>
     ═══════════════════════════════════════════════════════════════ -->

<style>
/* ── Campanita ── */
.notif-wrapper {
    position: relative;
    display: inline-flex;
    align-items: center;
    margin-right: 16px;
}

.notif-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    background: #f1f5f9;
    color: #475569;
    font-size: 17px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .2s, transform .2s;
    position: relative;
}
.notif-btn:hover {
    background: #e2e8f0;
    transform: scale(1.08);
}
.notif-btn.tiene-notifs {
    animation: campana 1.2s ease-in-out 1s 3;
}
@keyframes campana {
    0%,100% { transform: rotate(0); }
    15%      { transform: rotate(15deg); }
    30%      { transform: rotate(-12deg); }
    45%      { transform: rotate(10deg); }
    60%      { transform: rotate(-8deg); }
    75%      { transform: rotate(5deg); }
}

/* Badge contador */
.notif-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 18px;
    height: 18px;
    background: #ef4444;
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    border: 2px solid #fff;
    display: none;
    line-height: 1;
}

/* ── Panel desplegable ── */
.notif-panel {
    display: none;
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 400px;
    max-height: 560px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,.18), 0 4px 16px rgba(0,0,0,.08);
    z-index: 99999;
    overflow: hidden;
    flex-direction: column;
    animation: panelIn .2s ease-out;
}
.notif-panel.open {
    display: flex;
}
@keyframes panelIn {
    from { opacity: 0; transform: translateY(-8px) scale(.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* Header del panel */
.notif-header {
    padding: 18px 20px 14px;
    border-bottom: 1.5px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.notif-header h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 800;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}
.notif-header-right {
    display: flex;
    align-items: center;
    gap: 10px;
}
.notif-total-badge {
    background: #ef4444;
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    padding: 3px 9px;
    border-radius: 999px;
}
.notif-marcar-leidas {
    font-size: 12px;
    color: #64748b;
    background: none;
    border: none;
    cursor: pointer;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 6px;
}
.notif-marcar-leidas:hover {
    background: #f1f5f9;
    color: #1e293b;
}

/* Filtros por tipo */
.notif-filtros {
    display: flex;
    gap: 6px;
    padding: 10px 16px;
    border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0;
    overflow-x: auto;
}
.notif-filtro-btn {
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    border: 1.5px solid #e2e8f0;
    background: #fff;
    color: #64748b;
    cursor: pointer;
    white-space: nowrap;
    transition: all .15s;
}
.notif-filtro-btn:hover,
.notif-filtro-btn.activo {
    background: #1f3a5f;
    color: #fff;
    border-color: #1f3a5f;
}

/* Lista */
.notif-lista {
    overflow-y: auto;
    flex: 1;
    padding: 8px 0;
}
.notif-lista::-webkit-scrollbar { width: 4px; }
.notif-lista::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }

/* Item */
.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 18px;
    cursor: pointer;
    transition: background .15s;
    border-bottom: 1px solid #f8fafc;
    text-decoration: none;
    color: inherit;
}
.notif-item:hover {
    background: #f8fafc;
}
.notif-item.leida {
    opacity: .55;
}

.notif-icono {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
    margin-top: 1px;
}

.notif-body {
    flex: 1;
    min-width: 0;
}
.notif-titulo {
    font-size: 12px;
    font-weight: 800;
    margin-bottom: 2px;
    color: #1e293b;
}
.notif-mensaje {
    font-size: 12px;
    color: #475569;
    line-height: 1.4;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.notif-flecha {
    color: #cbd5e1;
    font-size: 12px;
    align-self: center;
    flex-shrink: 0;
}

/* Vacío */
.notif-vacio {
    text-align: center;
    padding: 40px 20px;
    color: #94a3b8;
}
.notif-vacio i {
    font-size: 36px;
    display: block;
    margin-bottom: 10px;
    color: #10b981;
}
.notif-vacio p {
    font-size: 14px;
    font-weight: 600;
    margin: 0;
}

/* Cargando */
.notif-loading {
    text-align: center;
    padding: 30px;
    color: #94a3b8;
    font-size: 13px;
}

/* Footer */
.notif-footer {
    padding: 12px 18px;
    border-top: 1.5px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
    background: #fafafa;
}
.notif-periodo {
    font-size: 11px;
    color: #94a3b8;
    font-weight: 600;
}
.notif-refresh {
    font-size: 12px;
    color: #64748b;
    background: none;
    border: none;
    cursor: pointer;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 5px;
}
.notif-refresh:hover { color: #1f3a5f; }

/* Resumen chips en header */
.notif-chips {
    display: flex;
    gap: 6px;
    padding: 8px 16px;
    flex-shrink: 0;
    flex-wrap: wrap;
    border-bottom: 1px solid #f1f5f9;
}
.notif-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}
</style>

<!-- CAMPANITA -->
<div class="notif-wrapper" id="notifWrapper">
    <button class="notif-btn" id="notifBtn" onclick="toggleNotifPanel()" title="Notificaciones">
        <i class="fa fa-bell"></i>
        <span class="notif-badge" id="notifBadge">0</span>
    </button>

    <!-- PANEL -->
    <div class="notif-panel" id="notifPanel">

        <div class="notif-header">
            <h3><i class="fa fa-bell" style="color:#1f3a5f"></i> Notificaciones</h3>
            <div class="notif-header-right">
                <span class="notif-total-badge" id="notifTotalBadge" style="display:none">0</span>
                <button class="notif-marcar-leidas" onclick="marcarTodasLeidas()">
                    <i class="fa fa-check-double"></i> Marcar leídas
                </button>
            </div>
        </div>

        <!-- Chips resumen -->
        <div class="notif-chips" id="notifChips"></div>

        <!-- Filtros por tipo — incluye Sin coords -->
        <div class="notif-filtros">
            <button class="notif-filtro-btn activo" onclick="filtrarNotifs('todos', this)">
                Todos
            </button>
            <button class="notif-filtro-btn" onclick="filtrarNotifs('sin_lpa', this)">
                <i class="fa fa-chart-line"></i> Sin LPA
            </button>
            <button class="notif-filtro-btn" onclick="filtrarNotifs('sin_documentos', this)">
                <i class="fa fa-folder-open"></i> Sin docs
            </button>
            <button class="notif-filtro-btn" onclick="filtrarNotifs('sin_cupo', this)">
                <i class="fa fa-scale-balanced"></i> Sin cupo
            </button>
            <button class="notif-filtro-btn" onclick="filtrarNotifs('pendiente_aprobacion', this)">
                <i class="fa fa-user-clock"></i> Pendientes
            </button>
            <button class="notif-filtro-btn" onclick="filtrarNotifs('sin_coordenadas', this)">
                <i class="fa fa-map-location-dot"></i> Sin coords
            </button>
        </div>

        <!-- Lista -->
        <div class="notif-lista" id="notifLista">
            <div class="notif-loading">
                <i class="fa fa-spinner fa-spin"></i> Cargando...
            </div>
        </div>

        <!-- Footer -->
        <div class="notif-footer">
            <span class="notif-periodo" id="notifPeriodo"></span>
            <button class="notif-refresh" onclick="cargarNotificaciones()">
                <i class="fa fa-rotate-right"></i> Actualizar
            </button>
        </div>

    </div>
</div>

<script>
(function() {

let _todasNotifs  = [];
let _leidasSet    = new Set(JSON.parse(localStorage.getItem('notifs_leidas') || '[]'));
let _filtroActual = 'todos';
let _intervalo    = null;

// Colores por tipo — incluye sin_coordenadas
const _colores = {
    sin_lpa:              { bg:'#fee2e2', color:'#991b1b', icono:'fa-chart-line',        label:'Sin LPA' },
    sin_documentos:       { bg:'#fef3c7', color:'#92400e', icono:'fa-folder-open',       label:'Sin docs' },
    sin_cupo:             { bg:'#ede9fe', color:'#5b21b6', icono:'fa-scale-balanced',    label:'Sin cupo' },
    pendiente_aprobacion: { bg:'#dbeafe', color:'#1e40af', icono:'fa-user-clock',        label:'Pendientes' },
    sin_coordenadas:      { bg:'#d1fae5', color:'#065f46', icono:'fa-map-location-dot',  label:'Sin coords' },
};

// ── Abrir/cerrar panel ────────────────────────────────────────────────────────
window.toggleNotifPanel = function() {
    const panel = document.getElementById('notifPanel');
    const abierto = panel.classList.contains('open');
    if (abierto) {
        panel.classList.remove('open');
    } else {
        panel.classList.add('open');
        cargarNotificaciones();
    }
};

// Cerrar al hacer clic fuera
document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('notifWrapper');
    if (wrapper && !wrapper.contains(e.target)) {
        document.getElementById('notifPanel')?.classList.remove('open');
    }
});

// ── Cargar notificaciones ─────────────────────────────────────────────────────
window.cargarNotificaciones = async function() {
    try {
        const res  = await fetch('notificaciones_api.php', { credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) return;

        _todasNotifs = data.notificaciones || [];

        // Actualizar badge
        const noLeidas = _todasNotifs.filter(n => !_leidasSet.has(_claveNotif(n))).length;
        const badge    = document.getElementById('notifBadge');
        const totalBdg = document.getElementById('notifTotalBadge');
        const btn      = document.getElementById('notifBtn');

        if (noLeidas > 0) {
            badge.textContent = noLeidas > 99 ? '99+' : noLeidas;
            badge.style.display = 'flex';
            totalBdg.textContent = noLeidas;
            totalBdg.style.display = 'inline-flex';
            btn.classList.add('tiene-notifs');
        } else {
            badge.style.display = 'none';
            totalBdg.style.display = 'none';
            btn.classList.remove('tiene-notifs');
        }

        // Chips resumen
        const chips = document.getElementById('notifChips');
        let chipsHtml = '';
        Object.entries(data.resumen || {}).forEach(([tipo, cnt]) => {
            if (cnt > 0 && _colores[tipo]) {
                const c = _colores[tipo];
                chipsHtml += `<span class="notif-chip" style="background:${c.bg};color:${c.color}">
                    <i class="fa ${c.icono}"></i> ${c.label}: <strong>${cnt}</strong>
                </span>`;
            }
        });
        chips.innerHTML = chipsHtml || '<span style="font-size:12px;color:#94a3b8">Todo en orden ✅</span>';

        // Periodo
        document.getElementById('notifPeriodo').textContent =
            data.periodo ? 'Período: ' + data.periodo : '';

        renderNotifs(_filtroActual);

    } catch(e) {
        console.error('notificaciones_api error:', e);
    }
};

// ── Renderizar lista ──────────────────────────────────────────────────────────
function renderNotifs(filtro) {
    _filtroActual = filtro;
    const lista = document.getElementById('notifLista');

    const filtradas = filtro === 'todos'
        ? _todasNotifs
        : _todasNotifs.filter(n => n.tipo === filtro);

    if (filtradas.length === 0) {
        lista.innerHTML = `
            <div class="notif-vacio">
                <i class="fa fa-check-circle"></i>
                <p>Sin notificaciones${filtro !== 'todos' ? ' de este tipo' : ''}</p>
            </div>`;
        return;
    }

    lista.innerHTML = filtradas.map(n => {
        const leida = _leidasSet.has(_claveNotif(n));
        const c     = _colores[n.tipo] || { bg:'#f1f5f9', color:'#64748b' };
        return `
        <a class="notif-item${leida ? ' leida' : ''}"
           href="${n.url}"
           onclick="_marcarLeida('${_claveNotif(n)}')"
           title="${n.mensaje}">
            <div class="notif-icono" style="background:${c.bg};color:${c.color}">
                <i class="fa ${n.icono}"></i>
            </div>
            <div class="notif-body">
                <div class="notif-titulo">${n.titulo}</div>
                <div class="notif-mensaje">${n.mensaje}</div>
            </div>
            <i class="fa fa-chevron-right notif-flecha"></i>
        </a>`;
    }).join('');
}

// ── Filtrar ───────────────────────────────────────────────────────────────────
window.filtrarNotifs = function(tipo, btnEl) {
    document.querySelectorAll('.notif-filtro-btn').forEach(b => b.classList.remove('activo'));
    btnEl.classList.add('activo');
    renderNotifs(tipo);
};

// ── Marcar leídas ─────────────────────────────────────────────────────────────
window._marcarLeida = function(clave) {
    _leidasSet.add(clave);
    _guardarLeidas();
};

window.marcarTodasLeidas = function() {
    _todasNotifs.forEach(n => _leidasSet.add(_claveNotif(n)));
    _guardarLeidas();
    cargarNotificaciones();
};

function _guardarLeidas() {
    try {
        localStorage.setItem('notifs_leidas', JSON.stringify([..._leidasSet]));
    } catch(e) {}
}

function _claveNotif(n) {
    return n.tipo + '_' + (n.id_socio || n.id_solicitud || n.id_lpa || n.cedula || '');
}

// ── Auto-refresh cada 2 minutos ───────────────────────────────────────────────
cargarNotificaciones(); // carga inicial para el badge
_intervalo = setInterval(cargarNotificaciones, 2 * 60 * 1000);

})();
</script>