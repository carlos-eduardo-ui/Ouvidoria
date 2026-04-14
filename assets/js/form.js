/**
 * form.js — Lógica do formulário multi-step da Ouvidoria Escolar
 * Grêmio Estudantil
 *
 * CORREÇÕES aplicadas em relação à versão anterior:
 *   [1] IDusu salvo em variável de módulo (loggedUserId)
 *   [2] URL da sessão corrigida para caminho relativo
 *   [3] Setores carregados dinamicamente via api/setores.php
 *   [4] getFormData() alinhado com colunas reais do banco
 *   [5] Banner de login inserido no lugar correto
 *   [6] Textos adaptados para contexto escolar (Setor, não Órgão)
 *   [7] _buildReview() exibe nome do setor, não o ID
 */

const Form = (() => {

  /* ── Estado do módulo ────────────────────────────────────────────
     Estas variáveis guardam o estado atual do formulário.
     São privadas — nada de fora consegue alterá-las diretamente.
  ─────────────────────────────────────────────────────────────────*/
  let currentStep  = 1;
  let isAnonymous  = false;
  let isLoggedIn   = false;
  let loggedUserId = null;   // [FIX 1] guarda o IDusu real do banco
  let sectorNames  = {};     // [FIX 7] mapa IDsetor → nome para o review

  const uploadedFiles = [];

  /* ── Init ────────────────────────────────────────────────────────
     Ponto de entrada. Chamado quando o DOM está pronto.
     Ordem importa: sessão e setores primeiro, depois os binds.
  ─────────────────────────────────────────────────────────────────*/
  function init() {
    _loadSetores();
    _checkSession();
    _bindStepNavigation();
    _bindCharCounter();
    _bindFileUpload();
    _bindServiceLinks();
    _bindNewManifestacao();
  }

  /* ══════════════════════════════════════════════════════════════
     SESSÃO — renderiza o Step 1 com base no estado de login
  ══════════════════════════════════════════════════════════════ */

  function _checkSession() {
    $.ajax({
      url:      'api/session.php',
      method:   'GET',
      timeout:  5000,
      dataType: 'json',
    })
    .done(res => {
      if (res.logado && res.usuario) {
        _handleLoggedIn(res.usuario);
      } else {
        _handleLoggedOut();
      }
    })
    .fail(() => _handleLoggedOut());
  }

  function _handleLoggedIn(usuario) {
    isLoggedIn   = true;
    isAnonymous  = false;
    loggedUserId = usuario.IDusu;

    const serie = usuario.serie ? usuario.serie + 'º ano' : '';
    const curso = usuario.curso ? ' — ' + usuario.curso : '';

    // Substituir o spinner pelo card de usuário logado
    // Dois botões: continuar identificado OU continuar anônimo
    $('#step1-loading').replaceWith(`
      <div id="card-logado">

        <!-- Card de boas-vindas — só visualização, sem edição -->
        <div class="step1-user-card mb-4">
          <div class="step1-user-avatar">
            <i class="fa-solid fa-user-check"></i>
          </div>
          <div class="step1-user-info">
            <div class="step1-user-name">${Utils.sanitize(usuario.nome)}</div>
            <div class="step1-user-meta">${Utils.sanitize(usuario.email)}</div>
            ${serie ? `<div class="step1-user-meta">${serie}${Utils.sanitize(curso)}</div>` : ''}
          </div>
          <div class="step1-user-badge">
            <i class="fa-solid fa-circle-check me-1"></i>Logado
          </div>
        </div>

        <!-- Dois botões de escolha -->
        <p class="text-muted mb-3" style="font-size:.9rem">Como deseja enviar sua manifestação?</p>
        <div class="step1-choices">
          <button type="button" class="step1-choice-btn step1-identified" id="btn-continuar-identificado">
            <i class="fa-solid fa-user-shield fa-lg mb-2"></i>
            <strong>Continuar como ${Utils.sanitize(usuario.nome.split(' ')[0])}</strong>
            <span>Sua manifestação ficará vinculada à sua conta</span>
          </button>
          <button type="button" class="step1-choice-btn step1-anon" id="btn-continuar-anonimo-logado">
            <i class="fa-solid fa-user-secret fa-lg mb-2"></i>
            <strong>Continuar como anônimo</strong>
            <span>Sua identidade não será revelada</span>
          </button>
        </div>

      </div>`);

    // Botão identificado → vai para step 2 vinculado
    $('#btn-continuar-identificado').on('click', () => {
      isAnonymous = false;
      _goTo(2);
    });

    // Botão anônimo → vai para step 2 sem vínculo
    // Mesmo logado, o IDusu não será enviado
    $('#btn-continuar-anonimo-logado').on('click', () => {
      isAnonymous  = true;
      loggedUserId = null; // garante que IDusu não vai para o banco
      _goTo(2);
    });
  }

  function _handleLoggedOut() {
    isLoggedIn   = false;
    loggedUserId = null;

    // Substituir o spinner pelos dois cards de escolha para não-logados
    $('#step1-loading').replaceWith(`
      <div id="cards-nao-logado">

        <p class="text-muted mb-3" style="font-size:.9rem">Como deseja enviar sua manifestação?</p>
        <div class="step1-choices">
          <a href="login.html?next=index.html%23manifestacao" class="step1-choice-btn step1-identified text-decoration-none">
            <i class="fa-solid fa-right-to-bracket fa-lg mb-2"></i>
            <strong>Entrar para se identificar</strong>
            <span>Faça login ou crie uma conta</span>
          </a>
          <button type="button" class="step1-choice-btn step1-anon" id="btn-anonimo-nao-logado">
            <i class="fa-solid fa-user-secret fa-lg mb-2"></i>
            <strong>Continuar como anônimo</strong>
            <span>Sem necessidade de login</span>
          </button>
        </div>

      </div>`);

    // Botão anônimo → vai para step 2
    $('#btn-anonimo-nao-logado').on('click', () => {
      isAnonymous = true;
      _goTo(2);
    });
  }

  /* ══════════════════════════════════════════════════════════════
     SETORES — carregados do banco via AJAX
     [FIX 3] Antes era um <select> hardcoded no HTML com nomes
     de secretarias genéricas. Agora vem da tabela tbsetores.
  ══════════════════════════════════════════════════════════════ */

  function _loadSetores() {
    $.ajax({
      url:      'api/setores.php',
      method:   'GET',
      dataType: 'json',
      timeout:  8000,
    })
    .done(res => {
      if (!res.success || !res.setores.length) {
        _setorError();
        return;
      }

      const $select = $('#setor');
      $select.empty().append('<option value="">Selecione o setor...</option>');

      res.setores.forEach(s => {
        // [FIX 7] guarda mapa ID → nome para usar no review
        sectorNames[s.IDsetor] = s.nome;
        $select.append(
          $('<option>', { value: s.IDsetor, text: s.nome })
        );
      });
    })
    .fail(() => _setorError());
  }

  function _setorError() {
    $('#setor')
      .empty()
      .append('<option value="">Erro ao carregar setores</option>')
      .prop('disabled', true);
    Utils.showToast('Não foi possível carregar os setores. Recarregue a página.', 'error');
  }

  /* ══════════════════════════════════════════════════════════════
     MÁSCARAS — removidas (campos de identificação não existem mais no step 1)
  ══════════════════════════════════════════════════════════════ */

  /* ══════════════════════════════════════════════════════════════
     CONTADOR DE CARACTERES
  ══════════════════════════════════════════════════════════════ */

  function _bindCharCounter() {
    $('#descricao').on('input', function () {
      const len = $(this).val().length;
      const max = 1000;
      $('#charCount').text(len);
      if (len > max) $(this).val($(this).val().slice(0, max));
      $('#charCount').toggleClass('text-danger', len >= max * 0.9);
    });
  }

  /* ══════════════════════════════════════════════════════════════
     UPLOAD DE ARQUIVOS
  ══════════════════════════════════════════════════════════════ */

  function _bindFileUpload() {
    const $area  = $('#uploadArea');
    const $input = $('#fileInput');
    const $list  = $('#fileList');

    $area.on('dragover',  e => { e.preventDefault(); $area.addClass('drag-over'); });
    $area.on('dragleave', ()  => $area.removeClass('drag-over'));
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
        const ext   = file.name.split('.').pop().toUpperCase();
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
      const idx  = uploadedFiles.findIndex(f => f.name === name);
      if (idx > -1) uploadedFiles.splice(idx, 1);
      $(this).closest('.file-item').remove();
    });
  }

  function _iconForExt(ext) {
    const map = { PDF: 'pdf', JPG: 'image', JPEG: 'image', PNG: 'image' };
    return map[ext] || 'alt';
  }

  /* ══════════════════════════════════════════════════════════════
     ATALHO DOS CARDS DE SERVIÇO → PRÉ-SELECIONA TIPO
  ══════════════════════════════════════════════════════════════ */

  function _bindServiceLinks() {
    $('.service-link').on('click', function () {
      const tipo = $(this).data('tipo');
      if (tipo) {
        $('#tipo option').filter(function () {
          return $(this).text() === tipo;
        }).prop('selected', true);
        $('html, body').animate(
          { scrollTop: $('#manifestacao').offset().top - 80 }, 500
        );
      }
    });
  }

  /* ══════════════════════════════════════════════════════════════
     NAVEGAÇÃO ENTRE STEPS
  ══════════════════════════════════════════════════════════════ */

  function _bindStepNavigation() {
    $('#next-to-2').on('click', () => { if (_validateStep1()) _goTo(2); });
    $('#next-to-3').on('click', () => { if (_validateStep2()) { _buildReview(); _goTo(3); } });
    $('#back-to-1').on('click', () => _goTo(1));
    $('#back-to-2').on('click', () => _goTo(2));
  }

  function _goTo(step) {
    $(`#step-${currentStep}`).removeClass('active');
    $(`#step-indicator-${currentStep}`)
      .removeClass('active')
      .toggleClass('done', currentStep < step);

    currentStep = step;

    $(`#step-${currentStep}`).addClass('active');
    $(`#step-indicator-${currentStep}`).addClass('active');

    for (let i = 1; i <= 3; i++) {
      $(`#step-indicator-${i}`).next('.step-line')
        .toggleClass('done', i < step);
    }

    $('html, body').animate(
      { scrollTop: $('#manifestacao').offset().top - 100 }, 400
    );
  }

  /* ══════════════════════════════════════════════════════════════
     VALIDAÇÕES
  ══════════════════════════════════════════════════════════════ */

  function _validateStep1() {
    // Step 1 agora é só escolha de identificação via botão
    // A validação real acontece no step 2
    return true;
  }

  function _validateStep2() {
    let valid = true;

    const IDtipo  = $('#tipo').val();
    const IDsetor = $('#setor').val();   // [FIX 4]
    const descr   = $.trim($('#descricao').val());

    _setValidity('#tipo',      !!IDtipo);
    _setValidity('#setor',     !!IDsetor);
    _setValidity('#descricao', descr.length >= 10);

    if (!IDtipo || !IDsetor || descr.length < 10) {
      valid = false;
      Utils.showToast(
        'Selecione o tipo, o setor e descreva a manifestação (mín. 10 caracteres).',
        'error'
      );
    }
    return valid;
  }

  function _setValidity(selector, isValid) {
    $(selector)
      .toggleClass('is-invalid', !isValid)
      .toggleClass('is-valid',   isValid);
  }

  /* ══════════════════════════════════════════════════════════════
     REVISÃO
  ══════════════════════════════════════════════════════════════ */

  function _buildReview() {
    const IDsetor   = $('#setor').val();
    const nomeSetor = sectorNames[IDsetor] || '—';         // [FIX 7]
    const nomeTipo  = $('#tipo option:selected').text() || '—';

    const fields = [
      {
        label: 'Identificação',
        value: isAnonymous ? 'Anônimo' : Utils.sanitize($('#nome').val()),
      },
      {
        label: 'Contato',
        value: isAnonymous ? '—' : Utils.sanitize($('#email').val() || '—'),
      },
      { label: 'Tipo',      value: nomeTipo  },
      { label: 'Setor',     value: nomeSetor }, // [FIX 6]
      {
        label: 'Descrição',
        value: Utils.truncate(Utils.sanitize($('#descricao').val()), 200),
        full:  true,
      },
    ];

    const html = fields.map(f => `
      <div class="review-item ${f.full ? 'full' : ''}">
        <span class="r-label">${f.label}</span>
        <span class="r-value">${f.value || '—'}</span>
      </div>`).join('');

    const attachments = uploadedFiles.length
      ? `<div class="review-item full">
           <span class="r-label">Anexos</span>
           <span class="r-value">
             ${uploadedFiles.map(f => Utils.truncate(f.name, 30)).join(', ')}
           </span>
         </div>`
      : '';

    $('#reviewContent').html(html + attachments);
  }

  /* ══════════════════════════════════════════════════════════════
     NOVA MANIFESTAÇÃO
  ══════════════════════════════════════════════════════════════ */

  function _bindNewManifestacao() {
    $('#newManifestacao').on('click', () => {
      // Resetar conteúdo e estado
      $('#descricao, #contato').val('');
      $('#tipo, #setor').val('');
      uploadedFiles.length = 0;
      $('#fileList').empty();
      $('#charCount').text(0);
      $('.is-invalid, .is-valid').removeClass('is-invalid is-valid');

      // Restaurar o spinner no step 1 e re-checar sessão
      // Isso reconstrói os cards corretamente
      $('#step-1').html(`
        <div id="step1-loading" class="text-center py-4">
          <span class="spinner-border spinner-border-sm me-2"></span>
          <span class="text-muted" style="font-size:.9rem">Verificando sessão...</span>
        </div>`);

      isAnonymous  = false;
      loggedUserId = null;
      _checkSession(); // reconstrói os cards do step 1

      // Resetar indicadores de step
      for (let i = 1; i <= 3; i++) {
        $(`#step-indicator-${i}`).removeClass('active done');
        $(`#step-indicator-${i}`).next('.step-line').removeClass('done');
      }

      $('#step-success').removeClass('active');
      currentStep = 1;
      $('#step-1').addClass('active');
      $('#step-indicator-1').addClass('active');

      $('html, body').animate(
        { scrollTop: $('#manifestacao').offset().top - 100 }, 400
      );
    });
  }

  /* ══════════════════════════════════════════════════════════════
     DADOS PARA ENVIO — alinhados com colunas reais de tbmanifest
     [FIX 4]
  ══════════════════════════════════════════════════════════════ */

  function getFormData() {
    return {
      // Identificação
      IDusu:    (isLoggedIn && !isAnonymous) ? loggedUserId : null,
      anonimo:  isAnonymous ? 1 : 0,

      // Para quem não está logado e não é anônimo
      nome:     (!isLoggedIn && !isAnonymous) ? $.trim($('#nome').val())  : null,
      email:    (!isLoggedIn && !isAnonymous) ? $.trim($('#email').val()) : null,

      // Conteúdo — nomes batem com colunas de tbmanifest
      IDtipo:   parseInt($('#tipo').val())  || null,
      IDsetor:  parseInt($('#setor').val()) || null,
      manifest: $.trim($('#descricao').val()),
      contato:  isAnonymous ? null : $.trim($('#contato').val() || ''),

      // Arquivos (nomes — upload feito separadamente)
      arquivos: uploadedFiles.map(f => f.name),
    };
  }

  /* ══════════════════════════════════════════════════════════════
     SUCESSO
  ══════════════════════════════════════════════════════════════ */

  function goToSuccess(protocolo) {
    $(`#step-${currentStep}`).removeClass('active');
    $(`#step-indicator-${currentStep}`).removeClass('active').addClass('done');
    $('#step-success').addClass('active');
    $('#protocolNumber').text(protocolo);
    $('html, body').animate(
      { scrollTop: $('#manifestacao').offset().top - 100 }, 400
    );
  }

  /* ── API pública ────────────────────────────────────────────── */
  return { init, getFormData, goToSuccess };

})();

$(document).ready(() => Form.init());