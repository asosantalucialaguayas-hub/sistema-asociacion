<!-- SISTEMA DE MODALES BONITOS CENTRALIZADO -->
<style>
.modal-overlay-system {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.5);
    z-index: 11000;
    align-items: center;
    justify-content: center;
}

.modal-overlay-system.active {
    display: flex;
}

.modal-message-box {
    background: #fff;
    width: 380px;
    border-radius: 16px;
    padding: 28px 24px;
    text-align: center;
    box-shadow: 0 30px 80px rgba(0,0,0,.25);
    animation: popIn .25s ease-out;
}

@keyframes popIn {
    from { transform: scale(.9); opacity: 0 }
    to { transform: scale(1); opacity: 1 }
}

.modal-message-icon {
    width: 70px;
    height: 70px;
    margin: 0 auto 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
}

.modal-message-icon.success {
    background: #dcfce7;
    color: #16a34a;
}

.modal-message-icon.error {
    background: #fee2e2;
    color: #dc2626;
}

.modal-message-icon.info {
    background: #dbeafe;
    color: #2563eb;
}

.modal-message-box h3 {
    margin: 10px 0 6px;
    font-size: 20px;
    color: #1f3a5f;
    font-weight: 700;
}

.modal-message-box p {
    font-size: 14px;
    color: #4b5563;
    margin-bottom: 20px;
}

.modal-message-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.modal-message-actions button {
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s ease;
}

.btn-success-modal {
    background: #10b981;
    color: #fff;
}

.btn-success-modal:hover {
    background: #059669;
}

.btn-danger-modal {
    background: #ef4444;
    color: #fff;
}

.btn-danger-modal:hover {
    background: #dc2626;
}

.btn-cancel-modal {
    background: #e5e7eb;
    color: #374151;
}

.btn-cancel-modal:hover {
    background: #d1d5db;
}
</style>

<div id="modalSystemOverlay" class="modal-overlay-system">
    <div class="modal-message-box">
        <div id="modalIcon" class="modal-message-icon success">✓</div>
        <h3 id="modalTitle">Título</h3>
        <p id="modalMessage">Mensaje</p>
        <div id="modalActionsContainer" class="modal-message-actions">
            <button id="modalPrimaryBtn" class="btn-success-modal">Aceptar</button>
        </div>
    </div>
</div>

<script>
// Sistema centralizado de modales
function mostrarMensaje(titulo, mensaje, tipo = 'success', callback = null) {
    const overlay = document.getElementById('modalSystemOverlay');
    const icon = document.getElementById('modalIcon');
    const titleEl = document.getElementById('modalTitle');
    const msgEl = document.getElementById('modalMessage');
    const actionsEl = document.getElementById('modalActionsContainer');
    
    // Limpiar clases del icono
    icon.className = 'modal-message-icon';
    icon.classList.add(tipo);
    
    // Establecer icono
    const iconos = {
        'success': '✓',
        'error': '✕',
        'info': 'ⓘ'
    };
    icon.textContent = iconos[tipo] || '✓';
    
    // Establecer contenido
    titleEl.textContent = titulo;
    msgEl.textContent = mensaje;
    
    // Establecer botón único
    actionsEl.innerHTML = '';
    const btn = document.createElement('button');
    btn.className = tipo === 'error' ? 'btn-danger-modal' : 'btn-success-modal';
    btn.textContent = 'Aceptar';
    btn.onclick = () => {
        overlay.classList.remove('active');
        if (callback) callback();
    };
    actionsEl.appendChild(btn);
    
    overlay.classList.add('active');
}

function mostrarConfirmacion(titulo, mensaje, onConfirm, onCancel = null) {
    const overlay = document.getElementById('modalSystemOverlay');
    const icon = document.getElementById('modalIcon');
    const titleEl = document.getElementById('modalTitle');
    const msgEl = document.getElementById('modalMessage');
    const actionsEl = document.getElementById('modalActionsContainer');
    
    // Icono de confirmación (interrogación)
    icon.className = 'modal-message-icon info';
    icon.textContent = '⚠';
    
    // Contenido
    titleEl.textContent = titulo;
    msgEl.textContent = mensaje;
    
    // Botones
    actionsEl.innerHTML = '';
    
    const btnConfirm = document.createElement('button');
    btnConfirm.className = 'btn-danger-modal';
    btnConfirm.textContent = 'Confirmar';
    btnConfirm.onclick = () => {
        overlay.classList.remove('active');
        if (onConfirm) onConfirm();
    };
    
    const btnCancel = document.createElement('button');
    btnCancel.className = 'btn-cancel-modal';
    btnCancel.textContent = 'Cancelar';
    btnCancel.onclick = () => {
        overlay.classList.remove('active');
        if (onCancel) onCancel();
    };
    
    actionsEl.appendChild(btnCancel);
    actionsEl.appendChild(btnConfirm);
    
    overlay.classList.add('active');
}

// Compatibilidad global: reemplaza alert y confirm
window.mostrarAlerta = mostrarMensaje;
window.mostrarConfirm = mostrarConfirmacion;
</script>
