/**
 * utils.js — Funções utilitárias reutilizáveis
 * Ouvidoria Municipal
 */

const Utils = (() => {

  /* ── Validar CPF ────────────────────────── */

  /* ── Validar E-mail ─────────────────────── */

  /* ── Truncar texto ──────────────────────── */
  function truncate(text, max = 80) {
    return text.length > max ? text.slice(0, max) + '...' : text;
  }

  /* ── Animar contador numérico ───────────── */
  function animateCounter($el) {
    const target = parseInt($el.data('target'), 10);
    const duration = 2000;
    const steps = 60;
    const step = target / steps;
    let current = 0;
    const interval = setInterval(() => {
      current += step;
      if (current >= target) {
        current = target;
        clearInterval(interval);
      }
      $el.text(Math.floor(current).toLocaleString('pt-BR'));
    }, duration / steps);
  }

  /* ── Mostrar Toast ──────────────────────── */
  function showToast(message, type = 'info', title = '') {
    const icons = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info', warning: 'fa-triangle-exclamation' };
    const titles = { success: 'Sucesso', error: 'Erro', info: 'Informação', warning: 'Atenção' };
    const id = 'toast_' + Date.now();
    const html = `
      <div id="${id}" class="toast custom-toast toast-${type}" role="alert" aria-live="assertive" data-bs-delay="4500">
        <div class="toast-header">
          <i class="fa-solid ${icons[type] || icons.info} me-2"></i>
          <strong class="me-auto">${title || titles[type]}</strong>
          <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">${message}</div>
      </div>`;
    $('#toastContainer').append(html);
    const toastEl = document.getElementById(id);
    new bootstrap.Toast(toastEl).show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
  }

  /* ── Debounce ───────────────────────────── */
  function debounce(fn, delay = 300) {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn(...args), delay);
    };
  }

  /* ── Sanitizar HTML ─────────────────────── */
  function sanitize(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  /* ── Public API ─────────────────────────── */
  return { truncate, animateCounter, showToast, debounce, sanitize };

})();