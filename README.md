# ✨ GLPI Copilot · `glpicopilot`

<p align="center">
  <strong>🤖 Copilot de inteligência artificial para o teu Service Desk no GLPI 11</strong><br/>
  <sub>Resumos · SLA · Sentimento · Diagnóstico · Base de conhecimento · E-mails · Multi-provedor</sub>
</p>

---

## 🎯 O que é isto?

O **GLPI Copilot** é um plugin **open source** ([GPLv3+](https://www.gnu.org/licenses/gpl-3.0.html)) que liga o teu **GLPI 11** a vários motores de IA (**Microsoft Azure OpenAI**, **OpenAI**, **Groq**, **xAI Grok**, **Google Gemini**). Tudo num só sítio de configuração: escolhes o provedor, a chave e — quando faz sentido — o endpoint e o modelo.

👉 O foco é o **dia a dia do técnico** na ficha do **chamado (ticket)**: menos tempo a ler tópicos longos, mais contexto sobre **prazos e SLA**, melhor leitura do **tom do requerente**, **perguntas de diagnóstico** sugeridas pela IA, **rascunhos de artigo** para a base de conhecimento quando o problema fica resolvido, e **textos de e-mail** prontos a copiar — incluindo o encerramento.

📦 **Código-fonte:** [github.com/celsocaninde/glpicopilot](https://github.com/celsocaninde/glpicopilot)

| | |
|:---|:---|
| 🖥️ **GLPI** | `11.0` – `11.99` |
| 🐘 **PHP** | `≥ 8.2` |
| 📌 **Versão do plugin** | `1.2.0` |
| ⚖️ **Licença** | **GPLv3+** |

---

## 🚀 Para quem é?

- 👷 **Técnicos e analistas** que querem um resumo rápido do histórico sem abrir dezenas de follow-ups.
- 📊 **Gestores de serviço** que precisam de uma visão **rápida de risco de SLA** (com contexto do próprio GLPI).
- 🏢 **Equipas** que já usam ou querem testar **vários fornecedores de IA** sem mudar de plugin.
- 📚 **Equipas de conhecimento** que querem **sementes de artigos KB** geradas a partir de tickets resolvidos (revisão humana continua essencial).

---

## 🧩 O que o plugin faz (em detalhe)

### 📝 Resumo inteligente do chamado
- Botão na **barra de ações** do ticket (alinhado com o fluxo GLPI).
- A IA lê **título, descrição e follow-ups** e devolve um **resumo em texto** (até ~3 parágrafos curtos), no idioma do ticket quando possível.
- Ideal para **passagem de turno**, auditoria rápida ou onboarding de alguém novo no ticket.

### 🔌 Multi-provedor (uma configuração, vários motores)
- **Azure OpenAI** — URL completa do deployment + cabeçalho `api-key`.
- **OpenAI / Groq / Grok** — API estilo OpenAI (`Bearer` + `/chat/completions`), com **base URL opcional** e lista de **modelos** em dropdown.
- **Gemini (Google AI Studio)** — chave na query; endpoint fixo na API Generative Language (campo de URL ignorado).
- Na página de configuração: **dicas por provedor**, **badges** (obrigatório / opcional / não usado) e **restrição por entidade** (`allowed_entities`).

### ⏱️ Análise de SLA
- Usa o **histórico do ticket** + **metadados GLPI** (estado, urgência, prioridade, impacto, prazos quando existem nos campos).
- A IA devolve uma leitura de **risco** (baixo / médio / alto) e **sugestões de próximos passos** para o técnico.
- Clica em **«Analisar SLA»** e o resultado aparece na área de alertas, com **Copiar**.

### 😠😐🙂 Sentimento do requerente
- **Badge** carregado automaticamente ao abrir o ticket (classificação aproximada: frustrado / neutro / satisfeito).
- Baseado no texto do requerente no thread — não substitui conversa humana, mas dá **contexto emocional** rápido.

### 🩺 Diagnóstico guiado
- Gera **5–7 perguntas** que o técnico pode usar para aprofundar (logs, configurações, verificações sim/não).
- Abre num **painel lateral** (drawer) para não poluir o formulário principal.

### 📚 Base de conhecimento (rascunho automático)
- Quando o ticket passa a **Resolvido**, o plugin pode:
  - Pedir à IA um **título + corpo** em JSON.
  - Criar um **`KnowbaseItem`** com prefixo **`[Draft AI]`** para revisão humana.
- Evita duplicar: regista metadados em `glpi_plugin_glpicopilot_ticketmeta`.
- Requer **permissões** para criar artigos na KB no GLPI.

### ✉️ E-mail de encerramento
- **Ao resolver** pode ser gerado um texto de e-mail profissional; na **próxima visualização** do ticket aparece um alerta (sessão).
- Botão **«E-mail encerramento»** gera de novo **sob demanda**.
- Todos os blocos longos têm **Copiar** para colar no cliente ou no modelo de notificação.

### 💬 Sugestão de resposta (API)
- Endpoint `ajax/suggest_reply.php` — corpo sugerido para responder ao requerente, com base no thread (integração conforme a tua UI).

---

## ⚙️ Configuração rápida (por provedor)

| Campo | ☁️ Azure | 🤖 OpenAI / Groq / Grok | ✨ Gemini |
|--------|----------|-------------------------|-----------|
| **Endpoint** | ✅ Obrigatório: URL **completa** do deployment (`…/chat/completions` + `api-version`). | ⭕ Opcional: só `…/v1` (sem `/chat/completions`). | ➖ Não usado (API Google fixa). |
| **Modelo** | ➖ Definido pelo deployment. | ⭕ Dropdown + “Default”. | ⭕ Dropdown + “Default”. |
| **Chave** | `api-key` | `Bearer` | `key=` na query |

🔐 As chaves ficam na **base de dados** do GLPI — trata backups e perfis de admin com rigor.

---

## 📥 Instalação

1. Clona ou copia a pasta para:
   ```text
   GLPI_ROOT/plugins/glpicopilot/
   ```
2. No GLPI: **Configurar → Plugins** → **Instalar** e **Ativar** o **GLPI Copilot**.
3. Tabelas criadas/atualizadas automaticamente (`config` + `ticketmeta`).

Atualizações futuras: usa **Atualizar** na lista de plugins para migrações.

---

## 🎮 Uso no ticket

| Ação | O que acontece |
|------|----------------|
| ✨ **Resumir** | Resumo abaixo do botão. |
| ⏱️ **Analisar SLA** | Análise na pilha de alertas + Copiar. |
| 🩺 **Diagnóstico** | Perguntas no painel lateral. |
| ✉️ **E-mail encerramento** | Texto na pilha + Copiar. |
| 😊 **Sentimento** | Badge automático. |
| ✅ **Marcar Resolvido** | Opcional: rascunho KB + e-mail de encerramento na sessão. |

---

## 🛡️ Segurança

- Restringe quem pode **configurar** o plugin.
- Protege **backups** (API keys em claro na BD).
- Usa **entidades permitidas** se não quiseres o Copilot em todas as entidades.

---

## 📂 Estrutura do código

```text
glpicopilot/
├── setup.php              # Versão, hooks
├── hook.php               # UI no ticket, migrações, hooks de update
├── glpicopilot.xml        # Metadados GLPI
├── front/config.form.php  # Configuração
├── ajax/
│   ├── summary.php
│   ├── copilot.php        # SLA, sentimento, diagnóstico, e-mail
│   └── suggest_reply.php
├── inc/
│   ├── config.class.php
│   ├── summarizer.class.php
│   ├── ticketintel.class.php
│   └── kb.class.php
├── js/  ·  css/
└── README.md
```

---

## 🔗 Repositório

**[github.com/celsocaninde/glpicopilot](https://github.com/celsocaninde/glpicopilot)**

```bash
git clone https://github.com/celsocaninde/glpicopilot.git
```

---

## 💡 Texto sugerido para a descrição do repositório no GitHub

> Copilot de IA para GLPI 11: resumos de tickets, análise de SLA, sentimento do requerente, diagnóstico guiado, rascunhos de KB ao resolver e e-mails de encerramento — Azure OpenAI, OpenAI, Groq, Grok e Gemini.

*(Cole em **Settings → General → About** no repositório.)*

---

## 🌐 English summary

**GLPI Copilot** is a **GLPI 11** plugin that connects **Azure OpenAI, OpenAI, Groq, xAI Grok, and Google Gemini** to your helpdesk workflow: **ticket summaries**, **SLA risk insight**, **requester sentiment** badge, **guided diagnostic** questions in a side panel, optional **[Draft AI] KnowbaseItem** on solve, and **closing e-mail** text with **copy-to-clipboard**. Configure once under **Setup → Plugins**, restrict by **entity** if needed. **GPLv3+**.

---

## ⚖️ Licença

GNU **General Public License v3 or later** (GPLv3+), aligned with the GLPI ecosystem.
