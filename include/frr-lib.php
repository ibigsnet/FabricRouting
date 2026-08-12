<?php
/**
 * UnraidFRR — shared helpers. Standalone: never require ThunderboltNet.
 */

function frr_plugin_name() {
  return 'UnraidFRR';
}

function frr_cfg_dir() {
  return '/boot/config/plugins/UnraidFRR';
}

function frr_cfg_path() {
  return frr_cfg_dir() . '/UnraidFRR.cfg';
}

function frr_packages_dir() {
  return frr_cfg_dir() . '/packages';
}

function frr_companion_path() {
  return frr_cfg_dir() . '/companion.json';
}

function frr_log_path() {
  return '/var/log/unraidfrr.log';
}

/**
 * Default catalog (Nvidia-style automated package source).
 */
function frr_default_catalog_url() {
  return 'https://raw.githubusercontent.com/ibigsnet/UnraidFRR/main/packages/manifest.json';
}

function frr_load_cfg() {
  $defaults = [
    // Fully automated: download + install without user package drops
    'install_on_start' => 'yes',
    'auto_download' => 'yes',
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
  @file_put_contents(frr_cfg_dir() . '/unraidfrr.log', $line, FILE_APPEND);
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
 */
function frr_fetch_manifest($force = false) {
  $cfg = frr_load_cfg();
  $url = frr_catalog_url($cfg);
  $cache = frr_cfg_dir() . '/manifest.cache.json';
  if (!$force && is_readable($cache) && (time() - filemtime($cache) < 3600)) {
    $j = json_decode((string)@file_get_contents($cache), true);
    if (is_array($j)) {
      return ['ok' => true, 'manifest' => $j, 'source' => 'cache', 'url' => $url];
    }
  }
  // Prefer plugin-tree copy when offline / first install
  $local = dirname(__DIR__) . '/packages/manifest.json';
  $installed = '/usr/local/emhttp/plugins/UnraidFRR/packages/manifest.json';

  $body = frr_http_get($url);
  if ($body === null || $body === '') {
    foreach ([$installed, $local] as $p) {
      if (is_readable($p)) {
        $body = (string)@file_get_contents($p);
        $url = $p;
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
  return ['ok' => true, 'manifest' => $j, 'source' => 'download', 'url' => $url];
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
      'header' => "User-Agent: UnraidFRR/1.0\r\n",
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
  $bundle = frr_resolve_bundle($cfg);
  if (empty($bundle['ok'])) {
    frr_log('download skip: ' . ($bundle['error'] ?? 'no bundle'));
    return $bundle + ['downloaded' => []];
  }

  $dir = frr_packages_dir();
  @mkdir($dir, 0755, true);
  $id_file = $dir . '/.bundle-id';
  $cur = is_readable($id_file) ? trim((string)@file_get_contents($id_file)) : '';
  $want = $bundle['id'] ?? '';

  $downloaded = [];
  $errors = [];
  $order = [];

  foreach ($bundle['packages'] as $pkg) {
    if (!is_array($pkg)) {
      continue;
    }
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
      $downloaded[] = ['file' => $file, 'status' => 'cached'];
      continue;
    }
    frr_log('download ' . $url . ' -> ' . $dest);
    $body = frr_http_get($url, 600);
    if ($body === null || $body === '') {
      $errors[] = 'download failed: ' . $file;
      continue;
    }
    if (@file_put_contents($dest, $body) === false) {
      $errors[] = 'write failed: ' . $file;
      continue;
    }
    if ($sha !== '') {
      $have = hash_file('sha256', $dest);
      if (strtolower((string)$have) !== $sha) {
        @unlink($dest);
        $errors[] = 'sha256 mismatch: ' . $file;
        continue;
      }
    }
    $downloaded[] = ['file' => $file, 'status' => 'downloaded', 'bytes' => strlen($body)];
  }

  if ($order) {
    $lines = ["# Generated by UnraidFRR — do not edit; install order", ''];
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
  $plg = '/var/log/plugins/UnraidFRR'; // not always present
  $cfg = frr_load_cfg();
  $det = frr_detect();
  $payload = [
    'plugin' => 'UnraidFRR',
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
! UnraidFRR baseline — host routing suite; no eth/br interfaces enrolled.
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
  if (strpos($raw, 'UnraidFRR baseline') === false && strpos($raw, $begin_tbn) === false) {
    // Prepend safety banner without destroying user/TBN config
    $banner = "! UnraidFRR: existing frr.conf preserved. Do not add br0/eth0 to protocols unless intended.\n";
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
  $script = '/usr/local/emhttp/plugins/UnraidFRR/scripts/frr-install-packages';
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
 * Full Apply path from UI / array start — fully automated (Nvidia-style).
 * Downloads catalog + packages, installpkg, daemons, start. No manual file drops.
 */
function frr_apply() {
  $cfg = frr_load_cfg();
  $result = [
    'ok' => true,
    'actions' => [],
    'detect_before' => frr_detect(),
  ];

  @mkdir(frr_packages_dir(), 0755, true);

  // 1) Auto-download package bundle when enabled (default yes)
  if (($cfg['auto_download'] ?? 'yes') === 'yes') {
    $dl = frr_download_bundle(false);
    $result['download'] = $dl;
    if (!empty($dl['ok'])) {
      $result['actions'][] = 'package bundle ready (auto-download/cache)';
    } else {
      $result['actions'][] = 'auto-download: ' . ($dl['error'] ?? 'no bundle for this Unraid version yet');
      // Continue if packages already cached or FRR already present
    }
  }

  $det = frr_detect();
  $have_pkgs = !empty($det['packages_on_flash']);
  $have_frr = !empty($det['present']);

  if (!$have_frr && !$have_pkgs) {
    $result['ok'] = true; // safe idle until catalog has a build
    $result['actions'][] = 'waiting for automated package set (nothing to install yet)';
    frr_write_companion_marker();
    $result['detect'] = $det;
    return $result;
  }

  // 2) installpkg into RAM root
  if (($cfg['install_on_start'] ?? 'yes') === 'yes' && $have_pkgs) {
    $ins = frr_run_install_packages();
    $result['install'] = $ins;
    $result['actions'][] = !empty($ins['ok']) ? 'packages installed into live system' : 'package install failed or incomplete';
    if (empty($ins['ok'])) {
      $result['ok'] = false;
    }
  }

  // Safety: never touch Unraid network.cfg; never sysctl ip_forward here.
  $base = frr_ensure_baseline_conf();
  $result['baseline_conf'] = $base;
  $result['actions'][] = !empty($base['ok']) ? 'frr.conf baseline checked' : ('frr.conf: ' . ($base['error'] ?? 'skip'));

  $dae = frr_apply_daemons_file($cfg);
  $result['daemons'] = $dae;
  $result['actions'][] = !empty($dae['ok']) ? 'daemons file updated' : ('daemons: ' . ($dae['error'] ?? 'skip'));

  if (($cfg['start_frr'] ?? 'yes') === 'yes' && (frr_detect()['present'] || !empty($dae['ok']))) {
    $st = frr_try_start();
    $result['start'] = $st;
    $result['actions'][] = !empty($st['ok']) ? 'frr start/restart attempted' : ($st['error'] ?? 'start skipped');
  }

  frr_write_companion_marker();
  $result['detect'] = frr_detect();
  return $result;
}

function frr_plugin_version() {
  $plg = '/var/log/plugins/unraidfrr.plg';
  // Prefer entity from installed plugin file
  foreach ([
    '/boot/config/plugins/UnraidFRR.plg',
    '/var/log/plugins/UnraidFRR.plg',
    '/usr/local/emhttp/plugins/UnraidFRR/unraidfrr.plg',
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
