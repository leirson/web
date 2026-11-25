-- Migration 007: link de vídeo de demonstração por produto
-- Execute no phpMyAdmin antes de usar o recurso de vídeo.

ALTER TABLE produtos
    ADD COLUMN link_video VARCHAR(500) DEFAULT '' AFTER link_apresentacao;
