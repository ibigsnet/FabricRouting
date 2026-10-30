<?php
/**
 * FabricRouting — shared helpers. Standalone: never requires ThunderboltNet PHP.
 *
 * Ownership: this plugin owns /etc/frr/frr.conf, /etc/frr/daemons and
 * /etc/frr/vtysh.conf. frr.conf is rebuilt from flash on Apply, array start and
 * scripts/frr-reload:
 *   - FabricRouting baseline (+ optional fabric route guard)
 *   - ThunderboltNet fragment (file contract, when present)
 *   - /boot/config/plugins/FabricRouting/frr.user.conf (when present)
 */

const FRR_CONF_HEADER = '! FabricRouting managed frr.conf';
const FRR_GUARD_V4_DEFAULT = '10.254.0.0/16 10.255.0.0/16';
const FRR_GUARD_V6_DEFAULT = 'fc00::/7';

/** Optional path prefix (CLI tests only). Empty on a real server. */
function frr_root() {
  $r = getenv('FABRICROUTING_ROOT');
  return is_string($r) ? rtrim($r, '/') : '';
}

function frr_p($path) {
  return frr_root() . $path;
}

function frr_plugin_name() {
  return 'FabricRouting';
}

function frr_plugin_dir() {
  return dirname(__DIR__);
}

function frr_cfg_dir() {
  return frr_p('/boot/config/plugins/FabricRouting');
}

function frr_cfg_path() {
  return frr_cfg_dir() . '/FabricRouting.cfg';
}

function frr_packages_dir() {
  return frr_cfg_dir() . '/packages';
}

function frr_manifest_txt_path() {
  return frr_packages_dir() . '/MANIFEST.txt';
}

function frr_companion_path() {
  return frr_cfg_dir() . '/companion.json';
}

function frr_user_conf_path() {
  return frr_cfg_dir() . '/frr.user.conf';
}

function frr_tbn_fragment_path() {
  return frr_p('/boot/config/plugins/ThunderboltNet/frr/thunderboltnet-openfabric.conf');
}

function frr_log_path() {
  return frr_p('/var/log/fabricrouting.log');
}

function frr_lock_path() {
  return frr_p('/run/fabricrouting.lock');
}

function frr_etc_dir() {
  return frr_p('/etc/frr');
}

function frr_run_dir() {
  return frr_p('/var/run/frr');
}

function frr_init_script() {
  return frr_p('/usr/sbin/frrinit.sh');
}

/**
 * Package catalog shipped inside this plugin version (packages/manifest.json).
 * It is the only source of package hashes.
 */
function frr_bundled_manifest_path() {
  $p = frr_plugin_dir() . '/packages/manifest.json';
  return is_readable($p) ? $p : '';
}

function frr_cfg_defaults() {
  return [
    // Array start: reinstall packages already on flash into RAM (no network).
    'install_on_start' => 'yes',
    'package_channel' => 'latest', // latest | previous
    // Empty = package URLs from the bundled catalog. Otherwise an https base
    // URL that serves the same file names (hashes still come from the bundle).
    'package_base_url' => '',
    'enable_zebra' => 'yes',
    'enable_fabricd' => 'yes',
    'enable_bgpd' => 'no',
    'enable_ospfd' => 'no',
    'enable_ospf6d' => 'no',
    'enable_isisd' => 'no',
    'enable_bfdd' => 'no',
    'enable_staticd' => 'yes',
    'start_frr' => 'yes',
    // Only OpenFabric routes inside these prefixes reach the kernel table.
    'fabric_guard' => 'yes',
    'fabric_guard_v4' => FRR_GUARD_V4_DEFAULT,
    'fabric_guard_v6' => FRR_GUARD_V6_DEFAULT,
  ];
}

function frr_load_cfg() {
  $cfg = [];
  if (frr_root() === '' && function_exists('parse_plugin_cfg')) {
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
  return array_merge(frr_cfg_defaults(), $cfg);
}

function frr_log($msg) {
  @file_put_contents(frr_log_path(), date('c') . ' ' . $msg . "\n", FILE_APPEND);
}

/**
 * Write a file only when its content changes (temp file + rename).
 * @return array{ok:bool,changed:bool}
 */
function frr_write_if_changed($path, $data, $mode = 0644) {
  $data = (string)$data;
  if (is_file($path) && @file_get_contents($path) === $data) {
    return ['ok' => true, 'changed' => false];
  }
  $dir = dirname($path);
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  $tmp = $path . '.tmp.' . getmypid();
  if (@file_put_contents($tmp, $data) === false) {
    @unlink($tmp);
    return ['ok' => false, 'changed' => false];
  }
  @chmod($tmp, $mode);
  if (!@rename($tmp, $path)) {
    @unlink($tmp);
    return ['ok' => false, 'changed' => false];
  }
  return ['ok' => true, 'changed' => true];
}

/**
 * Enable nchan progress for openPlugin jobs (StartCommand appends "nchan").
 */
function frr_nchan_enabled($set = null) {
  static $on = false;
  if ($set !== null) {
    $on = (bool)$set;
  }
  return $on;
}

/**
 * Progress line for package jobs / Apply.
 * - openBox / logging.htm / CLI: echo HTML-safe line + flush
 * - openPlugin / nchan: publish to plugins channel; openDone needs exact _DONE_ / _ERROR_
 */
function frr_progress($msg) {
  $msg = (string)$msg;
  if ($msg !== '_DONE_' && $msg !== '_ERROR_') {
    frr_log($msg);
  }

  if (frr_nchan_enabled() && ($msg === '_DONE_' || $msg === '_ERROR_')) {
    if (function_exists('publish')) {
      publish('plugins', $msg, 1, false);
    }
    return;
  }

  $line = htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
  if (frr_nchan_enabled() && function_exists('publish')) {
    publish('plugins', $line, 1, false);
    return;
  }

  echo $line;
  if (function_exists('ob_flush')) {
    @ob_flush();
  }
  @flush();
}

/**
 * Persist selected keys into FabricRouting.cfg (merge with current).
 *
 * @param array<string,string> $patch
 */
function frr_save_cfg_keys(array $patch) {
  $cfg = frr_load_cfg();
  foreach ($patch as $k => $v) {
    if (!is_string($k) || !preg_match('/^[A-Za-z0-9_]+$/', $k)) {
      continue;
    }
    $cfg[$k] = str_replace(['"', "\r", "\n"], '', (string)$v);
  }
  $lines = ['; FabricRouting.cfg — managed by plugin', ''];
  foreach ($cfg as $k => $v) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$k) || $k === 'auto_download') {
      continue;
    }
    $lines[] = $k . '="' . str_replace(['"', "\r", "\n"], '', (string)$v) . '"';
  }
  return frr_write_if_changed(frr_cfg_path(), implode("\n", $lines) . "\n")['ok'];
}

