<?php
/**
 * FabricRouting — shared helpers. Standalone: never require ThunderboltNet.
 */

function frr_plugin_name() {
  return 'FabricRouting';
}

function frr_cfg_dir() {
  return '/boot/config/plugins/FabricRouting';
}

function frr_cfg_path() {
  return frr_cfg_dir() . '/FabricRouting.cfg';
}

function frr_packages_dir() {
  return frr_cfg_dir() . '/packages';
}

function frr_companion_path() {
  return frr_cfg_dir() . '/companion.json';
}

function frr_log_path() {
  return '/var/log/fabricrouting.log';
}

/**
 * Official package catalog URL (GitHub raw manifest).
 */
function frr_default_catalog_url() {
  return 'https://raw.githubusercontent.com/ibigsnet/FabricRouting/main/packages/manifest.json';
}

function frr_load_cfg() {
  $defaults = [
    // Array start: rehydrate packages already on flash into RAM (NO network).
    'install_on_start' => 'yes',
    // Settings → Apply: catalog + package download only when Yes.
    // Default No = no surprise multi‑MB network fetch; first time set Yes once.
    'auto_download' => 'no',
    'package_channel' => 'latest', // latest | previous
    'package_base_url' => '', // empty = frr_default_catalog_url()
    'enable_zebra' => 'yes',
    'enable_fabricd' => 'yes',
    'enable_bgpd' => 'no',
    'enable_ospfd' => 'no',
    'enable_ospf6d' => 'no',
    'enable_isisd' => 'no',
    'enable_bfdd' => 'no',
    'enable_staticd' => 'yes',
    'start_frr' => 'yes',
  ];
  $cfg = [];
  if (function_exists('parse_plugin_cfg')) {
    $parsed = parse_plugin_cfg(frr_plugin_name());
    if (is_array($parsed)) {
      $cfg = $parsed;
    }
  } elseif (is_readable(frr_cfg_path())) {
    foreach (file(frr_cfg_path(), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
      $line = trim($line);
      if ($line === '' || $line[0] === ';' || $line[0] === '#') {
        continue;
      }
      if (preg_match('/^([A-Za-z0-9_]+)="([^"]*)"/', $line, $m)) {
        $cfg[$m[1]] = $m[2];
      }
    }
  }
  return array_merge($defaults, $cfg);
}

function frr_log($msg) {
  $line = date('c') . ' ' . $msg . "\n";
  @file_put_contents(frr_log_path(), $line, FILE_APPEND);
  @file_put_contents(frr_cfg_dir() . '/fabricrouting.log', $line, FILE_APPEND);
}

/**
 * Progress-frame / CLI line (Unraid update.php target=progressFrame).
 * Progress-frame line — leave the Unraid popup open until finished.
 */
function frr_progress($msg) {
  $msg = (string)$msg;
  frr_log($msg);
  // Plain text lines show in the Unraid progress iframe
  echo htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
  if (function_exists('ob_flush')) {
    @ob_flush();
  }
  @flush();
}

/**
 * Single-flight lock for Apply / download (avoid two concurrent package installs).
 * @return array{ok:bool,error?:string,path?:string}
 */
function frr_apply_lock($acquire = true) {
  $dir = frr_cfg_dir();
  @mkdir($dir, 0755, true);
  $path = $dir . '/apply.lock';
  if (!$acquire) {
    @unlink($path);
    return ['ok' => true, 'path' => $path];
  }
  if (is_readable($path)) {
    $raw = trim((string)@file_get_contents($path));
    $pid = (int)$raw;
    $age = time() - (int)@filemtime($path);
    // Stale lock > 30 min or dead pid
    if ($age < 1800 && $pid > 0 && @file_exists('/proc/' . $pid)) {
      return [
        'ok' => false,
        'error' => 'Another Fabric Routing Apply/download is already running (pid ' . $pid . '). Wait for it to finish — do not start a second one.',
        'path' => $path,
      ];
    }
  }
  @file_put_contents($path, (string)getmypid() . "\n");
  return ['ok' => true, 'path' => $path];
}

/**
 * Detect live FRR tools (works whether installed by us or not).
 */
function frr_detect() {
  $out = [
    'vtysh' => '',
    'fabricd' => '',
    'zebra' => '',
    'version' => '',
    'present' => false,
    'running_zebra' => false,
    'running_fabricd' => false,
    'packages_on_flash' => [],
    'packages_dir' => frr_packages_dir(),
    'note' => '',
  ];

  foreach (['/usr/bin/vtysh', '/usr/sbin/vtysh'] as $p) {
    if (is_executable($p)) {
      $out['vtysh'] = $p;
      break;
    }
  }
  if ($out['vtysh'] === '') {
    $w = trim((string)@shell_exec('command -v vtysh 2>/dev/null'));
    if ($w !== '' && is_executable($w)) {
      $out['vtysh'] = $w;
    }
  }

  foreach (['/usr/lib/frr/fabricd', '/usr/sbin/fabricd', '/usr/bin/fabricd'] as $p) {
    if (is_executable($p)) {
      $out['fabricd'] = $p;
      break;
    }
  }
  foreach (['/usr/lib/frr/zebra', '/usr/sbin/zebra', '/usr/bin/zebra'] as $p) {
    if (is_executable($p)) {
      $out['zebra'] = $p;
      break;
    }
  }

  $out['present'] = ($out['vtysh'] !== '' || $out['fabricd'] !== '' || $out['zebra'] !== '');
  if ($out['vtysh'] !== '') {
    $out['version'] = trim((string)@shell_exec(escapeshellarg($out['vtysh']) . ' -v 2>/dev/null | head -1'));
  }

  $out['running_zebra'] = trim((string)@shell_exec('pgrep -x zebra 2>/dev/null')) !== '';
  $out['running_fabricd'] = trim((string)@shell_exec('pgrep -x fabricd 2>/dev/null')) !== '';

  $dir = frr_packages_dir();
  if (is_dir($dir)) {
    foreach (@scandir($dir) ?: [] as $f) {
      if (preg_match('/\.(txz|tgz|tar\.gz)$/i', $f)) {
        $out['packages_on_flash'][] = $f;
      }
    }
    sort($out['packages_on_flash']);
  }

  $out['unraid_version'] = frr_unraid_version();
  $out['arch'] = frr_arch();
  $out['bundle'] = frr_resolve_bundle();

  if ($out['present']) {
    $out['note'] = 'FRR tools present on this host';
  } elseif ($out['packages_on_flash']) {
    $out['note'] = 'Packages cached on flash but not live — Apply or reboot to install into RAM';
  } elseif (!empty($out['bundle']['ok'])) {
    $out['note'] = 'Ready to auto-download FRR ' . ($out['bundle']['frr_version'] ?? '') . ' for Unraid ' . $out['unraid_version'];
  } else {
    $out['note'] = $out['bundle']['error']
      ?? 'No automated package set for this Unraid build yet — plugin stays idle (no manual package drop required)';
  }

  return $out;
}

function frr_unraid_version() {
  $v = '';
  if (is_readable('/etc/unraid-version')) {
    $raw = (string)@file_get_contents('/etc/unraid-version');
    // version="7.0.0" or similar
    if (preg_match('/version="([^"]+)"/', $raw, $m)) {
      $v = $m[1];
    } elseif (preg_match('/(\d+\.\d+(?:\.\d+)?)/', $raw, $m)) {
      $v = $m[1];
    }
  }
  if ($v === '' && is_readable('/etc/slackware-version')) {
    // fallback only for lab; not ideal
    $v = trim((string)@file_get_contents('/etc/slackware-version'));
  }
  return $v !== '' ? $v : 'unknown';
}

function frr_arch() {
  $m = trim((string)@shell_exec('uname -m 2>/dev/null'));
  if ($m === 'amd64' || $m === 'x86_64') {
    return 'x86_64';
  }
  if ($m === 'aarch64' || $m === 'arm64') {
    return 'aarch64';
  }
  return $m !== '' ? $m : 'x86_64';
}

function frr_catalog_url(array $cfg = null) {
  if ($cfg === null) {
    $cfg = frr_load_cfg();
  }
  $u = trim((string)($cfg['package_base_url'] ?? ''));
  if ($u === '') {
    return frr_default_catalog_url();
  }
  // Allow base dir or full manifest URL
  if (substr($u, -5) === '.json') {
    return $u;
  }
  return rtrim($u, '/') . '/manifest.json';
}

/**
 * Fetch package catalog (cached briefly on flash).
 *
 * Empty-bundle catalogs are NOT kept for the full TTL — otherwise a cache written
 * before packages shipped shows "No match" for up to an hour after the real catalog updates.
 */
function frr_fetch_manifest($force = false) {
  $cfg = frr_load_cfg();
  $url = frr_catalog_url($cfg);
  $cache = frr_cfg_dir() . '/manifest.cache.json';
  $ttl = 3600;
  if (!$force && is_readable($cache) && (time() - filemtime($cache) < $ttl)) {
    $j = json_decode((string)@file_get_contents($cache), true);
    if (is_array($j)) {
      $bundles = $j['bundles'] ?? [];
      // Re-fetch soon if catalog was empty (or invalid) when cached
      if (is_array($bundles) && count($bundles) > 0) {
        return ['ok' => true, 'manifest' => $j, 'source' => 'cache', 'url' => $url];
      }
      // empty bundles: only trust cache for 2 minutes
      if ((time() - filemtime($cache) < 120)) {
        return ['ok' => true, 'manifest' => $j, 'source' => 'cache-empty', 'url' => $url];
      }
    }
  }
  // Prefer plugin-tree copy when offline / first install
  $local = dirname(__DIR__) . '/packages/manifest.json';
  $installed = '/usr/local/emhttp/plugins/FabricRouting/packages/manifest.json';

  $body = frr_http_get($url);
  $source = 'download';
  if ($body === null || $body === '') {
    foreach ([$installed, $local] as $p) {
      if (is_readable($p)) {
        $body = (string)@file_get_contents($p);
        $url = $p;
        $source = 'local';
        break;
      }
    }
  }
  if ($body === null || $body === '') {
    return ['ok' => false, 'error' => 'could not fetch package catalog', 'url' => $url];
  }
  $j = json_decode($body, true);
  if (!is_array($j)) {
    return ['ok' => false, 'error' => 'invalid catalog JSON', 'url' => $url];
  }
  @mkdir(frr_cfg_dir(), 0755, true);
  @file_put_contents($cache, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
  return ['ok' => true, 'manifest' => $j, 'source' => $source, 'url' => $url];
}

function frr_http_get($url, $timeout = 60) {
  if (!preg_match('#^https?://#i', $url) && is_readable($url)) {
    return (string)@file_get_contents($url);
  }
  if (!preg_match('#^https://#i', $url)) {
    frr_log('refuse non-https URL: ' . $url);
    return null;
  }
  $ctx = stream_context_create([
    'http' => [
      'timeout' => $timeout,
      'header' => "User-Agent: FabricRouting/1.0\r\n",
      'follow_location' => 1,
    ],
    'ssl' => [
      'verify_peer' => true,
      'verify_peer_name' => true,
    ],
  ]);
  $body = @file_get_contents($url, false, $ctx);
  if ($body === false || $body === '') {
    // curl fallback
    $tmp = tempnam('/tmp', 'frrget');
    $cmd = 'curl -fsSL --max-time ' . (int)$timeout . ' -o ' . escapeshellarg($tmp) . ' ' . escapeshellarg($url) . ' 2>/dev/null';
    @exec($cmd, $o, $rc);
    if ($rc === 0 && is_readable($tmp)) {
      $body = (string)@file_get_contents($tmp);
      @unlink($tmp);
      return $body;
    }
    @unlink($tmp);
    return null;
  }
  return $body;
}

function frr_version_compare_loose($a, $b) {
  // Strip non-numeric suffix junk for Unraid strings
  $na = preg_replace('/[^0-9.].*$/', '', (string)$a);
  $nb = preg_replace('/[^0-9.].*$/', '', (string)$b);
  return version_compare($na, $nb);
}

/**
 * Resolve automated package bundle for this host + channel.
 *
 * @return array{ok:bool,error?:string,channel?:string,frr_version?:string,packages?:array,id?:string}
 */
function frr_resolve_bundle(array $cfg = null) {
  if ($cfg === null) {
    $cfg = frr_load_cfg();
  }
  $mf = frr_fetch_manifest(false);
  if (empty($mf['ok'])) {
    return ['ok' => false, 'error' => $mf['error'] ?? 'catalog unavailable'];
  }
  $manifest = $mf['manifest'];
  $channel = trim((string)($cfg['package_channel'] ?? 'latest'));
  if ($channel === '') {
    $channel = $manifest['default_channel'] ?? 'latest';
  }
  $arch = frr_arch();
  $uver = frr_unraid_version();
  $bundles = $manifest['bundles'] ?? [];
  if (!is_array($bundles) || !$bundles) {
    return [
      'ok' => false,
      'error' => 'No automated FRR builds published yet for any Unraid version (catalog empty). Maintainer will add bundles — no manual package drop required from you.',
      'channel' => $channel,
      'unraid_version' => $uver,
      'arch' => $arch,
    ];
  }

  $candidates = [];
  foreach ($bundles as $b) {
    if (!is_array($b)) {
      continue;
    }
    if (($b['channel'] ?? 'latest') !== $channel) {
      continue;
    }
    if (($b['arch'] ?? 'x86_64') !== $arch) {
      continue;
    }
    $min = (string)($b['unraid_min'] ?? '0');
    $max = (string)($b['unraid_max'] ?? '999.99.99');
    if ($uver !== 'unknown') {
      if (frr_version_compare_loose($uver, $min) < 0) {
        continue;
      }
      if (frr_version_compare_loose($uver, $max) > 0) {
        continue;
      }
    }
    $pkgs = $b['packages'] ?? [];
    if (!is_array($pkgs) || !$pkgs) {
      continue;
    }
    $candidates[] = $b;
  }

  if (!$candidates) {
    return [
      'ok' => false,
      'error' => 'No automated package set for Unraid ' . $uver . ' / ' . $arch . ' (channel=' . $channel . '). Catalog will be updated when a build is ready — no manual package steps.',
      'channel' => $channel,
      'unraid_version' => $uver,
      'arch' => $arch,
    ];
  }

  usort($candidates, function ($a, $b) {
    return frr_version_compare_loose($b['frr_version'] ?? '0', $a['frr_version'] ?? '0');
  });
  $best = $candidates[0];
  $id = ($best['channel'] ?? 'latest') . '|' . ($best['frr_version'] ?? '') . '|' . ($best['arch'] ?? '') . '|' . ($best['unraid_min'] ?? '');
  return [
    'ok' => true,
    'channel' => $channel,
    'frr_version' => $best['frr_version'] ?? '',
    'packages' => $best['packages'],
    'id' => $id,
    'unraid_version' => $uver,
    'arch' => $arch,
    'label' => $best['label'] ?? '',
  ];
}

/**
 * Download resolved bundle into flash packages cache (automated).
 */
function frr_download_bundle($force = false) {
  $cfg = frr_load_cfg();
  // Always re-check live catalog on download (avoid stale empty cache)
  $mf = frr_fetch_manifest(true);
  if (empty($mf['ok'])) {
    frr_progress('Catalog fetch failed: ' . ($mf['error'] ?? 'unknown'));
  } else {
    frr_progress('Catalog: ' . ($mf['source'] ?? '?') . ' (' . ($mf['url'] ?? '') . ')');
  }
  $bundle = frr_resolve_bundle($cfg);
  if (empty($bundle['ok'])) {
    frr_progress('Download skip: ' . ($bundle['error'] ?? 'no bundle'));
    return $bundle + ['downloaded' => []];
  }

  frr_progress('Bundle match: FRR ' . ($bundle['frr_version'] ?? '') . ' for Unraid '
    . ($bundle['unraid_version'] ?? '') . ' / ' . ($bundle['arch'] ?? ''));

  $dir = frr_packages_dir();
  @mkdir($dir, 0755, true);
  $id_file = $dir . '/.bundle-id';
  $cur = is_readable($id_file) ? trim((string)@file_get_contents($id_file)) : '';
  $want = $bundle['id'] ?? '';

  $downloaded = [];
  $errors = [];
  $order = [];
  $n = count($bundle['packages'] ?? []);
  $i = 0;

  foreach ($bundle['packages'] as $pkg) {
    if (!is_array($pkg)) {
      continue;
    }
    $i++;
    $file = basename((string)($pkg['file'] ?? ''));
    $url = trim((string)($pkg['url'] ?? ''));
    $sha = strtolower(trim((string)($pkg['sha256'] ?? '')));
    if ($file === '' || $url === '') {
      $errors[] = 'package entry missing file/url';
      continue;
    }
    if (!preg_match('#^https://#i', $url)) {
      $errors[] = 'refusing non-https package URL for ' . $file;
      continue;
    }
    $dest = $dir . '/' . $file;
    $order[] = $file;
    $need = $force || $cur !== $want || !is_readable($dest);
    if (!$need && $sha !== '') {
      $have = hash_file('sha256', $dest);
      if (strtolower((string)$have) !== $sha) {
        $need = true;
      }
    }
    if (!$need) {
      frr_progress("[$i/$n] Cached (ok): $file");
      $downloaded[] = ['file' => $file, 'status' => 'cached'];
      continue;
    }
    frr_progress("[$i/$n] Downloading: $file …");
    frr_progress('  URL: ' . $url);
    $body = frr_http_get($url, 600);
    if ($body === null || $body === '') {
      $errors[] = 'download failed: ' . $file;
      frr_progress("  FAILED download: $file");
      continue;
    }
    if (@file_put_contents($dest, $body) === false) {
      $errors[] = 'write failed: ' . $file;
      frr_progress("  FAILED write: $file");
      continue;
    }
    $bytes = strlen($body);
    if ($sha !== '') {
      $have = hash_file('sha256', $dest);
      if (strtolower((string)$have) !== $sha) {
        @unlink($dest);
        $errors[] = 'sha256 mismatch: ' . $file;
        frr_progress("  FAILED sha256: $file");
        continue;
      }
      frr_progress('  sha256 ok · ' . frr_format_bytes($bytes));
    } else {
      frr_progress('  saved · ' . frr_format_bytes($bytes));
    }
    $downloaded[] = ['file' => $file, 'status' => 'downloaded', 'bytes' => $bytes];
  }

  if ($order) {
    $lines = ["# Generated by FabricRouting — do not edit; install order", ''];
    foreach ($order as $f) {
      $lines[] = $f;
    }
    @file_put_contents($dir . '/MANIFEST.txt', implode("\n", $lines) . "\n");
  }
  if (!$errors) {
    @file_put_contents($id_file, $want . "\n");
  }

  return [
    'ok' => empty($errors),
    'bundle' => $bundle,
    'downloaded' => $downloaded,
    'errors' => $errors,
    'error' => $errors ? implode('; ', $errors) : null,
  ];
}

/**
 * Write companion marker for other plugins (e.g. Thunderbolt Net UI).
 */
function frr_write_companion_marker() {
  $ver = '';
  $plg = '/var/log/plugins/FabricRouting'; // not always present
  $cfg = frr_load_cfg();
  $det = frr_detect();
  $payload = [
    'plugin' => 'FabricRouting',
    'provides' => ['frr', 'zebra', 'fabricd'],
    'version' => $det['version'] !== '' ? $det['version'] : 'unknown',
    'present' => $det['present'],
    'fabricd_enabled_cfg' => ($cfg['enable_fabricd'] ?? 'yes') === 'yes',
    'updated' => date('c'),
  ];
  $dir = frr_cfg_dir();
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  return @file_put_contents(frr_companion_path(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n") !== false;
}

/**
 * Ensure a minimal frr.conf exists that does NOT enroll eth/br interfaces.
 * Never deletes ThunderboltNet marked blocks. Never enables ip_forward.
 */
function frr_ensure_baseline_conf() {
  $path = '/etc/frr/frr.conf';
  $dir = dirname($path);
  if (!is_dir($dir)) {
    // Package not installed yet
    return ['ok' => false, 'error' => 'no /etc/frr'];
  }

  $begin_tbn = '! BEGIN ThunderboltNet OpenFabric';
  $baseline = <<<'CONF'
! FabricRouting baseline — host routing suite; no eth/br interfaces enrolled.
! Thunderbolt Net may append a marked OpenFabric block for thunderbolt* + lo only.
! Do not add br0/eth0 here unless you intend LAN into a routing protocol.
frr defaults traditional
!
hostname unraid
!
! zebra runs; protocols need explicit interface stanzas elsewhere (or TBN markers)
!
CONF;

  if (!is_file($path) || filesize($path) === 0) {
    $ok = @file_put_contents($path, $baseline) !== false;
    frr_log($ok ? 'wrote baseline frr.conf' : 'failed baseline frr.conf');
    return ['ok' => $ok, 'created' => true];
  }

  $raw = (string)@file_get_contents($path);
  // Refuse to "fix" confs that already have content; only ensure header comment once
  if (strpos($raw, 'FabricRouting baseline') === false && strpos($raw, $begin_tbn) === false) {
    // Prepend safety banner without destroying user/TBN config
    $banner = "! FabricRouting: existing frr.conf preserved. Do not add br0/eth0 to protocols unless intended.\n";
    @file_put_contents($path, $banner . $raw);
    return ['ok' => true, 'banner' => true];
  }
  return ['ok' => true, 'exists' => true];
}

/**
 * Best-effort patch /etc/frr/daemons for selected daemons.
 */
function frr_apply_daemons_file(array $cfg = null) {
  if ($cfg === null) {
    $cfg = frr_load_cfg();
  }
  $dae = '/etc/frr/daemons';
  if (!is_readable($dae)) {
    return ['ok' => false, 'error' => 'no ' . $dae . ' (FRR package not installed?)'];
  }
  $raw = (string)@file_get_contents($dae);
  $map = [
    'zebra' => ($cfg['enable_zebra'] ?? 'yes') === 'yes' ? 'yes' : 'no',
    'fabricd' => ($cfg['enable_fabricd'] ?? 'yes') === 'yes' ? 'yes' : 'no',
    'bgpd' => ($cfg['enable_bgpd'] ?? 'no') === 'yes' ? 'yes' : 'no',
    'ospfd' => ($cfg['enable_ospfd'] ?? 'no') === 'yes' ? 'yes' : 'no',
    'ospf6d' => ($cfg['enable_ospf6d'] ?? 'no') === 'yes' ? 'yes' : 'no',
    'isisd' => ($cfg['enable_isisd'] ?? 'no') === 'yes' ? 'yes' : 'no',
    'bfdd' => ($cfg['enable_bfdd'] ?? 'no') === 'yes' ? 'yes' : 'no',
    'staticd' => ($cfg['enable_staticd'] ?? 'yes') === 'yes' ? 'yes' : 'no',
  ];
  $new = $raw;
  foreach ($map as $daemon => $val) {
    if (preg_match('/^\s*' . preg_quote($daemon, '/') . '\s*=/mi', $new)) {
      $new = preg_replace('/^\s*' . preg_quote($daemon, '/') . '\s*=\s*\S+/mi', $daemon . '=' . $val, $new, 1);
    } else {
      $new = rtrim($new) . "\n" . $daemon . '=' . $val . "\n";
    }
  }
  if ($new !== $raw) {
    if (@file_put_contents($dae, $new) === false) {
      return ['ok' => false, 'error' => 'cannot write ' . $dae];
    }
  }
  return ['ok' => true, 'daemons' => $map];
}

/**
 * Run package install script.
 */
function frr_run_install_packages() {
  $script = '/usr/local/emhttp/plugins/FabricRouting/scripts/frr-install-packages';
  // Dev tree
  if (!is_executable($script)) {
    $dev = dirname(__DIR__) . '/scripts/frr-install-packages';
    if (is_executable($dev)) {
      $script = $dev;
    }
  }
  if (!is_file($script)) {
    return ['ok' => false, 'error' => 'install script missing'];
  }
  $out = [];
  $rc = 0;
  @exec('bash ' . escapeshellarg($script) . ' 2>&1', $out, $rc);
  frr_log('install-packages rc=' . $rc . ' ' . implode(' | ', array_slice($out, 0, 20)));
  return ['ok' => $rc === 0, 'rc' => $rc, 'output' => $out];
}

function frr_try_start() {
  // Our Slackware-style packages ship tools/frrinit.sh as /usr/sbin/frrinit.sh
  $cmds = [
    '/usr/sbin/frrinit.sh start',
    '/usr/sbin/frrinit.sh restart',
    '/usr/lib/frr/frrinit.sh start',
    '/usr/lib/frr/frrinit.sh restart',
    'systemctl start frr',
    'systemctl restart frr',
    'service frr start',
    'service frr restart',
  ];
  foreach ($cmds as $cmd) {
    $rc = 1;
    $o = [];
    @exec($cmd . ' 2>/dev/null', $o, $rc);
    if ($rc === 0) {
      frr_log('start: ' . $cmd);
      return ['ok' => true, 'cmd' => $cmd];
    }
  }
  // Already running counts as success
  if (is_file('/proc') && @file_exists('/proc') && trim((string)@shell_exec('pgrep -x zebra 2>/dev/null'))) {
    return ['ok' => true, 'cmd' => 'already-running'];
  }
  return ['ok' => false, 'error' => 'could not start frr service (package may lack unit)'];
}

/**
 * Full Apply path from UI — catalog download (if enabled) + installpkg + daemons + start.
 * Prints progress lines for Unraid progressFrame (leave the popup open until Done).
 *
 * @param array $opts {
 *   @type bool local_only  If true, never touch the network (array-start rehydrate).
 * }
 */
function frr_apply($opts = []) {
  if (!is_array($opts)) {
    $opts = [];
  }
  $lock = frr_apply_lock(true);
  if (empty($lock['ok'])) {
    frr_progress($lock['error'] ?? 'Apply locked');
    return ['ok' => false, 'actions' => [$lock['error'] ?? 'locked'], 'detect' => frr_detect()];
  }

  try {
    return frr_apply_inner($opts);
  } finally {
    frr_apply_lock(false);
  }
}

/**
 * Array-start / boot-safe: installpkg from flash cache + start FRR. No download.
 */
function frr_rehydrate_local() {
  return frr_apply(['local_only' => true]);
}

function frr_apply_inner($opts = []) {
  if (!is_array($opts)) {
    $opts = [];
  }
  $local_only = !empty($opts['local_only']);
  $cfg = frr_load_cfg();
  $result = [
    'ok' => true,
    'actions' => [],
    'detect_before' => frr_detect(),
    'local_only' => $local_only,
  ];

  frr_progress('=== Fabric Routing Apply' . ($local_only ? ' (local rehydrate)' : '') . ' ===');
  frr_progress('Unraid ' . frr_unraid_version() . ' · ' . frr_arch());
  if (!$local_only) {
    frr_progress('Do not close this window until finished (Unraid progress dialog).');
  }

  @mkdir(frr_packages_dir(), 0755, true);

  // 1) Network download — UI Apply only. Never during boot plg or local rehydrate.
  //    Flash cache is the durable store; array start rehydrates without network.
  if ($local_only) {
    frr_progress('Step 1/4: local-only — skip catalog/download (flash cache only).');
    $result['actions'][] = 'local-only: no network download';
  } elseif (($cfg['auto_download'] ?? 'no') === 'yes') {
    frr_progress('Step 1/4: Catalog + package download…');
    $dl = frr_download_bundle(false);
    $result['download'] = $dl;
    if (!empty($dl['ok'])) {
      $result['actions'][] = 'package bundle ready (auto-download/cache)';
      frr_progress('Step 1/4: package bundle ready.');
    } else {
      $result['actions'][] = 'auto-download: ' . ($dl['error'] ?? 'no bundle for this Unraid version yet');
      frr_progress('Step 1/4: ' . ($dl['error'] ?? 'no bundle yet') . ' (will use flash cache if any)');
    }
  } else {
    frr_progress('Step 1/4: Auto-download Off — using flash cache only.');
  }

  $det = frr_detect();
  $have_pkgs = !empty($det['packages_on_flash']);
  $have_frr = !empty($det['present']);

  if (!$have_frr && !$have_pkgs) {
    $result['ok'] = true; // safe idle until catalog has a build
    $result['actions'][] = 'waiting for automated package set (nothing to install yet)';
    frr_progress('Nothing to install yet (no packages on flash, FRR not present).');
    frr_progress('See packages/SUPPORTED.md — lab-confirmed vs suggested Unraid versions.');
    frr_write_companion_marker();
    $result['detect'] = $det;
    frr_progress('=== Apply finished (idle) ===');
    return $result;
  }

  // 2) installpkg into RAM root
  if (($cfg['install_on_start'] ?? 'yes') === 'yes' && $have_pkgs) {
    frr_progress('Step 2/4: installpkg into live system…');
    $ins = frr_run_install_packages();
    $result['install'] = $ins;
    $result['actions'][] = !empty($ins['ok']) ? 'packages installed into live system' : 'package install failed or incomplete';
    if (empty($ins['ok'])) {
      $result['ok'] = false;
      frr_progress('Step 2/4: install FAILED');
      foreach (array_slice($ins['output'] ?? [], 0, 15) as $line) {
        frr_progress('  ' . $line);
      }
    } else {
      frr_progress('Step 2/4: packages installed.');
    }
  } else {
    frr_progress('Step 2/4: skip installpkg (disabled or no flash packages).');
  }

  // Safety: never touch Unraid network.cfg; never sysctl ip_forward here.
  frr_progress('Step 3/4: daemons + baseline conf…');
  $base = frr_ensure_baseline_conf();
  $result['baseline_conf'] = $base;
  $result['actions'][] = !empty($base['ok']) ? 'frr.conf baseline checked' : ('frr.conf: ' . ($base['error'] ?? 'skip'));

  $dae = frr_apply_daemons_file($cfg);
  $result['daemons'] = $dae;
  $result['actions'][] = !empty($dae['ok']) ? 'daemons file updated' : ('daemons: ' . ($dae['error'] ?? 'skip'));
  frr_progress('Step 3/4: daemons file ' . (!empty($dae['ok']) ? 'updated' : 'skipped/failed'));

  if (($cfg['start_frr'] ?? 'yes') === 'yes' && (frr_detect()['present'] || !empty($dae['ok']))) {
    frr_progress('Step 4/4: start/restart FRR…');
    $st = frr_try_start();
    $result['start'] = $st;
    $result['actions'][] = !empty($st['ok']) ? 'frr start/restart attempted' : ($st['error'] ?? 'start skipped');
    frr_progress('Step 4/4: ' . (!empty($st['ok']) ? ('OK (' . ($st['cmd'] ?? '') . ')') : ($st['error'] ?? 'failed')));
  } else {
    frr_progress('Step 4/4: start FRR skipped (disabled or not present).');
  }

  frr_write_companion_marker();
  $result['detect'] = frr_detect();
  $d = $result['detect'];
  frr_progress('Result: FRR present=' . (!empty($d['present']) ? 'yes' : 'no')
    . ' zebra=' . (!empty($d['running_zebra']) ? 'up' : 'down')
    . ' fabricd=' . (!empty($d['running_fabricd']) ? 'up' : 'down'));
  frr_progress('=== Apply finished ===');
  frr_progress('You can close this window and hard-refresh the Fabric Routing page (Ctrl+Shift+R).');
  return $result;
}

function frr_format_bytes($n) {
  $n = (float)$n;
  if ($n < 1024) {
    return (int)$n . ' B';
  }
  if ($n < 1048576) {
    return round($n / 1024, 1) . ' KiB';
  }
  if ($n < 1073741824) {
    return round($n / 1048576, 1) . ' MiB';
  }
  return round($n / 1073741824, 2) . ' GiB';
}

function frr_plugin_version() {
  // Unraid installs as fabricrouting.plg (symlink under /var/log/plugins)
  foreach ([
    '/var/log/plugins/fabricrouting.plg',
    '/boot/config/plugins/fabricrouting.plg',
    '/boot/config/plugins/FabricRouting.plg',
    '/var/log/plugins/FabricRouting.plg',
    '/boot/config/plugins/unraidfrr.plg',
    '/var/log/plugins/unraidfrr.plg',
  ] as $p) {
    if (is_readable($p)) {
      $t = (string)@file_get_contents($p);
      if (preg_match('/ENTITY version "([^"]+)"/', $t, $m)) {
        return $m[1];
      }
    }
  }
  return 'dev';
}

function frr_status() {
  $cfg = frr_load_cfg();
  return [
    'plugin_version' => frr_plugin_version(),
    'cfg' => $cfg,
    'detect' => frr_detect(),
    'catalog_url' => frr_catalog_url($cfg),
    'bundle' => frr_resolve_bundle($cfg),
    'companion' => is_readable(frr_companion_path()) ? json_decode((string)file_get_contents(frr_companion_path()), true) : null,
    'thunderboltnet_present' => is_dir('/usr/local/emhttp/plugins/ThunderboltNet'),
    'time' => date('c'),
  ];
}
