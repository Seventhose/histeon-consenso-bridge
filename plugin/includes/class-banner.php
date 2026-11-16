<?php
/** Il CSS del banner di Complianz: le variabili del framework messe dopo quelle di Complianz, e la loro rigenerazione. */

namespace ConsensoBridge;

defined( 'ABSPATH' ) || exit;

final class Banner {

	/** Aggancia il CSS del banner di Complianz, dopo le sue variabili. */
	public static function init(): void {
		add_filter( 'cmplz_cookiebanner_css', array( self::class, 'aggiungi' ), 20 );
	}

	/** Aggiunge al CSS di Complianz le variabili del framework attivo (con «Solo testi» solo le dimensioni); senza framework lo lascia com'è. */
	public static function aggiungi( $css ): string {
		$css = is_string( $css ) ? $css : '';

		$nostro = Framework::css( Framework::attivo(), Framework::solo_testi() );
		if ( '' === $nostro ) {
			return $css;
		}
		return $css . "\n" . $nostro;
	}

	/** Rigenera il CSS di tutti i banner di Complianz; restituisce quanti ne ha rigenerati. */
	public static function rigenera(): int {
		if ( ! function_exists( 'cmplz_get_cookiebanners' ) || ! class_exists( 'CMPLZ_COOKIEBANNER' ) ) {
			return 0;
		}

		$rigenerati = 0;
		foreach ( (array) cmplz_get_cookiebanners() as $banner ) {
			if ( empty( $banner->ID ) ) {
				continue;
			}
			// Si toglie il transient che impedisce una nuova generazione del CSS a pochi secondi dalla precedente.
			delete_transient( 'cmplz_generate_css_active' );
			( new \CMPLZ_COOKIEBANNER( (int) $banner->ID ) )->generate_css();
			++$rigenerati;
		}
		// Dopo generate_css() Complianz lancia 'cmplz_after_css_generation', che ascolta Static Bridge.
		return $rigenerati;
	}
}