/**
 * Single-flight lock (flock on a RAM file). Released on process exit.
 * The descriptor is close-on-exec so FRR daemons never inherit it.
 */
function frr_lock_acquire($wait_seconds = 0) {
  if (!empty($GLOBALS['frr_lock_fh'])) {
    return true;
  }
  $path = frr_lock_path();
  if (!is_dir(dirname($path))) {
    @mkdir(dirname($path), 0755, true);
  }
  $fh = @fopen($path, 'ce');
  if (!is_resource($fh)) {
    return false;
  }
  $deadline = microtime(true) + max(0, (int)$wait_seconds);
  while (true) {
    if (@flock($fh, LOCK_EX | LOCK_NB)) {
      @ftruncate($fh, 0);
      @fwrite($fh, getmypid() . "\n");
      @fflush($fh);
      $GLOBALS['frr_lock_fh'] = $fh;
      return true;
    }
    if (microtime(true) >= $deadline) {
      break;
    }
    usleep(250000);
  }
  fclose($fh);
  return false;
}

function frr_lock_release() {
  $fh = $GLOBALS['frr_lock_fh'] ?? null;
  if (is_resource($fh)) {
    @flock($fh, LOCK_UN);
    @fclose($fh);
  }
  $GLOBALS['frr_lock_fh'] = null;
}

/**
 * Loud banner for package jobs (openBox / logging.htm).
 */
function frr_progress_dont_close_banner() {
  frr_progress('');
  frr_progress('+==============================================================================');
  frr_progress("| Don't close this window with the red 'X' until the DONE button is displayed!");
  frr_progress('+==============================================================================');
  frr_progress('');
}

/**
 * Explicit package job from UI (openBox / openPlugin → scripts/frr-packages-job).
 * Modes: latest | previous (download + install) | flash (install from flash only).
 *
 * @param string $mode
 * @return array
 */
function frr_packages_job($mode = 'latest') {
  $mode = strtolower(trim((string)$mode));
  if (!in_array($mode, ['latest', 'previous', 'flash'], true)) {
    $mode = 'latest';
  }

  $job_state = ['finished' => false];
  register_shutdown_function(function () use (&$job_state) {
    if (!empty($job_state['finished'])) {
      return;
    }
    $job_state['finished'] = true;
    frr_lock_release();
    frr_progress('');
    frr_progress('Package job ended unexpectedly (see /var/log/fabricrouting.log). You can click Done.');
    frr_progress_openbox_done_button();
    frr_progress('_ERROR_');
    frr_progress_close_stdio();
  });

  frr_progress_dont_close_banner();
  frr_progress('Fabric Routing — package job');
  frr_progress('Unraid ' . frr_unraid_version() . ' · ' . frr_arch());

  try {
    if ($mode === 'flash') {
      frr_progress('Mode: install from flash (no download).');
      frr_progress('');
      $r = frr_apply(['install' => true]);
    } else {
      frr_progress('Mode: download channel "' . $mode . '", then install and start FRR.');
      frr_progress('');
      frr_save_cfg_keys(['package_channel' => $mode]);
      $r = frr_apply(['download' => true, 'install' => true]);
    }
  } catch (Throwable $e) {
    frr_progress('FATAL: ' . $e->getMessage());
    $r = ['ok' => false, 'error' => $e->getMessage()];
  }

  $job_state['finished'] = true;
  frr_progress('');
  if (!empty($r['ok'])) {
    frr_progress('Package job complete. You can close this window or click Done.');
    frr_progress_openbox_done_button();
    frr_progress('_DONE_');
  } else {
    frr_progress('Package job FAILED — see the lines above. Nothing was changed after the failing step.');
    frr_progress_openbox_done_button();
    frr_progress('_ERROR_');
  }
  frr_progress_close_stdio();
  return $r;
}

/**
 * Last-resort Done control for openBox/logging.htm (not nchan).
 */
function frr_progress_openbox_done_button() {
  if (frr_nchan_enabled() || getenv('FABRICROUTING_NO_HTML')) {
    return;
  }
  echo "<p class='centered'><button class='logLine' type='button' onclick=\"try{parent.Shadowbox.close()}catch(e){try{window.close()}catch(e2){}}\">Done</button></p>\n";
  if (function_exists('ob_flush')) {
    @ob_flush();
  }
  @flush();
}

/**
 * Drop the logging.htm pipe so Unraid onload can fire even if a child lingers.
 */
function frr_progress_close_stdio() {
  if (function_exists('fastcgi_finish_request')) {
    @fastcgi_finish_request();
  }
  if (PHP_SAPI !== 'cli') {
    return;
  }
  if (defined('STDOUT') && is_resource(STDOUT)) {
    @fclose(STDOUT);
  }
  if (defined('STDERR') && is_resource(STDERR)) {
    @fclose(STDERR);
  }
}

/** Daemons FabricRouting manages in /etc/frr/daemons. */
function frr_daemon_names() {
  return ['zebra', 'fabricd', 'staticd', 'bgpd', 'ospfd', 'ospf6d', 'isisd', 'bfdd'];
}

/**
 * True when a daemon's FRR pidfile points at a live process of that name.
 */
function frr_daemon_running($daemon) {
  $pf = frr_run_dir() . '/' . $daemon . '.pid';
  if (!is_readable($pf)) {
    return false;
  }
  $pid = (int)trim((string)@file_get_contents($pf));
  if ($pid <= 1) {
    return false;
  }
  $comm = trim((string)@file_get_contents('/proc/' . $pid . '/comm'));
  return $comm === substr($daemon, 0, 15);
}

function frr_installed() {
  return is_file(frr_init_script()) && is_executable(frr_p('/usr/sbin/zebra'));
}

