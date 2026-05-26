-- ============================================================
--  SEED DATA — Geo-Ecomers
--  Usuarios, Productos, Inventario, Configuración
--  Ejecutar en phpMyAdmin dentro de corporat_ecommerce_geo
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ============================================================
-- LIMPIAR DATOS PREVIOS (orden inverso por FKs)
-- ============================================================
DELETE FROM `inventory`;
DELETE FROM `product_images`;
DELETE FROM `product_variants`;
DELETE FROM `products`;
DELETE FROM `categories`;
DELETE FROM `bank_accounts`;
DELETE FROM `payment_methods_config`;
DELETE FROM `shipping_carriers`;
DELETE FROM `shipping_rates`;
DELETE FROM `shipping_zones`;
DELETE FROM `discounts`;
DELETE FROM `settings`;
DELETE FROM `users`;

-- Reiniciar AUTO_INCREMENT
ALTER TABLE `users`                  AUTO_INCREMENT = 1;
ALTER TABLE `categories`             AUTO_INCREMENT = 1;
ALTER TABLE `products`               AUTO_INCREMENT = 1;
ALTER TABLE `product_images`         AUTO_INCREMENT = 1;
ALTER TABLE `inventory`              AUTO_INCREMENT = 1;
ALTER TABLE `payment_methods_config` AUTO_INCREMENT = 1;
ALTER TABLE `bank_accounts`          AUTO_INCREMENT = 1;
ALTER TABLE `shipping_zones`         AUTO_INCREMENT = 1;
ALTER TABLE `shipping_carriers`      AUTO_INCREMENT = 1;
ALTER TABLE `shipping_rates`         AUTO_INCREMENT = 1;
ALTER TABLE `discounts`              AUTO_INCREMENT = 1;
ALTER TABLE `settings`               AUTO_INCREMENT = 1;

-- ============================================================
-- USUARIOS
-- Admin  → admin@geo-ecomers.com  / Admin@Geo2024!
-- Cliente→ cliente@test.com       / Cliente123!
-- ============================================================
INSERT INTO `users` (`name`,`email`,`password`,`phone`,`role`,`status`,`email_verified_at`) VALUES
('Administrador GeoEcomers', 'admin@geo-ecomers.com',
 '$2b$10$N8QYIWpPGXJcrdOxGyr9Hu0TVo.Ev9LQ0N73YmtwKOrSYhLnQ/iDa',
 '+593 99 000 0001', 'admin', 'active', NOW()),

('María González', 'cliente@test.com',
 '$2b$10$KIOOpwJ91H56YQfyR8FsjONQEVQw4NYSnQwHk2/vcaMm4NUowIo7y',
 '+593 99 111 2222', 'customer', 'active', NOW()),

('Carlos Rodríguez', 'carlos@test.com',
 '$2b$10$KIOOpwJ91H56YQfyR8FsjONQEVQw4NYSnQwHk2/vcaMm4NUowIo7y',
 '+593 98 333 4444', 'customer', 'active', NOW());

-- ============================================================
-- CATEGORÍAS
-- ============================================================
INSERT INTO `categories` (`name`,`slug`,`description`,`status`,`sort_order`) VALUES
('Electrónica',      'electronica',      'Celulares, laptops, accesorios tecnológicos y más.',     'active', 1),
('Ropa y Moda',      'ropa-moda',        'Camisas, pantalones, vestidos y accesorios de moda.',    'active', 2),
('Hogar y Jardín',   'hogar-jardin',     'Muebles, decoración, jardín y utensilios del hogar.',    'active', 3),
('Deportes',         'deportes',         'Equipos y ropa deportiva para todas las actividades.',   'active', 4),
('Juguetes y Niños', 'juguetes-ninos',   'Juguetes educativos y entretenimiento para niños.',      'active', 5),
('Belleza y Salud',  'belleza-salud',    'Cosméticos, cuidado personal y productos de salud.',     'active', 6);

-- ============================================================
-- PRODUCTOS  (15 productos variados)
-- Imágenes de Unsplash (libres de uso)
-- ============================================================

