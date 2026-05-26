-- ============================================================
--  BASE DE DATOS : corporat_ecommerce_geo
--  Proyecto      : Geo-Ecomers | Plataforma E-Commerce
--  Motor         : MySQL 5.7+ / MariaDB 10.3+
--  Herramienta   : phpMyAdmin  (copiar y ejecutar completo)
--
--  INSTRUCCIONES:
--   1. Abre phpMyAdmin
--   2. Haz clic en la pestaña "SQL"
--   3. Pega TODO este contenido y presiona "Continuar / Go"
--   4. Listo, la base y todos los datos iniciales quedan creados
-- ============================================================

-- Configuración de sesión (obligatorio para phpMyAdmin)
SET NAMES utf8mb4;
SET time_zone          = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode           = 'NO_AUTO_VALUE_ON_ZERO';

-- ============================================================
-- TABLA 1: users  (clientes y administradores)
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`              VARCHAR(100)    NOT NULL,
  `email`             VARCHAR(150)    NOT NULL,
  `password`          VARCHAR(255)    NOT NULL,
  `phone`             VARCHAR(20)     DEFAULT NULL,
  `role`              ENUM('admin','customer','seller') NOT NULL DEFAULT 'customer',
  `status`            ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
  `avatar`            VARCHAR(500)    DEFAULT NULL,
  `email_verified_at` TIMESTAMP       NULL DEFAULT NULL,
  `remember_token`    VARCHAR(100)    DEFAULT NULL,
  `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 2: addresses  (direcciones de envío del cliente)
-- ============================================================
CREATE TABLE IF NOT EXISTS `addresses` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED  NOT NULL,
  `full_name`     VARCHAR(150)  NOT NULL,
  `phone`         VARCHAR(20)   DEFAULT NULL,
  `address_line1` VARCHAR(255)  NOT NULL,
  `address_line2` VARCHAR(255)  DEFAULT NULL,
  `city`          VARCHAR(100)  NOT NULL,
  `state`         VARCHAR(100)  DEFAULT NULL,
  `postal_code`   VARCHAR(20)   DEFAULT NULL,
  `country`       VARCHAR(100)  NOT NULL DEFAULT 'Ecuador',
  `is_default`    TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_addresses_user` (`user_id`),
  CONSTRAINT `fk_addresses_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 3: categories  (con soporte de subcategorías)
-- ============================================================
CREATE TABLE IF NOT EXISTS `categories` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `parent_id`   INT UNSIGNED  DEFAULT NULL COMMENT 'NULL = categoría raíz',
  `name`        VARCHAR(100)  NOT NULL,
  `slug`        VARCHAR(120)  NOT NULL,
  `description` TEXT          DEFAULT NULL,
  `image`       VARCHAR(500)  DEFAULT NULL,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order`  INT           NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `fk_categories_parent` (`parent_id`),
  CONSTRAINT `fk_categories_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 4: products
