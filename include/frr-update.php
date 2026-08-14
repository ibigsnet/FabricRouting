<?php
/**
 * Settings form #include after writing FabricRouting.cfg.
 * Settings only: daemons + start from flash — NEVER catalog download.
 * Package download is only via scripts/frr-packages-job (openBox).
 */
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) {
  @ob_end_flush();
}
ob_implicit_flush(true);

require_once '/usr/local/emhttp/plugins/FabricRouting/include/frr-lib.php';

frr_apply([
  'local_only' => true,
  'settings_apply' => true,
  'force_download' => false,
]);
