#!/bin/bash
# ============================================================
# OrinHeberge — Script de migration BDD
# Usage : bash db/migrate.sh
# Exécute toutes les migrations dans l'ordre, en sautant
# celles déjà appliquées (table migrations_log).
# ============================================================

DB_HOST="localhost"
DB_USER="root"
DB_PASS="1504"
DB_NAME="s43_orinheberge"
MIGRATION_DIR="$(cd "$(dirname "$0")/migrations" && pwd)"

MYSQL="mysql -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME"

# Créer la table de log si elle n'existe pas
$MYSQL -e "
CREATE TABLE IF NOT EXISTS \`migrations_log\` (
  \`id\`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  \`filename\`   VARCHAR(255)  NOT NULL,
  \`applied_at\` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (\`id\`),
  UNIQUE KEY \`filename\` (\`filename\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
" 2>/dev/null

echo ""
echo "╔══════════════════════════════════════════╗"
echo "║   OrinHeberge — Database Migrations      ║"
echo "╚══════════════════════════════════════════╝"
echo ""

APPLIED=0
SKIPPED=0
FAILED=0

for FILE in $(ls "$MIGRATION_DIR"/*.sql 2>/dev/null | sort); do
    FILENAME=$(basename "$FILE")

    # Vérifier si déjà appliquée
    EXISTS=$($MYSQL -sN -e "SELECT COUNT(*) FROM migrations_log WHERE filename='$FILENAME';" 2>/dev/null)

    if [ "$EXISTS" -gt 0 ]; then
        echo "  ⏭  SKIP    $FILENAME"
        SKIPPED=$((SKIPPED+1))
        continue
    fi

    # Appliquer la migration
    echo -n "  ⏳ APPLY   $FILENAME ... "
    ERROR=$($MYSQL < "$FILE" 2>&1)

    if [ $? -eq 0 ]; then
        $MYSQL -e "INSERT IGNORE INTO migrations_log (filename) VALUES ('$FILENAME');" 2>/dev/null
        echo "✅ OK"
        APPLIED=$((APPLIED+1))
    else
        echo "❌ ERREUR"
        echo "     → $ERROR"
        FAILED=$((FAILED+1))
    fi
done

echo ""
echo "─────────────────────────────────────────"
echo "  Appliquées : $APPLIED"
echo "  Ignorées   : $SKIPPED"
echo "  Erreurs    : $FAILED"
echo "─────────────────────────────────────────"
echo ""

if [ $FAILED -gt 0 ]; then
    exit 1
fi
