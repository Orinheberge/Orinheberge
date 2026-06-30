#!/bin/bash
# ============================================================
# OrinHeberge — Commandes de gestion BDD (référence rapide)
# ============================================================
# 
# LANCER TOUTES LES MIGRATIONS :
#   cd /var/www/orinheberge && bash db/migrate.sh
#
# CONNEXION MYSQL DIRECTE :
#   mysql -u root -p1504 s43_orinheberge
#
# MIGRATION MANUELLE D'UN FICHIER SPÉCIFIQUE :
#   mysql -u root -p1504 s43_orinheberge < db/migrations/006_create_nodes_eggs_products_extensions.sql
#   mysql -u root -p1504 s43_orinheberge < db/migrations/007_seed_nodes_eggs_products.sql
#
# VÉRIFIER LES MIGRATIONS APPLIQUÉES :
#   mysql -u root -p1504 s43_orinheberge -e "SELECT * FROM migrations_log ORDER BY applied_at;"
#
# LISTER LES TABLES :
#   mysql -u root -p1504 s43_orinheberge -e "SHOW TABLES;"
#
# VOIR LES PRODUITS EN BDD :
#   mysql -u root -p1504 s43_orinheberge -e "SELECT id, slug, name, type, price FROM products ORDER BY sort_order;"
#
# VOIR LES EGGS EN BDD :
#   mysql -u root -p1504 s43_orinheberge -e "SELECT id, name, panel_egg_id, panel_nest_id FROM eggs;"
#
# VOIR LES NODES EN BDD :
#   mysql -u root -p1504 s43_orinheberge -e "SELECT id, name, panel_node_id FROM nodes;"
#
# VOIR LES EXTENSIONS :
#   mysql -u root -p1504 s43_orinheberge -e "SELECT slug, name, is_enabled FROM extensions;"
#
# ACTIVER UNE EXTENSION (ex: stripe) :
#   mysql -u root -p1504 s43_orinheberge -e "UPDATE extensions SET is_enabled=1 WHERE slug='stripe';"
#
# RESET COMPLET (DANGER — supprime tout) :
#   mysql -u root -p1504 -e "DROP DATABASE s43_orinheberge; CREATE DATABASE s43_orinheberge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
#   cd /var/www/orinheberge && bash db/migrate.sh
#
# ============================================================
echo "Référence commandes DB — voir commentaires dans ce fichier."
echo "Pour lancer les migrations : bash db/migrate.sh"
