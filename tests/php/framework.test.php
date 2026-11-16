<?php
/** ConsensoBridge\Framework: quale framework ha il sito e come le variabili di Complianz prendono le sue. */

use ConsensoBridge\Framework;
use ConsensoBridge\Impostazioni;

prova( 'rileva(): la scelta esplicita vince', function () {
	uguale( 'acss', Framework::rileva( 'acss', true, false ) );
	uguale( 'histeon', Framework::rileva( 'histeon', false, true ) );
	uguale( 'nessuno', Framework::rileva( 'nessuno', true, true ) );
} );

prova( 'rileva(): in automatico Histeon prima di ACSS, poi nessuno', function () {
	uguale( 'histeon', Framework::rileva( 'auto', true, true ) );
	uguale( 'histeon', Framework::rileva( 'auto', true, false ) );
	uguale( 'acss', Framework::rileva( 'auto', false, true ) );
	uguale( 'nessuno', Framework::rileva( 'auto', false, false ) );
	uguale( 'nessuno', Framework::rileva( 'strano', false, false ), 'una scelta sconosciuta vale auto' );
} );

prova( 'attivo(): legge l\'impostazione (qui nessun framework definito)', function () {
	uguale( 'nessuno', Framework::attivo() );
	update_option( Impostazioni::OPZIONE, array( 'framework' => 'acss' ) );
	uguale( 'acss', Framework::attivo() );
} );

prova( 'mappa(acss): tutte le variabili di colore, bordo e testo di Complianz', function () {
	uguale(
		array(
			'cmplz_banner_background_color'             => 'var(--body-bg-color)',
			'cmplz_banner_border_color'                 => 'var(--border-color-dark)',
			'cmplz_banner_border_width'                 => 'var(--border-width)',
			'cmplz_banner_border_radius'                => 'var(--radius)',
			'cmplz_text_color'                          => 'var(--text-color)',
			'cmplz_hyperlink_color'                     => 'var(--link-color)',
			'cmplz_title_font_size'                     => 'var(--h4)',
			'cmplz_text_font_size'                      => 'var(--text-m)',
			'cmplz_link_font_size'                      => 'var(--text-m)',
			'cmplz_category_body_font_size'             => 'var(--text-m)',
			'cmplz_category_header_title_font_size'     => 'var(--text-m)',
			'cmplz_category_header_active_font_size'    => 'var(--text-m)',
			'cmplz_category_header_always_active_color' => 'var(--primary-dark)',
			'cmplz_button_font_size'                    => 'var(--text-m)',
			'cmplz_button_border_radius'                => 'var(--btn-radius)',
			'cmplz_button_accept_background_color'      => 'var(--primary-dark)',
			'cmplz_button_accept_border_color'          => 'var(--primary-dark)',
			'cmplz_button_accept_text_color'            => 'var(--white)',
			'cmplz_button_deny_background_color'        => 'var(--body-bg-color)',
			'cmplz_button_deny_border_color'            => 'var(--border-color-dark)',
			'cmplz_button_deny_text_color'              => 'var(--text-color)',
			'cmplz_button_settings_background_color'    => 'var(--body-bg-color)',
			'cmplz_button_settings_border_color'        => 'var(--border-color-dark)',
			'cmplz_button_settings_text_color'          => 'var(--text-color)',
			'cmplz_slider_active_color'                 => 'var(--primary)',
			'cmplz_slider_inactive_color'               => 'var(--neutral-light)',
			'cmplz_slider_bullet_color'                 => 'var(--white)',
		),
		Framework::mappa( 'acss' )
	);
} );

prova( 'mappa(histeon): le stesse chiavi, con i token --xb-*', function () {
	$mappa = Framework::mappa( 'histeon' );
	uguale( array_keys( Framework::mappa( 'acss' ) ), array_keys( $mappa ), 'stesse chiavi, stesso ordine' );
	uguale( 'var(--xb-surface)', $mappa['cmplz_banner_background_color'] );
	uguale( 'var(--xb-border-color)', $mappa['cmplz_banner_border_color'] );
	uguale( 'var(--xb-border-width)', $mappa['cmplz_banner_border_width'] );
	uguale( 'var(--xb-card-radius)', $mappa['cmplz_banner_border_radius'] );
	uguale( 'var(--xb-text)', $mappa['cmplz_text_color'] );
	uguale( 'var(--xb-link)', $mappa['cmplz_hyperlink_color'] );
	uguale( 'var(--xb-text-l)', $mappa['cmplz_title_font_size'] );
	uguale( 'var(--xb-text-m)', $mappa['cmplz_text_font_size'] );
	uguale( 'var(--xb-text-m)', $mappa['cmplz_button_font_size'] );
	uguale( 'var(--xb-primary)', $mappa['cmplz_category_header_always_active_color'] );
	uguale( 'var(--xb-btn-radius)', $mappa['cmplz_button_border_radius'] );
	uguale( 'var(--xb-primary)', $mappa['cmplz_button_accept_background_color'] );
	uguale( 'var(--xb-primary)', $mappa['cmplz_button_accept_border_color'] );
	uguale( 'var(--xb-on-primary)', $mappa['cmplz_button_accept_text_color'] );
	uguale( 'var(--xb-surface)', $mappa['cmplz_button_deny_background_color'] );
	uguale( 'var(--xb-border-color)', $mappa['cmplz_button_deny_border_color'] );
	uguale( 'var(--xb-text)', $mappa['cmplz_button_deny_text_color'] );
	uguale( 'var(--xb-surface)', $mappa['cmplz_button_settings_background_color'] );
	uguale( 'var(--xb-border-color)', $mappa['cmplz_button_settings_border_color'] );
	uguale( 'var(--xb-text)', $mappa['cmplz_button_settings_text_color'] );
	uguale( 'var(--xb-primary)', $mappa['cmplz_slider_active_color'] );
	uguale( 'var(--xb-border-color)', $mappa['cmplz_slider_inactive_color'] );
	uguale( 'var(--xb-surface)', $mappa['cmplz_slider_bullet_color'] );
	foreach ( $mappa as $chiave => $valore ) {
		vero( 1 === preg_match( '/^var\(--xb-[a-z0-9-]+\)$/', $valore ), "$chiave usa un token --xb-*" );
	}
} );

