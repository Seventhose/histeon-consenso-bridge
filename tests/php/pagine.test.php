<?php
/** ConsensoBridge\Pagine (le parti pure) e ConsensoBridge\Blocco (senza Complianz). */

use ConsensoBridge\Blocco;
use ConsensoBridge\Impostazioni;
use ConsensoBridge\Pagine;

prova( 'TIPI e titoli delle pagine', function () {
	uguale( array( 'privacy', 'accessibilita' ), Pagine::TIPI );
	uguale( 'Informativa sulla privacy', Pagine::titolo( 'privacy' ) );
	uguale( 'Dichiarazione di accessibilità', Pagine::titolo( 'accessibilita' ) );
	uguale( '', Pagine::titolo( 'altro' ) );
} );

prova( 'contenuto(): un blocco shortcode', function () {
	uguale( '<!-- wp:shortcode -->[consenso_documento tipo="privacy"]<!-- /wp:shortcode -->', Pagine::contenuto( 'privacy' ) );
	uguale( '<!-- wp:shortcode -->[consenso_documento tipo="accessibilita"]<!-- /wp:shortcode -->', Pagine::contenuto( 'accessibilita' ) );
} );

prova( 'privacy_gestita_da_complianz(): solo premium con la privacy generata', function () {
	uguale( true, Pagine::privacy_gestita_da_complianz( true, 'generated' ) );
	uguale( false, Pagine::privacy_gestita_da_complianz( false, 'generated' ) );
	uguale( false, Pagine::privacy_gestita_da_complianz( true, 'custom' ) );
	uguale( false, Pagine::privacy_gestita_da_complianz( true, false ) );
} );

prova( 'nomi_servizi(): etichette di Complianz, poi social, poi statistiche, senza doppi', function () {
	uguale(
		array( 'YouTube', 'Google Maps', 'nuovo-servizio', 'Facebook', 'Google Analytics' ),
		Pagine::nomi_servizi(
			array( 'youtube', 'google-maps', 'nuovo-servizio', 'youtube' ),
			array( 'facebook' ),
			'google-analytics',
			array( 'youtube' => 'YouTube', 'google-maps' => 'Google Maps' ),
			array( 'facebook' => 'Facebook' )
		)
	);
} );

prova( 'nomi_servizi(): le statistiche note e quelle no', function () {
	$attese = array(
		'gtg'                => array( 'Google Analytics' ),
		'google-analytics'   => array( 'Google Analytics' ),
		'google-tag-manager' => array( 'Google Tag Manager' ),
		'matomo'             => array( 'Matomo' ),
		'matomo-tag-manager' => array( 'Matomo Tag Manager' ),
		'clicky'             => array( 'Clicky' ),
		'yandex'             => array( 'Yandex Metrica' ),
		'clarity'            => array( 'Microsoft Clarity' ),
		'yes'                => array(),
		'no'                 => array(),
		''                   => array(),
	);
	foreach ( $attese as $statistiche => $nomi ) {
		uguale( $nomi, Pagine::nomi_servizi( array(), array(), $statistiche, array(), array() ), $statistiche );
	}
} );

prova( 'nomi_servizi(): valori non stringa o non array ignorati', function () {
	uguale( array( 'YouTube' ), Pagine::nomi_servizi( array( 'youtube', 3, null, array( 'x' ) ), 'nessuno', 'no', array( 'youtube' => 'YouTube' ), array() ) );
} );

prova( 'Blocco: senza Complianz è spento anche con il flag acceso', function () {
	uguale( false, Blocco::complianz_attivo() );
	update_option( Impostazioni::OPZIONE, array( 'blocco' => true ) );
	uguale( false, Blocco::attivo() );
} );

prova( 'Blocco::config(): categoria e testi', function () {
	uguale(
		array(
			'categoria' => 'marketing',
			'testi'     => array(
				'video'      => 'Questo video è fornito da %s: caricandolo, il tuo browser contatta i suoi server. Per guardarlo serve il tuo consenso ai cookie di marketing.',
				'mappa'      => 'Questa mappa è fornita da %s: caricandola, il tuo browser contatta i suoi server. Per vederla serve il tuo consenso ai cookie di marketing.',
				'accetta'    => 'Accetta e mostra',
				'preferenze' => 'Gestisci le preferenze',
			),
		),
		Blocco::config()
	);
} );

prova( 'Blocco::config(): il filtro consenso_bridge_blocco_config', function () {
	add_filter( 'consenso_bridge_blocco_config', static function ( $c ) { $c['categoria'] = 'statistics'; return $c; } );
	uguale( 'statistics', Blocco::config()['categoria'] );
} );

prova( 'Blocco::init() aggancia wp_enqueue_scripts', function () {
	Blocco::init();
	uguale( 10, has_action( 'wp_enqueue_scripts', array( Blocco::class, 'carica' ) ) );
} );
