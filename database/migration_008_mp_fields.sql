-- Migration 008: Campos do Mercado Pago na tabela pedidos
-- Executar via phpMyAdmin antes de ativar o PIX automático

ALTER TABLE pedidos
    ADD COLUMN mp_payment_id BIGINT DEFAULT NULL AFTER txid,
    ADD COLUMN mp_status VARCHAR(50) DEFAULT NULL AFTER mp_payment_id,
    ADD COLUMN webhook_confirmado TINYINT(1) DEFAULT 0 AFTER mp_status;