-- === ELECTRÓNICA (cat 1) ===
INSERT INTO `products` (`category_id`,`sku`,`name`,`slug`,`short_description`,`description`,`price`,`compare_price`,`weight`,`status`,`is_featured`,`tax_rate`) VALUES
(1,'GEO-ELEC-001','Smartphone Samsung Galaxy A54','smartphone-samsung-galaxy-a54',
 'Pantalla AMOLED 6.4", 128GB, cámara triple 50MP.',
 'El Samsung Galaxy A54 combina diseño elegante con tecnología avanzada. Pantalla Super AMOLED de 6.4 pulgadas, procesador Exynos 1380, batería de 5000mAh y triple cámara trasera de 50MP. Resistente al agua IP67.',
 459.99, 529.99, 0.20, 'active', 1, 12.00),

(1,'GEO-ELEC-002','Audífonos Bluetooth JBL Tune 520BT','audifonos-jbl-tune-520bt',
 'Sonido Pure Bass, 57 horas de batería, plegables.',
 'Audífonos inalámbricos JBL con tecnología Pure Bass. Hasta 57 horas de reproducción, conexión rápida Bluetooth 5.3, diseño plegable y ultraligero. Incluye cable USB-C para carga.',
 59.99, 79.99, 0.25, 'active', 1, 12.00),

(1,'GEO-ELEC-003','Laptop HP 15 Core i5 8GB RAM','laptop-hp-15-core-i5',
 'Intel Core i5 12va Gen, 8GB RAM, 512GB SSD, pantalla FHD.',
 'Laptop HP con procesador Intel Core i5 de 12va generación, 8GB de RAM DDR4, 512GB SSD NVMe y pantalla Full HD de 15.6". Ideal para trabajo, estudio y entretenimiento. Incluye Windows 11 Home.',
 699.99, 849.99, 2.10, 'active', 1, 12.00),

(1,'GEO-ELEC-004','Tablet iPad 10ma Generación','tablet-ipad-10ma-generacion',
 'Pantalla Liquid Retina 10.9", chip A14 Bionic, 64GB.',
 'iPad de 10ma generación con chip A14 Bionic, pantalla Liquid Retina de 10.9 pulgadas y cámara trasera de 12MP. Compatible con Apple Pencil y teclado Smart Folio. Color: Azul.',
 549.99, NULL, 0.48, 'active', 0, 12.00),

-- === ROPA Y MODA (cat 2) ===
(2,'GEO-ROPA-001','Camiseta Polo Ralph Lauren Classic','camiseta-polo-ralph-lauren-classic',
 'Algodón 100%, corte slim fit, disponible en varios colores.',
 'Camiseta polo clásica de Ralph Lauren confeccionada en algodón piqué de alta calidad. Corte slim fit, cuello ribeteado y bordado del icónico logo del jinete. Disponible en blanco, navy y rojo.',
 49.99, 65.00, 0.30, 'active', 1, 12.00),

(2,'GEO-ROPA-002','Jeans Levi\'s 511 Slim Fit','jeans-levis-511-slim-fit',
 'Denim premium, corte slim, 98% algodón.',
 'Los legendarios Levi\'s 511 con su icónico corte slim fit. Fabricados en denim de alta calidad con elasticidad para mayor comodidad. Cierre con cremallera YKK, 5 bolsillos clásicos.',
 79.99, 95.00, 0.60, 'active', 0, 12.00),

(2,'GEO-ROPA-003','Zapatillas Nike Air Max 270','zapatillas-nike-air-max-270',
 'Unidad Air Max más grande, amortiguación superior, diseño moderno.',
 'Las Nike Air Max 270 presentan la unidad Air Max más grande hasta la fecha en el talón, brindando comodidad y estilo. Upper de malla transpirable, suela de goma resistente.',
 129.99, 159.99, 0.90, 'active', 1, 12.00),

-- === HOGAR Y JARDÍN (cat 3) ===
(3,'GEO-HOGAR-001','Licuadora Oster 600W 10 Velocidades','licuadora-oster-600w',
 '600W, jarra de vidrio 1.5L, 10 velocidades, garantía 2 años.',
 'Licuadora Oster con motor de 600 vatios, jarra de vidrio borosilicato de 1.5 litros y 10 velocidades con función pulse. Base antideslizante, fácil de limpiar. Garantía de 2 años.',
 59.99, 74.99, 1.80, 'active', 0, 12.00),