/**
 * Detect live FRR tools.
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
    if (is_executable(frr_p($p))) {
      $out['vtysh'] = frr_p($p);
      break;
    }
  }
  foreach (['fabricd', 'zebra'] as $d) {
    foreach (['/usr/sbin/', '/usr/lib/frr/'] as $dir) {
      if (is_executable(frr_p($dir . $d))) {
        $out[$d] = frr_p($dir . $d);
        break;
      }
    }
  }

  $out['present'] = ($out['vtysh'] !== '' || $out['fabricd'] !== '' || $out['zebra'] !== '');
  if ($out['vtysh'] !== '') {
    $out['version'] = trim((string)@shell_exec(escapeshellarg($out['vtysh']) . ' -v 2>/dev/null | head -1'));
  }

  $out['running_zebra'] = frr_daemon_running('zebra');
  $out['running_fabricd'] = frr_daemon_running('fabricd');

  $dir = frr_packages_dir();
  if (is_dir($dir)) {
    foreach (@scandir($dir) ?: [] as $f) {
      if (preg_match('/\.(txz|tgz)$/i', $f)) {
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
    $out['note'] = 'Packages on flash but not installed — run Download & Install (Flash cache) or start the array';
  } elseif (!empty($out['bundle']['ok'])) {
    $out['note'] = 'FRR ' . ($out['bundle']['frr_version'] ?? '') . ' available — use Download & Install packages';
  } else {
    $out['note'] = $out['bundle']['error'] ?? 'No package set for this Unraid version';
  }

  return $out;
}

function frr_unraid_version() {
  $v = '';
  $f = frr_p('/etc/unraid-version');
  if (is_readable($f)) {
    $raw = (string)@file_get_contents($f);
    if (preg_match('/version="([^"]+)"/', $raw, $m)) {
      $v = $m[1];
    } elseif (preg_match('/(\d+\.\d+(?:\.\d+)?)/', $raw, $m)) {
      $v = $m[1];
    }
  }
  return $v !== '' ? $v : 'unknown';
}

function frr_arch() {
  $m = php_uname('m');
  if ($m === 'amd64' || $m === 'x86_64') {
    return 'x86_64';
  }
  if ($m === 'aarch64' || $m === 'arm64') {
    return 'aarch64';
  }
  return $m !== '' ? $m : 'x86_64';
}

/**
 * Mirror base URL (https) or '' for the catalog's own URLs.
 */
function frr_mirror_base(?array $cfg = null) {
  if ($cfg === null) {
    $cfg = frr_load_cfg();
  }
  $u = trim((string)($cfg['package_base_url'] ?? ''));
  if ($u === '') {
    return '';
  }
  // Older versions accepted a manifest URL; only its directory is used now.
  if (substr($u, -5) === '.json') {
    $u = substr($u, 0, (int)strrpos($u, '/'));
  }
  return rtrim($u, '/');
}

/** Human-readable catalog/mirror description for the status page. */
function frr_catalog_url(?array $cfg = null) {
  $m = frr_mirror_base($cfg);
  return $m === '' ? 'bundled packages/manifest.json' : ('bundled hashes, files from ' . $m);
}

/**
 * Load the bundled package catalog. Never touches the network.
 */
function frr_fetch_manifest() {
  $bundled = frr_bundled_manifest_path();
  if ($bundled === '') {
    return ['ok' => false, 'error' => 'bundled package catalog missing (reinstall the plugin)', 'url' => ''];
  }
  $j = json_decode((string)@file_get_contents($bundled), true);
  if (!is_array($j)) {
    return ['ok' => false, 'error' => 'invalid catalog JSON', 'url' => $bundled];
  }
  return ['ok' => true, 'manifest' => $j, 'source' => 'bundled', 'url' => $bundled];
}

function frr_valid_pkg_file($file) {
  return is_string($file) && (bool)preg_match('/^(frr|libyang)-[A-Za-z0-9._+-]+_unraid\.t[gx]z$/', $file);
}

function frr_version_compare_loose($a, $b) {
  $na = preg_replace('/[^0-9.].*$/', '', (string)$a);
  $nb = preg_replace('/[^0-9.].*$/', '', (string)$b);
  return version_compare($na, $nb);
}

/**
 * Resolve the package bundle for this host + channel from the bundled catalog.
 *
 * @return array{ok:bool,error?:string,channel?:string,frr_version?:string,packages?:array,id?:string}
 */
function frr_resolve_bundle(?array $cfg = null) {
  if ($cfg === null) {
    $cfg = frr_load_cfg();
  }
  $mf = frr_fetch_manifest();
  if (empty($mf['ok'])) {
    return ['ok' => false, 'error' => $mf['error'] ?? 'catalog unavailable'];
  }
  $manifest = $mf['manifest'];
  $channel = trim((string)($cfg['package_channel'] ?? 'latest'));
  if (!in_array($channel, ['latest', 'previous'], true)) {
    $channel = (string)($manifest['default_channel'] ?? 'latest');
  }
  $arch = frr_arch();
  $uver = frr_unraid_version();
  $bundles = $manifest['bundles'] ?? [];
  $base = ['channel' => $channel, 'unraid_version' => $uver, 'arch' => $arch];
  if (!is_array($bundles) || !$bundles) {
    return $base + ['ok' => false, 'error' => 'The bundled catalog lists no package sets.'];
  }

  $candidates = [];
  foreach ($bundles as $b) {
    if (!is_array($b) || ($b['channel'] ?? 'latest') !== $channel || ($b['arch'] ?? 'x86_64') !== $arch) {
      continue;
    }
    if ($uver === 'unknown') {
      continue;
    }
    if (frr_version_compare_loose($uver, (string)($b['unraid_min'] ?? '0')) < 0
      || frr_version_compare_loose($uver, (string)($b['unraid_max'] ?? '999.99.99')) > 0) {
      continue;
    }
    if (empty($b['packages']) || !is_array($b['packages'])) {
      continue;
    }
    $candidates[] = $b;
  }

  if (!$candidates) {
    return $base + [
      'ok' => false,
      'error' => 'No package set for Unraid ' . $uver . ' / ' . $arch . ' (channel ' . $channel . '). See packages/SUPPORTED.md.',
    ];
  }

  usort($candidates, function ($a, $b) {
    return frr_version_compare_loose($b['frr_version'] ?? '0', $a['frr_version'] ?? '0');
  });
  $best = $candidates[0];
  $id = ($best['channel'] ?? 'latest') . '|' . ($best['frr_version'] ?? '') . '|' . ($best['arch'] ?? '') . '|' . ($best['unraid_min'] ?? '');
  return $base + [
    'ok' => true,
    'frr_version' => $best['frr_version'] ?? '',
    'packages' => $best['packages'],
    'id' => $id,
    'label' => $best['label'] ?? '',
  ];
}

