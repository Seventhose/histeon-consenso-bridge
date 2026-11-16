<?php
/** ConsensoBridge\Pagine: le pagine dell'informativa sulla privacy e della dichiarazione di accessibilità, collegate a Complianz. */

namespace ConsensoBridge;

defined( 'ABSPATH' ) || exit;

final class Pagine {

	const TIPI      = array( 'privacy', 'accessibilita' );
	const SHORTCODE = 'consenso_documento';

	/** Le statistiche di Complianz con il nome che si legge nel documento. */
	const STATISTICHE = array(
		'gtg'                => 'Google Analytics',
		'google-analytics'   => 'Google Analytics',
		'google-tag-manager' => 'Google Tag Manager',
		'matomo'             => 'Matomo',
		'matomo-tag-manager' => 'Matomo Tag Manager',
		'clicky'             => 'Clicky',
		'yandex'             => 'Yandex Metrica',
		'clarity'            => 'Microsoft Clarity',
	);

	public static function init(): void {
		add_shortcode( self::SHORTCODE, array( self::class, 'shortcode' ) );
	}

	/** Il titolo della pagina, per tipo. */
	public static function titolo( string $tipo ): string {
		if ( 'privacy' === $tipo ) {
			return __( 'Informativa sulla privacy', 'consenso-bridge' );
		}
		if ( 'accessibilita' === $tipo ) {
			return __( 'Dichiarazione di accessibilità', 'consenso-bridge' );
		}
		return '';
	}

	/** Il contenuto della pagina: solo lo shortcode del documento. */
	public static function contenuto( string $tipo ): string {
		return '<!-- wp:shortcode -->[' . self::SHORTCODE . ' tipo="' . esc_attr( $tipo ) . '"]<!-- /wp:shortcode -->';
	}

	/** Vero se la privacy è generata da Complianz premium (che fa già l'informativa). */
	public static function privacy_gestita_da_complianz( bool $premium, $scelta ): bool {
		return $premium && 'generated' === $scelta;
	}

	/** I nomi dei servizi, dei social e della statistica, senza doppi. */
	public static function nomi_servizi( $servizi, $social, $statistiche, array $etichette_servizi, array $etichette_social ): array {
		$nomi = array();
		foreach ( self::voci( $servizi ) as $servizio ) {
			$nomi[] = $etichette_servizi[ $servizio ] ?? $servizio;
		}
		foreach ( self::voci( $social ) as $profilo ) {
			$nomi[] = $etichette_social[ $profilo ] ?? $profilo;
		}
		$statistica = is_string( $statistiche ) ? ( self::STATISTICHE[ $statistiche ] ?? '' ) : '';
		if ( '' !== $statistica ) {
			$nomi[] = $statistica;
		}
		return array_values( array_unique( $nomi ) );
	}

