#!/usr/bin/env bash
# Vérifie qu'aucun nom interdit n'apparaît dans le plugin (contenu ET noms de fichiers).
# La liste vit HORS du dépôt : $OLI_CLIENT_NAMES_FILE (défaut /workspace/wp-team/client-names.txt),
# une entrée par ligne (littéral ou regex étendue), # = commentaire.
# Correspondance par sous-chaîne, insensible à la casse (pas de \b).
# Échoue (2) si la liste est absente ou vide ; (1) si un terme est trouvé.
# Usage : tools/check-no-client-names.sh [dossier|fichier.zip]
set -uo pipefail
LIST="${OLI_CLIENT_NAMES_FILE:-/workspace/wp-team/client-names.txt}"
TARGET="${1:-$(cd "$(dirname "$0")/.." && pwd)}"
# La liste est en mode 600 : le script doit tourner sous le même utilisateur que son propriétaire.
if [[ -e "$LIST" && ! -r "$LIST" ]]; then
	echo "FAIL: name list not readable by $(id -un) (mode 600, owner $(stat -c %U "$LIST" 2>/dev/null)): $LIST"
	exit 2
fi
if [[ ! -s "$LIST" ]]; then
	echo "FAIL: name list missing or empty: $LIST"
	exit 2
fi
PATTERN="$(grep -vE '^[[:space:]]*(#|$)' "$LIST" | paste -sd'|')"
if [[ -z "$PATTERN" ]]; then
	echo "FAIL: name list has no entries: $LIST"
	exit 2
fi
TMP=""
if [[ -f "$TARGET" && "$TARGET" == *.zip ]]; then
	TMP="$(mktemp -d)"
	unzip -q "$TARGET" -d "$TMP"
	TARGET="$TMP"
fi
HITS="$(grep -rniIE "$PATTERN" "$TARGET" --exclude-dir=.git || true)"
FILES="$(find "$TARGET" -path "*/.git" -prune -o -print | sed "s#^$TARGET##" | grep -iE "$PATTERN" || true)"
[[ -n "$TMP" ]] && rm -rf "$TMP"
if [[ -n "$HITS$FILES" ]]; then
	echo "FAIL: forbidden names found:"
	printf '%s\n%s\n' "$HITS" "$FILES" | sed '/^$/d'
	exit 1
fi
echo "OK: no forbidden names in ${1:-repo} ($(grep -cvE '^[[:space:]]*(#|$)' "$LIST") entries)"
exit 0