-- ============================================================
CREATE TABLE IF NOT EXISTS `products` (
  `id`                INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `category_id`       INT UNSIGNED   NOT NULL,
  `sku`               VARCHAR(100)   NOT NULL,
  `name`              VARCHAR(255)   NOT NULL,
  `slug`              VARCHAR(280)   NOT NULL,
  `description`       TEXT           DEFAULT NULL,
  `short_description` VARCHAR(500)   DEFAULT NULL,
  `price`             DECIMAL(10,2)  NOT NULL,
  `compare_price`     DECIMAL(10,2)  DEFAULT NULL COMMENT 'Precio tachado (antes costaba)',
  `cost_price`        DECIMAL(10,2)  DEFAULT NULL COMMENT 'Costo interno del producto',
  `weight`            DECIMAL(8,2)   DEFAULT NULL COMMENT 'Peso en kg para calcular envío',
  `status`            ENUM('active','inactive','draft') NOT NULL DEFAULT 'draft',
  `is_featured`       TINYINT(1)     NOT NULL DEFAULT 0,
  `is_digital`        TINYINT(1)     NOT NULL DEFAULT 0 COMMENT '1 = descargable, sin envío físico',
  `tax_rate`          DECIMAL(5,2)   NOT NULL DEFAULT 0.00,
  `views`             INT            NOT NULL DEFAULT 0,
  `created_at`        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_sku`  (`sku`),
  UNIQUE KEY `uq_products_slug` (`slug`),
  KEY `fk_products_category` (`category_id`),
  KEY `idx_products_status`  (`status`),
  CONSTRAINT `fk_products_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 5: product_images
-- ============================================================
CREATE TABLE IF NOT EXISTS `product_images` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED  NOT NULL,
  `image_url`  VARCHAR(500)  NOT NULL,
  `alt_text`   VARCHAR(255)  DEFAULT NULL,
  `is_primary` TINYINT(1)    NOT NULL DEFAULT 0,
  `sort_order` INT           NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_pimages_product` (`product_id`),
  CONSTRAINT `fk_pimages_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 6: product_variants  (talla, color, etc.)
-- ============================================================
CREATE TABLE IF NOT EXISTS `product_variants` (
  `id`              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `product_id`      INT UNSIGNED   NOT NULL,
  `attribute_name`  VARCHAR(100)   NOT NULL COMMENT 'Ej: Color, Talla',
  `attribute_value` VARCHAR(100)   NOT NULL COMMENT 'Ej: Rojo, XL',
  `sku_suffix`      VARCHAR(50)    DEFAULT NULL,
  `price_modifier`  DECIMAL(10,2)  NOT NULL DEFAULT 0.00 COMMENT 'Diferencia vs precio base',
  `is_active`       TINYINT(1)     NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_variants_product` (`product_id`),
  CONSTRAINT `fk_variants_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 7: inventory  (stock por producto / variante)
-- ============================================================
CREATE TABLE IF NOT EXISTS `inventory` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `product_id`        INT UNSIGNED  NOT NULL,
  `variant_id`        INT UNSIGNED  DEFAULT NULL,
  `quantity`          INT           NOT NULL DEFAULT 0,
  `reserved_quantity` INT           NOT NULL DEFAULT 0 COMMENT 'Unidades en pedidos activos',
  `reorder_level`     INT           NOT NULL DEFAULT 5  COMMENT 'Alerta de stock bajo',
  `warehouse_location` VARCHAR(100) DEFAULT NULL,
  `updated_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_inventory_product` (`product_id`),
  KEY `fk_inventory_variant` (`variant_id`),
  CONSTRAINT `fk_inventory_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inventory_variant`
    FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 8: discounts  (cupones y descuentos)
-- ============================================================
CREATE TABLE IF NOT EXISTS `discounts` (
  `id`                INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `code`              VARCHAR(50)    NOT NULL,
  `name`              VARCHAR(100)   NOT NULL,
  `type`              ENUM('percentage','fixed_amount','free_shipping') NOT NULL,
  `value`             DECIMAL(10,2)  NOT NULL,
  `min_order_amount`  DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `max_uses`          INT            DEFAULT NULL COMMENT 'NULL = ilimitado',
  `uses_count`        INT            NOT NULL DEFAULT 0,
  `max_uses_per_user` INT            NOT NULL DEFAULT 1,
  `starts_at`         TIMESTAMP      NULL DEFAULT NULL,
  `expires_at`        TIMESTAMP      NULL DEFAULT NULL,
  `status`            ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_discounts_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 9: carts  (carrito de compras)
-- ============================================================
CREATE TABLE IF NOT EXISTS `carts` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED  DEFAULT NULL COMMENT 'NULL = visitante sin cuenta',
  `session_id` VARCHAR(255)  DEFAULT NULL COMMENT 'Para visitantes',
  `coupon_id`  INT UNSIGNED  DEFAULT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_carts_user`   (`user_id`),
  KEY `fk_carts_coupon` (`coupon_id`),
  CONSTRAINT `fk_carts_user`
    FOREIGN KEY (`user_id`)   REFERENCES `users`     (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_carts_coupon`
    FOREIGN KEY (`coupon_id`) REFERENCES `discounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 10: cart_items  (productos dentro del carrito)
-- ============================================================
CREATE TABLE IF NOT EXISTS `cart_items` (
  `id`         INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `cart_id`    INT UNSIGNED   NOT NULL,
  `product_id` INT UNSIGNED   NOT NULL,
  `variant_id` INT UNSIGNED   DEFAULT NULL,
  `quantity`   INT            NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2)  NOT NULL COMMENT 'Precio al momento de agregar al carrito',
  `added_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_citems_cart`    (`cart_id`),
  KEY `fk_citems_product` (`product_id`),
  KEY `fk_citems_variant` (`variant_id`),
  CONSTRAINT `fk_citems_cart`
    FOREIGN KEY (`cart_id`)    REFERENCES `carts`            (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_citems_product`
    FOREIGN KEY (`product_id`) REFERENCES `products`         (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_citems_variant`
    FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 11: orders  (pedidos)
-- ============================================================
CREATE TABLE IF NOT EXISTS `orders` (
  `id`              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `order_number`    VARCHAR(30)    NOT NULL COMMENT 'Ej: GEO-2024-00001',
  `user_id`         INT UNSIGNED   DEFAULT NULL,
  `address_id`      INT UNSIGNED   DEFAULT NULL,
  `coupon_id`       INT UNSIGNED   DEFAULT NULL,
  `status`          ENUM(
                      'pending',
                      'confirmed',
                      'processing',
                      'shipped',
                      'delivered',
                      'cancelled',
                      'refunded'
                    ) NOT NULL DEFAULT 'pending',
  `subtotal`        DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `shipping_cost`   DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `tax_amount`      DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `total`           DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `notes`           TEXT           DEFAULT NULL COMMENT 'Notas del cliente',
  `admin_notes`     TEXT           DEFAULT NULL COMMENT 'Notas internas del admin',
  `ip_address`      VARCHAR(45)    DEFAULT NULL,
  `created_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_number` (`order_number`),
  KEY `fk_orders_user`    (`user_id`),
  KEY `fk_orders_address` (`address_id`),
  KEY `fk_orders_coupon`  (`coupon_id`),
  KEY `idx_orders_status` (`status`),
  CONSTRAINT `fk_orders_user`
    FOREIGN KEY (`user_id`)    REFERENCES `users`     (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_address`
    FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_coupon`
    FOREIGN KEY (`coupon_id`)  REFERENCES `discounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 12: order_items  (detalle de cada pedido)
-- ============================================================
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `order_id`     INT UNSIGNED   NOT NULL,
  `product_id`   INT UNSIGNED   NOT NULL,
  `variant_id`   INT UNSIGNED   DEFAULT NULL,
  `product_name` VARCHAR(255)   NOT NULL COMMENT 'Snapshot del nombre al momento de compra',
  `product_sku`  VARCHAR(100)   DEFAULT NULL,
  `quantity`     INT            NOT NULL,
  `unit_price`   DECIMAL(10,2)  NOT NULL,
  `total_price`  DECIMAL(10,2)  NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_oitems_order`   (`order_id`),
  KEY `fk_oitems_product` (`product_id`),
  KEY `fk_oitems_variant` (`variant_id`),
  CONSTRAINT `fk_oitems_order`
    FOREIGN KEY (`order_id`)   REFERENCES `orders`           (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oitems_product`
    FOREIGN KEY (`product_id`) REFERENCES `products`         (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_oitems_variant`
    FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 13: order_status_history  (historial de cambios)
-- ============================================================
CREATE TABLE IF NOT EXISTS `order_status_history` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `order_id`   INT UNSIGNED  NOT NULL,
  `status`     VARCHAR(50)   NOT NULL,
  `comment`    TEXT          DEFAULT NULL,
  `created_by` INT UNSIGNED  DEFAULT NULL COMMENT 'Admin o sistema',
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_osh_order`   (`order_id`),
  KEY `fk_osh_creator` (`created_by`),
  CONSTRAINT `fk_osh_order`
    FOREIGN KEY (`order_id`)   REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_osh_creator`
    FOREIGN KEY (`created_by`) REFERENCES `users`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 14: payment_methods_config  (métodos de pago)
-- ============================================================
CREATE TABLE IF NOT EXISTS `payment_methods_config` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(100)  NOT NULL COMMENT 'Ej: Stripe, PayPal, Transferencia Bancaria',
  `type`         ENUM('credit_card','bank_transfer','paypal','cash_on_delivery','other') NOT NULL,
  `provider`     VARCHAR(100)  DEFAULT NULL COMMENT 'Stripe, PayPal, etc.',
  `config`       TEXT          DEFAULT NULL COMMENT 'JSON con API keys (encriptar en la app)',
  `instructions` TEXT          DEFAULT NULL COMMENT 'Instrucciones visibles al cliente',
  `is_active`    TINYINT(1)    NOT NULL DEFAULT 1,
  `sort_order`   INT           NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 15: bank_accounts  (cuentas bancarias para transferencias)
-- ============================================================
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `bank_name`      VARCHAR(100)  NOT NULL,
  `account_name`   VARCHAR(150)  NOT NULL COMMENT 'Nombre del titular',
  `account_number` VARCHAR(50)   NOT NULL,
  `account_type`   ENUM('corriente','ahorros') NOT NULL DEFAULT 'corriente',
  `identification` VARCHAR(20)   DEFAULT NULL COMMENT 'RUC o Cédula del titular',
  `is_active`      TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 16: payments  (registro de cada transacción)
-- ============================================================
CREATE TABLE IF NOT EXISTS `payments` (
  `id`                 INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `order_id`           INT UNSIGNED   NOT NULL,
  `payment_method_id`  INT UNSIGNED   NOT NULL,
  `transaction_id`     VARCHAR(255)   DEFAULT NULL COMMENT 'ID de Stripe / PayPal',
  `amount`             DECIMAL(10,2)  NOT NULL,
  `currency`           VARCHAR(10)    NOT NULL DEFAULT 'USD',
  `status`             ENUM('pending','processing','completed','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  `payment_type`       ENUM('credit_card','bank_transfer','paypal','cash_on_delivery','other') DEFAULT NULL,
  -- Transferencia bancaria
  `bank_account_id`    INT UNSIGNED   DEFAULT NULL COMMENT 'Cuenta bancaria destino',
  `transfer_reference` VARCHAR(100)   DEFAULT NULL COMMENT 'Nro. de comprobante de transferencia',
  `transfer_proof_url` VARCHAR(500)   DEFAULT NULL COMMENT 'Imagen del comprobante subida por el cliente',
  `transfer_verified`  TINYINT(1)     NOT NULL DEFAULT 0 COMMENT '1 = Admin verificó la transferencia',
  -- Tarjeta de crédito / débito
  `card_brand`         VARCHAR(30)    DEFAULT NULL COMMENT 'Visa, Mastercard, AmEx, etc.',
  `card_last4`         CHAR(4)        DEFAULT NULL COMMENT 'Últimos 4 dígitos de la tarjeta',
  -- Metadatos del proveedor
  `metadata`           TEXT           DEFAULT NULL COMMENT 'Respuesta JSON del proveedor de pago',
  `paid_at`            TIMESTAMP      NULL DEFAULT NULL,
  `created_at`         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_payments_order`   (`order_id`),
  KEY `fk_payments_method`  (`payment_method_id`),
  KEY `fk_payments_bank`    (`bank_account_id`),
  KEY `idx_payments_status` (`status`),
  CONSTRAINT `fk_payments_order`
    FOREIGN KEY (`order_id`)          REFERENCES `orders`                 (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_payments_method`
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods_config` (`id`),
  CONSTRAINT `fk_payments_bank`
    FOREIGN KEY (`bank_account_id`)   REFERENCES `bank_accounts`          (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 17: shipping_carriers  (transportistas)
-- ============================================================
CREATE TABLE IF NOT EXISTS `shipping_carriers` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(100)  NOT NULL,
  `tracking_url` VARCHAR(500)  DEFAULT NULL COMMENT 'URL con {tracking_number} como variable',
  `logo`         VARCHAR(500)  DEFAULT NULL,
  `is_active`    TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 18: shipping_zones  (zonas de cobertura)
-- ============================================================
CREATE TABLE IF NOT EXISTS `shipping_zones` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)  NOT NULL,
  `locations`  TEXT          DEFAULT NULL COMMENT 'Lista de ciudades o países (JSON)',
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 19: shipping_rates  (tarifas por zona y transportista)
-- ============================================================
CREATE TABLE IF NOT EXISTS `shipping_rates` (
  `id`                      INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `zone_id`                 INT UNSIGNED   NOT NULL,
  `carrier_id`              INT UNSIGNED   DEFAULT NULL,
  `name`                    VARCHAR(100)   NOT NULL COMMENT 'Ej: Envío Estándar, Express',
  `base_cost`               DECIMAL(10,2)  NOT NULL,
  `cost_per_kg`             DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `min_order_free_shipping` DECIMAL(10,2)  DEFAULT NULL COMMENT 'Monto mínimo para envío gratis',
  `estimated_days_min`      INT            DEFAULT NULL,
  `estimated_days_max`      INT            DEFAULT NULL,
  `is_active`               TINYINT(1)     NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_srates_zone`    (`zone_id`),
  KEY `fk_srates_carrier` (`carrier_id`),
  CONSTRAINT `fk_srates_zone`
    FOREIGN KEY (`zone_id`)    REFERENCES `shipping_zones`    (`id`),
  CONSTRAINT `fk_srates_carrier`
    FOREIGN KEY (`carrier_id`) REFERENCES `shipping_carriers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 20: shipments  (envío asignado a un pedido)
-- ============================================================
CREATE TABLE IF NOT EXISTS `shipments` (
  `id`               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `order_id`         INT UNSIGNED   NOT NULL,
  `carrier_id`       INT UNSIGNED   DEFAULT NULL,
  `shipping_rate_id` INT UNSIGNED   DEFAULT NULL,
  `tracking_number`  VARCHAR(100)   DEFAULT NULL COMMENT 'Número de guía del transportista',
  `status`           ENUM(
                       'preparing',
                       'shipped',
                       'in_transit',
                       'out_for_delivery',
                       'delivered',
                       'failed_attempt',
                       'returned'
                     ) NOT NULL DEFAULT 'preparing',
  `estimated_delivery` DATE         DEFAULT NULL,
  `shipped_at`         TIMESTAMP    NULL DEFAULT NULL,
  `delivered_at`       TIMESTAMP    NULL DEFAULT NULL,
  `shipping_cost`      DECIMAL(10,2) DEFAULT NULL,
  `notes`              TEXT         DEFAULT NULL,
  `created_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_ship_order`   (`order_id`),
  KEY `fk_ship_carrier` (`carrier_id`),
  KEY `fk_ship_rate`    (`shipping_rate_id`),
  KEY `idx_ship_track`  (`tracking_number`),
  CONSTRAINT `fk_ship_order`
    FOREIGN KEY (`order_id`)         REFERENCES `orders`          (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ship_carrier`
    FOREIGN KEY (`carrier_id`)       REFERENCES `shipping_carriers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ship_rate`
    FOREIGN KEY (`shipping_rate_id`) REFERENCES `shipping_rates`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 21: shipment_tracking  (historial visible al cliente)
-- ============================================================
CREATE TABLE IF NOT EXISTS `shipment_tracking` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `shipment_id` INT UNSIGNED  NOT NULL,
  `status`      VARCHAR(100)  NOT NULL,
  `description` TEXT          DEFAULT NULL,
  `location`    VARCHAR(255)  DEFAULT NULL COMMENT 'Ciudad o lugar del evento',
  `occurred_at` TIMESTAMP     NOT NULL,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_stracking_shipment` (`shipment_id`),
  CONSTRAINT `fk_stracking_shipment`
    FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 22: reviews  (reseñas de productos)
-- ============================================================
CREATE TABLE IF NOT EXISTS `reviews` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED  NOT NULL,
  `user_id`    INT UNSIGNED  NOT NULL,
  `order_id`   INT UNSIGNED  DEFAULT NULL COMMENT 'Solo puede reseñar si compró el producto',
  `rating`     TINYINT       NOT NULL DEFAULT 5 COMMENT 'Valor entre 1 y 5',
  `title`      VARCHAR(255)  DEFAULT NULL,
  `comment`    TEXT          DEFAULT NULL,
  `status`     ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_reviews_product` (`product_id`),
  KEY `fk_reviews_user`    (`user_id`),
  KEY `fk_reviews_order`   (`order_id`),
  CONSTRAINT `fk_reviews_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_user`
    FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_order`
    FOREIGN KEY (`order_id`)   REFERENCES `orders`   (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 23: wishlists  (lista de deseos)
-- ============================================================
CREATE TABLE IF NOT EXISTS `wishlists` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED  NOT NULL,
  `product_id` INT UNSIGNED  NOT NULL,
  `added_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wishlist` (`user_id`, `product_id`),
  KEY `fk_wish_product` (`product_id`),
  CONSTRAINT `fk_wish_user`
    FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wish_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 24: notifications  (alertas al cliente y admin)
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED  NOT NULL,
  `type`       VARCHAR(100)  NOT NULL COMMENT 'order_status, payment_confirmed, shipment_update',
  `title`      VARCHAR(255)  NOT NULL,
  `message`    TEXT          DEFAULT NULL,
  `data`       TEXT          DEFAULT NULL COMMENT 'JSON con order_id, shipment_id, etc.',
  `read_at`    TIMESTAMP     NULL DEFAULT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_notif_user` (`user_id`),
  CONSTRAINT `fk_notif_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 25: activity_log  (auditoría del panel admin)
-- ============================================================
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED  DEFAULT NULL,
  `action`     VARCHAR(100)  NOT NULL COMMENT 'create, update, delete, login',
  `model`      VARCHAR(100)  DEFAULT NULL COMMENT 'Tabla afectada',
  `model_id`   INT UNSIGNED  DEFAULT NULL,
  `old_values` TEXT          DEFAULT NULL COMMENT 'JSON con valores anteriores',
  `new_values` TEXT          DEFAULT NULL COMMENT 'JSON con valores nuevos',
  `ip_address` VARCHAR(45)   DEFAULT NULL,
  `user_agent` VARCHAR(500)  DEFAULT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_log_user` (`user_id`),
  CONSTRAINT `fk_log_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 26: settings  (configuración general del sitio)
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `key_name`   VARCHAR(100)  NOT NULL,
  `value`      TEXT          DEFAULT NULL,
  `type`       ENUM('text','number','boolean','json','image') NOT NULL DEFAULT 'text',
  `group_name` VARCHAR(50)   NOT NULL DEFAULT 'general',
  `updated_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VISTAS ÚTILES PARA EL PANEL ADMIN
-- ============================================================

-- Vista: productos con stock disponible
CREATE OR REPLACE VIEW `v_products_stock` AS
SELECT
  p.id,
  p.sku,
  p.name,
  p.price,
  p.status,
  c.name                                                    AS category,
  COALESCE(i.quantity, 0)                                   AS stock_total,
  COALESCE(i.reserved_quantity, 0)                          AS stock_reserved,
  COALESCE(i.quantity, 0) - COALESCE(i.reserved_quantity,0) AS stock_available,
  CASE WHEN COALESCE(i.quantity,0) <= COALESCE(i.reorder_level,5)
       THEN 'LOW' ELSE 'OK' END                             AS stock_alert
FROM `products` p
JOIN `categories` c ON c.id = p.category_id
LEFT JOIN `inventory` i ON i.product_id = p.id AND i.variant_id IS NULL;

-- Vista: resumen de pedidos con pago y envío
CREATE OR REPLACE VIEW `v_orders_summary` AS
SELECT
  o.id,
  o.order_number,
  u.name                AS customer_name,
  u.email               AS customer_email,
  o.status              AS order_status,
  o.total,
  py.status             AS payment_status,
  py.payment_type,
  py.transfer_verified,
  sh.status             AS shipment_status,
  sh.tracking_number,
  o.created_at
FROM `orders` o
LEFT JOIN `users`     u  ON u.id = o.user_id
LEFT JOIN `payments`  py ON py.order_id = o.id
LEFT JOIN `shipments` sh ON sh.order_id = o.id;

-- ============================================================
-- DATOS INICIALES
-- ============================================================

-- USUARIO ADMINISTRADOR
-- Contraseña en texto plano: Admin@Geo2024!
-- (el hash bcrypt debe generarse desde la app, este es solo un placeholder)
INSERT IGNORE INTO `users` (`name`, `email`, `password`, `role`, `status`, `email_verified_at`) VALUES
('Administrador', 'admin@geo-ecomers.com', '$2y$12$PH_HASH_CHANGE_ON_FIRST_LOGIN', 'admin', 'active', NOW());

-- CATEGORÍAS
INSERT IGNORE INTO `categories` (`name`, `slug`, `status`, `sort_order`) VALUES
('Electrónica',      'electronica',      'active', 1),
('Ropa y Moda',      'ropa-moda',        'active', 2),
('Hogar y Jardín',   'hogar-jardin',     'active', 3),
('Deportes',         'deportes',         'active', 4),
('Juguetes y Niños', 'juguetes-ninos',   'active', 5),
('Belleza y Salud',  'belleza-salud',    'active', 6);

-- MÉTODOS DE PAGO
INSERT IGNORE INTO `payment_methods_config` (`name`, `type`, `provider`, `instructions`, `is_active`, `sort_order`) VALUES
('Tarjeta de Crédito / Débito', 'credit_card',      'Stripe',           'Pago seguro con Visa, Mastercard y más. Procesado por Stripe.',                                                    1, 1),
('Transferencia Bancaria',       'bank_transfer',    NULL,               'Realiza la transferencia a una de nuestras cuentas y sube el comprobante para confirmar tu pedido.',               1, 2),
('PayPal',                       'paypal',           'PayPal',           NULL,                                                                                                                0, 3),
('Pago Contra Entrega',          'cash_on_delivery', NULL,               'Paga en efectivo al momento de recibir tu pedido. Solo en zonas habilitadas.',                                     0, 4);

-- CUENTAS BANCARIAS PARA TRANSFERENCIAS
INSERT IGNORE INTO `bank_accounts` (`bank_name`, `account_name`, `account_number`, `account_type`, `identification`, `is_active`) VALUES
('Banco Pichincha',    'Geo Ecomers S.A.', '2200000001', 'corriente', '1792345678001', 1),
('Banco Guayaquil',    'Geo Ecomers S.A.', '0700000001', 'ahorros',   '1792345678001', 1),
('Banco del Pacífico', 'Geo Ecomers S.A.', '3000000001', 'corriente', '1792345678001', 1);

-- TRANSPORTISTAS
INSERT IGNORE INTO `shipping_carriers` (`name`, `tracking_url`, `is_active`) VALUES
('Correos del Ecuador', 'https://www.correos.gob.ec/rastreo/?codigo={tracking_number}',                       1),
('Servientrega',        'https://www.servientrega.com.ec/rastreo/{tracking_number}',                          1),
('Speed',               'https://www.speed.com.ec/rastreo/{tracking_number}',                                 1),
('DHL Express',         'https://www.dhl.com/ec-es/home/tracking.html?tracking-id={tracking_number}',         1);

-- ZONAS DE ENVÍO
INSERT IGNORE INTO `shipping_zones` (`name`, `locations`) VALUES
('Sierra - Principal',    '["Quito","Cuenca","Ambato","Riobamba","Loja","Ibarra","Latacunga"]'),
('Costa - Principal',     '["Guayaquil","Manta","Esmeraldas","Santo Domingo","Portoviejo","Machala"]'),
('Oriente y otras zonas', '["Lago Agrio","Tena","Macas","Zamora","Puyo"]'),
('Internacional',         '["Estados Unidos","España","Colombia","Perú","México"]');

-- TARIFAS DE ENVÍO
INSERT IGNORE INTO `shipping_rates` (`zone_id`, `name`, `base_cost`, `cost_per_kg`, `estimated_days_min`, `estimated_days_max`, `min_order_free_shipping`, `is_active`) VALUES
(1, 'Envío Estándar Sierra',      3.50, 0.50, 2,  5,  50.00, 1),
(1, 'Envío Express Sierra',       6.00, 0.80, 1,  2,  NULL,  1),
(2, 'Envío Estándar Costa',       4.00, 0.50, 3,  7,  60.00, 1),
(2, 'Envío Express Costa',        8.00, 0.80, 1,  3,  NULL,  1),
(3, 'Envío Estándar Oriente',     5.50, 0.60, 4,  8,  NULL,  1),
(4, 'Envío Internacional',       25.00, 2.00, 7, 15,  NULL,  1);

-- CUPONES DE BIENVENIDA
INSERT IGNORE INTO `discounts` (`code`, `name`, `type`, `value`, `min_order_amount`, `max_uses`, `expires_at`, `status`) VALUES
('BIENVENIDO10', 'Descuento de Bienvenida 10%',      'percentage',   10.00, 20.00, 500,  DATE_ADD(NOW(), INTERVAL 1 YEAR),  'active'),
('ENVIOGRATIS',  'Envío Gratis - Primer Pedido',      'free_shipping', 0.00, 30.00, 1000, DATE_ADD(NOW(), INTERVAL 6 MONTH), 'active'),
('GEO5OFF',      'Descuento Fijo $5 en tu pedido',   'fixed_amount',  5.00, 40.00, 200,  DATE_ADD(NOW(), INTERVAL 3 MONTH), 'active');

-- CONFIGURACIÓN GENERAL DEL SITIO
INSERT IGNORE INTO `settings` (`key_name`, `value`, `type`, `group_name`) VALUES
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
('require_verified_review','1',                             'boolean', 'reviews'),
('stripe_public_key',      '',                              'text',    'payments'),
('stripe_secret_key',      '',                              'text',    'payments');

-- ============================================================
-- REACTIVA LAS CLAVES FORÁNEAS
-- ============================================================
SET foreign_key_checks = 1;

-- ============================================================
-- FIN DEL SCRIPT
-- Base: corporat_ecommerce_geo  |  Tablas: 26  |  Vistas: 2
-- ============================================================
