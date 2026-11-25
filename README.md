# AppVendas — Sistema de Venda de Aplicativos Web

## 📋 Visão Geral

Sistema completo em **PHP 8.3 + MySQL** para venda de aplicativos web com:
- Site público com catálogo de produtos, slideshow de imagens e compra via PIX
- Área administrativa completa para gerenciar produtos, pedidos e personalização
- **PIX automático via Mercado Pago API** com confirmação automática por webhook
- Fallback para PIX estático (payload EMV) quando a API não está configurada
- Registro completo de todos os pedidos com dados dos clientes

---

## ✅ Funcionalidades Implementadas

### Site Público (`index.php`)
- Cabeçalho configurável (texto ou imagem/logo) com slogan
- Seção hero animada com partículas
- Catálogo de produtos com slideshow de imagens (transição, dots, setas, touch swipe)
- Cards com nome, descrição, valor de implantação e toggle **Mensal / Anual**
- Botão **"Mais Detalhes"** — modal fullscreen com slideshow ampliado + descrição + toggle mensal/anual
- Botão **"Apresentação"** no card — abre Google Slides (prioridade) ou baixa PPTX
- Botão **"Vídeo"** no card — abre modal fullscreen com YouTube/Vimeo embed
- Botões no modal de detalhes: Manual (PDF), Exemplo, Apresentação, PPTX, Vídeo
- Paginação configurável por produto
- Modal de compra em 2 etapas: dados do cliente + pagamento PIX
- **QR Code PIX real** (imagem base64 via Mercado Pago API) com badge "PIX Automático"
- Fallback: QR Code gerado localmente via `qrcode.js` com payload EMV estático
- Campo "Copia e Cola" com botão de copiar
- Rodapé com botão WhatsApp e texto personalizado
- Layout 100% responsivo (mobile-first)

### Área Administrativa (`/admin/`)
- Login seguro com bcrypt + proteção CSRF
- **Dashboard** com estatísticas (produtos, pedidos, receita)
- **Produtos**: listar, criar, editar, excluir com múltiplas imagens por upload
  - Campo `link_exemplo` (URL de demonstração)
  - Campo `link_apresentacao` (Google Slides ou similar)
  - Campo `link_video` (YouTube / Vimeo)
  - Campo `valor_mensal` (preço mensal alternativo)
  - Upload de **arquivo ZIP** (validação MIME, máx. 200 MB)
  - Upload de **PDF / Manual** (validação MIME, máx. 50 MB)
  - Upload de **PPTX / PPSX** (validação MIME, máx. 100 MB)
  - Todos os arquivos servidos via endpoint seguro (nunca expostos por URL pública)
  - Substituição e exclusão automática dos arquivos físicos ao editar/deletar produto
- **Pedidos**: listar com filtros (status, busca), alterar status, link direto pro WhatsApp, excluir pedido
- **Download seguro** (`admin/download.php`, `admin/manual.php`, `admin/apresentacao.php`): serve arquivos via `readfile()` com autenticação obrigatória
- **Personalização** (`admin/configuracoes.php`):
  - Cabeçalho (tipo, texto, imagem/logo, tamanho, cor)
  - Rodapé (texto, WhatsApp, cores)
  - Produtos (por página, velocidade do slideshow, cores primária/secundária)
  - **PIX Estático** (chave, nome beneficiário, cidade — fallback)
  - **Mercado Pago PIX Automático** (access token, ambiente sandbox/produção, webhook secret, e-mail pagador, URL do webhook gerada automaticamente)
- **Minha Conta**: alterar nome, e-mail e senha do admin

### Pagamento PIX — Fluxo Completo
1. Cliente preenche dados → `checkout.php` salva pedido com status `aguardando`
2. Se **Mercado Pago configurado**: cria cobrança via `POST /v1/payments` e retorna QR Code real (imagem base64 + copia e cola)
3. Se **MP não configurado**: gera payload EMV estático (BR Code) como fallback
4. Quando cliente paga → MP envia notificação para `admin/pix_webhook.php`
5. Webhook valida assinatura HMAC-SHA256, consulta status na API e atualiza pedido para `pago`

---

## 📁 Estrutura de Arquivos