/** sha256 for a package file name from any bundle in the bundled catalog. */
function frr_bundled_sha_for($file) {
  $mf = frr_fetch_manifest();
  if (empty($mf['ok'])) {
    return '';
  }
  foreach ($mf['manifest']['bundles'] ?? [] as $b) {
    foreach ((array)($b['packages'] ?? []) as $p) {
      if (is_array($p) && ($p['file'] ?? '') === $file) {
        $sha = strtolower(trim((string)($p['sha256'] ?? '')));
        return preg_match('/^[0-9a-f]{64}$/', $sha) ? $sha : '';
      }
    }
  }
  return '';
}

/**
 * Read MANIFEST.txt. Lines: "<sha256>  <file>" (current) or "<file>" (older plugin versions).
 * @return array<int,array{file:string,sha:string}>
 */
function frr_manifest_read() {
  $rows = [];
  $path = frr_manifest_txt_path();
  if (!is_readable($path)) {
    return $rows;
  }
  foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    $line = trim(preg_replace('/#.*$/', '', str_replace("\r", '', $line)));
    if ($line === '') {
      continue;
    }
    if (preg_match('/^([0-9a-f]{64})\s+(\S+)$/', $line, $m)) {
      $rows[] = ['file' => $m[2], 'sha' => $m[1]];
    } else {
      $rows[] = ['file' => basename($line), 'sha' => ''];
    }
  }
  return $rows;
}

function frr_manifest_write(array $rows) {
  $lines = ['# Generated by FabricRouting — install order, sha256 verified before install', ''];
  foreach ($rows as $r) {
    $lines[] = $r['sha'] . '  ' . $r['file'];
  }
  return frr_write_if_changed(frr_manifest_txt_path(), implode("\n", $lines) . "\n")['ok'];
}

/**
 * Older plugin versions wrote MANIFEST.txt without hashes. Fill them in from the
 * bundled catalog when the file on flash matches; otherwise leave it so the
 * install step refuses it.
 */
function frr_manifest_migrate() {
  $rows = frr_manifest_read();
  if (!$rows) {
    return ['ok' => true, 'migrated' => false];
  }
  $need = false;
  foreach ($rows as $r) {
    if ($r['sha'] === '') {
      $need = true;
    }
  }
  if (!$need) {
    return ['ok' => true, 'migrated' => false];
  }
  foreach ($rows as $i => $r) {
    if ($r['sha'] !== '') {
      continue;
    }
    $sha = frr_valid_pkg_file($r['file']) ? frr_bundled_sha_for($r['file']) : '';
    $path = frr_packages_dir() . '/' . $r['file'];
    if ($sha === '' || !is_file($path) || hash_file('sha256', $path) !== $sha) {
      frr_log('MANIFEST migrate: no trusted hash for ' . $r['file'] . ' — use Download & Install packages');
      return ['ok' => false, 'migrated' => false];
    }
    $rows[$i]['sha'] = $sha;
  }
  frr_manifest_write($rows);
  frr_log('MANIFEST migrated to sha256 format');
  return ['ok' => true, 'migrated' => true];
}

/**
 * Download one https URL to a /tmp staging file (curl, https only, TLS verified).
 * @return array{ok:bool,tmp?:string,error?:string}
 */
function frr_http_download_tmp($url, $timeout = 600) {
  if (!preg_match('#^https://#i', (string)$url)) {
    return ['ok' => false, 'error' => 'refuse non-https URL'];
  }
  $tmp = tempnam(sys_get_temp_dir(), 'frrdl');
  if ($tmp === false) {
    return ['ok' => false, 'error' => 'cannot create temp file'];
  }
  $timeout = max(60, (int)$timeout);
  $cmd = 'curl -sS -fL --proto =https --proto-redir =https --connect-timeout 30 --max-time ' . $timeout
    . ' -A ' . escapeshellarg('FabricRouting/1.0')
    . ' -o ' . escapeshellarg($tmp) . ' ' . escapeshellarg($url) . ' 2>&1';
  $o = [];
  $rc = 1;
  @exec($cmd, $o, $rc);
  if ($rc !== 0 || !is_file($tmp) || filesize($tmp) < 1) {
    @unlink($tmp);
    $hint = trim(implode(' ', array_slice($o, 0, 3)));
    return ['ok' => false, 'error' => 'curl rc=' . $rc . ($hint !== '' ? (': ' . $hint) : '')];
  }
  return ['ok' => true, 'tmp' => $tmp];
}

/**
 * Download the resolved bundle into the flash package cache.
 * Every file must match the sha256 in the bundled catalog. On any error nothing
 * already installable changes: MANIFEST.txt and .bundle-id are only written
 * when every package verified.
 */
