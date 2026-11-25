-- Migration 006: adiciona coluna de valor de manutenção mensal (opcional)
-- Quando preenchido, exibe toggle Mensal / Anual no card do produto no site.
-- Execute no phpMyAdmin antes de usar o recurso de pagamento mensal.

ALTER TABLE produtos
    ADD COLUMN valor_mensal DECIMAL(10,2) DEFAULT 0.00 AFTER valor_anual;
