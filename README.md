# Orinheberge

Panel d'hebergement avec espace client, espace admin, commandes, facturation et gestion de serveurs Pterodactyl.

## Gestion des serveurs

### Cote admin

- Creer un serveur pour un client depuis une offre active.
- Voir tous les serveurs, leur client, leur prix, leur statut et leur date d'expiration.
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

Cron principal :

```bash
php shop/order/renewal/cron.php reminders
php shop/order/renewal/cron.php urgent
php shop/order/renewal/cron.php suspend
php shop/order/renewal/cron.php unsuspend
php shop/order/renewal/cron.php delete
```
