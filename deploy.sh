#!/bin/bash
# ===========================================
# Script de déploiement automatique — Orinheberge
# Déploie sur : root@5.48.143.126
# Chemin distant : /var/www/html
# ===========================================

SERVER_IP="5.48.143.126"
SERVER_USER="root"
SERVER_PASS="1504"
REMOTE_PATH="/var/www/html"
LOCAL_PATH="$(git rev-parse --show-toplevel)"

echo ""
echo "🚀 Déploiement vers $SERVER_USER@$SERVER_IP:$REMOTE_PATH ..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# ── Vérifier que sshpass est installé ─────────────────────────────────────────
if ! command -v sshpass &> /dev/null; then
    echo "❌ sshpass n'est pas installé."
    echo "   Git Bash / WSL  : sudo apt install sshpass"
    echo "   macOS           : brew install sshpass"
    exit 1
fi

# ── Sync des fichiers via rsync ────────────────────────────────────────────────
sshpass -p "$SERVER_PASS" rsync -avz --delete \
    --exclude='.git/' \
    --exclude='.env' \
    --exclude='node_modules/' \
    --exclude='*.log' \
    --exclude='inc/uploads/avatars/*.webp' \
    --exclude='inc/uploads/avatars/*.png' \
    --exclude='inc/uploads/avatars/*.jpg' \
    -e "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10" \
    "$LOCAL_PATH/" \
    "$SERVER_USER@$SERVER_IP:$REMOTE_PATH/"

if [ $? -ne 0 ]; then
    echo "❌ Échec du rsync."
    exit 1
fi

# ── Composer install sur le serveur ───────────────────────────────────────────
echo ""
echo "📦 Lancement de composer install sur le serveur..."

sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no "$SERVER_USER@$SERVER_IP" \
    "cd $REMOTE_PATH && composer install --no-dev --optimize-autoloader --no-interaction 2>&1"

if [ $? -ne 0 ]; then
    echo "⚠️  composer install a échoué (composer installé sur le serveur ?)"
fi

# ── Correction des permissions ─────────────────────────────────────────────────
echo ""
echo "🔒 Correction des permissions..."

sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no "$SERVER_USER@$SERVER_IP" \
    "chown -R www-data:www-data $REMOTE_PATH && chmod -R 755 $REMOTE_PATH && chmod -R 775 $REMOTE_PATH/inc/uploads/"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Déploiement terminé !"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
