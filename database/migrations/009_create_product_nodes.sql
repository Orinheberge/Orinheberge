-- Migration 009 : Table pivot product_nodes (produit → plusieurs nodes possibles)
-- Permet à l'admin de choisir sur quels nodes une offre est disponible,
-- et au client de choisir son node lors de la commande.

CREATE TABLE IF NOT EXISTS `product_nodes` (
  `product_id` INT UNSIGNED NOT NULL,
  `node_id`    INT UNSIGNED NOT NULL,
  PRIMARY KEY (`product_id`, `node_id`),
  CONSTRAINT `fk_pn_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pn_node`    FOREIGN KEY (`node_id`)    REFERENCES `nodes`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Peupler la table depuis les liaisons existantes (node_id du produit)
INSERT IGNORE INTO `product_nodes` (product_id, node_id)
SELECT id, node_id FROM `products` WHERE node_id IS NOT NULL AND node_id > 0;

-- Ajouter colonne chosen_node_id sur orders pour tracer le node choisi
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `chosen_node_id` INT UNSIGNED DEFAULT NULL AFTER `product_id`;
