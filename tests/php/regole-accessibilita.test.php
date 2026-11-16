<?php
/** ConsensoBridge\RegoleAccessibilita: le regole dei controlli di Histeon che diventano frasi per la dichiarazione. */

use ConsensoBridge\RegoleAccessibilita;

$accessibilita = array(
	'h1-missing',
	'h1-many',
	'heading-skip',
	'heading-empty',
	'img-alt',
	'link-name',
	'link-vague',
	'button-name',
	'field-label',
	'iframe-title',
	'contrast',
	'lang-missing',
	'title-missing',
);

$altre = array(
	'img-src',
	'img-size',
	'img-big',
	'link-href',
	'link-noopener',
	'untranslated',
	'description-missing',
	'canonical-missing',
	'noindex',
	'site-hidden',
	'link-broken',
	'page-heavy',
);

foreach ( $accessibilita as $regola ) {
	prova( "RegoleAccessibilita::frase(): «{$regola}» dà una frase che finisce con un punto", function () use ( $regola ) {
		$frase = RegoleAccessibilita::frase( $regola );
		vero( is_string( $frase ) && '' !== trim( $frase ), "frase vuota per $regola" );
		vero( '.' === substr( $frase, -1 ), "la frase di $regola non finisce con un punto" );
	} );
}

foreach ( $altre as $regola ) {
	prova( "RegoleAccessibilita::frase(): «{$regola}» (non accessibilità) dà null", function () use ( $regola ) {
		uguale( null, RegoleAccessibilita::frase( $regola ) );
	} );
}

prova( 'RegoleAccessibilita::frase(): una regola inventata dà null', function () {
	uguale( null, RegoleAccessibilita::frase( 'regola-che-non-esiste' ) );
	uguale( null, RegoleAccessibilita::frase( '' ) );
} );

prova( 'RegoleAccessibilita::frase(): «title-missing» parla del titolo della scheda, non del titolo principale', function () {
	$frase = RegoleAccessibilita::frase( 'title-missing' );
	contiene( 'scheda del browser', $frase );
	contiene( 'risultati dei motori di ricerca', $frase );
	vero( RegoleAccessibilita::frase( 'h1-missing' ) !== $frase, 'title-missing e h1-missing hanno la stessa frase' );
} );

prova( 'RegoleAccessibilita::conosciuta(): le regole di accessibilità e le altre sì, una inventata no', function () use ( $accessibilita, $altre ) {
	foreach ( array_merge( $accessibilita, $altre ) as $regola ) {
		uguale( true, RegoleAccessibilita::conosciuta( $regola ), $regola );
	}
	uguale( $altre, RegoleAccessibilita::ALTRE );
	uguale( false, RegoleAccessibilita::conosciuta( 'regola-che-non-esiste' ) );
} );

/*
 * Le regole del plugin sono le stesse di Histeon: si legge ReleaseChecks::RULES dal builder accanto (o dal file in
 * XB_RELEASE_CHECKS). Senza il file la prova fallisce, salvo CB_SENZA_HISTEON=1, che la salta dicendolo.
 */
prova( 'RegoleAccessibilita: le stesse regole di ReleaseChecks::RULES del builder', function () use ( $accessibilita, $altre ) {
	$file = getenv( 'XB_RELEASE_CHECKS' ) ?: dirname( __DIR__, 3 ) . '/builder/plugin/src/Versioning/ReleaseChecks.php';
	if ( ! is_file( $file ) ) {
		if ( '1' === getenv( 'CB_SENZA_HISTEON' ) ) {
			echo "SALTATA: ReleaseChecks.php non trovato ($file), CB_SENZA_HISTEON=1\n";
			return;
		}
		throw new RuntimeException( "ReleaseChecks.php non trovato in $file: metti il percorso in XB_RELEASE_CHECKS (o CB_SENZA_HISTEON=1 per saltare)" );
	}
	vero( 1 === preg_match( '/const RULES = array\((.*?)\);/s', (string) file_get_contents( $file ), $m ), 'RULES non trovato in ' . $file );
	preg_match_all( "/'([a-z0-9-]+)'/", $m[1], $nomi );
	$histeon = $nomi[1];
	vero( count( $histeon ) > 0, 'RULES vuoto' );
	$plugin = array_merge( $accessibilita, $altre );
	sort( $histeon );
	sort( $plugin );
	uguale( $histeon, $plugin, 'regole di Histeon e di Consenso Bridge diverse: classifica le nuove in RegoleAccessibilita' );
	foreach ( $histeon as $regola ) {
		vero( RegoleAccessibilita::conosciuta( $regola ), "regola di Histeon non classificata: $regola" );
	}
} );
