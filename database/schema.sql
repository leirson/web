-- ============================================================
-- SCHEMA DO BANCO DE DADOS - AppVendas
-- Compatível com MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

-- ATENÇÃO: Execute este script já dentro do banco de dados criado no Hostinger.
-- NÃO inclua CREATE DATABASE nem USE aqui — o Hostinger não permite.

-- Tabela de administradores
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de configurações do site
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de produtos
CREATE TABLE IF NOT EXISTS produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    descricao TEXT,
    valor_implantacao DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_anual DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de imagens dos produtos
CREATE TABLE IF NOT EXISTS produto_imagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    caminho VARCHAR(500) NOT NULL,
    ordem INT NOT NULL DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de pedidos
CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    produto_nome VARCHAR(200) NOT NULL,
    valor_implantacao DECIMAL(10,2) NOT NULL,
    valor_anual DECIMAL(10,2) NOT NULL,
    vencimento_anual DATE NOT NULL,
    cliente_nome VARCHAR(200) NOT NULL,
    cliente_documento VARCHAR(20) NOT NULL,
    cliente_estabelecimento VARCHAR(200) NOT NULL,
    cliente_whatsapp VARCHAR(30) NOT NULL,
    status ENUM('aguardando','pago','cancelado') NOT NULL DEFAULT 'aguardando',
    txid VARCHAR(100),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DADOS INICIAIS
-- ============================================================

-- Admin padrão: admin@admin.com / admin123
INSERT INTO admins (nome, email, senha) VALUES
('Administrador', 'admin@admin.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Configurações padrão
INSERT INTO configuracoes (chave, valor) VALUES
('site_nome', 'AppVendas'),
('header_tipo', 'texto'),
('header_texto', 'AppVendas'),
('header_texto_tamanho', '2.5'),
('header_imagem', ''),
('header_imagem_largura', '200'),
('header_slogan', 'Soluções digitais para o seu negócio'),
('header_slogan_tamanho', '1.2'),
('header_bg_cor', '#0f172a'),
('header_texto_cor', '#ffffff'),
('rodape_whatsapp', '5511999999999'),
('rodape_texto', 'Entre em contato conosco pelo WhatsApp para mais informações.'),
('rodape_bg_cor', '#0f172a'),
('rodape_texto_cor', '#ffffff'),
('produtos_por_pagina', '6'),
('pix_chave', ''),
('pix_nome_beneficiario', 'AppVendas'),
('pix_cidade', 'São Paulo'),
('site_cor_primaria', '#6366f1'),
('site_cor_secundaria', '#8b5cf6'),
('slideshow_velocidade', '4000');
