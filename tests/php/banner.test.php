<?php
/** ConsensoBridge\Banner e ConsensoBridge\Ponti: il CSS dentro il file del banner, Lingua Bridge e Static Bridge. */

use ConsensoBridge\Banner;
use ConsensoBridge\Impostazioni;
use ConsensoBridge\Ponti;

prova( 'Banner::init() aggancia cmplz_cookiebanner_css a priorità 20', function () {
	Banner::init();
	uguale( 20, has_filter( 'cmplz_cookiebanner_css', array( Banner::class, 'aggiungi' ) ) );
} );

prova( 'Banner::aggiungi(): senza framework il CSS di Complianz resta identico', function () {
	uguale( ':root{--cmplz_text_color:black;}', Banner::aggiungi( ':root{--cmplz_text_color:black;}' ) );
} );

prova( 'Banner::aggiungi(): con ACSS le variabili vengono dopo quelle di Complianz', function () {
	update_option( Impostazioni::OPZIONE, array( 'framework' => 'acss' ) );
	$css = Banner::aggiungi( ':root{--cmplz_text_color:black;}' );
	uguale( 0, strpos( $css, ':root{--cmplz_text_color:black;}' ), 'il CSS di Complianz resta in testa' );
	contiene( "\n:root {\n\t--cmplz_banner_background_color: var(--body-bg-color);", $css );
	vero( strpos( $css, 'var(--text-color)' ) > strpos( $css, 'black' ), 'le nostre variabili dopo' );
} );

prova( 'Banner::aggiungi(): un valore non stringa vale vuoto', function () {
	update_option( Impostazioni::OPZIONE, array( 'framework' => 'histeon' ) );
	$css = Banner::aggiungi( null );
	uguale( 0, strpos( $css, "\n:root {" ) );
	contiene( 'var(--xb-on-primary)', $css );
} );

prova( 'Banner::rigenera(): senza Complianz non fa niente e restituisce 0', function () {
	uguale( 0, Banner::rigenera() );
} );

prova( 'Ponti::init() aggancia Lingua Bridge e Static Bridge', function () {
	Ponti::init();
	uguale( 10, has_filter( 'lingua_bridge_wpml_string_domains', array( Ponti::class, 'domini_lingua_bridge' ) ) );
	uguale( 10, has_filter( 'static_bridge_complianz_options', array( Ponti::class, 'opzioni_static_bridge' ) ) );
} );

prova( 'Ponti: Lingua Bridge traduce anche le stringhe del dominio consenso-bridge, una volta sola', function () {
	uguale( array( 'complianz', 'consenso-bridge' ), Ponti::domini_lingua_bridge( array( 'complianz' ) ) );
	uguale( array( 'complianz', 'consenso-bridge' ), Ponti::domini_lingua_bridge( array( 'complianz', 'consenso-bridge' ) ) );
	uguale( array( 'consenso-bridge' ), Ponti::domini_lingua_bridge( null ) );
} );

prova( 'Ponti: Static Bridge sorveglia anche l\'opzione del plugin, una volta sola', function () {
	uguale( array( 'cmplz_options', 'consenso_bridge_impostazioni' ), Ponti::opzioni_static_bridge( array( 'cmplz_options' ) ) );
	uguale( array( 'consenso_bridge_impostazioni' ), Ponti::opzioni_static_bridge( array( 'consenso_bridge_impostazioni' ) ) );
	uguale( array( 'consenso_bridge_impostazioni' ), Ponti::opzioni_static_bridge( 'x' ) );
} );

prova( 'Banner::aggiungi(): con «Solo testi» passano le dimensioni, non i colori', function () {
	update_option( Impostazioni::OPZIONE, array( 'framework' => 'testi' ) );
	add_filter(
		'consenso_bridge_mappa',
		static function ( $mappa ) {
			$mappa['cmplz_text_font_size']                 = 'var(--text-m)';
			$mappa['cmplz_button_accept_background_color'] = 'var(--primary)';
			return $mappa;
		}
	);
	$css = Banner::aggiungi( ':root{--cmplz_text_color:black;}' );
	contiene( '--cmplz_text_font_size: var(--text-m);', $css );
	non_contiene( 'var(--primary)', $css );
	uguale( 0, strpos( $css, ':root{--cmplz_text_color:black;}' ) );
} );