function frr_download_bundle() {
  $cfg = frr_load_cfg();
  $bundle = frr_resolve_bundle($cfg);
  if (empty($bundle['ok'])) {
    frr_progress('Download: ' . ($bundle['error'] ?? 'no bundle'));
    return $bundle + ['ok' => false, 'downloaded' => []];
  }
  $mirror = frr_mirror_base($cfg);
  if ($mirror !== '' && !preg_match('#^https://#i', $mirror)) {
    $err = 'package mirror must be an https URL';
    frr_progress('FAILED: ' . $err);
    return ['ok' => false, 'error' => $err, 'downloaded' => []];
  }

  frr_progress('Package set: FRR ' . ($bundle['frr_version'] ?? '') . ' for Unraid '
    . ($bundle['unraid_version'] ?? '') . ' / ' . ($bundle['arch'] ?? '')
    . ($mirror !== '' ? ' (mirror ' . $mirror . ')' : ''));

  $dir = frr_packages_dir();
  @mkdir($dir, 0755, true);

  // Validate the whole set before touching anything.
  $plan = [];
  foreach ($bundle['packages'] as $pkg) {
    $file = is_array($pkg) ? (string)($pkg['file'] ?? '') : '';
    $sha = is_array($pkg) ? strtolower(trim((string)($pkg['sha256'] ?? ''))) : '';
    $url = is_array($pkg) ? trim((string)($pkg['url'] ?? '')) : '';
    if (!frr_valid_pkg_file($file) || !preg_match('/^[0-9a-f]{64}$/', $sha)) {
      $err = 'catalog entry without a valid file name or sha256';
      frr_progress('FAILED: ' . $err);
      return ['ok' => false, 'error' => $err, 'downloaded' => []];
    }
    if ($mirror !== '') {
      $url = $mirror . '/' . rawurlencode($file);
    }
    if (!preg_match('#^https://#i', $url)) {
      $err = 'refusing non-https package URL for ' . $file;
      frr_progress('FAILED: ' . $err);
      return ['ok' => false, 'error' => $err, 'downloaded' => []];
    }
    $plan[] = ['file' => $file, 'sha' => $sha, 'url' => $url];
  }

  $downloaded = [];
  $n = count($plan);
  foreach ($plan as $i => $p) {
    $k = $i + 1;
    $dest = $dir . '/' . $p['file'];
    if (is_file($dest) && hash_file('sha256', $dest) === $p['sha']) {
      frr_progress("[$k/$n] Cached (sha256 ok): " . $p['file']);
      $downloaded[] = ['file' => $p['file'], 'status' => 'cached'];
      continue;
    }
    frr_progress("[$k/$n] Downloading " . $p['file'] . ' …');
    $got = frr_http_download_tmp($p['url']);
    if (empty($got['ok'])) {
      $err = 'download failed: ' . $p['file'] . ' (' . ($got['error'] ?? '?') . ')';
      frr_progress('  FAILED — ' . $err);
      return ['ok' => false, 'error' => $err, 'downloaded' => $downloaded];
    }
    $tmp = $got['tmp'];
    if (hash_file('sha256', $tmp) !== $p['sha']) {
      @unlink($tmp);
      $err = 'sha256 mismatch: ' . $p['file'];
      frr_progress('  FAILED — ' . $err);
      return ['ok' => false, 'error' => $err, 'downloaded' => $downloaded];
    }
    $bytes = (int)filesize($tmp);
    $part = $dest . '.part';
    $copied = @copy($tmp, $part);
    @unlink($tmp);
    if (!$copied || hash_file('sha256', $part) !== $p['sha'] || !@rename($part, $dest)) {
      @unlink($part);
      $err = 'flash write failed: ' . $p['file'];
      frr_progress('  FAILED — ' . $err);
      return ['ok' => false, 'error' => $err, 'downloaded' => $downloaded];
    }
    frr_progress('  sha256 ok · ' . frr_format_bytes($bytes));
    $downloaded[] = ['file' => $p['file'], 'status' => 'downloaded', 'bytes' => $bytes];
  }

  $rows = [];
  $keep = [];
  foreach ($plan as $p) {
    $rows[] = ['file' => $p['file'], 'sha' => $p['sha']];
    $keep[$p['file']] = true;
  }
  if (!frr_manifest_write($rows)) {
    frr_progress('FAILED: cannot write MANIFEST.txt');
    return ['ok' => false, 'error' => 'cannot write MANIFEST.txt', 'downloaded' => $downloaded];
  }
  frr_write_if_changed($dir . '/.bundle-id', ($bundle['id'] ?? '') . "\n");
  // Drop package files from older sets.
  foreach (@scandir($dir) ?: [] as $f) {
    if (preg_match('/\.(txz|tgz)(\.part)?$/', $f) && !isset($keep[$f])) {
      @unlink($dir . '/' . $f);
      frr_progress('  removed old ' . $f);
    }
  }

  return ['ok' => true, 'bundle' => $bundle, 'downloaded' => $downloaded];
}

/**
 * Write companion marker for other plugins (only when it changes).
 */
