-- Migration 004: Apresentação (link externo) + Manual (PDF upload) por produto
-- Execute via phpMyAdmin após migration_003
ALTER TABLE produtos
    ADD COLUMN link_apresentacao VARCHAR(500) DEFAULT '' AFTER link_exemplo,
    ADD COLUMN arquivo_manual    VARCHAR(500) DEFAULT '' AFTER link_apresentacao;
