<?php
/**
 * #include after writing UnraidFRR.cfg
 * Output goes to Unraid progressFrame — leave that window open until finished
 * (same UX as plugin install, Docker update, Nvidia driver download).
 */
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) {
  @ob_end_flush();
}
ob_implicit_flush(true);

require_once '/usr/local/emhttp/plugins/UnraidFRR/include/frr-lib.php';

frr_apply();