	/** Crea (o riusa) la pagina del documento e la collega alle impostazioni e a Complianz. */
	public static function crea( string $tipo ) {
		if ( ! in_array( $tipo, self::TIPI, true ) ) {
			return new \WP_Error( 'consenso_bridge_tipo', __( 'Tipo di documento sconosciuto.', 'consenso-bridge' ) );
		}
		if ( 'privacy' === $tipo && self::privacy_gestita_da_complianz( defined( 'cmplz_premium' ), self::opzione_complianz( 'privacy-statement' ) ) ) {
			return new \WP_Error( 'consenso_bridge_privacy_complianz', __( 'L\'informativa sulla privacy la genera già Complianz.', 'consenso-bridge' ) );
		}

		$id = (int) Impostazioni::valore( 'pagina_' . $tipo );
		if ( ! self::pagina_riusabile( $id ) ) {
			$nuova = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => self::titolo( $tipo ),
					'post_content' => self::contenuto( $tipo ),
				),
				true
			);
			if ( is_wp_error( $nuova ) ) {
				return $nuova;
			}
			$id = (int) $nuova;
		}

		// Il salvataggio non tocca 'aggiornato': non è una modifica dei dati.
		$tutte                      = Impostazioni::tutte();
		$tutte[ 'pagina_' . $tipo ] = $id;
		update_option( Impostazioni::OPZIONE, $tutte, false );

		if ( 'privacy' === $tipo ) {
			$opzioni = get_option( 'cmplz_options', array() );
			$opzioni = is_array( $opzioni ) ? $opzioni : array();
			$opzioni['privacy-statement'] = 'custom';
			update_option( 'cmplz_options', $opzioni );
			update_option( 'cmplz_privacy-statement_custom_page', $id );
			self::svuota_link_del_banner();
		}

		return $id;
	}

	/** Lo shortcode [consenso_documento tipo="..."]: il documento del tipo indicato. */
	public static function shortcode( $atts ): string {
		$atts = is_array( $atts ) ? $atts : array();
		$tipo = (string) ( $atts['tipo'] ?? '' );

		if ( 'privacy' === $tipo ) {
			return Documenti::privacy( self::dati_privacy() );
		}
		if ( 'accessibilita' === $tipo ) {
			return Documenti::accessibilita( self::dati_accessibilita() );
		}
		return '';
	}

	/** La data in formato leggibile, secondo le impostazioni del sito. */
	public static function data_leggibile( string $ymd ): string {
		if ( '' === $ymd ) {
			return '';
		}
		return date_i18n( get_option( 'date_format' ), strtotime( $ymd ) );
	}

	/** Svuota la cache dei link del banner (transient page_links_<banner>_<lingua>), così il banner mostra subito i documenti nuovi. */
	private static function svuota_link_del_banner(): void {
		if ( function_exists( 'cmplz_delete_transients_by_prefix' ) ) {
			cmplz_delete_transients_by_prefix( 'page_links_' );
			return;
		}
		$cache = get_option( 'cmplz_transients', array() );
		if ( ! is_array( $cache ) ) {
			return;
		}
		foreach ( array_keys( $cache ) as $nome ) {
			if ( 0 === strpos( (string) $nome, 'page_links_' ) ) {
				unset( $cache[ $nome ] );
			}
		}
		update_option( 'cmplz_transients', $cache, false );
	}

	/** Il dato di Complianz, o stringa vuota se Complianz manca. */
	private static function opzione_complianz( string $nome ) {
		return function_exists( 'cmplz_get_option' ) ? cmplz_get_option( $nome ) : '';
	}

	/** Un dato di Complianz come testo. */
	private static function testo( $valore ): string {
		return is_scalar( $valore ) ? (string) $valore : '';
	}

	/** Le etichette di una proprietà della configurazione di Complianz (vuote se manca). */
	private static function etichette( string $proprieta ): array {
		if ( ! class_exists( 'COMPLIANZ' ) || ! isset( \COMPLIANZ::$config ) ) {
			return array();
		}
		return (array) ( \COMPLIANZ::$config->{$proprieta} ?? array() );
	}

	/** Le voci stringa di una lista, le altre ignorate. */
	private static function voci( $valore ): array {
		return is_array( $valore ) ? array_filter( $valore, 'is_string' ) : array();
	}

	/** Vero se l'id è una pagina esistente e non nel cestino. */
	private static function pagina_riusabile( int $id ): bool {
		if ( $id <= 0 ) {
			return false;
		}
		$pagina = get_post( $id );
		return $pagina instanceof \WP_Post && 'page' === $pagina->post_type && 'trash' !== $pagina->post_status;
	}

	/** I dati dell'informativa, da Complianz e dalle impostazioni. */
	private static function dati_privacy(): array {
		$codice = self::testo( self::opzione_complianz( 'country_company' ) );
		$paesi  = self::etichette( 'countries' );
		$paese  = isset( $paesi[ $codice ] ) ? (string) $paesi[ $codice ] : $codice;

		$url_cookie = '';
		if ( class_exists( 'COMPLIANZ' ) && isset( \COMPLIANZ::$document ) && is_object( \COMPLIANZ::$document ) && method_exists( \COMPLIANZ::$document, 'get_page_url' ) ) {
			$url_cookie = (string) \COMPLIANZ::$document->get_page_url( 'cookie-statement', 'eu' );
		}

		$servizi = self::nomi_servizi(
			'yes' === self::opzione_complianz( 'uses_thirdparty_services' ) ? self::opzione_complianz( 'thirdparty_services_on_site' ) : array(),
			'yes' === self::opzione_complianz( 'uses_social_media' ) ? self::opzione_complianz( 'socialmedia_on_site' ) : array(),
			self::testo( self::opzione_complianz( 'compile_statistics' ) ),
			self::etichette( 'thirdparty_services' ),
			self::etichette( 'thirdparty_socialmedia' )
		);

		return array(
			'titolare'     => self::testo( self::opzione_complianz( 'organisation_name' ) ),
			'indirizzo'    => implode( ', ', Documenti::paragrafi( self::testo( self::opzione_complianz( 'address_company' ) ) ) ),
			'paese'        => $paese,
			'email'        => self::testo( self::opzione_complianz( 'email_company' ) ),
			'telefono'     => self::testo( self::opzione_complianz( 'telephone_company' ) ),
			'sito'         => get_bloginfo( 'name' ),
			'url_cookie'   => $url_cookie,
			'servizi'      => $servizi,
			'finalita'     => Impostazioni::traduci( 'pr_finalita' ),
			'conservazione' => Impostazioni::traduci( 'pr_conservazione' ),
			'aggiornato'   => self::data_leggibile( (string) Impostazioni::valore( 'aggiornato' ) ),
		);
	}

	/** I dati della dichiarazione di accessibilità. */
	private static function dati_accessibilita(): array {
		$titolare = self::testo( self::opzione_complianz( 'organisation_name' ) );
		$email    = (string) Impostazioni::valore( 'acc_email' );

		return array(
			'titolare'    => '' !== $titolare ? $titolare : get_bloginfo( 'name' ),
			'sito'        => get_bloginfo( 'name' ),
			'url'         => home_url( '/' ),
			'stato'       => (string) Impostazioni::valore( 'acc_stato' ),
			'valutazione' => (string) Impostazioni::valore( 'acc_valutazione' ),
			'contenuti'   => Impostazioni::traduci( 'acc_contenuti' ),
			'data'        => self::data_leggibile( (string) Impostazioni::valore( 'acc_data' ) ),
			'email'       => '' !== $email ? $email : self::testo( self::opzione_complianz( 'email_company' ) ),
		);
	}
}
