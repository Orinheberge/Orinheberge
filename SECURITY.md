# Politique de Sécurité (Security Policy)

## 🛡️ Engagement de sécurité

Chez **OrinHeberge**, la sécurité de nos infrastructures, de nos plateformes et des données de nos clients est une priorité absolue. Nous encourageons les chercheurs en sécurité et les utilisateurs responsables à nous signaler toute vulnérabilité découverte dans nos systèmes.

Nous nous engageons à traiter chaque rapport avec sérieux, rapidité et transparence, dans le cadre d'une politique de **divulgation responsable**.

---

## 📬 Comment signaler une vulnérabilité

Si vous pensez avoir découvert une faille de sécurité, veuillez nous la signaler **uniquement** par les canaux suivants :

- **Email** : [security@orinheberge.fr](mailto:deepstone@deepstone.fr) *(ou deeepstone@deeepstone.fr avec l'objet "[SÉCURITÉ]" si l'adresse dédiée n'est pas active)*
- **Contenu attendu du rapport** :
  1. Description claire de la vulnérabilité.
  2. Étapes détaillées pour la reproduire (Proof of Concept).
  3. Impact potentiel (ex: accès aux données, élévation de privilèges).
  4. Votre pseudo/nom (pour les crédits, si vous le souhaitez).

> ⚠️ **Ne publiez jamais** les détails d'une vulnérabilité sur des forums publics, GitHub Issues ou les réseaux sociaux avant que nous n'ayons eu le temps de la corriger.

---

## 🔭 Périmètre (In Scope)

Les systèmes suivants sont inclus dans notre programme de divulgation responsable :

- Le site web principal d'OrinHeberge (`*.orinheberge.fr`, `*.deepstone.fr`).
- L'espace client et le système de facturation.
- Le panel d'administration personnalisé.
- Les API publiques et privées développées en interne par OrinHeberge.
- Les intégrations spécifiques entre notre site et le panel Pterodactyl.

---

## 🚫 Hors périmètre (Out of Scope)

Les éléments suivants **ne sont pas** couverts par cette politique :

- Les vulnérabilités dans des logiciels tiers non modifiés (ex : cœur de Pterodactyl, Wings, PHP, MySQL, Linux), sauf si elles sont exploitées via une mauvaise configuration spécifique à OrinHeberge.
- Les attaques par déni de service (DDoS) ou brute-force massif.
- L'ingénierie sociale, le phishing ou les attaques physiques.
- Les vulnérabilités nécessitant un accès physique à nos serveurs.
- Les rapports automatisés de scanners de vulnérabilité sans Proof of Concept (PoC) valide.
- Les problèmes liés à des navigateurs obsolètes ou non supportés.

---

## 📜 Règles d'engagement (Safe Harbor)

Pour bénéficier de notre protection légale ("Safe Harbor"), vous devez respecter les règles suivantes lors de vos tests :

1. **Ne pas perturber le service** : N'effectuez pas de tests de charge, de DDoS ou d'actions pouvant dégrader les performances pour nos clients.
2. **Ne pas accéder aux données d'autrui** : Limitez vos tests à votre propre compte ou à des comptes de test que vous avez créés. Ne lisez, ne modifiez et ne supprimez jamais les données d'autres utilisateurs.
3. **Ne pas exploiter la faille au-delà de la preuve** : Dès que vous avez la preuve de concept, arrêtez les tests et signalez-nous la vulnérabilité.
4. **Confidentialité** : Ne divulguez pas la faille tant qu'elle n'est pas corrigée et que nous ne vous avons pas donné notre accord.

Si vous respectez ces règles, **nous nous engageons à ne pas engager de poursuites légales** à votre encontre pour les tests effectués dans le cadre de cette recherche.

---

## ⏱️ Délais de réponse

Nous nous efforçons de respecter le calendrier suivant :

| Étape | Délai cible |
| :--- | :--- |
| Accusé de réception du rapport | Sous 48 à 72 heures |
| Analyse et qualification de la vulnérabilité | Sous 5 à 7 jours ouvrés |
| Correction et déploiement du correctif | Selon la sévérité (Critical: < 7 jours, High: < 15 jours) |
| Notification au chercheur | Dès le déploiement du correctif |
| Divulgation publique (optionnelle) | Après accord mutuel et déploiement généralisé |

---

## 🏆 Reconnaissance

Nous tenons à remercier sincèrement les chercheurs en sécurité qui nous aident à améliorer OrinHeberge. 

Sauf demande contraire de votre part, nous serons ravis de vous créditer dans notre tableau d'honneur (Hall of Fame) une fois la vulnérabilité corrigée.

*Dernière mise à jour : Juillet 2026*