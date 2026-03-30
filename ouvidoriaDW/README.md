# 📋 Ouvidoria Municipal — Sistema Web

Interface pública da Ouvidoria Municipal, construída com HTML5, CSS3, Bootstrap 5, jQuery/AJAX e Font Awesome 6.

---

## 🗂️ Estrutura de Pastas

```
ouvidoria/
├── index.html                  ← Página principal
├── README.md                   ← Este arquivo
└── assets/
    ├── css/
    │   └── style.css           ← Estilos globais (variáveis CSS, componentes, responsivo)
    ├── js/
    │   ├── utils.js            ← Funções utilitárias (máscaras, validação, toast, contador)
    │   ├── form.js             ← Lógica do formulário multi-step (steps, validação, revisão)
    │   ├── ajax.js             ← Camada de comunicação AJAX (submit, consulta, upload)
    │   └── main.js             ← Interações UI gerais (navbar, scroll, reveal, counters)
    └── img/
        └── (coloque aqui imagens estáticas: logo, og-image, etc.)
```

---

## 🚀 Funcionalidades

| Recurso | Descrição |
|---|---|
| **Formulário Multi-Step** | 3 etapas: Identificação → Manifestação → Revisão |
| **Modo Anônimo** | Toggle que oculta campos de identificação |
| **Máscaras de Input** | CPF (000.000.000-00) e Telefone formatados dinamicamente |
| **Upload de Arquivos** | Drag & drop + seleção, validação de tamanho (5MB) e tipo |
| **Consulta de Protocolo** | Busca via AJAX com exibição de timeline de status |
| **Toast Notifications** | Feedback visual de sucesso, erro, aviso e info |
| **Contador Animado** | Estatísticas no hero com IntersectionObserver |
| **Scroll Reveal** | Animação de entrada dos elementos ao rolar a página |
| **Navbar Inteligente** | Sombra ao rolar, link ativo por seção, responsivo |
| **Back-to-Top** | Botão flutuante que aparece após 400px de scroll |
| **Accordion FAQ** | Perguntas frequentes com Bootstrap Collapse |

---

## 🔌 Integração com API Real

O arquivo `assets/js/ajax.js` possui **mocks** para todas as chamadas. Para integrar à API real:

### 1. Registrar Manifestação
```js
// Descomente em Ajax.submitManifestacao():
return _request('POST', '/manifestacoes', payload);
```

**Endpoint:** `POST /api/manifestacoes`  
**Body:**
```json
{
  "anonimo": false,
  "nome": "João Silva",
  "cpf": "000.000.000-00",
  "email": "joao@email.com",
  "tipo": "Reclamação",
  "orgao": "Secretaria de Saúde",
  "assunto": "Falta de atendimento",
  "descricao": "Descrição detalhada...",
  "endereco": "Rua X, 123",
  "dataFato": "2024-03-25"
}
```

**Resposta:**
```json
{
  "success": true,
  "protocolo": "OUV-2024-09999",
  "dataRegistro": "25/03/2024 14:32",
  "prazoResposta": "30 dias úteis"
}
```

### 2. Consultar Protocolo
```js
// Descomente em Ajax.consultarProtocolo():
return _request('GET', `/manifestacoes/${encodeURIComponent(numero)}`);
```

**Endpoint:** `GET /api/manifestacoes/:numero`

### 3. Upload de Arquivos
```js
// Descomente em Ajax.uploadArquivos():
// (bloco multipart/form-data já preparado)
```

**Endpoint:** `POST /api/manifestacoes/upload`

---

## 🛠️ Tecnologias Utilizadas

- **Bootstrap 5.3** — Grid, componentes, accordion, toast
- **jQuery 3.7.1** — AJAX, DOM, eventos
- **Font Awesome 6.5** — Ícones
- **Google Fonts** — Playfair Display (display) + DM Sans (body)
- **CSS Custom Properties** — Tema consistente via variáveis

---

## ♿ Acessibilidade

- Atributos `aria-*` nos componentes Bootstrap
- Labels semânticos em todos os inputs
- Cores com contraste mínimo AA (WCAG 2.1)
- Navegação por teclado funcional

---

## 📜 Legislação de Base

- **Lei nº 13.460/2017** — Defesa dos direitos do usuário dos serviços públicos
- **Lei nº 13.709/2018 (LGPD)** — Tratamento de dados pessoais
- **Lei nº 12.527/2011 (LAI)** — Acesso à informação

---

## 👨‍💻 Desenvolvimento

```bash
# Servir localmente com Python
python3 -m http.server 8080

# Ou com Node.js (npx serve)
npx serve .
```

> **Dica:** Em modo dev (localhost), o console exibe aviso de que as chamadas são mockadas.
