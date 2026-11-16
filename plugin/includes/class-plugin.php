<?php
/** ConsensoBridge\Plugin: l'avvio del plugin, con la traduzione e i moduli agganciati. */

namespace ConsensoBridge;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** Carica la traduzione e aggancia i moduli; l'amministrazione solo nel pannello. */
	public static function init(): void {
		load_plugin_textdomain( 'consenso-bridge', false, dirname( plugin_basename( CONSENSO_BRIDGE_FILE ) ) . '/languages' );

		Banner::init();
		Ponti::init();
		Pagine::init();
		Blocco::init();

		if ( is_admin() ) {
			Admin::init();
		}
	}
}