function frr_write_companion_marker(?array $det = null) {
  $cfg = frr_load_cfg();
  if ($det === null) {
    $det = frr_detect();
  }
  $payload = [
    'plugin' => 'FabricRouting',
    'provides' => ['frr', 'zebra', 'fabricd'],
    'version' => $det['version'] !== '' ? $det['version'] : 'unknown',
    'present' => $det['present'],
    'fabricd_enabled_cfg' => ($cfg['enable_fabricd'] ?? 'yes') === 'yes',
    'reload_hook' => '/usr/local/emhttp/plugins/FabricRouting/scripts/frr-reload',
    'fragment_path' => '/boot/config/plugins/ThunderboltNet/frr/thunderboltnet-openfabric.conf',
  ];
  return frr_write_if_changed(frr_companion_path(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n")['ok'];
}

/**
 * Parse a whitespace/comma list of prefixes; returns normalized network prefixes.
 * @return array{ok:array<int,string>,bad:array<int,string>}
 */
function frr_parse_prefixes($list, $family) {
  $ok = [];
  $bad = [];
  foreach (preg_split('/[\s,]+/', trim((string)$list)) ?: [] as $tok) {
    if ($tok === '') {
      continue;
    }
    if (!preg_match('#^([0-9A-Fa-f:.]+)/(\d{1,3})$#', $tok, $m)) {
      $bad[] = $tok;
      continue;
    }
    $bin = @inet_pton($m[1]);
    $len = (int)$m[2];
    $max = $family === 4 ? 32 : 128;
    if (!is_string($bin) || strlen($bin) !== ($family === 4 ? 4 : 16) || $len > $max) {
      $bad[] = $tok;
      continue;
    }
    if ($len === 0) {
      // Never allow a default-covering guard entry.
      $bad[] = $tok;
      continue;
    }
    $out = '';
    for ($i = 0; $i < strlen($bin); $i++) {
      $bits = max(0, min(8, $len - $i * 8));
      $mask = $bits === 0 ? 0 : (0xFF << (8 - $bits)) & 0xFF;
      $out .= chr(ord($bin[$i]) & $mask);
    }
    $norm = inet_ntop($out) . '/' . $len;
    $ok[$norm] = true;
  }
  return ['ok' => array_keys($ok), 'bad' => $bad];
}

/** Route guard: only OpenFabric routes inside the fabric prefixes reach the kernel. */
function frr_guard_lines(array $cfg) {
  if (($cfg['fabric_guard'] ?? 'yes') !== 'yes') {
    return ['! Fabric route guard: off'];
  }
  $v4 = frr_parse_prefixes($cfg['fabric_guard_v4'] ?? FRR_GUARD_V4_DEFAULT, 4);
  $v6 = frr_parse_prefixes($cfg['fabric_guard_v6'] ?? FRR_GUARD_V6_DEFAULT, 6);
  foreach (array_merge($v4['bad'], $v6['bad']) as $b) {
    frr_log('fabric guard: ignored invalid prefix ' . $b);
  }
  $lines = ['! Fabric route guard: OpenFabric routes outside these prefixes are not installed'];
  $seq = 5;
  foreach ($v4['ok'] as $p) {
    $len = (int)substr($p, strpos($p, '/') + 1);
    $lines[] = 'ip prefix-list FR-FABRIC-V4 seq ' . $seq . ' permit ' . $p . ($len < 32 ? ' le 32' : '');
    $seq += 5;
  }
  $lines[] = 'route-map FR-FABRIC-V4 permit 10';
  $lines[] = ' match ip address prefix-list FR-FABRIC-V4';
  $lines[] = 'exit';
  $lines[] = 'route-map FR-FABRIC-V4 deny 100';
  $lines[] = 'exit';
  $lines[] = 'ip protocol openfabric route-map FR-FABRIC-V4';
  $seq = 5;
  foreach ($v6['ok'] as $p) {
    $len = (int)substr($p, strpos($p, '/') + 1);
    $lines[] = 'ipv6 prefix-list FR-FABRIC-V6 seq ' . $seq . ' permit ' . $p . ($len < 128 ? ' le 128' : '');
    $seq += 5;
  }
  $lines[] = 'route-map FR-FABRIC-V6 permit 10';
  $lines[] = ' match ipv6 address prefix-list FR-FABRIC-V6';
  $lines[] = 'exit';
  $lines[] = 'route-map FR-FABRIC-V6 deny 100';
  $lines[] = 'exit';
  $lines[] = 'ipv6 protocol openfabric route-map FR-FABRIC-V6';
  return $lines;
}

function frr_read_piece($path) {
  if (!is_file($path) || !is_readable($path)) {
    return '';
  }
  $raw = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], (string)@file_get_contents($path));
  return trim($raw) === '' ? '' : rtrim($raw) . "\n";
}

function frr_hostname() {
  $h = preg_replace('/[^A-Za-z0-9._-]/', '-', (string)(gethostname() ?: 'unraid'));
  return $h !== '' ? $h : 'unraid';
}

/**
 * Build /etc/frr/frr.conf from the baseline, the ThunderboltNet fragment and frr.user.conf.
 */
function frr_build_conf(array $cfg) {
  $out = [
    FRR_CONF_HEADER . ' — rebuilt on Apply, array start and scripts/frr-reload.',
    '! Do not edit here. Your own FRR config: /boot/config/plugins/FabricRouting/frr.user.conf',
    'frr defaults traditional',
    'hostname ' . frr_hostname(),
    'log syslog informational',
    'service integrated-vtysh-config',
    '!',
  ];
  foreach (frr_guard_lines($cfg) as $l) {
    $out[] = $l;
  }
  $out[] = '!';
  $body = implode("\n", $out) . "\n";

  $tbn = frr_read_piece(frr_tbn_fragment_path());
  if ($tbn !== '') {
    $body .= "! BEGIN FabricRouting include: ThunderboltNet fragment\n" . $tbn
      . "! END FabricRouting include: ThunderboltNet fragment\n!\n";
  }
  $user = frr_read_piece(frr_user_conf_path());
  if ($user !== '') {
    $body .= "! BEGIN FabricRouting include: frr.user.conf\n" . $user
      . "! END FabricRouting include: frr.user.conf\n!\n";
  }
  return $body;
}

/**
 * One-time import: an /etc/frr/frr.conf written before FabricRouting owned the
 * file may hold hand edits. Keep them in frr.user.conf so a rebuild does not drop them.
 */
function frr_import_legacy_conf() {
  $path = frr_etc_dir() . '/frr.conf';
  $user = frr_user_conf_path();
  $flag = frr_cfg_dir() . '/.frr-conf-imported';
  if (!is_file($path) || is_file($user) || is_file($flag)) {
    return false;
  }
  $raw = str_replace("\r", '', (string)@file_get_contents($path));
  if (strpos($raw, FRR_CONF_HEADER) === 0) {
    return false;
  }
  // Only the first unmanaged frr.conf after the upgrade is considered; a later
  // `write memory` dump of the managed config must not be imported.
  frr_write_if_changed($flag, date('c') . "\n");
  // Drop the ThunderboltNet block (it has its own flash copy).
  $raw = (string)preg_replace('/! BEGIN ThunderboltNet OpenFabric.*?! END ThunderboltNet OpenFabric[^\n]*\n?/s', '', $raw);
  $keep = [];
  $meaningful = false;
  foreach (explode("\n", $raw) as $line) {
    $t = trim($line);
    if ($t === '' || $t[0] === '!' || preg_match('/^(frr (version|defaults)|hostname|log syslog|service integrated-vtysh-config|end)\b/', $t)
      || strpos($t, 'FR-FABRIC-') !== false) {
      continue;
    }
    $keep[] = rtrim($line);
    if ($t !== 'exit') {
      $meaningful = true;
    }
  }
  if (!$meaningful) {
    return false;
  }
  $data = "! Imported by FabricRouting from /etc/frr/frr.conf on " . date('c') . "\n" . implode("\n", $keep) . "\n";
  $ok = frr_write_if_changed($user, $data)['ok'];
  frr_log($ok ? 'imported hand edits from /etc/frr/frr.conf into frr.user.conf' : 'failed to import /etc/frr/frr.conf edits');
  return $ok;
}

/**
 * Patch (or create) the daemons file for the selected daemons.
 */