prova( 'mappa(nessuno) e sconosciuto: vuota', function () {
	uguale( array(), Framework::mappa( 'nessuno' ) );
	uguale( array(), Framework::mappa( 'tailwind' ) );
} );

prova( 'mappa(): il filtro consenso_bridge_mappa la può cambiare, con il framework come secondo argomento', function () {
	add_filter(
		'consenso_bridge_mappa',
		static function ( $mappa, $framework ) {
			$mappa['cmplz_button_accept_text_color'] = 'acss' === $framework ? 'var(--black)' : 'x';
			return $mappa;
		},
		10,
		2
	);
	uguale( 'var(--black)', Framework::mappa( 'acss' )['cmplz_button_accept_text_color'] );
} );

prova( 'css(acss): una regola :root con le variabili, una per riga, nell\'ordine della mappa', function () {
	$css = Framework::css( 'acss' );
	$righe = explode( "\n", trim( $css ) );
	uguale( ':root {', $righe[0] );
	uguale( "\t--cmplz_banner_background_color: var(--body-bg-color);", $righe[1] );
	uguale( "\t--cmplz_slider_bullet_color: var(--white);", $righe[ count( $righe ) - 2 ] );
	uguale( '}', $righe[ count( $righe ) - 1 ] );
	uguale( count( Framework::mappa( 'acss' ) ) + 2, count( $righe ) );
	non_contiene( '{}', $css, 'Complianz cancella le regole vuote' );
} );

prova( 'css(): vuoto senza framework; un valore con ; { } < viene scartato', function () {
	uguale( '', Framework::css( 'nessuno' ) );
	add_filter(
		'consenso_bridge_mappa',
		static function ( $mappa ) {
			$mappa['cmplz_text_color'] = 'red;} body{display:none';
			$mappa['cmplz_hyperlink_color'] = '</style><script>';
			return $mappa;
		}
	);
	$css = Framework::css( 'acss' );
	non_contiene( 'display:none', $css );
	non_contiene( '<script>', $css );
	non_contiene( '--cmplz_text_color', $css );
	contiene( '--cmplz_banner_background_color: var(--body-bg-color);', $css );
} );

prova( 'css(): un nome di variabile che non è cmplz_[a-z_]+ viene scartato', function () {
	add_filter(
		'consenso_bridge_mappa',
		static function ( $mappa ) {
			$mappa['x; color:red'] = 'var(--primary)';
			return $mappa;
		}
	);
	non_contiene( 'color:red', Framework::css( 'acss' ) );
} );

prova( 'rileva(): «Solo testi» riconosce il framework come l\'automatico', function () {
	uguale( 'histeon', Framework::rileva( 'testi', true, true ) );
	uguale( 'acss', Framework::rileva( 'testi', false, true ) );
	uguale( 'nessuno', Framework::rileva( 'testi', false, false ) );
} );

prova( 'solo_testi(): vero solo con la scelta «Solo testi»', function () {
	update_option( Impostazioni::OPZIONE, array( 'framework' => 'testi' ) );
	vero( Framework::solo_testi(), 'con «Solo testi»' );
	update_option( Impostazioni::OPZIONE, array( 'framework' => 'acss' ) );
	vero( ! Framework::solo_testi(), 'con ACSS' );
} );

prova( 'css( …, true ): solo le dimensioni dei testi, nessun colore né bordo', function () {
	foreach ( array( 'acss', 'histeon' ) as $framework ) {
		$css   = Framework::css( $framework, true );
		$righe = explode( "\n", trim( $css ) );
		uguale( ':root {', $righe[0] );
		uguale( '}', $righe[ count( $righe ) - 1 ] );
		foreach ( array_slice( $righe, 1, -1 ) as $riga ) {
			uguale( 1, preg_match( '/^\t--cmplz_[a-z_]+_font_size: var\([^)]+\);$/', $riga ), $framework . ': ' . $riga );
		}
		$attese = count( array_filter( array_keys( Framework::mappa( $framework ) ), static fn( $k ) => str_ends_with( $k, '_font_size' ) ) );
		uguale( $attese + 2, count( $righe ), $framework . ': tutte le dimensioni della mappa' );
		non_contiene( '_color', $css );
		non_contiene( 'radius', $css );
	}
	contiene( "\t--cmplz_text_font_size: var(--text-m);", Framework::css( 'acss', true ) );
	uguale( '', Framework::css( 'nessuno', true ) );
} );
