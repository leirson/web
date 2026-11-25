<?php
// ============================================================
// CONFIGURAÇÃO DO BANCO DE DADOS — AppVendas
// ============================================================
// Como encontrar os dados no Hostinger:
//   Painel Hostinger → Hospedagem → Gerenciar → Banco de Dados MySQL
//   Os dados (host, nome, usuário, senha) ficam na listagem do banco criado.
// ============================================================

// Hostinger geralmente usa 'localhost' — não altere
define('DB_HOST', 'localhost');

// Nome do banco criado no painel Hostinger (ex: u123456789_appvendas)
define('DB_NAME', 'NOME_DO_BANCO');

// Usuário do banco criado no painel Hostinger (ex: u123456789_usuario)
define('DB_USER', 'USUARIO_DO_BANCO');

// Senha definida na criação do banco
define('DB_PASS', 'SENHA_DO_BANCO');

define('DB_CHARSET', 'utf8mb4');

// ============================================================
// URL BASE DO SITE — sem barra no final!
// Exemplos:
//   https://seudominio.com.br
//   https://www.seudominio.com.br
//   http://seudominio.com.br  (sem SSL)
// ============================================================
define('BASE_URL', 'https://SEUDOMINIO.COM.BR');

// Caminhos para uploads (não alterar)
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// Sessão
define('SESSION_NAME', 'appvendas_session');
define('SESSION_LIFETIME', 3600 * 8); // 8 horas

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Modo debug — deixe false em produção
// Mude para true TEMPORARIAMENTE se quiser ver erros detalhados
define('DEBUG_MODE', false);
