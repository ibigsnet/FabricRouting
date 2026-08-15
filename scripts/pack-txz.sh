#!/usr/bin/env bash
# Build FabricRouting-VERSION-x86_64-1.txz (plugin shell only — not FRR .txz packages).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
VERSION="${1:-$(sed -n 's/.*ENTITY version "\([^"]*\)".*/\1/p' fabricrouting.plg | head -1)}"
PKG="FabricRouting-${VERSION}-x86_64-1"
STAGE=$(mktemp -d); trap 'rm -rf "$STAGE"' EXIT
DEST="$STAGE/usr/local/emhttp/plugins/FabricRouting"
mkdir -p "$DEST"/{include,scripts,event,packages}
cp -a "$ROOT/FabricRouting.page" "$ROOT/default.cfg" "$ROOT/README.md" "$DEST/"
cp -a "$ROOT"/include/* "$DEST/include/"
for s in frr-install-packages frr-apply frr-download frr-packages-job frr-force-cleanup; do
  cp -a "$ROOT/scripts/$s" "$DEST/scripts/"
done
cp -a "$ROOT/event/started" "$DEST/event/"
cp -a "$ROOT/packages/manifest.json" "$DEST/packages/"
# do NOT ship packages/dist FRR binaries here
mkdir -p "$ROOT/archive"
OUT="$ROOT/archive/${PKG}.txz"
rm -f "$OUT"
( cd "$STAGE" && tar --owner=0 --group=0 --numeric-owner -cJf "$OUT" . )
ls -la "$OUT"
echo "files: $(tar -tJf "$OUT" | wc -l)"
