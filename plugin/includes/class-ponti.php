<?php
/** I ponti verso Lingua Bridge (testi da tradurre) e Static Bridge (pagine da rigenerare quando cambiano le opzioni). */

namespace ConsensoBridge;

defined( 'ABSPATH' ) || exit;

final class Ponti {

	/** Aggancia i due filtri dei plugin amici. */
	public static function init(): void {
		add_filter( 'lingua_bridge_wpml_string_domains', array( self::class, 'domini_lingua_bridge' ) );
		add_filter( 'static_bridge_complianz_options', array( self::class, 'opzioni_static_bridge' ) );
	}

	/** Aggiunge il dominio dei nostri testi ai domini che Lingua Bridge traduce, una volta sola. */
	public static function domini_lingua_bridge( $domini ): array {
		$domini = is_array( $domini ) ? $domini : array();
		if ( ! in_array( Impostazioni::DOMINIO, $domini, true ) ) {
			$domini[] = Impostazioni::DOMINIO;
		}
		return array_values( $domini );
	}

	/** Aggiunge la nostra opzione alle opzioni di Complianz che Static Bridge sorveglia, una volta sola. */
	public static function opzioni_static_bridge( $opzioni ): array {
		$opzioni = is_array( $opzioni ) ? $opzioni : array();
		if ( ! in_array( Impostazioni::OPZIONE, $opzioni, true ) ) {
			$opzioni[] = Impostazioni::OPZIONE;
		}
		return array_values( $opzioni );
	}
}
