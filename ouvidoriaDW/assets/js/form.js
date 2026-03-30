/**
 * form.js — Lógica do formulário multi-step da Ouvidoria
 * Gerencia steps, validações, upload de arquivos e revisão
 */

const Form = (() => {

  let currentStep = 1;
  let isAnonymous = false;
  const uploadedFiles = [];

  /* ── Init ─────────────────────────────── */
  function init() {
    _bindMasks();
    _bindStepNavigation();
    _bindAnonymousToggle();
    _bindCharCounter();
    _bindFileUpload();
    _bindServiceLinks();
    _bindNewManifestacao();
  }

  /* ── Máscaras de input ────────────────── */
  function _bindMasks() {
    $('#cpf').on('input', function () {
      $(this).val(Utils.formatCPF($(this).val()));
    });
  }

  /* ── Toggle anônimo ───────────────────── */
  function _bindAnonymousToggle() {
    $('#anonimo').on('change', function () {
      isAnonymous = $(this).is(':checked');
      if (isAnonymous) {
        $('.identificado-field').hide();
        $('.identificado-field input').val('').removeClass('is-invalid');
      } else {
        $('.identificado-field').show();
      }
    });
  }

  /* ── Contador de caracteres ───────────── */
  function _bindCharCounter() {
    $('#descricao').on('input', function () {
      const len = $(this).val().length;
      $('#charCount').text(len);
      if (len > 1000) $(this).val($(this).val().slice(0, 1000));
    });
  }

  /* ── Upload de arquivos ───────────────── */
  function _bindFileUpload() {
    const $area = $('#uploadArea');
    const $input = $('#fileInput');
    const $list = $('#fileList');

    $area.on('dragover', e => { e.preventDefault(); $area.addClass('drag-over'); });
    $area.on('dragleave', () => $area.removeClass('drag-over'));
    $area.on('drop', e => {
      e.preventDefault();
      $area.removeClass('drag-over');
      _handleFiles(e.originalEvent.dataTransfer.files);
    });

    $input.on('change', function () { _handleFiles(this.files); });

    function _handleFiles(files) {
      Array.from(files).forEach(file => {
        if (file.size > 5 * 1024 * 1024) {
          Utils.showToast(`"${file.name}" excede 5MB e foi ignorado.`, 'warning');
          return;
        }
        if (uploadedFiles.find(f => f.name === file.name)) return;
        uploadedFiles.push(file);
        const ext = file.name.split('.').pop().toUpperCase();
        const $item = $(`
          <div class="file-item" data-name="${Utils.sanitize(file.name)}">
            <i class="fa-solid fa-file-${_iconForExt(ext)}"></i>
            <span>${Utils.truncate(file.name, 28)}</span>
            <i class="fa-solid fa-xmark remove-file" title="Remover"></i>
          </div>`);
        $list.append($item);
      });
    }

    $list.on('click', '.remove-file', function () {
      const name = $(this).closest('.file-item').data('name');
      const idx = uploadedFiles.findIndex(f => f.name === name);
      if (idx > -1) uploadedFiles.splice(idx, 1);
      $(this).closest('.file-item').remove();
    });
  }

  function _iconForExt(ext) {
    const map = { PDF: 'pdf', JPG: 'image', JPEG: 'image', PNG: 'image' };
    return map[ext] || 'alt';
  }

  /* ── Links de serviço → pré-seleciona tipo ─ */
  function _bindServiceLinks() {
    $('.service-link').on('click', function (e) {
      const tipo = $(this).data('tipo');
      if (tipo) $('#tipo').val(tipo);
    });
  }

  /* ── Navegação entre steps ────────────── */
  function _bindStepNavigation() {
    $('#next-to-2').on('click', () => { if (_validateStep1()) _goTo(2); });
    $('#next-to-3').on('click', () => { if (_validateStep2()) { _buildReview(); _goTo(3); } });
    $('#back-to-1').on('click', () => _goTo(1));
    $('#back-to-2').on('click', () => _goTo(2));
  }

  function _goTo(step) {
    $(`#step-${currentStep}`).removeClass('active');
    $(`#step-indicator-${currentStep}`).removeClass('active').toggleClass('done', currentStep < step);
    currentStep = step;
    $(`#step-${currentStep}`).addClass('active');
    $(`#step-indicator-${currentStep}`).addClass('active');
    // Mark lines between steps
    for (let i = 1; i <= 3; i++) {
      const $line = $(`#step-indicator-${i}`).next('.step-line');
      if (i < step) $line.addClass('done');
      else $line.removeClass('done');
    }
    // Scroll to form
    $('html, body').animate({ scrollTop: $('#manifestacao').offset().top - 100 }, 400);
  }

  /* ── Validações ───────────────────────── */
  function _validateStep1() {
  if (isAnonymous) return true;

  const nome  = $.trim($('#nome').val());
  const cpf   = $.trim($('#cpf').val());
  const email = $.trim($('#email').val());
  const turma = $('#turma').val(); // Pega o valor do select

  // 1. Regras de Validação
  const isNomeValido  = nome.length >= 3;
  const isCpfValido   = Utils.validateCPF(cpf);
  const isEmailValido = Utils.validateEmail(email);
  
  // Turma é válida se NÃO for o valor inicial "turma" e não estiver vazio
  const isTurmaValida = turma !== "turma" && turma !== "" && turma !== null;

  // 2. Atualiza o visual dos campos (bordinhas, ícones, etc)
  _setValidity('#nome',  isNomeValido);
  _setValidity('#cpf',   isCpfValido);
  _setValidity('#email', isEmailValido);
  _setValidity('#turma', isTurmaValida);

  // 3. Resultado final
  const isValid = isNomeValido && isCpfValido && isEmailValido && isTurmaValida;

  if (!isValid) {
    Utils.showToast('Preencha corretamente os campos obrigatórios.', 'error');
  }

  return isValid;
}

  function _validateStep2() {
    let valid = true;
    const tipo    = $('#tipo').val();
    const orgao   = $('#orgao').val();
    const assunto = $.trim($('#assunto').val());
    const desc    = $.trim($('#descricao').val());

    _setValidity('#tipo',      !!tipo);
    _setValidity('#orgao',     !!orgao);
    _setValidity('#assunto',   assunto.length >= 5);
    _setValidity('#descricao', desc.length >= 20);

    if (!tipo || !orgao || assunto.length < 5 || desc.length < 20) {
      valid = false;
      Utils.showToast('Preencha todos os campos obrigatórios com detalhes suficientes.', 'error');
    }
    return valid;
  }

  function _setValidity(selector, isValid) {
    const $el = $(selector);
    $el.toggleClass('is-invalid', !isValid).toggleClass('is-valid', isValid);
  }

  /* ── Montar revisão ───────────────────── */
  function _buildReview() {
    const fields = [
      { label: 'Identificação',    value: isAnonymous ? 'Anônimo' : Utils.sanitize($('#nome').val()) },
      { label: 'E-mail',           value: isAnonymous ? '—' : Utils.sanitize($('#email').val()) },
      { label: 'Tipo',             value: Utils.sanitize($('#tipo').val()) },
      { label: 'Órgão/Secretaria', value: Utils.sanitize($('#orgao').val()) },
      { label: 'Data do Fato',     value: Utils.formatDate($('#dataFato').val()) },
      { label: 'Endereço',         value: Utils.sanitize($('#endereco').val()) || '—' },
      { label: 'Assunto',          value: Utils.sanitize($('#assunto').val()), full: true },
      { label: 'Descrição',        value: Utils.truncate(Utils.sanitize($('#descricao').val()), 200), full: true },
    ];

    const html = fields.map(f => `
      <div class="review-item ${f.full ? 'full' : ''}">
        <span class="r-label">${f.label}</span>
        <span class="r-value">${f.value || '—'}</span>
      </div>`).join('');

    const attachments = uploadedFiles.length
      ? `<div class="review-item full"><span class="r-label">Anexos</span>
         <span class="r-value">${uploadedFiles.map(f => Utils.truncate(f.name, 30)).join(', ')}</span></div>`
      : '';

    $('#reviewContent').html(html + attachments);
  }

  /* ── Nova manifestação ────────────────── */
  function _bindNewManifestacao() {
    $('#newManifestacao').on('click', () => {
      $('#ouvidoriaForm')[0].reset();
      uploadedFiles.length = 0;
      $('#fileList').empty();
      $('.identificado-field').show();
      isAnonymous = false;
      $('.is-invalid, .is-valid').removeClass('is-invalid is-valid');
      $('#charCount').text(0);
      // Reset steps
      for (let i = 1; i <= 3; i++) {
        $(`#step-indicator-${i}`).removeClass('active done');
        $(`#step-indicator-${i}`).next('.step-line').removeClass('done');
      }
      $(`#step-success`).removeClass('active');
      currentStep = 1;
      $(`#step-1`).addClass('active');
      $(`#step-indicator-1`).addClass('active');
      $('html, body').animate({ scrollTop: $('#manifestacao').offset().top - 100 }, 400);
    });
  }

  /* ── Dados do formulário para envio ───── */
  function getFormData() {
    return {
      anonimo:   isAnonymous,
      nome:      isAnonymous ? null : $.trim($('#nome').val()),
      cpf:       isAnonymous ? null : $.trim($('#cpf').val()),
      email:     isAnonymous ? null : $.trim($('#email').val()),
      turma:     isAnonymous ? null : $.trim($('#turma').val()),
      tipo:      $('#tipo').val(),
      orgao:     $('#orgao').val(),
      assunto:   $.trim($('#assunto').val()),
      descricao: $.trim($('#descricao').val()),
      endereco:  $.trim($('#endereco').val()),
      dataFato:  $('#dataFato').val(),
      arquivos:  uploadedFiles.map(f => f.name),
    };
  }

  /* ── Ir para step (público, para Ajax) ── */
  function goToSuccess(protocol) {
    $(`#step-${currentStep}`).removeClass('active');
    $(`#step-indicator-${currentStep}`).removeClass('active').addClass('done');
    $('#step-success').addClass('active');
    $('#protocolNumber').text(protocol);
    $('html, body').animate({ scrollTop: $('#manifestacao').offset().top - 100 }, 400);
  }

  return { init, getFormData, goToSuccess };

})();

/* ── Inicializar quando DOM pronto ── */
$(document).ready(() => Form.init());
