<?php
/** Le impostazioni del plugin: un'unica opzione, i valori di serie, la pulizia del modulo e i testi da tradurre. */

namespace ConsensoBridge;

defined( 'ABSPATH' ) || exit;

final class Impostazioni {

	const OPZIONE      = 'consenso_bridge_impostazioni';
	const DOMINIO      = 'consenso-bridge';
	const TESTI_LIBERI = array( 'acc_contenuti', 'pr_finalita', 'pr_conservazione' );

	/** I valori di serie, nell'ordine in cui vanno salvati. */
	public static function predefinite(): array {
		return array(
			'framework'            => 'auto',
			'blocco'               => false,
			'acc_stato'            => 'parziale',
			'acc_contenuti'        => '',
			'acc_valutazione'      => 'autovalutazione',
			'acc_data'             => '',
			'acc_email'            => '',
			'pr_finalita'          => '',
			'pr_conservazione'     => '',
			'pagina_privacy'       => 0,
			'pagina_accessibilita' => 0,
			'aggiornato'           => '',
		);
	}

	/** Le impostazioni salvate sopra i valori di serie; le chiavi sconosciute non passano. */
	public static function tutte(): array {
		$salvate = get_option( self::OPZIONE, array() );
		if ( ! is_array( $salvate ) ) {
			$salvate = array();
		}

		$tutte = array();
		foreach ( self::predefinite() as $chiave => $serie ) {
			$tutte[ $chiave ] = array_key_exists( $chiave, $salvate ) ? $salvate[ $chiave ] : $serie;
		}
		return $tutte;
	}

	public static function valore( string $chiave ) {
		return self::tutte()[ $chiave ] ?? null;
	}

	/** Ripulisce il modulo inviato: valori fuori elenco tornano a quelli di serie. */
	public static function pulisci( array $in ): array {
		$pulite = array();
		foreach ( self::predefinite() as $chiave => $serie ) {
			$v = $in[ $chiave ] ?? null;

			$pulite[ $chiave ] = match ( $chiave ) {
				'framework'       => self::scelta( $v, array( 'auto', 'acss', 'histeon', 'testi', 'nessuno' ), 'auto' ),
				'blocco'          => in_array( $v, array( true, 1, '1', 'on', 'si' ), true ),
				'acc_stato'       => self::scelta( $v, array( 'conforme', 'parziale', 'non_conforme' ), 'parziale' ),
				'acc_valutazione' => self::scelta( $v, array( 'autovalutazione', 'strumenti', 'terzi' ), 'autovalutazione' ),
				'acc_data', 'aggiornato' => is_string( $v ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '',
				'acc_email'       => sanitize_email( self::testo( $v ) ),
				'acc_contenuti', 'pr_finalita', 'pr_conservazione' => sanitize_textarea_field( self::testo( $v ) ),
				'pagina_privacy', 'pagina_accessibilita' => absint( self::testo( $v ) ),
				default           => $serie,
			};
		}
		return $pulite;
	}

	/** Salva il modulo pulito e registra i testi liberi da tradurre. */
	public static function salva( array $in, ?string $oggi = null ): array {
		$tutte = self::tutte();
		foreach ( array( 'pagina_privacy', 'pagina_accessibilita' ) as $pagina ) {
			if ( ! array_key_exists( $pagina, $in ) ) {
				$in[ $pagina ] = $tutte[ $pagina ];
			}
		}

		$pulite               = self::pulisci( $in );
		$pulite['aggiornato'] = $oggi ?? current_time( 'Y-m-d' );
		update_option( self::OPZIONE, $pulite, false );

		foreach ( self::TESTI_LIBERI as $chiave ) {
			if ( '' !== $pulite[ $chiave ] ) {
				do_action( 'wpml_register_single_string', self::DOMINIO, $chiave, $pulite[ $chiave ] );
			}
		}
		return $pulite;
	}

	/** Il testo di una impostazione nella lingua del visitatore, passando da Lingua Bridge (API di WPML). */
	public static function traduci( string $chiave ): string {
		return (string) apply_filters( 'wpml_translate_single_string', (string) self::valore( $chiave ), self::DOMINIO, $chiave );
	}

	private static function scelta( $valore, array $ammesse, string $predefinita ): string {
		return is_string( $valore ) && in_array( $valore, $ammesse, true ) ? $valore : $predefinita;
	}

	private static function testo( $valore ): string {
		return is_scalar( $valore ) ? (string) $valore : '';
	}
}
