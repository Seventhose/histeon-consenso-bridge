<?php
/** Alla disinstallazione toglie le impostazioni del plugin; le pagine create restano, sono contenuti del sito. */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'consenso_bridge_impostazioni' );
