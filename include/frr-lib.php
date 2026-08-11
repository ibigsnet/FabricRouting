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

function frr_load_cfg() {
  $defaults = [
    'install_on_start' => 'yes',
    'enable_zebra' => 'yes',
    'enable_fabricd' => 'yes',
    'enable_bgpd' => 'no',
    'enable_ospfd' => 'no',
    'enable_ospf6d' => 'no',
    'enable_isisd' => 'no',
    'enable_bfdd' => 'no',
    'enable_staticd' => 'yes',
    'auto_download' => 'no',
    'package_base_url' => '',
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

  if ($out['present']) {
    $out['note'] = 'FRR tools present on this host';
  } elseif ($out['packages_on_flash']) {
    $out['note'] = 'Packages on flash but FRR not live — run Apply or reboot (array start install)';
  } else {
    $out['note'] = 'No FRR binaries and no packages in ' . $dir . ' — idle (safe)';
  }

  return $out;
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
  $cmds = [
    'systemctl start frr',
    'systemctl restart frr',
    '/usr/lib/frr/frrinit.sh start',
    '/usr/lib/frr/frrinit.sh restart',
    'service frr start',
    'service frr restart',
  ];
  foreach ($cmds as $cmd) {
    $rc = 1;
    @exec($cmd . ' 2>/dev/null', $o, $rc);
    if ($rc === 0) {
      frr_log('start: ' . $cmd);
      return ['ok' => true, 'cmd' => $cmd];
    }
  }
  return ['ok' => false, 'error' => 'could not start frr service (package may lack unit)'];
}

/**
 * Full Apply path from UI / array start.
 */
function frr_apply() {
  $cfg = frr_load_cfg();
  $result = [
    'ok' => true,
    'actions' => [],
    'detect_before' => frr_detect(),
  ];

  @mkdir(frr_packages_dir(), 0755, true);

  $det = $result['detect_before'];
  if (!$det['present'] && empty($det['packages_on_flash'])) {
    $result['actions'][] = 'idle: no packages and no live FRR';
    frr_write_companion_marker();
    $result['detect'] = frr_detect();
    return $result;
  }

  if (($cfg['install_on_start'] ?? 'yes') === 'yes' && !empty($det['packages_on_flash'])) {
    $ins = frr_run_install_packages();
    $result['install'] = $ins;
    $result['actions'][] = $ins['ok'] ? 'packages installed/checked' : 'package install failed or skipped';
  }

  // Safety: never touch Unraid network.cfg; never sysctl ip_forward here.
  // Baseline conf must not auto-enroll eth*/br*.
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
  return [
    'plugin_version' => frr_plugin_version(),
    'cfg' => frr_load_cfg(),
    'detect' => frr_detect(),
    'companion' => is_readable(frr_companion_path()) ? json_decode((string)file_get_contents(frr_companion_path()), true) : null,
    'thunderboltnet_present' => is_dir('/usr/local/emhttp/plugins/ThunderboltNet'),
    'time' => date('c'),
  ];
}
