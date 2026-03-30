/**
 * ajax.js — Camada de comunicação AJAX (jQuery)
 * Ouvidoria Municipal
 *
 * Simula requisições ao servidor com delay realístico.
 * Para integração real, substitua os métodos _mockXxx
 * pelos endpoints da API REST correspondentes.
 *
 * Endpoints esperados (REST):
 *   POST   /api/manifestacoes          → registrar manifestação
 *   GET    /api/manifestacoes/:numero  → consultar protocolo
 *   POST   /api/manifestacoes/upload   → upload de anexos
 */

const Ajax = (() => {

  /* ── Config da API ──────────────────────
   * Em produção, defina a baseURL real e
   * adicione headers de autenticação/CSRF.
  ───────────────────────────────────────── */
  const API = {
    baseURL: '/api',          // Altere para URL real em produção
    timeout: 15000,
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      // 'Authorization': 'Bearer <token>',
      // 'X-CSRF-Token':  '<token>',
    },
  };

  /* ── Utilitário interno de requisição ── */
  function _request(method, endpoint, data = null) {
    const options = {
      method,
      url: API.baseURL + endpoint,
      timeout: API.timeout,
      headers: API.headers,
      contentType: 'application/json',
    };
    if (data) options.data = JSON.stringify(data);
    return $.ajax(options);
  }

  /* ─────────────────────────────────────────────────────────────
     REGISTRAR MANIFESTAÇÃO
     POST /api/manifestacoes
  ───────────────────────────────────────────────────────────── */
  function submitManifestacao(payload) {
    /* --- Produção: descomentar abaixo ---
    return _request('POST', '/manifestacoes', payload)
      .then(res => res)
      .catch(err => { throw err; });
    */

    /* --- Mock (simulação) --- */
    return _mockSubmit(payload);
  }

  function _mockSubmit(payload) {
    return new Promise((resolve, reject) => {
      setTimeout(() => {
        // Simular falha ocasional (5% de chance) para teste
        if (Math.random() < 0.05) {
          reject({ status: 500, responseJSON: { message: 'Erro interno do servidor.' } });
          return;
        }
        resolve({
          success: true,
          protocolo: Utils.generateProtocol(),
          mensagem: 'Manifestação registrada com sucesso.',
          dataRegistro: Utils.nowBR(),
          prazoResposta: '30 dias úteis',
        });
      }, 1800);
    });
  }

  /* ─────────────────────────────────────────────────────────────
     CONSULTAR PROTOCOLO
     GET /api/manifestacoes/:numero
  ───────────────────────────────────────────────────────────── */
  function consultarProtocolo(numero) {
    /* --- Produção: descomentar abaixo ---
    return _request('GET', `/manifestacoes/${encodeURIComponent(numero)}`)
      .then(res => res)
      .catch(err => { throw err; });
    */

    /* --- Mock --- */
    return _mockConsulta(numero);
  }

  function _mockConsulta(numero) {
    const MOCK_DB = {
      'OUV-2024-08741': {
        protocolo: 'OUV-2024-08741',
        tipo: 'Reclamação',
        orgao: 'Secretaria de Infraestrutura',
        assunto: 'Buraco em via pública — Av. Santos Dumont',
        status: 'Respondido',
        dataAbertura: '12/03/2024',
        dataResposta: '20/03/2024',
        timeline: [
          { label: 'Registro recebido',       status: 'done',    data: '12/03/2024' },
          { label: 'Em análise pelo órgão',   status: 'done',    data: '14/03/2024' },
          { label: 'Resposta elaborada',       status: 'done',    data: '19/03/2024' },
          { label: 'Respondido ao cidadão',    status: 'done',    data: '20/03/2024' },
          { label: 'Encerrado',                status: 'pending', data: null         },
        ],
        resposta: 'O buraco foi identificado e a equipe de tapa-buracos realizará o reparo em até 5 dias úteis.',
      },
      'OUV-2024-09102': {
        protocolo: 'OUV-2024-09102',
        tipo: 'Denúncia',
        orgao: 'Secretaria de Saúde',
        assunto: 'Falta de medicamentos na UBS Benfica',
        status: 'Em análise',
        dataAbertura: '25/03/2024',
        dataResposta: null,
        timeline: [
          { label: 'Registro recebido',     status: 'done',    data: '25/03/2024' },
          { label: 'Em análise pelo órgão', status: 'active',  data: 'Hoje' },
          { label: 'Resposta elaborada',    status: 'pending', data: null },
          { label: 'Respondido ao cidadão', status: 'pending', data: null },
          { label: 'Encerrado',             status: 'pending', data: null },
        ],
        resposta: null,
      },
    };

    return new Promise((resolve, reject) => {
      setTimeout(() => {
        const numUpper = numero.trim().toUpperCase();
        const entry = MOCK_DB[numUpper];
        if (entry) {
          resolve(entry);
        } else {
          reject({ status: 404, responseJSON: { message: 'Protocolo não encontrado. Verifique o número e tente novamente.' } });
        }
      }, 1200);
    });
  }

  /* ─────────────────────────────────────────────────────────────
     UPLOAD DE ARQUIVOS
     POST /api/manifestacoes/upload  (multipart/form-data)
  ───────────────────────────────────────────────────────────── */
  function uploadArquivos(files, protocolo) {
    /* --- Produção: descomentar abaixo ---
    const formData = new FormData();
    formData.append('protocolo', protocolo);
    files.forEach(file => formData.append('arquivos', file));
    return $.ajax({
      method: 'POST',
      url: API.baseURL + '/manifestacoes/upload',
      data: formData,
      processData: false,
      contentType: false,
      timeout: 30000,
    });
    */

    /* --- Mock --- */
    return new Promise(resolve => setTimeout(() => resolve({ success: true, arquivos: files.length }), 800));
  }

  /* ─────────────────────────────────────────────────────────────
     HANDLER DE ERROS GLOBAL
  ───────────────────────────────────────────────────────────── */
  function handleError(err) {
    if (err.status === 0) {
      return 'Sem conexão com o servidor. Verifique sua internet.';
    }
    if (err.status === 404) {
      return err.responseJSON?.message || 'Recurso não encontrado.';
    }
    if (err.status === 422) {
      return err.responseJSON?.message || 'Dados inválidos. Revise o formulário.';
    }
    if (err.status === 429) {
      return 'Muitas tentativas. Aguarde alguns minutos e tente novamente.';
    }
    return err.responseJSON?.message || 'Erro inesperado. Tente novamente mais tarde.';
  }

  /* ── Public API ─────────────────────────── */
  return { submitManifestacao, consultarProtocolo, uploadArquivos, handleError };

})();


