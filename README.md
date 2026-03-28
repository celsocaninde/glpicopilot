# GLPI Copilot (`glpicopilot`)

Plugin para **GLPI 11** que integra vários fornecedores de IA no fluxo de **chamados (tickets)**: resumos, análise de SLA, sentimento do requerente, diagnóstico guiado, rascunho de artigo na base de conhecimento ao resolver e texto de e-mail de encerramento.

| | |
|---|---|
| **GLPI** | 11.0 – 11.99 |
| **PHP** | ≥ 8.2 |
| **Versão do plugin** | 1.2.0 |
| **Licença** | GPLv3+ |

---

## Funcionalidades

| Funcionalidade | Descrição |
|----------------|-----------|
| **Resumo do chamado** | Botão na barra de ações do ticket para gerar um resumo do título, descrição e follow-ups. |
| **Multi-provider** | Azure OpenAI, OpenAI, Groq, xAI Grok e Google Gemini — configurável na página do plugin. |
| **Análise de SLA** | A IA analisa histórico + metadados GLPI (prazos, prioridade, etc.) e indica risco de violação de SLA. |
| **Sentimento** | Badge com classificação aproximada do tom do requerente (frustrado / neutro / satisfeito). |
| **Diagnóstico guiado** | Lista de perguntas sugeridas pela IA; painel lateral para leitura. |
| **Base de conhecimento** | Ao passar o ticket para **Resolvido**, é criado um rascunho de artigo (`KnowbaseItem`) com prefixo `[Draft AI]`. |
| **E-mail de encerramento** | Texto profissional gerado ao resolver (também disponível sob demanda) e botão **Copiar**. |
| **Sugestão de resposta** | Endpoint AJAX `ajax/suggest_reply.php` para sugerir resposta ao requerente (conforme integração). |

### Restrição por entidade

Na configuração é possível limitar o plugin a entidades específicas (`allowed_entities` em JSON). Vazio = todas as entidades.

---

## Instalação

1. Copie a pasta do plugin para o diretório de plugins do GLPI:
   ```text
   GLPI_ROOT/plugins/glpicopilot/
   ```
2. No GLPI: **Configurar → Plugins**, localize **GLPI Copilot** e **instalar** e **ativar**.
3. Na primeira execução são criadas/atualizadas as tabelas:
   - `glpi_plugin_glpicopilot_config`
   - `glpi_plugin_glpicopilot_ticketmeta` (metadados por ticket, ex.: KB já criado)

Se atualizar de uma versão antiga, use **Atualizar** na lista de plugins para aplicar migrações.

---

## Configuração

**Configurar → Plugins → GLPI Copilot** (ou o caminho indicado pelo GLPI).

| Campo | Azure OpenAI | OpenAI / Groq / Grok | Gemini |
|-------|----------------|----------------------|--------|
| **Endpoint / URL** | Obrigatório: URL **completa** do deployment (`…/chat/completions` + `api-version` na query). | Opcional: só a **base** `…/v1` (sem `/chat/completions`). | Não usado (URL fixa na API Google). |
| **Modelo** | Não usado (deployment na URL). | Lista em dropdown + opção “Default”. | Lista em dropdown. |
| **Chave** | Cabeçalho `api-key`. | `Authorization: Bearer`. | Parâmetro `key=` na query (Google AI Studio). |

Guarde as chaves com cuidado: ficam na **base de dados** do GLPI.

---

## Uso no ticket

Na ficha do chamado (com permissão de visualização):

- **Resumir** — gera resumo na área abaixo do botão.
- **Analisar SLA**, **Diagnóstico**, **E-mail encerramento** — resultados na pilha de alertas; textos longos têm **Copiar**.
- **Sentimento** — carregado automaticamente no badge.
- Ao **resolver** o ticket: se configurado e com permissões, pode ser criado o rascunho na KB e mostrado o e-mail de encerramento na próxima visualização.

---

## Segurança e boas práticas

- Restrinja **quem pode alterar configuração** do plugin (perfil GLPI).
- Proteja **backups** da BD (API keys em texto).
- Valide **entidades** se o plugin não deve correr em todas.

---

## Estrutura do projeto

```text
glpicopilot/
├── setup.php              # Versão, hooks, init
├── hook.php               # UI no ticket, migrações, hooks item_update
├── glpicopilot.xml        # Metadados marketplace GLPI
├── front/config.form.php  # Página de configuração
├── ajax/
│   ├── summary.php        # Resumo
│   ├── copilot.php        # SLA, sentimento, diagnóstico, e-mail
│   └── suggest_reply.php  # Sugestão de resposta
├── inc/
│   ├── config.class.php
│   ├── summarizer.class.php  # Chamadas à API (OpenAI-compat + Gemini)
│   ├── ticketintel.class.php # Contexto SLA
│   └── kb.class.php          # KB ao resolver + e-mail em sessão
├── js/ / css/            # Assets auxiliares
└── README.md
```

---

## Enviar para o GitHub

No diretório do plugin:

```bash
git init
git add .
git commit -m "Initial commit: GLPI Copilot plugin v1.2.0"
```

Crie um repositório vazio em [github.com/new](https://github.com/new) (ex.: `glpicopilot`), depois:

```bash
git branch -M main
git remote add origin https://github.com/SEU_USUARIO/glpicopilot.git
git push -u origin main
```

Substitua `SEU_USUARIO` e o nome do repositório. Se usar **SSH**:

```bash
git remote add origin git@github.com:SEU_USUARIO/glpicopilot.git
```

Com [GitHub CLI](https://cli.github.com/) (`gh auth login` feito):

```bash
gh repo create glpicopilot --public --source=. --remote=origin --push
```

---

## English summary

**GLPI Copilot** is a GLPI 11 plugin that connects multiple AI backends (Azure OpenAI, OpenAI, Groq, Grok, Gemini) to tickets: summaries, SLA risk insight, requester sentiment badge, guided diagnostic questions, optional **KnowbaseItem** draft on solve, and closing e-mail text with copy-to-clipboard. Configure the provider under **Setup → Plugins**, install/update database tables from the plugin UI, and restrict by entity if needed. Licensed under **GPLv3+**.

---

## Licença

Este projeto está licenciado nos termos da **GNU General Public License v3 ou superior** (GPLv3+), em linha com o ecossistema GLPI.
