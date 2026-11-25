-- ============================================================
-- MIGRATION 002 — Adicionar arquivo_zip na tabela produtos
-- Execute no phpMyAdmin do Hostinger
-- ============================================================

ALTER TABLE produtos
  ADD COLUMN arquivo_zip VARCHAR(500) DEFAULT '' AFTER link_exemplo;
