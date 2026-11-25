-- Migration 005: adiciona coluna para arquivo PPTX/PPSX da apresentação do produto
-- Execute no phpMyAdmin antes de usar o recurso de upload de apresentação.

ALTER TABLE produtos
    ADD COLUMN arquivo_apresentacao VARCHAR(500) DEFAULT '' AFTER link_apresentacao;