```
/
├── index.php                    # Página principal do site
├── checkout.php                 # API de checkout: cria pedido + PIX automático (MP) ou estático
├── config.php                   # Configurações do banco (editar antes de usar)
├── .htaccess                    # Segurança e redirecionamento
│
├── includes/
│   ├── functions.php            # Funções globais (DB, PIX EMV, upload, helpers)
│   └── mercadopago.php          # Classe MercadoPagoApi + factory getMercadoPagoApi()
│
├── admin/
│   ├── login.php                # Login do admin
│   ├── logout.php               # Logout
│   ├── dashboard.php            # Painel principal
│   ├── produtos.php             # Listagem de produtos (badges ZIP/PDF/PPTX)
│   ├── produto_form.php         # Criar/editar produto + imagens + arquivos
│   ├── download.php             # ⬇ Download seguro de ZIP (admin only)
│   ├── manual.php               # ⬇ Serve PDF do manual (inline, admin only)
│   ├── apresentacao.php         # ⬇ Download seguro de PPTX (admin only)
│   ├── pix_webhook.php          # 🔔 Webhook Mercado Pago — confirma pagamento automaticamente
│   ├── pedidos.php              # Listagem, gestão e exclusão de pedidos
│   ├── configuracoes.php        # Personalização do site + config MP
│   ├── conta.php                # Dados do admin
│   └── partials/
│       ├── sidebar.php          # Menu lateral
│       └── topbar.php           # Barra superior
│
├── assets/
│   ├── css/
│   │   ├── site.css             # Estilos do site público + modais
│   │   └── admin.css            # Estilos do painel
│   └── js/
│       ├── site.js              # JS do site (slideshows, modais, PIX, vídeo)
│       └── admin.js             # JS do admin (sidebar, alerts)
│
├── uploads/                     # Arquivos enviados (criada automaticamente)
│   ├── produtos/                # Imagens dos produtos
│   ├── logos/                   # Logo do cabeçalho
│   ├── zips/                    # ZIPs dos produtos (.htaccess: Deny from all)
│   ├── manuais/                 # PDFs dos produtos (.htaccess: Deny from all)
│   └── apresentacoes/           # PPTX/PPSX (.htaccess: Deny from all)
│
└── database/
    ├── schema.sql                                # Script de criação do banco
    ├── migration_001_link_exemplo.sql            # ADD link_exemplo
    ├── migration_002_arquivo_zip.sql             # ADD arquivo_zip
    ├── migration_003_imagem_descricao.sql        # ADD descricao em produto_imagens
    ├── migration_004_apresentacao_manual.sql     # ADD link_apresentacao + arquivo_manual
    ├── migration_005_arquivo_apresentacao.sql    # ADD arquivo_apresentacao
    ├── migration_006_valor_mensal.sql            # ADD valor_mensal DECIMAL
    ├── migration_007_link_video.sql              # ADD link_video VARCHAR(500)
    └── migration_008_mp_fields.sql              # ADD mp_payment_id, mp_status, webhook_confirmado
```

---

## 🗄️ Modelo de Dados

### Tabela `produtos`
| Coluna | Tipo | Descrição |
|---|---|---|
| id | INT PK AUTO | Identificador |
| nome | VARCHAR(200) | Nome do produto |
| descricao | TEXT | Descrição completa |
| valor_implantacao | DECIMAL(10,2) | Valor único de implantação |
| valor_anual | DECIMAL(10,2) | Valor da licença anual |
| valor_mensal | DECIMAL(10,2) | Valor mensal alternativo (migration 006) |
| link_exemplo | VARCHAR(500) | URL de demonstração |
| link_apresentacao | VARCHAR(500) | URL Google Slides / apresentação |
| link_video | VARCHAR(500) | URL YouTube / Vimeo |
| arquivo_zip | VARCHAR(500) | Caminho relativo do ZIP em `uploads/` |
| arquivo_manual | VARCHAR(500) | Caminho relativo do PDF em `uploads/` |
| arquivo_apresentacao | VARCHAR(500) | Caminho relativo do PPTX em `uploads/` |
| ativo | TINYINT(1) | Visível no site (1) ou não (0) |
| ordem | INT | Ordem de exibição |
| criado_em | TIMESTAMP | Data de criação |

### Tabela `pedidos`
| Coluna | Tipo | Descrição |
|---|---|---|
| id | INT PK AUTO | Identificador |
| produto_id | INT FK | Produto adquirido |
| produto_nome | VARCHAR(200) | Nome do produto (snapshot) |
| valor_implantacao | DECIMAL(10,2) | Valor cobrado |
| valor_anual | DECIMAL(10,2) | Valor de manutenção |
| vencimento_anual | DATE | Data de vencimento da licença |
| cliente_nome | VARCHAR(200) | Nome completo do cliente |
| cliente_documento | VARCHAR(20) | CPF ou CNPJ |
| cliente_estabelecimento | VARCHAR(200) | Nome do estabelecimento |
| cliente_whatsapp | VARCHAR(30) | WhatsApp do cliente |
| status | ENUM | `aguardando`, `pago`, `cancelado` |
| txid | VARCHAR(100) | Referência interna (external_reference no MP) |
| mp_payment_id | BIGINT | ID do pagamento no Mercado Pago (migration 008) |
| mp_status | VARCHAR(50) | Status retornado pelo MP (migration 008) |
| webhook_confirmado | TINYINT(1) | 1 = confirmado via webhook (migration 008) |
| criado_em | TIMESTAMP | Data do pedido |
| atualizado_em | TIMESTAMP | Última atualização |

### Tabela `configuracoes`
Pares `chave / valor`. Chaves relevantes para PIX:

