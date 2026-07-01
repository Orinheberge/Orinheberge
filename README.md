# Orinheberge

Panel d'hebergement avec espace client, espace admin, commandes, facturation et gestion de serveurs Pterodactyl.

## Gestion des serveurs

### Cote admin

- Creer un serveur pour un client depuis une offre active.
- Voir tous les serveurs, leur client, leur prix, leur statut et leur date d'expiration.
- Cliquer sur un serveur pour ouvrir sa fiche detail complete.
- Renommer un serveur cote site, avec tentative de synchronisation du nom sur Pterodactyl.
- Modifier l'expiration et le prix de renouvellement d'un serveur.
- Ajouter du temps avant expiration avec une duree configurable.
- Suspendre un serveur avec une duree indicative et un delai avant suppression definitive.
- Reactiver un serveur suspendu et annuler sa suppression programmee.
- Supprimer un serveur du panel tout en gardant l'historique de commande en base.

### Cote client

- Lister ses serveurs.
- Demarrer, arreter et redemarrer un serveur actif.
- Ouvrir la console et le file manager.
- Voir les ressources et le statut.
- Les actions client sont bloquees quand le serveur est suspendu ou supprime.

## Cycle de vie

La migration `db/migrations/009_server_lifecycle.sql` ajoute les champs de suspension et suppression automatique.
La migration `db/migrations/011_admin_server_management.sql` ajoute les champs de suspension temporaire et de note admin.

## Migration de la base de donnees

Ces commandes ajoutent uniquement les nouvelles colonnes/index. Elles ne suppriment pas les anciennes donnees.

Depuis le dossier du projet sur le serveur Linux :

```bash
cd /var/www/orinheberge
```

Faire une sauvegarde avant migration :

```bash
mysqldump -u root -p s43_orinheberge > backup_s43_orinheberge_$(date +%Y%m%d_%H%M%S).sql
```

Appliquer les nouvelles migrations :

```bash
mysql -u root -p s43_orinheberge < db/migrations/009_server_lifecycle.sql
mysql -u root -p s43_orinheberge < db/migrations/011_admin_server_management.sql
```

Verifier que les colonnes sont bien presentes :

```bash
mysql -u root -p s43_orinheberge -e "SHOW COLUMNS FROM orders LIKE 'suspended_at';"
mysql -u root -p s43_orinheberge -e "SHOW COLUMNS FROM orders LIKE 'suspension_until';"
mysql -u root -p s43_orinheberge -e "SHOW COLUMNS FROM orders LIKE 'admin_note';"
```

Si MySQL refuse `ADD COLUMN IF NOT EXISTS` sur une vieille version, applique manuellement les colonnes manquantes :

```bash
mysql -u root -p s43_orinheberge -e "ALTER TABLE orders ADD COLUMN suspended_at DATETIME DEFAULT NULL AFTER expires_at;"
mysql -u root -p s43_orinheberge -e "ALTER TABLE orders ADD COLUMN delete_after DATETIME DEFAULT NULL AFTER suspended_at;"
mysql -u root -p s43_orinheberge -e "ALTER TABLE orders ADD COLUMN created_by_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER delete_after;"
mysql -u root -p s43_orinheberge -e "ALTER TABLE orders ADD COLUMN admin_note VARCHAR(500) DEFAULT NULL AFTER created_by_admin;"
mysql -u root -p s43_orinheberge -e "ALTER TABLE orders ADD COLUMN suspension_until DATETIME DEFAULT NULL AFTER admin_note;"
```

Cron principal :

```bash
php shop/order/renewal/cron.php reminders
php shop/order/renewal/cron.php urgent
php shop/order/renewal/cron.php suspend
php shop/order/renewal/cron.php unsuspend
php shop/order/renewal/cron.php delete
```
