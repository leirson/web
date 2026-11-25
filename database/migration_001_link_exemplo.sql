-- ============================================================
-- MIGRATION 001 — Adicionar link_exemplo na tabela produtos
-- Execute este script no phpMyAdmin do Hostinger
-- ============================================================

ALTER TABLE produtos
  ADD COLUMN link_exemplo VARCHAR(500) DEFAULT '' AFTER valor_anual;