| Chave | Descrição |
|---|---|
| `pix_chave` | Chave PIX estática (fallback) |
| `pix_nome_beneficiario` | Nome do beneficiário (fallback) |
| `pix_cidade` | Cidade do beneficiário (fallback) |
| `mp_access_token` | Access Token do Mercado Pago |
| `mp_ambiente` | `sandbox` ou `producao` |
| `mp_webhook_secret` | Secret key para validar assinatura do webhook |
| `mp_email_pagador` | E-mail padrão do pagador (obrigatório pela API MP) |

---

## 🚀 Instalação no Hostinger

### 1. Banco de Dados
1. No painel Hostinger, crie um banco MySQL
2. Importe `database/schema.sql` via phpMyAdmin
3. Execute os migrations **em ordem**:
   ```
   migration_001_link_exemplo.sql
   migration_002_arquivo_zip.sql
   migration_003_imagem_descricao.sql
   migration_004_apresentacao_manual.sql
   migration_005_arquivo_apresentacao.sql
   migration_006_valor_mensal.sql
   migration_007_link_video.sql
   migration_008_mp_fields.sql          ← PIX automático
   ```

### 2. Configuração
Edite o arquivo `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'seu_banco');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
define('BASE_URL', 'https://seudominio.com.br');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');
```

### 3. Configurar Mercado Pago (PIX Automático)
1. Acesse o [Painel de Desenvolvedores do Mercado Pago](https://www.mercadopago.com.br/developers/panel)
2. Crie ou selecione uma aplicação
3. Copie o **Access Token** (TEST-xxx para sandbox, APP_USR-xxx para produção)
4. Em **Webhooks**, adicione a URL: `https://seudominio.com.br/admin/pix_webhook.php`
   - Tópico: **payment**
   - Versão: **v1**
5. Copie o **Webhook Secret** gerado
6. No admin do site: **Personalização → Mercado Pago PIX Automático**
   - Cole o Access Token
   - Cole o Webhook Secret
   - Informe um e-mail padrão do pagador
   - Selecione o ambiente (sandbox para testes)

### 4. Testar PIX Automático (Sandbox)
1. Configure com token `TEST-xxx` no ambiente **Sandbox**
2. Faça um pedido no site — o QR Code exibido será real, mas não cobrará nada
3. Use o [Simulador de Pagamentos do MP](https://www.mercadopago.com.br/developers/pt/docs/checkout-api/integration-test/test-cards) para aprovar
4. Verifique nos pedidos admin se o status mudou para `pago`

---

## 🔌 Endpoints Funcionais

| Método | URL | Descrição |
|---|---|---|
| GET | `/` | Site público com catálogo |
| POST | `/checkout.php` | Cria pedido + gera cobrança PIX (MP ou estático) |
| POST | `/admin/pix_webhook.php` | Webhook do Mercado Pago (chamado pelo servidor MP) |
| GET | `/admin/download.php?produto_id=N` | Download seguro do ZIP (admin) |
| GET | `/admin/manual.php?produto_id=N` | Visualizar PDF do manual (admin) |
| GET | `/admin/apresentacao.php?produto_id=N` | Download do PPTX (admin) |

### `POST /checkout.php` — Payload de entrada:
```json
{
  "produto_id": 1,
  "cliente_nome": "João Silva",
  "cliente_doc": "123.456.789-00",
  "cliente_estab": "Pizzaria do João",
  "cliente_wa": "5511999999999"
}
```

### `POST /checkout.php` — Resposta (MP ativo):
```json
{
  "sucesso": true,
  "pedido_id": 42,
  "txid": "AV1A2B3C4D5E6F7G8H9I0J",
  "produto_nome": "Sistema PDV",
  "valor_implantacao": 1500.00,
  "valor_anual": 600.00,
  "vencimento_anual": "17/05/2027",
  "pix_automatico": true,
  "mp_payment_id": 1234567890,
  "qr_code_base64": "iVBORw0KGgo...",
  "qr_code_text": "00020126580014br.gov.bcb.pix...",
  "pix_payload": "",
  "pix_chave": ""
}
```

---

## 🔐 Segurança

- Todos os uploads validados por MIME real (`finfo_open`) + extensão
- Arquivos sensíveis (ZIP, PDF, PPTX) protegidos com `.htaccess` (`Deny from all`)
- Servidos somente via PHP com `realpath()` + verificação de prefixo (anti path traversal)
- Queries com PDO + prepared statements (anti SQL injection)
- CSRF token em todos os formulários admin
- Webhook MP validado por assinatura HMAC-SHA256 (`X-Signature`)
- Sem credenciais do MP expostas no frontend

---

## ⏳ Pendências / Próximos Passos

- [ ] Notificação automática por e-mail/WhatsApp quando pedido for confirmado
- [ ] Tela de status do pedido para o cliente (polling ou SSE)
- [ ] Suporte a pagamento de renovação anual via PIX
- [ ] Relatórios de receita por período no dashboard
- [ ] Exportação de pedidos para CSV/Excel
