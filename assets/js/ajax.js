/**
 * ajax.js — Camada de comunicação AJAX
 * Ouvidoria Escolar — Grêmio Estudantil — EEEP Dom Walfrido
 *
 * Conecta o formulário (form.js) aos endpoints PHP reais.
 * Todos os caminhos são relativos — funciona em qualquer subpasta.
 */

/* ══════════════════════════════════════════════════════════════════
   SUBMIT DA MANIFESTAÇÃO
   Chamado pelo botão #submitBtn no step-3.
   Envia os dados para api/manifestacoes.php e trata a resposta.
══════════════════════════════════════════════════════════════════ */
$(document).ready(() => {

  $('#submitBtn').on('click', function () {

    // 1. Checar checkbox de termos
    if (!$('#termos').is(':checked')) {
      Utils.showToast('Você precisa aceitar a Política de Privacidade para continuar.', 'warning');
      return;
    }

    // 2. Pegar os dados do formulário via Form.getFormData()
    const payload = Form.getFormData();

    // 3. Validação mínima client-side antes de enviar
    if (!payload.IDtipo || !payload.IDsetor || !payload.manifest?.trim()) {
      Utils.showToast('Dados incompletos. Volte e verifique os campos obrigatórios.', 'error');
      return;
    }

    // 4. Estado de loading
    const $btn = $(this);
    $btn.prop('disabled', true);
    $('#submitText').addClass('d-none');
    $('#submitSpinner').removeClass('d-none');

    // 5. Chamada AJAX para o backend real
    $.ajax({
      method:      'POST',
      url:         'api/manifestacoes.php',   // caminho relativo
      contentType: 'application/json',
      data:        JSON.stringify(payload),
      timeout:     15000,
      dataType:    'json',
    })
    .done(res => {
      if (res.success && res.protocolo) {
        // Sucesso — mostrar tela de protocolo
        Utils.showToast('Manifestação enviada com sucesso!', 'success');
        Form.goToSuccess(res.protocolo);
      } else {
        // Backend retornou success=false com mensagem
        Utils.showToast(res.message || 'Erro ao registrar. Tente novamente.', 'error');
      }
    })
    .fail(xhr => {
      const msg = _parseError(xhr);
      Utils.showToast(msg, 'error', 'Falha no envio');
    })
    .always(() => {
      $btn.prop('disabled', false);
      $('#submitText').removeClass('d-none');
      $('#submitSpinner').addClass('d-none');
    });

  });

  /* ════════════════════════════════════════════════════════════════
     CONSULTA DE PROTOCOLO
     Chamado pelo botão #btnConsultar na seção de consulta.
     Busca em api/consulta.php pelo número informado.
  ════════════════════════════════════════════════════════════════ */
  $('#btnConsultar').on('click', function () {
    const numero = $.trim($('#protocolSearch').val()).toUpperCase();

    if (!numero) {
      Utils.showToast('Informe o número do protocolo.', 'warning');
      return;
    }

    // Validação básica do formato OUV-AAAA-NNNNN
    if (!/^OUV-\d{4}-\d{5}$/.test(numero)) {
      Utils.showToast('Formato inválido. Use o padrão OUV-2025-00042.', 'warning');
      return;
    }

    const $btn = $(this);
    $btn.prop('disabled', true)
        .html('<span class="spinner-border spinner-border-sm me-2"></span>Buscando...');

    $.ajax({
      method:   'GET',
      url:      'api/consulta.php',
      data:     { protocolo: numero },
      dataType: 'json',
      timeout:  10000,
    })
    .done(data => {
      if (data.success) {
        _renderConsultaSuccess(data);
      } else {
        _renderConsultaError(data.message || 'Protocolo não encontrado.');
      }
    })
    .fail(xhr => {
      _renderConsultaError(_parseError(xhr));
    })
    .always(() => {
      $btn.prop('disabled', false)
          .html('<i class="fa-solid fa-search me-2"></i>Consultar');
    });
  });

  // Enter no campo de protocolo dispara a busca
  $('#protocolSearch').on('keydown', function (e) {
    if (e.key === 'Enter') $('#btnConsultar').trigger('click');
  });

  /* ════════════════════════════════════════════════════════════════
     RENDER — Resultado da consulta com sucesso
  ════════════════════════════════════════════════════════════════ */
  function _renderConsultaSuccess(data) {
    // Mapa de status → classe CSS e ícone
    const statusMap = {
      'Aberta':      { cls: 'status-aberto',     icon: 'fa-circle-dot' },
      'Em análise':  { cls: 'status-analise',    icon: 'fa-magnifying-glass' },
      'Respondida':  { cls: 'status-respondido', icon: 'fa-circle-check' },
      'Encerrada':   { cls: 'status-encerrado',  icon: 'fa-circle-xmark' },
    };
    const st = statusMap[data.status] || statusMap['Aberta'];

    // Montar timeline
    const etapas = [
      { label: 'Manifestação recebida', done: true,                            data: data.criado_em },
      { label: 'Em análise pelo Grêmio', done: data.status !== 'Aberta',       data: null },
      { label: 'Respondida',             done: data.status === 'Respondida' || data.status === 'Encerrada', data: data.atualizado_em },
      { label: 'Encerrada',              done: data.status === 'Encerrada',    data: null },
    ];

    const timelineHtml = etapas.map(e => `
      <div class="timeline-item">
        <div class="timeline-dot ${e.done ? 'dot-done' : 'dot-pending'}"></div>
        <div>
          <strong>${Utils.sanitize(e.label)}</strong>
          ${e.data && e.done ? `<span class="text-muted ms-2 small">${Utils.sanitize(e.data)}</span>` : ''}
        </div>
      </div>`).join('');

    const respostaHtml = data.feedback
      ? `<div class="mt-3 p-3 rounded"
              style="background:rgba(0,122,66,.07);border-left:3px solid #007A42">
           <strong style="font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;color:#007A42">
             Resposta do Grêmio
           </strong>
           <p class="mb-0 mt-1" style="font-size:.9rem">
             ${Utils.sanitize(data.feedback)}
           </p>
         </div>`
      : '';

    const html = `
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
          <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">Protocolo</div>
          <div style="font-size:1.1rem;font-weight:700;color:var(--navy)">${Utils.sanitize(data.protocolo)}</div>
        </div>
        <span class="status-badge ${st.cls}">
          <i class="fa-solid ${st.icon} fa-xs me-1"></i>${Utils.sanitize(data.status)}
        </span>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-sm-6">
          <span style="font-size:.75rem;color:var(--text-muted)">Tipo</span>
          <div style="font-weight:600">${Utils.sanitize(data.tipo ?? '—')}</div>
        </div>
        <div class="col-sm-6">
          <span style="font-size:.75rem;color:var(--text-muted)">Setor</span>
          <div style="font-weight:600">${Utils.sanitize(data.setor ?? '—')}</div>
        </div>
        <div class="col-sm-6">
          <span style="font-size:.75rem;color:var(--text-muted)">Enviada em</span>
          <div>${Utils.sanitize(data.criado_em ?? '—')}</div>
        </div>
        <div class="col-sm-6">
          <span style="font-size:.75rem;color:var(--text-muted)">Atualizada em</span>
          <div>${Utils.sanitize(data.atualizado_em ?? '—')}</div>
        </div>
      </div>
      <div class="progress-timeline">${timelineHtml}</div>
      ${respostaHtml}`;

    $('#consultaResult')
      .removeClass('d-none result-error')
      .addClass('result-success')
      .html(html);
  }

  /* ════════════════════════════════════════════════════════════════
     RENDER — Resultado da consulta com erro
  ════════════════════════════════════════════════════════════════ */
  function _renderConsultaError(message) {
    $('#consultaResult')
      .removeClass('d-none result-success')
      .addClass('result-error')
      .html(`
        <div class="d-flex align-items-center gap-3">
          <i class="fa-solid fa-circle-xmark fa-2x" style="color:var(--danger)"></i>
          <div>
            <strong>Protocolo não encontrado</strong>
            <p class="mb-0 small text-muted">${Utils.sanitize(message)}</p>
          </div>
        </div>`);
  }

  /* ════════════════════════════════════════════════════════════════
     PARSER DE ERROS HTTP
     Traduz os status HTTP em mensagens legíveis para o usuário.
  ════════════════════════════════════════════════════════════════ */
  function _parseError(xhr) {
    // Tentar extrair mensagem do JSON de resposta
    if (xhr.responseJSON?.message) return xhr.responseJSON.message;

    // Fallback por código HTTP
    switch (xhr.status) {
      case 0:   return 'Sem conexão com o servidor. Verifique sua internet.';
      case 404: return 'Endpoint não encontrado. Contate o suporte.';
      case 405: return 'Método não permitido.';
      case 422: return 'Dados inválidos. Revise o formulário.';
      case 429: return 'Muitas tentativas. Aguarde alguns minutos.';
      case 500: return 'Erro interno no servidor. Tente novamente mais tarde.';
      default:  return `Erro inesperado (${xhr.status}). Tente novamente.`;
    }
  }

});