/* ═══════════════════════════════════════════════════════════
   BINDINGS DE EVENTOS AJAX
═══════════════════════════════════════════════════════════ */
$(document).ready(() => {

  /* ── Submit do formulário ──────────────── */
  $('#submitBtn').on('click', function () {
    if (!$('#termos').is(':checked')) {
      Utils.showToast('Você precisa aceitar os Termos e a Política de Privacidade.', 'warning');
      return;
    }

    const $btn = $(this);
    const payload = Form.getFormData();

    // Loading state
    $btn.prop('disabled', true);
    $('#submitText').addClass('d-none');
    $('#submitSpinner').removeClass('d-none');

    Ajax.submitManifestacao(payload)
      .then(res => {
        Utils.showToast('Manifestação enviada com sucesso!', 'success');
        Form.goToSuccess(res.protocolo);
      })
      .catch(err => {
        const msg = Ajax.handleError(err);
        Utils.showToast(msg, 'error', 'Falha no envio');
      })
      .finally(() => {
        $btn.prop('disabled', false);
        $('#submitText').removeClass('d-none');
        $('#submitSpinner').addClass('d-none');
      });
  });

  /* ── Consulta de protocolo ──────────────── */
  $('#btnConsultar').on('click', function () {
    const numero = $.trim($('#protocolSearch').val());
    if (!numero) {
      Utils.showToast('Informe o número do protocolo.', 'warning');
      return;
    }

    const $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Buscando...');

    Ajax.consultarProtocolo(numero)
      .then(data => _renderConsultaSuccess(data))
      .catch(err  => _renderConsultaError(Ajax.handleError(err)))
      .finally(() => {
        $btn.prop('disabled', false).html('<i class="fa-solid fa-search me-2"></i>Consultar');
      });
  });

  /* Tecla Enter no campo de consulta */
  $('#protocolSearch').on('keydown', function (e) {
    if (e.key === 'Enter') $('#btnConsultar').trigger('click');
  });

  /* ── Render resultado consulta ──────────── */
  function _renderConsultaSuccess(data) {
    const statusClass = {
      'Aberto':      'status-aberto',
      'Em análise':  'status-analise',
      'Respondido':  'status-respondido',
      'Encerrado':   'status-encerrado',
    }[data.status] || 'status-analise';

    const timelineHtml = data.timeline.map(t => `
      <div class="timeline-item">
        <div class="timeline-dot dot-${t.status}"></div>
        <div>
          <strong>${Utils.sanitize(t.label)}</strong>
          ${t.data ? `<span class="text-muted ms-2 small">${Utils.sanitize(t.data)}</span>` : ''}
        </div>
      </div>`).join('');

    const respostaHtml = data.resposta
      ? `<div class="mt-3 p-3 rounded" style="background:rgba(46,125,82,.07); border-left:3px solid #2e7d52">
           <strong style="font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;color:#2e7d52">Resposta da Ouvidoria</strong>
           <p class="mb-0 mt-1" style="font-size:.9rem">${Utils.sanitize(data.resposta)}</p>
         </div>`
      : '';

    const html = `
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
          <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">Protocolo</div>
          <div style="font-size:1.1rem;font-weight:700;color:var(--navy)">${Utils.sanitize(data.protocolo)}</div>
        </div>
        <span class="status-badge ${statusClass}">
          <i class="fa-solid fa-circle fa-xs"></i> ${Utils.sanitize(data.status)}
        </span>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-sm-6"><span style="font-size:.75rem;color:var(--text-muted)">Tipo</span><div style="font-weight:600">${Utils.sanitize(data.tipo)}</div></div>
        <div class="col-sm-6"><span style="font-size:.75rem;color:var(--text-muted)">Órgão</span><div style="font-weight:600">${Utils.sanitize(data.orgao)}</div></div>
        <div class="col-12"><span style="font-size:.75rem;color:var(--text-muted)">Assunto</span><div>${Utils.sanitize(data.assunto)}</div></div>
        <div class="col-sm-6"><span style="font-size:.75rem;color:var(--text-muted)">Abertura</span><div>${Utils.sanitize(data.dataAbertura)}</div></div>
        <div class="col-sm-6"><span style="font-size:.75rem;color:var(--text-muted)">Resposta</span><div>${data.dataResposta ? Utils.sanitize(data.dataResposta) : 'Aguardando'}</div></div>
      </div>
      <div class="progress-timeline">${timelineHtml}</div>
      ${respostaHtml}`;

    $('#consultaResult')
      .removeClass('d-none result-error')
      .addClass('result-success')
      .html(html);
  }

  function _renderConsultaError(message) {
    const html = `
      <div class="d-flex align-items-center gap-3">
        <i class="fa-solid fa-circle-xmark fa-2x" style="color:var(--danger)"></i>
        <div>
          <strong>Protocolo não encontrado</strong>
          <p class="mb-0 small text-muted">${Utils.sanitize(message)}</p>
        </div>
      </div>`;
    $('#consultaResult')
      .removeClass('d-none result-success')
      .addClass('result-error')
      .html(html);
  }

});
