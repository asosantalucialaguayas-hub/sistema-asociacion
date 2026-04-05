// Modal Message System (ARREGLADO)
// - Evita callback doble (botón + timeout)
// - Evita múltiples overlays a la vez
// - Z-index alto para que no quede “atrás”
// - Cierre por click fuera y tecla ESC
// - No repite window.mostrarMensaje 2 veces

(function () {
  let modal = null;
  let timer = null;
  let callbackDone = false;

  function closeModalMessage() {
    if (timer) {
      clearTimeout(timer);
      timer = null;
    }
    if (modal) {
      modal.remove();
      modal = null;
    }
  }

  function showModalMessage(title, message, type = 'success', duration = 3000, callback) {
    // Si ya existe uno, lo cerramos antes de crear otro
    closeModalMessage();
    callbackDone = false;

    modal = document.createElement('div');
    modal.className = 'modal-message-overlay';
    modal.style.zIndex = '999999'; // 🔥 encima del sidebar/modales

    const icon = (type === 'success') ? '✔️' : (type === 'error') ? '❌' : 'ℹ️';

    modal.innerHTML = `
      <div class="modal-message-box modal-${type}" role="dialog" aria-modal="true">
        <div class="modal-message-icon">${icon}</div>
        <h2>${title ?? ''}</h2>
        <p>${message ?? ''}</p>
        <button class="modal-message-btn" id="modalMessageCloseBtn" type="button">Cerrar</button>
      </div>
    `;

    document.body.appendChild(modal);

    const runCallbackOnce = () => {
      if (typeof callback === 'function' && !callbackDone) {
        callbackDone = true;
        callback();
      }
    };

    // Cerrar por botón
    const btn = modal.querySelector('#modalMessageCloseBtn');
    btn.addEventListener('click', () => {
      closeModalMessage();
      runCallbackOnce();
    });

    // Cerrar al hacer click fuera de la caja
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        closeModalMessage();
        runCallbackOnce();
      }
    });

    // Cerrar con ESC
    const onKeyDown = (e) => {
      if (e.key === 'Escape') {
        closeModalMessage();
        runCallbackOnce();
        document.removeEventListener('keydown', onKeyDown);
      }
    };
    document.addEventListener('keydown', onKeyDown);

    // Cierre automático (solo si duration > 0)
    if (duration && duration > 0) {
      timer = setTimeout(() => {
        closeModalMessage();
        runCallbackOnce();
        document.removeEventListener('keydown', onKeyDown);
      }, duration);
    }
  }

  // Optional: Listen for custom events
  document.addEventListener('show-modal-message', function (e) {
    const d = e.detail || {};
    showModalMessage(d.title, d.message, d.type, d.duration, d.callback);
  });

  window.mostrarMensaje = showModalMessage;
})();