function frr_build_daemons($raw, array $cfg, $fragment) {
  if (trim((string)$raw) === '') {
    $raw = "vtysh_enable=yes\n"
      . "zebra_options=\"  -A 127.0.0.1 -s 90000000\"\n"
      . "fabricd_options=\"  -A 127.0.0.1\"\n"
      . "staticd_options=\"  -A 127.0.0.1\"\n";
  }
  $new = (string)$raw;
  foreach (frr_daemon_names() as $daemon) {
    $want = ($cfg['enable_' . $daemon] ?? 'no') === 'yes';
    if ($daemon === 'fabricd' && $fragment) {
      $want = true;
    }
    $val = $want ? 'yes' : 'no';
    if (preg_match('/^\s*' . $daemon . '\s*=/m', $new)) {
      $new = (string)preg_replace('/^\s*' . $daemon . '\s*=.*$/m', $daemon . '=' . $val, $new, 1);
    } else {
      $new = rtrim($new) . "\n" . $daemon . '=' . $val . "\n";
    }
  }
  return rtrim($new) . "\n";
}

/**
 * Write frr.conf, daemons and vtysh.conf from flash state.
 * @return array{ok:bool,changed:bool,fragment:bool,user:bool,errors:array}
 */
function frr_sync_config(array $cfg) {
  $etc = frr_etc_dir();
  if (!is_dir($etc)) {
    @mkdir($etc, 0755, true);
  }
  frr_import_legacy_conf();
  $fragment = frr_read_piece(frr_tbn_fragment_path()) !== '';
  $errors = [];
  $changed = false;

  $w = frr_write_if_changed($etc . '/frr.conf', frr_build_conf($cfg), 0640);
  $w['ok'] ? ($changed = $changed || $w['changed']) : ($errors[] = 'cannot write frr.conf');

  $dae = $etc . '/daemons';
  $raw = is_readable($dae) ? (string)@file_get_contents($dae) : '';
  $w = frr_write_if_changed($dae, frr_build_daemons($raw, $cfg, $fragment));
  $w['ok'] ? ($changed = $changed || $w['changed']) : ($errors[] = 'cannot write daemons');

  $vty = $etc . '/vtysh.conf';
  if (!is_file($vty)) {
    $w = frr_write_if_changed($vty, "! FabricRouting managed vtysh.conf\nservice integrated-vtysh-config\n", 0640);
    if (!$w['ok']) {
      $errors[] = 'cannot write vtysh.conf';
    }
  }

  return [
    'ok' => !$errors,
    'changed' => $changed,
    'fragment' => $fragment,
    'user' => frr_read_piece(frr_user_conf_path()) !== '',
    'errors' => $errors,
  ];
}

/** Enabled daemons according to the daemons file we wrote. */
function frr_enabled_daemons() {
  $out = [];
  $raw = (string)@file_get_contents(frr_etc_dir() . '/daemons');
  foreach (frr_daemon_names() as $d) {
    if (preg_match('/^\s*' . $d . '\s*=\s*yes\s*$/m', $raw)) {
      $out[] = $d;
    }
  }
  return $out;
}

function frr_stack_up() {
  $enabled = frr_enabled_daemons();
  if (!$enabled) {
    return false;
  }
  foreach ($enabled as $d) {
    if (!frr_daemon_running($d)) {
      return false;
    }
  }
  return true;
}

function frr_stack_any_running() {
  foreach (frr_daemon_names() as $d) {
    if (frr_daemon_running($d)) {
      return true;
    }
  }
  return false;
}

/**
 * Run frrinit.sh start|restart|stop with closed stdio and a hard timeout.
 */
function frr_init_run($action, $timeout_sec = 90) {
  $inner = escapeshellarg(frr_init_script()) . ' ' . escapeshellarg($action) . ' </dev/null >/dev/null 2>&1';
  $run = 'timeout -k 5 ' . (int)$timeout_sec . 's setsid bash -c ' . escapeshellarg($inner);
  $rc = 1;
  $o = [];
  @exec($run, $o, $rc);
  return $rc;
}

/**
 * Rebuild config, then start or restart FRR when it is installed and should run.
 * Restarts only when config or packages changed (or $force).
 *
 * @return array{ok:bool,action:string,error?:string,sync:array}
 */
function frr_stack_apply(array $cfg, $force = false) {
  $sync = frr_sync_config($cfg);
  if (!$sync['ok']) {
    frr_progress('  config write failed: ' . implode(', ', $sync['errors']));
    return ['ok' => false, 'action' => 'none', 'error' => implode(', ', $sync['errors']), 'sync' => $sync];
  }
  frr_progress('  frr.conf: baseline'
    . ($sync['fragment'] ? ' + ThunderboltNet fragment' : '')
    . ($sync['user'] ? ' + frr.user.conf' : '')
    . ($sync['changed'] ? ' (changed)' : ' (unchanged)'));

  if (!frr_installed()) {
    return ['ok' => true, 'action' => 'conf-only (FRR not installed)', 'sync' => $sync];
  }
  if (($cfg['start_frr'] ?? 'yes') !== 'yes') {
    return ['ok' => true, 'action' => 'conf-only (start disabled)', 'sync' => $sync];
  }

  $running = frr_stack_any_running();
  if ($running && !$sync['changed'] && !$force && frr_stack_up()) {
    frr_progress('  FRR running, nothing changed.');
    return ['ok' => true, 'action' => 'none', 'sync' => $sync];
  }
  $action = $running ? 'restart' : 'start';
  frr_progress('  frrinit.sh ' . $action . ' …');
  $rc = frr_init_run($action);
  for ($i = 0; $i < 20 && !frr_stack_up(); $i++) {
    usleep(400000);
  }
  if (!frr_stack_up()) {
    $missing = [];
    foreach (frr_enabled_daemons() as $d) {
      if (!frr_daemon_running($d)) {
        $missing[] = $d;
      }
    }
    $err = 'FRR ' . $action . ' rc=' . $rc . '; not running: ' . ($missing ? implode(' ', $missing) : 'all');
    frr_log($err);
    frr_progress('  FAILED — ' . $err);
    return ['ok' => false, 'action' => $action, 'error' => $err, 'sync' => $sync];
  }
  frr_log('frrinit.sh ' . $action . ' ok');
  frr_progress('  OK — ' . implode(' ', frr_enabled_daemons()) . ' up.');
  return ['ok' => true, 'action' => $action, 'sync' => $sync];
}

/**
 * Install packages listed (with sha256) in MANIFEST.txt.
 * @return array{ok:bool,rc:int,output:array,changed:bool}
 */
