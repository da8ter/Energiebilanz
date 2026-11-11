#!/bin/zsh
# Spielt die Kachel in einem eigenen Headless-Chrome durch (eigenes Profil, fasst keinen laufenden Browser an).
#   zsh Energiebilanz/tests/tile_test.sh
set -e
D=${0:a:h}; W=$(mktemp -d)
{
  echo '<style>:root{--content-color:#f2f2f7;--accent-color:#00cdab;--card-color:#1c1c1e}</style>'
  cat "$D/../module.html"
  echo '<script>'; cat "$D/tile_probe.js"; echo '</script>'
} > "$W/probe.html"
OUT=$("/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new --user-data-dir=$W/profile --window-size=800,600 --virtual-time-budget=3000 --dump-dom "file://$W/probe.html" 2>/dev/null | sed -n '/<pre id="result">/,/<\/pre>/p')
rm -rf "$W"
echo "$OUT" | sed 's/<[^>]*>//g'
echo "$OUT" | grep -q PASS && ! echo "$OUT" | grep -q FAIL
