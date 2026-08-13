#!/bin/bash
# Run inside Slackware 15 (or compatible) build container with /build mounted.
# Produces: /build/out/libyang-*.txz and /build/out/frr-*.txz
set -euo pipefail
export TERM="${TERM:-linux}"
BUILD_ROOT="${BUILD_ROOT:-/build}"
SRC="$BUILD_ROOT/src"
OUT="$BUILD_ROOT/out"
mkdir -p "$SRC" "$OUT"

# shellcheck disable=SC1091
source "$BUILD_ROOT/scripts/config.env" 2>/dev/null || source /build/config.env

nproc_j="$(nproc 2>/dev/null || echo 4)"
# Leave headroom on lab hosts
if [ "$nproc_j" -gt 16 ]; then nproc_j=16; fi

need_cmd() { command -v "$1" >/dev/null 2>&1 || { echo "missing: $1"; exit 1; }; }
need_cmd gcc
need_cmd make
need_cmd wget
need_cmd tar

# ---- helpers ----
make_txz() {
  # make_txz NAME VERSION ARCH BUILD stagedir
  local name="$1" ver="$2" arch="$3" build="$4" stage="$5"
  local pkg="${name}-${ver}-${arch}-${build}.txz"
  (
    cd "$stage"
    # Slackware makepkg if present
    if command -v makepkg >/dev/null 2>&1; then
      makepkg -l y -c n "$OUT/$pkg"
    else
      # Fallback: tar + xz (installpkg accepts .txz)
      tar -cJf "$OUT/$pkg" .
    fi
  )
  echo "Built $OUT/$pkg"
  sha256sum "$OUT/$pkg" | tee -a "$OUT/SHA256SUMS"
}

# ---- libyang ----
build_libyang() {
  local ver="$LIBYANG_VERSION"
  local tarball="libyang-${ver}.tar.gz"
  local url="https://github.com/CESNET/libyang/archive/refs/tags/v${ver}.tar.gz"
  cd "$SRC"
  if [ ! -f "$tarball" ]; then
    wget --no-check-certificate -O "$tarball" "$url"
  fi
  rm -rf "libyang-${ver}"
  tar -xzf "$tarball"
  # tarball extracts to libyang-VER
  cd "libyang-${ver}" || cd "libyang-${ver}"*
  mkdir -p build && cd build
  cmake .. \
    -DCMAKE_INSTALL_PREFIX=/usr \
    -DCMAKE_BUILD_TYPE=Release \
    -DENABLE_LYD_PRIV=ON \
    -DGEN_LANGUAGE_BINDINGS=OFF
  make -j"$nproc_j"
  local stage="$SRC/stage-libyang"
  rm -rf "$stage"
  mkdir -p "$stage"
  make DESTDIR="$stage" install
  mkdir -p "$stage/install"
  cat > "$stage/install/slack-desc" <<EOF
libyang: libyang (YANG data modeling library)
libyang:
libyang: libyang is a YANG data modelling language parser and toolkit.
libyang: Required by FRRouting.
libyang:
EOF
  make_txz libyang "$ver" "$ARCH" "$PACKAGE_SUFFIX" "$stage"
  # Install into build env for FRR link
  installpkg "$OUT/libyang-${ver}-${ARCH}-${PACKAGE_SUFFIX}.txz" || \
    (cd "$stage" && cp -a . /)
}

# ---- frr ----
build_frr() {
  local ver="$FRR_VERSION"
  local tag="$FRR_TAG"
  local tarball="frr-${ver}.tar.gz"
  local url="https://github.com/FRRouting/frr/archive/refs/tags/${tag}.tar.gz"
  cd "$SRC"
  if [ ! -f "$tarball" ]; then
    wget --no-check-certificate -O "$tarball" "$url"
  fi
  rm -rf "frr-${tag}" "frr-frr-${ver}" "frr-${ver}"
  tar -xzf "$tarball"
  local dir
  dir="$(find "$SRC" -maxdepth 1 -type d -name 'frr*' | head -1)"
  cd "$dir"
  # bootstrap if needed
  if [ -x ./bootstrap.sh ]; then
    ./bootstrap.sh
  elif [ ! -f ./configure ]; then
    autoreconf -i
  fi

  # Group/user: Unraid typically runs as root for lab packages
  ./configure \
    --prefix=/usr \
    --sysconfdir=/etc \
    --localstatedir=/var \
    --enable-user=root \
    --enable-group=root \
    --enable-vty-group=root \
    --enable-multipath=64 \
    --enable-shared \
    --enable-fpm \
    --enable-fabricd \
    --enable-staticd \
    --enable-bgpd \
    --enable-ospfd \
    --enable-ospf6d \
    --enable-isisd \
    --enable-bfdd \
    --disable-doc \
    --disable-protobuf \
    --disable-rpki \
    || ./configure \
      --prefix=/usr \
      --sysconfdir=/etc \
      --localstatedir=/var \
      --enable-user=root \
      --enable-group=root \
      --enable-multipath=64 \
      --enable-fabricd \
      --enable-staticd \
      --disable-doc \
      --disable-protobuf

  make -j"$nproc_j"
  local stage="$SRC/stage-frr"
  rm -rf "$stage"
  mkdir -p "$stage"
  make DESTDIR="$stage" install
  mkdir -p "$stage/etc/frr" "$stage/install"
  # Minimal daemons file — plugin rewrites this
  cat > "$stage/etc/frr/daemons" <<'EOF'
zebra=yes
bgpd=no
ospfd=no
ospf6d=no
ripd=no
ripngd=no
isisd=no
fabricd=yes
staticd=yes
bfdd=no
vtysh_enable=yes
zebra_options="  -A 127.0.0.1 -s 90000000"
fabricd_options="  -A 127.0.0.1"
staticd_options="  -A 127.0.0.1"
EOF
  cat > "$stage/install/slack-desc" <<EOF
frr: frr (FRRouting suite)
frr:
frr: FRRouting is a free routing protocol suite for Linux.
frr: Unraid package with fabricd (OpenFabric) enabled.
frr: Managed by FabricRouting / Fabric Routing plugin.
frr:
EOF
  # doinst: do not enable ip_forward or touch network.cfg
  cat > "$stage/install/doinst.sh" <<'EOF'
#!/bin/sh
# FabricRouting-managed package — no automatic sysctl or network.cfg changes.
if [ ! -e etc/frr/frr.conf ]; then
  mkdir -p etc/frr
  cat > etc/frr/frr.conf <<'CONF'
! FabricRouting baseline — policy comes from Fabric Routing / Thunderbolt Net
frr defaults traditional
hostname unraid
log syslog informational
service integrated-vtysh-config
CONF
fi
EOF
  chmod 755 "$stage/install/doinst.sh"
  make_txz frr "$ver" "$ARCH" "$PACKAGE_SUFFIX" "$stage"
}

echo "=== building libyang ${LIBYANG_VERSION} ==="
build_libyang
echo "=== building frr ${FRR_VERSION} ==="
build_frr
echo "=== done ==="
ls -la "$OUT"
cat "$OUT/SHA256SUMS" 2>/dev/null || true