function frr_run_install_packages() {
  $script = frr_plugin_dir() . '/scripts/frr-install-packages';
  if (!is_file($script)) {
    return ['ok' => false, 'rc' => 127, 'output' => ['install script missing'], 'changed' => false];
  }
  $out = [];
  $rc = 1;
  @exec('bash ' . escapeshellarg($script) . ' 2>&1', $out, $rc);
  $changed = false;
  foreach ($out as $line) {
    if (strpos($line, 'INSTALLED ') === 0) {
      $changed = true;
    }
  }
  return ['ok' => $rc === 0, 'rc' => $rc, 'output' => $out, 'changed' => $changed];
}

/**
 * Locked entry point for Apply, package jobs and array-start rehydrate.
 *
 * @param array $opts download(bool) install(bool) rehydrate(bool) settings_apply(bool) lock_wait(int)
 */
function frr_apply($opts = []) {
  if (!is_array($opts)) {
    $opts = [];
  }
  if (!frr_lock_acquire((int)($opts['lock_wait'] ?? 0))) {
    $msg = 'Another Fabric Routing job is running. Wait for it to finish, then try again.';
    frr_progress($msg);
    return ['ok' => false, 'error' => $msg, 'actions' => [$msg]];
  }
  try {
    return frr_apply_inner($opts);
  } finally {
    frr_lock_release();
  }
}

/**
 * Array start: install from flash (no network), rebuild config, start FRR.
 */
function frr_rehydrate_local() {
  return frr_apply(['rehydrate' => true, 'lock_wait' => 600]);
}

/**
 * Hook for companion plugins (scripts/frr-reload): rebuild config and
 * start/restart FRR if it is installed and should run.
 */
function frr_reload_cli() {
  if (!frr_lock_acquire(120)) {
    fwrite(STDERR, "FabricRouting: lock busy, reload not done\n");
    return false;
  }
  try {
    frr_log('frr-reload requested');
    $r = frr_stack_apply(frr_load_cfg(), false);
    echo 'FabricRouting frr-reload: ' . ($r['ok'] ? 'ok' : 'FAILED') . ' (' . ($r['action'] ?? '') . ')'
      . (!empty($r['error']) ? ' ' . $r['error'] : '') . "\n";
    return !empty($r['ok']);
  } finally {
    frr_lock_release();
  }
}

function frr_apply_inner($opts = []) {
  $download = !empty($opts['download']);
  $settings_apply = !empty($opts['settings_apply']);
  $cfg = frr_load_cfg();
  $install = array_key_exists('install', $opts)
    ? !empty($opts['install'])
    : (($cfg['install_on_start'] ?? 'yes') === 'yes');
  $result = ['ok' => true, 'actions' => []];

  $title = $settings_apply ? 'Settings Apply' : (!empty($opts['rehydrate']) ? 'array start' : 'packages');
  frr_progress('=== Fabric Routing ' . $title . ' ===');

  // 1) Download (explicit package job only). Any failure stops the job.
  if ($download) {
    frr_progress('Step 1/3: download and verify packages…');
    $dl = frr_download_bundle();
    $result['download'] = $dl;
    if (empty($dl['ok'])) {
      $result['ok'] = false;
      $result['error'] = $dl['error'] ?? 'download failed';
      frr_progress('Step 1/3 FAILED: ' . $result['error']);
      return $result;
    }
    frr_progress('Step 1/3: packages verified.');
    if (!$install) {
      return $result;
    }
  } else {
    frr_progress('Step 1/3: no download.');
  }

  // 2) Install from flash (sha256-verified).
  $pkgs_changed = false;
  if ($install && is_file(frr_manifest_txt_path())) {
    frr_progress('Step 2/3: install verified packages…');
    frr_manifest_migrate();
    $ins = frr_run_install_packages();
    $result['install'] = $ins;
    foreach (array_slice($ins['output'], 0, 20) as $line) {
      frr_progress('  ' . $line);
    }
    if (empty($ins['ok'])) {
      $result['ok'] = false;
      $result['error'] = 'package install failed (rc=' . $ins['rc'] . ')';
      frr_progress('Step 2/3 FAILED: ' . $result['error']);
      return $result;
    }
    $pkgs_changed = !empty($ins['changed']);
    frr_progress('Step 2/3: packages ' . ($pkgs_changed ? 'installed.' : 'already installed.'));
  } else {
    frr_progress('Step 2/3: no install (' . ($install ? 'no packages on flash' : 'Install on array start is No') . ').');
  }

  // 3) Config + daemons.
  frr_progress('Step 3/3: config and daemons…');
  $st = frr_stack_apply($cfg, $pkgs_changed);
  $result['stack'] = $st;
  if (empty($st['ok'])) {
    $result['ok'] = false;
    $result['error'] = $st['error'] ?? 'FRR start failed';
  }
  frr_progress('Step 3/3: ' . ($st['ok'] ? 'OK' : 'FAILED') . ' (' . ($st['action'] ?? '') . ')');

  $det = frr_detect();
  frr_write_companion_marker($det);
  $result['detect'] = $det;
  frr_progress('Result: FRR ' . ($det['present'] ? 'installed' : 'not installed')
    . ' · zebra ' . ($det['running_zebra'] ? 'up' : 'down')
    . ' · fabricd ' . ($det['running_fabricd'] ? 'up' : 'down'));
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
  foreach (['/var/log/plugins/fabricrouting.plg', '/boot/config/plugins/fabricrouting.plg'] as $p) {
    $p = frr_p($p);
    if (is_readable($p) && preg_match('/ENTITY version "([^"]+)"/', (string)@file_get_contents($p), $m)) {
      return $m[1];
    }
  }
  return 'dev';
}

function frr_status() {
  $cfg = frr_load_cfg();
  $det = frr_detect();
  return [
    'plugin_version' => frr_plugin_version(),
    'cfg' => $cfg,
    'detect' => $det,
    'catalog_url' => frr_catalog_url($cfg),
    'bundle' => $det['bundle'],
    'fragment' => frr_read_piece(frr_tbn_fragment_path()) !== '',
    'user_conf' => frr_read_piece(frr_user_conf_path()) !== '',
    'thunderboltnet_present' => is_dir(frr_p('/usr/local/emhttp/plugins/ThunderboltNet')),
    'time' => date('c'),
  ];
}
