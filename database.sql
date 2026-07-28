-- Banco de dados Fight Arcade B2B
-- Criar o banco na Hostinger e importar este arquivo

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Tabela `settings`
--
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_name` varchar(100) NOT NULL DEFAULT 'Helena Flores',
  `whatsapp_phone` varchar(30) NOT NULL DEFAULT '5511986727872',
  `admin_email` varchar(100) NOT NULL DEFAULT 'contato@helenafloresjardins.com.br',
  `logo_path` varchar(255) DEFAULT NULL,
  `banner_title` varchar(120) DEFAULT 'Helena Flores - Floricultura nos Jardins',
  `banner_subtitle` varchar(255) DEFAULT 'Buquês de Rosas Colombianas, Cestas e Arranjos de Luxo',
  `banner_image` varchar(255) DEFAULT NULL,
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`site_name`, `whatsapp_phone`, `admin_email`) VALUES
('Helena Flores', '5511986727872', 'contato@helenafloresjardins.com.br');

--
-- Tabela `users`
--
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `document` varchar(20) DEFAULT NULL COMMENT 'CPF ou CNPJ',
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','customer') NOT NULL DEFAULT 'customer',
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar admin padrão (Senha: admin123) - RECOMENDADO ALTERAR DEPOIS
INSERT INTO `users` (`name`, `email`, `password`, `role`) VALUES
('Administrador', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

--
-- Tabela `categories`
--
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`name`, `slug`, `sort_order`) VALUES
('Kits Completos', 'kits-completos', 1),
('Joysticks', 'joysticks', 2),
('Botões', 'botoes', 3),
('Placas & Interfaces', 'placas-interfaces', 4),
('Peças de Reposição', 'pecas-reposicao', 5);

--
-- Tabela `products`
--
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `sku` varchar(60) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_wholesale` decimal(10,2) DEFAULT NULL,
  `min_wholesale_qty` int(11) DEFAULT 10,
  `image_path` varchar(255) DEFAULT NULL,
  `stock_qty` int(11) DEFAULT 100,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demo Products (Based on requested links)
INSERT INTO `products` (`category_id`, `name`, `slug`, `description`, `sku`, `price`, `price_wholesale`, `image_path`) VALUES
(2, 'Controle Arcade Luatek LPS-601', 'controle-arcade-luatek-lps-601', 'Controle Arcade Duplo profissional com milhares de jogos clássicos. Conexão HDMI, zero delay. Ideal para revenda.', 'LPS-601', 556.00, 372.00, NULL),
(3, 'Kit Botões LED Iluminados (10 un)', 'kit-botoes-led-10un', 'Botões estilo Sanwa com LED embutido. Cores variadas. Alta durabilidade.', 'BTN-LED-01', 61.40, 50.00, NULL),
(4, 'Placa Zero Delay USB', 'placa-zero-delay-usb', 'Interface USB para controles arcade. Compatível com PC, PS3 e Raspberry. Acompanha cabos.', 'PCB-ZD-01', 32.19, 25.00, NULL),
(2, 'Joystick Óptico Profissional', 'joystick-optico', 'Manche óptico de alta precisão sem microswitches mecânicos. Vida útil prolongada.', 'JOY-OPT-02', 207.85, 180.00, NULL),
(4, 'Moedeiro Eletrônico Comparador', 'moedeiro-eletronico', 'Moedeiro multimoedas configurável. Ideal para máquinas de fliperama comerciais.', 'COIN-01', 70.29, 60.00, NULL),
(5, 'Microswitch Ação Rápida', 'microswitch-acao-rapida', 'Peça de reposição para botões e joysticks. Alta sensibilidade.', 'SW-FAST', 12.25, 8.00, NULL);

--
-- Tabela `orders`
--
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','paid','shipped','canceled') NOT NULL DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT 'pix',
  `tracking_code` varchar(100) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tabela `order_items`
--
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(120) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
