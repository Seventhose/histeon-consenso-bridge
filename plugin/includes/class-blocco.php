<?php
/** ConsensoBridge\Blocco: lo script che blocca video e mappe di Histeon fino al consenso di marketing di Complianz. */

namespace ConsensoBridge;

defined( 'ABSPATH' ) || exit;

final class Blocco {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'carica' ) );
	}

	/** Vero se Complianz è attivo. */
	public static function complianz_attivo(): bool {
		return function_exists( 'cmplz_get_option' );
	}

	/** Vero se il blocco è acceso e Complianz è attivo. */
	public static function attivo(): bool {
		return true === Impostazioni::valore( 'blocco' ) && self::complianz_attivo();
	}

	/** La configurazione passata allo script, con il filtro consenso_bridge_blocco_config. */
	public static function config(): array {
		$config = array(
			'categoria' => 'marketing',
			'testi'     => array(
				'video'      => __( 'Questo video è fornito da %s: caricandolo, il tuo browser contatta i suoi server. Per guardarlo serve il tuo consenso ai cookie di marketing.', 'consenso-bridge' ),
				'mappa'      => __( 'Questa mappa è fornita da %s: caricandola, il tuo browser contatta i suoi server. Per vederla serve il tuo consenso ai cookie di marketing.', 'consenso-bridge' ),
				'accetta'    => __( 'Accetta e mostra', 'consenso-bridge' ),
				'preferenze' => __( 'Gestisci le preferenze', 'consenso-bridge' ),
			),
		);
		return (array) apply_filters( 'consenso_bridge_blocco_config', $config );
	}

	/** Carica lo stile del banner e, col blocco acceso, lo script con la sua configurazione. */
	public static function carica(): void {
		if ( self::complianz_attivo() ) {
			wp_enqueue_style(
				'consenso-bridge',
				plugins_url( 'assets/css/consenso-bridge.css', CONSENSO_BRIDGE_FILE ),
				array(),
				CONSENSO_BRIDGE_VERSION
			);
		}

		if ( ! self::attivo() ) {
			return;
		}

		wp_enqueue_script(
			'consenso-bridge-blocco',
			plugins_url( 'assets/js/blocco.js', CONSENSO_BRIDGE_FILE ),
			array(),
			CONSENSO_BRIDGE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_add_inline_script(
			'consenso-bridge-blocco',
			'window.consensoBridge = ' . wp_json_encode( self::config() ) . ';',
			'before'
		);
	}
}
