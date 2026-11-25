-- Migration 003: adiciona coluna descricao em produto_imagens
-- Execute via phpMyAdmin antes de usar o upload com descrição de imagens
ALTER TABLE produto_imagens ADD COLUMN descricao VARCHAR(255) DEFAULT '' AFTER caminho;
