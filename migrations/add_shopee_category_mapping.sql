-- ============================================
-- MIGRAÇÃO: Adicionar Mapeamento Shopee
-- ============================================
-- Este script adiciona suporte para mapear categorias
-- internas para categorias da Shopee

-- 1. Adicionar coluna shopee_category_id
ALTER TABLE categories 
ADD COLUMN shopee_category_id VARCHAR(50) DEFAULT NULL 
AFTER name;

-- 2. Definir categoria padrão para todas as categorias existentes
UPDATE categories 
SET shopee_category_id = '121101' 
WHERE shopee_category_id IS NULL;

-- ============================================
-- EXEMPLOS DE CATEGORIAS SHOPEE COMUNS
-- ============================================
-- Depois de rodar este script, você pode atualizar
-- manualmente cada categoria no painel admin

-- Eletrônicos > Acessórios: 121101
-- Eletrônicos > Games: 120039
-- Eletrônicos > Computadores: 120038
-- Hobbies > Colecionáveis: 125001
-- Esportes > Equipamentos: 130001

-- ============================================
-- EXEMPLO DE USO MANUAL
-- ============================================
-- UPDATE categories 
-- SET shopee_category_id = '120039' 
-- WHERE name = 'Jogos Arcade';

-- ============================================
-- VERIFICAÇÃO
-- ============================================
SELECT id, name, shopee_category_id 
FROM categories 
ORDER BY id;
