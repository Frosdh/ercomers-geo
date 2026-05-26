-- ============================================================
--  Actualizar contraseña del administrador
--  Contraseña: Admin@Geo2024!
--  Ejecutar en phpMyAdmin dentro de corporat_ecommerce_geo
-- ============================================================

UPDATE `users`
SET `password` = '$2b$12$u5krueFIwUZwpm11pB8bcO09vDy53LPfm5/Yp4c1PYztiDNb/Yp6q'
WHERE `email` = 'admin@geo-ecomers.com'
  AND `role`  = 'admin'
LIMIT 1;