(3,'GEO-HOGAR-002','Juego de Sábanas Microfibra King','sabanas-microfibra-king',
 'Set 4 piezas: sábana plana, ajustable y 2 fundas. Suaves y frescas.',
 'Set completo de sábanas en microfibra de 1800 hilos para cama King. Incluye sábana plana, sábana ajustable con bolsillo profundo de 40cm y dos fundas de almohada. Anti-arrugas, resistentes al desteñido.',
 39.99, 55.00, 1.20, 'active', 0, 12.00),

(3,'GEO-HOGAR-003','Cafetera De\'Longhi Espresso Automática','cafetera-delonghi-espresso',
 'Espresso, cappuccino y latte en un toque. Depósito 1.8L.',
 'Cafetera espresso completamente automática De\'Longhi con molinillo integrado, vaporizador de leche y pantalla táctil. Depósito de agua de 1.8 litros, presión 15 bares.',
 299.99, 379.99, 5.50, 'active', 1, 12.00),

-- === DEPORTES (cat 4) ===
(4,'GEO-DEP-001','Bicicleta de Montaña Trek Marlin 5','bicicleta-trek-marlin-5',
 'Cuadro aluminio Alpha, 21 velocidades, frenos de disco.',
 'Bicicleta de montaña Trek Marlin 5 con cuadro de aluminio Alpha Gold, 21 velocidades Shimano, frenos de disco hidráulicos y horquilla SR Suntour XCT con 100mm de recorrido. Ruedas 29".',
 649.99, 749.99, 14.00, 'active', 1, 12.00),

(4,'GEO-DEP-002','Set Mancuernas Ajustables 20kg','set-mancuernas-ajustables-20kg',
 'Par de mancuernas 2.5-10kg c/u, hierro fundido, ajustables.',
 'Set de mancuernas ajustables de hierro fundido con recubrimiento de vinilo antideslizante. Cada mancuerna ajustable de 2.5 a 10 kg. Incluye soporte de almacenamiento. Ideales para entrenamiento en casa.',
 89.99, 109.99, 20.00, 'active', 0, 12.00),

-- === JUGUETES (cat 5) ===
(5,'GEO-JUG-001','LEGO City Set 60303 Holiday Calendar','lego-city-holiday-calendar',
 '336 piezas, 24 ventanas sorpresa, niños 5+ años.',
 'Calendario de Adviento LEGO City con 336 piezas. Contiene 24 regalos sorpresa incluyendo minifiguras, vehículos y accesorios. Recomendado para niños de 5 años en adelante.',
 44.99, 54.99, 0.70, 'active', 0, 12.00),

-- === BELLEZA (cat 6) ===
(6,'GEO-BELL-001','Perfume Carolina Herrera Good Girl 80ml','perfume-carolina-herrera-good-girl',
 'Eau de Parfum femenino, frasco icónico, notas florales y maderas.',
 'Good Girl de Carolina Herrera es una fragancia femenina que combina notas de jazmín sambac, tuberosa, cacao y vetiver. Eau de Parfum 80ml. Frasco de tacón icónico en negro y dorado.',
 109.99, 135.00, 0.35, 'active', 1, 12.00),

(6,'GEO-BELL-002','Set Skincare Neutrogena Hydro Boost','set-skincare-neutrogena-hydro-boost',
 'Crema hidratante + sérum + contorno de ojos. Ácido hialurónico.',
 'Set completo de cuidado facial Neutrogena Hydro Boost con ácido hialurónico. Incluye crema hidratante gel 50ml, sérum concentrado 30ml y crema contorno de ojos 15ml. Para todo tipo de piel.',
 54.99, 69.99, 0.40, 'active', 0, 12.00);

