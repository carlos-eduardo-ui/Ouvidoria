/**
 * utils.js — Funções utilitárias reutilizáveis
 * Ouvidoria Municipal
 */

const Utils = (() => {

  /* ── Formatar CPF ───────────────────────── */
  function formatCPF(value) {
    return value
      .replace(/\D/g, '')
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  }

  /* ── Validar CPF ────────────────────────── */
  function validateCPF(cpf) {
    cpf = cpf.replace(/\D/g, '');
    if (cpf.length !== 11 || /^(\d)\1+$/.test(cpf)) return false;
    let sum = 0;
    for (let i = 0; i < 9; i++) sum += parseInt(cpf[i]) * (10 - i);
    let r = 11 - (sum % 11);
    if (r >= 10) r = 0;
    if (r !== parseInt(cpf[9])) return false;
    sum = 0;
    for (let i = 0; i < 10; i++) sum += parseInt(cpf[i]) * (11 - i);
    r = 11 - (sum % 11);
    if (r >= 10) r = 0;
    return r === parseInt(cpf[10]);
  }

  /* ── Formatar Telefone ──────────────────── */
  function formatPhone(value) {
    return value
      .replace(/\D/g, '')
      .replace(/^(\d{2})(\d)/, '($1) $2')
      .replace(/(\d{5})(\d{4})$/, '$1-$2');
  }

  /* ── Validar E-mail ─────────────────────── */
  function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  /* ── Gerar Protocolo aleatório ──────────── */
  function generateProtocol() {
    const year = new Date().getFullYear();
    const rand = Math.floor(10000 + Math.random() * 89999);
    return `OUV-${year}-${rand}`;
  }

  /* ── Formatar data pt-BR ────────────────── */
  function formatDate(dateString) {
    if (!dateString) return '—';
    const [y, m, d] = dateString.split('-');
    return `${d}/${m}/${y}`;
  }

  /* ── Data/hora atual ────────────────────── */
  function nowBR() {
    return new Date().toLocaleString('pt-BR', { timeZone: 'America/Fortaleza' });
  }

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
  return { formatCPF, validateCPF, formatPhone, validateEmail, generateProtocol, formatDate, nowBR, truncate, animateCounter, showToast, debounce, sanitize };

})();