-- ============================================================
-- IMÁGENES DE PRODUCTOS (Unsplash - uso libre)
-- ============================================================
INSERT INTO `product_images` (`product_id`,`image_url`,`alt_text`,`is_primary`,`sort_order`) VALUES
(1,  'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=600', 'Samsung Galaxy A54', 1, 1),
(2,  'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600', 'Audífonos JBL', 1, 1),
(3,  'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=600', 'Laptop HP', 1, 1),
(4,  'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=600', 'iPad 10ma Gen', 1, 1),
(5,  'https://images.unsplash.com/photo-1586790170083-2f9ceadc732d?w=600', 'Polo Ralph Lauren', 1, 1),
(6,  'https://images.unsplash.com/photo-1542272604-787c3835535d?w=600', 'Jeans Levis 511', 1, 1),
(7,  'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600', 'Nike Air Max 270', 1, 1),
(8,  'https://images.unsplash.com/photo-1570197571499-166b36435e9f?w=600', 'Licuadora Oster', 1, 1),
(9,  'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=600', 'Sábanas Microfibra', 1, 1),
(10, 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?w=600', 'Cafetera Espresso', 1, 1),
(11, 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?w=600', 'Bicicleta Trek', 1, 1),
(12, 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=600', 'Mancuernas', 1, 1),
(13, 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=600', 'LEGO City', 1, 1),
(14, 'https://images.unsplash.com/photo-1541643600914-78b084683702?w=600', 'Perfume Good Girl', 1, 1),
(15, 'https://images.unsplash.com/photo-1556228578-8c89e6adf883?w=600', 'Skincare Neutrogena', 1, 1);

-- ============================================================
-- INVENTARIO (stock inicial)
-- ============================================================
INSERT INTO `inventory` (`product_id`,`quantity`,`reserved_quantity`,`reorder_level`,`warehouse_location`) VALUES
(1,  25, 0, 5,  'A-01'),
(2,  40, 0, 10, 'A-02'),
(3,  10, 0, 3,  'A-03'),
(4,  8,  0, 3,  'A-04'),
(5,  50, 0, 10, 'B-01'),
(6,  35, 0, 10, 'B-02'),
(7,  20, 0, 5,  'B-03'),
(8,  30, 0, 8,  'C-01'),
(9,  45, 0, 10, 'C-02'),
(10, 12, 0, 3,  'C-03'),
(11, 6,  0, 2,  'D-01'),
(12, 15, 0, 5,  'D-02'),
(13, 22, 0, 5,  'E-01'),
(14, 18, 0, 5,  'F-01'),
(15, 28, 0, 8,  'F-02');

-- ============================================================
-- MÉTODOS DE PAGO
-- ============================================================
INSERT INTO `payment_methods_config` (`name`,`type`,`provider`,`instructions`,`is_active`,`sort_order`) VALUES
('Tarjeta de Crédito / Débito', 'credit_card',      'Stripe',  'Pago seguro con Visa, Mastercard y Amex.',                                                                       1, 1),
('Transferencia Bancaria',       'bank_transfer',    NULL,      'Realiza la transferencia y sube el comprobante para confirmar tu pedido.',                                       1, 2),
('PayPal',                       'paypal',           'PayPal',  NULL,                                                                                                             0, 3),
('Pago Contra Entrega',          'cash_on_delivery', NULL,      'Paga en efectivo al recibir tu pedido. Solo disponible en zonas habilitadas.',                                   0, 4);

-- ============================================================
-- CUENTAS BANCARIAS
-- ============================================================
INSERT INTO `bank_accounts` (`bank_name`,`account_name`,`account_number`,`account_type`,`identification`,`is_active`) VALUES
('Banco Pichincha',    'Geo Ecomers S.A.', '2200000001', 'corriente', '1792345678001', 1),
('Banco Guayaquil',    'Geo Ecomers S.A.', '0700000001', 'ahorros',   '1792345678001', 1),
('Banco del Pacífico', 'Geo Ecomers S.A.', '3000000001', 'corriente', '1792345678001', 1);

-- ============================================================
-- TRANSPORTISTAS
-- ============================================================
INSERT INTO `shipping_carriers` (`name`,`tracking_url`,`is_active`) VALUES
('Correos del Ecuador', 'https://www.correos.gob.ec/rastreo/?codigo={tracking_number}', 1),
('Servientrega',        'https://www.servientrega.com.ec/rastreo/{tracking_number}',    1),
('Speed',               'https://www.speed.com.ec/rastreo/{tracking_number}',           1),
('DHL Express',         'https://www.dhl.com/ec-es/home/tracking.html?tracking-id={tracking_number}', 1);

-- ============================================================
-- ZONAS DE ENVÍO
-- ============================================================
INSERT INTO `shipping_zones` (`name`,`locations`) VALUES
('Sierra - Principal',    '["Quito","Cuenca","Ambato","Riobamba","Loja","Ibarra","Latacunga"]'),
('Costa - Principal',     '["Guayaquil","Manta","Esmeraldas","Santo Domingo","Portoviejo","Machala"]'),
('Oriente y otras zonas', '["Lago Agrio","Tena","Macas","Zamora","Puyo"]'),
('Internacional',         '["Estados Unidos","España","Colombia","Perú","México"]');

-- ============================================================
-- TARIFAS DE ENVÍO
-- ============================================================
INSERT INTO `shipping_rates` (`zone_id`,`name`,`base_cost`,`cost_per_kg`,`estimated_days_min`,`estimated_days_max`,`min_order_free_shipping`,`is_active`) VALUES
(1, 'Envío Estándar Sierra',   3.50, 0.50, 2,  5,  50.00, 1),
(1, 'Envío Express Sierra',    6.00, 0.80, 1,  2,  NULL,  1),
(2, 'Envío Estándar Costa',    4.00, 0.50, 3,  7,  60.00, 1),
(2, 'Envío Express Costa',     8.00, 0.80, 1,  3,  NULL,  1),
(3, 'Envío Estándar Oriente',  5.50, 0.60, 4,  8,  NULL,  1),
(4, 'Envío Internacional',    25.00, 2.00, 7, 15,  NULL,  1);

-- ============================================================
-- CUPONES DE DESCUENTO
-- ============================================================
INSERT INTO `discounts` (`code`,`name`,`type`,`value`,`min_order_amount`,`max_uses`,`expires_at`,`status`) VALUES
('BIENVENIDO10', 'Descuento bienvenida 10%',      'percentage',   10.00, 20.00, 500,  DATE_ADD(NOW(), INTERVAL 1 YEAR),  'active'),
('ENVIOGRATIS',  'Envío gratis primer pedido',     'free_shipping', 0.00, 30.00, 1000, DATE_ADD(NOW(), INTERVAL 6 MONTH), 'active'),
('GEO5OFF',      'Descuento fijo $5',              'fixed_amount',  5.00, 40.00, 200,  DATE_ADD(NOW(), INTERVAL 3 MONTH), 'active'),
('TECH20',       'Electrónica 20% OFF',            'percentage',   20.00, 100.00, 100, DATE_ADD(NOW(), INTERVAL 2 MONTH), 'active');

-- ============================================================
-- CONFIGURACIÓN DEL SITIO
-- ============================================================
INSERT INTO `settings` (`key_name`,`value`,`type`,`group_name`) VALUES
('site_name',              'Geo-Ecomers',                   'text',    'general'),
('site_tagline',           'Tu tienda online de confianza', 'text',    'general'),
('site_email',             'info@geo-ecomers.com',          'text',    'general'),
('site_phone',             '+593 99 000 0000',              'text',    'general'),
('currency',               'USD',                           'text',    'general'),
('currency_symbol',        '$',                             'text',    'general'),
('tax_rate',               '12',                            'number',  'general'),
('free_shipping_min',      '50',                            'number',  'shipping'),
('orders_per_page',        '20',                            'number',  'admin'),
('products_per_page',      '12',                            'number',  'store'),
('low_stock_threshold',    '5',                             'number',  'inventory'),
('allow_guest_checkout',   '1',                             'boolean', 'checkout'),
('stripe_public_key',      '',                              'text',    'payments'),
('stripe_secret_key',      '',                              'text',    'payments');

SET foreign_key_checks = 1;

-- ============================================================
-- RESUMEN DE ACCESOS
-- Admin : admin@geo-ecomers.com   / Admin@Geo2024!
-- Cliente: cliente@test.com       / Cliente123!
-- ============================================================
