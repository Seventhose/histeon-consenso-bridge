<?php
/**
 * ConsensoBridge\BozzaAccessibilita: la bozza della dichiarazione dall'ultimo esito dei controlli di Histeon.
 * La regola provata qui: mai «conforme», lo stato scelto non si tocca, al più «parzialmente conforme» con problemi.
 */

use ConsensoBridge\BozzaAccessibilita;

// Una finta xb_last_checks() di Histeon: restituisce quello che la prova mette in $GLOBALS['cb_xb_esito'].
if ( ! function_exists( 'xb_last_checks' ) ) {
	function xb_last_checks() {
		if ( ( $GLOBALS['cb_xb_esito'] ?? null ) instanceof Throwable ) {
			throw $GLOBALS['cb_xb_esito'];
		}
		return $GLOBALS['cb_xb_esito'] ?? null;
	}
}

/**
 * Un esito di Histeon. Di serie il conteggio delle pagine è pieno (3 su 3): senza un conteggio valido l'esito è
 * sempre incompleto (MEDIA-1), e le prove che guardano altro non direbbero più niente.
 */
function cb_esito_histeon( array $regole, int $errori = 2, int $avvisi = 3, mixed $pagine = array( 'checked' => 3, 'total' => 3 ) ): array {
	return array(
		'version'  => '1.4.0',
		'at'       => '2026-11-12T09:30:00+00:00',
		'errors'   => $errori,
		'warnings' => $avvisi,
		'rules'    => $regole,
		'pages'    => $pagine,
	);
}

/** L'esito di un Histeon che non manda `pages`: la chiave non c'è proprio. */
function cb_esito_senza_pagine( array $regole, int $errori = 2, int $avvisi = 3 ): array {
	$grezzo = cb_esito_histeon( $regole, $errori, $avvisi );
	unset( $grezzo['pages'] );
	return $grezzo;
}

/** Il riquadro come lo vede l'amministratore, dall'esito messo nella finta xb_last_checks(). */
function cb_riquadro( $grezzo, bool $stato_scelto = false, string $stato = 'parziale' ): string {
	$GLOBALS['cb_xb_esito'] = $grezzo;
	return BozzaAccessibilita::riquadro( BozzaAccessibilita::leggi(), $stato_scelto, $stato );
}

prova( 'funzione di Histeon assente: nessun esito e nessun riquadro', function () {
	uguale( null, BozzaAccessibilita::leggi( 'cb_funzione_che_non_esiste' ) );
	uguale( '', BozzaAccessibilita::riquadro( BozzaAccessibilita::leggi( 'cb_funzione_che_non_esiste' ), false, 'parziale' ) );
} );

prova( 'esito null di Histeon: nessun riquadro', function () {
	uguale( '', cb_riquadro( null ) );
} );

prova( 'esito con problemi di accessibilità: bozza con un punto per regola e al più «parzialmente conforme»', function () {
	$esito = BozzaAccessibilita::esito( cb_esito_histeon( array( 'img-alt' => 4, 'contrast' => 1 ) ) );
	$bozza = BozzaAccessibilita::bozza( $esito, false );

	uguale( '2026-11-12', $bozza['data'] );
	uguale( 'strumenti', $bozza['valutazione'] );
	uguale( array( 'Alcune immagini non hanno un testo alternativo.', 'In alcuni punti il testo ha poco contrasto con lo sfondo.' ), $bozza['contenuti'] );
	uguale( 'parziale', $bozza['stato'] );

	$html = cb_riquadro( cb_esito_histeon( array( 'img-alt' => 4, 'contrast' => 1 ) ) );
	contiene( 'Dall&#039;ultimo controllo di Histeon, del 2026-11-12: 2 errori e 3 avvisi', $html );
	contiene( '<li class="consenso-bozza__voce">Alcune immagini non hanno un testo alternativo.</li>', $html );
	contiene( 'Usa come bozza', $html );
	contiene( 'type="button"', $html, 'il pulsante non deve inviare il modulo' );
	contiene( 'data-cb-stato="parziale"', $html );
	contiene( 'data-cb-valutazione="strumenti"', $html );
	contiene( 'data-cb-data="2026-11-12"', $html );
	non_contiene( 'conforme"', $html );
} );

prova( 'esito pulito con tutte le pagine guardate: niente «conforme», stato e contenuti non toccati, la riga che ricorda il limite', function () {
	foreach ( array( false, true ) as $scelto ) {
		$bozza = BozzaAccessibilita::bozza( BozzaAccessibilita::esito( cb_esito_histeon( array(), 0, 0 ) ), $scelto );
		uguale( null, $bozza['stato'] );
		uguale( null, $bozza['contenuti'] );
		uguale( '2026-11-12', $bozza['data'] );
		uguale( 'strumenti', $bozza['valutazione'] );
	}

	$html = cb_riquadro( cb_esito_histeon( array(), 0, 0 ) );
	contiene( '0 errori e 0 avvisi', $html );
	contiene( 'Il controllo non ha trovato problemi di accessibilità.', $html );
	non_contiene( 'non dice quante pagine', $html );
	contiene( 'non basta a dichiarare il sito conforme', $html );
	non_contiene( 'data-cb-stato', $html );
	non_contiene( 'data-cb-contenuti', $html );
} );

prova( 'stato già scelto: la bozza non lo cambia, nemmeno con problemi', function () {
	foreach ( array( 'conforme', 'parziale', 'non_conforme' ) as $stato ) {
		$scelto = BozzaAccessibilita::stato_scelto( array( 'acc_stato' => $stato ) );
		uguale( true, $scelto, $stato );
		$bozza = BozzaAccessibilita::bozza( BozzaAccessibilita::esito( cb_esito_histeon( array( 'img-alt' => 1 ) ) ), $scelto );
		uguale( null, $bozza['stato'], $stato );
		non_contiene( 'data-cb-stato', cb_riquadro( cb_esito_histeon( array( 'img-alt' => 1 ) ), $scelto, $stato ), $stato );
	}
	contiene( 'Lo stato salvato è «Conforme»', cb_riquadro( cb_esito_histeon( array( 'img-alt' => 1 ) ), true, 'conforme' ) );
	uguale( false, BozzaAccessibilita::stato_scelto( array() ) );
	uguale( false, BozzaAccessibilita::stato_scelto( false ) );
	uguale( false, BozzaAccessibilita::stato_scelto( array( 'acc_stato' => 'inventato' ) ) );
} );

prova( 'regole non di accessibilità (page-heavy, noindex) e sconosciute non entrano nella bozza', function () {
	// Tutti i risultati spiegati da regole note che non sono di accessibilità: niente frasi, stato non toccato.
	$esito = BozzaAccessibilita::esito( cb_esito_histeon( array( 'page-heavy' => 4, 'noindex' => 1 ) ) );
	uguale( array(), $esito['regole'] );
	uguale( 0, $esito['non_descritti'] );
	$bozza = BozzaAccessibilita::bozza( $esito, false );
	uguale( null, $bozza['contenuti'] );
	uguale( null, $bozza['stato'] );

	// Una regola sconosciuta non diventa una frase, ma i suoi risultati restano «non descritti» (M-CB2).
	$esito = BozzaAccessibilita::esito( cb_esito_histeon( array( 'page-heavy' => 3, 'noindex' => 1, 'regola-nuova' => 1 ) ) );
	uguale( array(), $esito['regole'] );
	uguale( 1, $esito['non_descritti'] );
	uguale( null, BozzaAccessibilita::bozza( $esito, false )['contenuti'] );

	$misto = BozzaAccessibilita::esito( cb_esito_histeon( array( 'page-heavy' => 3, 'button-name' => 2 ) ) );
	uguale( array( 'button-name' => 2 ), $misto['regole'] );
} );

prova( 'esito non fidato: forme sbagliate danno null, voci sbagliate si scartano', function () {
	uguale( null, BozzaAccessibilita::esito( 'testo' ) );
	uguale( null, BozzaAccessibilita::esito( array() ) );
	uguale( null, BozzaAccessibilita::esito( array( 'errors' => '2' ) + cb_esito_histeon( array() ) ) );
	uguale( null, BozzaAccessibilita::esito( array( 'warnings' => -1 ) + cb_esito_histeon( array() ) ) );
	uguale( null, BozzaAccessibilita::esito( array( 'errors' => PHP_INT_MAX ) + cb_esito_histeon( array() ) ) );
	uguale( null, BozzaAccessibilita::esito( array( 'at' => 'ieri' ) + cb_esito_histeon( array() ) ) );
	uguale( null, BozzaAccessibilita::esito( array( 'rules' => 'img-alt' ) + cb_esito_histeon( array() ) ) );

	$esito = BozzaAccessibilita::esito( cb_esito_histeon( array( 'img-alt' => '3', 'contrast' => 0, 'h1-many' => 1.5, 'link-name' => -2, 0 => 4, 'field-label' => 1 ) ) );
	uguale( array( 'field-label' => 1 ), $esito['regole'] );

	$molte = array();
	for ( $i = 0; $i < 60; $i++ ) {
		$molte[ 'x-' . $i ] = 1;
	}
	$molte['img-alt'] = 1; // oltre le prime MAX_REGOLE voci: si scarta
	$esito            = BozzaAccessibilita::esito( cb_esito_histeon( $molte ) );
	uguale( array(), $esito['regole'] );
	uguale( 5, $esito['non_descritti'], 'i risultati delle voci scartate non spariscono' );
	uguale( 'parziale', BozzaAccessibilita::bozza( $esito, false )['stato'] );
} );

prova( 'risultati che il plugin non sa descrivere: «N problemi non descritti», mai «nessun problema» (M-CB2)', function () {
	foreach ( array(
		'regola sconosciuta' => array( 'aria-roles' => 3 ),
		'elenco vuoto'       => array(),
		'elenco scartato'    => array( 'img-alt' => '3' ),
	) as $nome => $regole ) {
		$grezzo = cb_esito_histeon( $regole, 3, 0 );
		$esito  = BozzaAccessibilita::esito( $grezzo );
		uguale( 3, $esito['non_descritti'], $nome );
		uguale( true, BozzaAccessibilita::incompleto( $esito ), $nome );
		$bozza = BozzaAccessibilita::bozza( $esito, false );
		uguale( 'parziale', $bozza['stato'], $nome );
		uguale( null, $bozza['contenuti'], $nome );
		$html = cb_riquadro( $grezzo );
		contiene( '3 problemi non descritti', $html, $nome );
		non_contiene( 'non ha trovato problemi', $html, $nome );
		contiene( 'data-cb-stato="parziale"', $html, $nome );
	}
	contiene( '1 problema non descritto', cb_riquadro( cb_esito_histeon( array( 'img-alt' => 1, 'regola-nuova' => 1 ), 2, 0 ) ) );
	// Con lo stato già scelto non si tocca, e un «Conforme» salvato riceve l'avviso.
	uguale( null, BozzaAccessibilita::bozza( BozzaAccessibilita::esito( cb_esito_histeon( array(), 3, 0 ) ), true )['stato'] );
	contiene( 'Lo stato salvato è «Conforme»', cb_riquadro( cb_esito_histeon( array(), 3, 0 ), true, 'conforme' ) );
} );

prova( 'pagine controllate meno di quelle del sito: mai meglio di «parzialmente conforme» (M-CB2 con M-B1)', function () {
	$grezzo = cb_esito_histeon( array(), 0, 0, array( 'checked' => 20, 'total' => 60 ) );
	$esito  = BozzaAccessibilita::esito( $grezzo );
	uguale( array( 'controllate' => 20, 'totali' => 60 ), $esito['pagine'] );
	uguale( 'parziale', BozzaAccessibilita::bozza( $esito, false )['stato'] );
	$html = cb_riquadro( $grezzo );
	contiene( 'Il controllo ha guardato 20 pagine su 60: delle altre non dice nulla.', $html );
	non_contiene( 'non ha trovato problemi', $html );
	non_contiene( 'non dice quante pagine', $html, 'il conteggio c\'è: la frase è quella delle pagine mancanti' );
	contiene( 'Lo stato salvato è «Conforme»', cb_riquadro( $grezzo, true, 'conforme' ) );

	// Nessuna pagina guardata su 5: il conteggio vale, e dice che manca tutto.
	$nessuna = cb_esito_histeon( array(), 0, 0, array( 'checked' => 0, 'total' => 5 ) );
	uguale( 'parziale', BozzaAccessibilita::bozza( BozzaAccessibilita::esito( $nessuna ), false )['stato'] );
	contiene( 'Il controllo ha guardato 0 pagine su 5', cb_riquadro( $nessuna ) );
	non_contiene( 'non ha trovato problemi', cb_riquadro( $nessuna ) );

	// Tutte le pagine, nessun problema: l'unico caso che resta «non ha trovato problemi».
	$tutte = cb_esito_histeon( array(), 0, 0, array( 'checked' => 60, 'total' => 60 ) );
	uguale( false, BozzaAccessibilita::incompleto( BozzaAccessibilita::esito( $tutte ) ) );
	uguale( null, BozzaAccessibilita::bozza( BozzaAccessibilita::esito( $tutte ), false )['stato'] );
	contiene( 'non ha trovato problemi', cb_riquadro( $tutte ) );
	non_contiene( 'pagine su', cb_riquadro( $tutte ) );
	non_contiene( 'non dice quante pagine', cb_riquadro( $tutte ) );
	non_contiene( 'Lo stato salvato è «Conforme»', cb_riquadro( $tutte, true, 'conforme' ) );
} );

prova( 'conteggio delle pagine assente, non valido o a zero: esito incompleto, mai «non ha trovato problemi» (MEDIA-1)', function () {
	$frase  = 'Il controllo non dice quante pagine ha guardato: delle pagine non dice nulla.';
	$grezzi = array( 'pages assente' => cb_esito_senza_pagine( array(), 0, 0 ) );
	foreach ( array(
		'null'                => null,
		'stringa'             => 'tutte',
		'elenco vuoto'        => array(),
		'zero su zero'        => array( 'checked' => 0, 'total' => 0 ),
		'checked > total'     => array( 'checked' => 61, 'total' => 60 ),
		'checked stringa'     => array( 'checked' => '20', 'total' => 60 ),
		'checked negativo'    => array( 'checked' => -1, 'total' => 3 ),
		'checked decimale'    => array( 'checked' => 1.5, 'total' => 3 ),
		'total assente'       => array( 'checked' => 3 ),
		'checked assente'     => array( 'total' => 3 ),
		'total non credibile' => array( 'checked' => 3, 'total' => BozzaAccessibilita::MAX_CONTEGGIO + 1 ),
	) as $nome => $pagine ) {
		$grezzi[ $nome ] = cb_esito_histeon( array(), 0, 0, $pagine );
	}

	foreach ( $grezzi as $nome => $grezzo ) {
		$esito = BozzaAccessibilita::esito( $grezzo );
		uguale( null, $esito['pagine'], $nome );
		uguale( 0, $esito['non_descritti'], $nome );
		uguale( true, BozzaAccessibilita::incompleto( $esito ), $nome );

		$bozza = BozzaAccessibilita::bozza( $esito, false );
		uguale( 'parziale', $bozza['stato'], $nome );
		uguale( null, $bozza['contenuti'], $nome );
		uguale( null, BozzaAccessibilita::bozza( $esito, true )['stato'], $nome . ': lo stato scelto non si tocca' );

		$html = cb_riquadro( $grezzo );
		contiene( '0 errori e 0 avvisi', $html, $nome );
		contiene( $frase, $html, $nome );
		non_contiene( 'non ha trovato problemi', $html, $nome );
		non_contiene( 'pagine su', $html, $nome );
		contiene( 'data-cb-stato="parziale"', $html, $nome );
		non_contiene( 'conforme"', $html, $nome );
		// Con lo stato già scelto non si tocca, e un «Conforme» salvato riceve l'avviso.
		non_contiene( 'data-cb-stato', cb_riquadro( $grezzo, true, 'conforme' ), $nome );
		contiene( 'Lo stato salvato è «Conforme»', cb_riquadro( $grezzo, true, 'conforme' ), $nome );
	}

	// Un esito() senza la voce `pagine` (costruito a mano) è incompleto anche lui.
	uguale( true, BozzaAccessibilita::incompleto( array( 'non_descritti' => 0 ) ) );

	// Con problemi trovati: i punti si mostrano come sempre, e in più la frase del conteggio che manca.
	$html = cb_riquadro( cb_esito_senza_pagine( array( 'img-alt' => 2 ), 2, 0 ) );
	contiene( '<li class="consenso-bozza__voce">Alcune immagini non hanno un testo alternativo.</li>', $html );
	contiene( $frase, $html );
	contiene( 'data-cb-stato="parziale"', $html );

	// Il conteggio pieno, senza problemi, resta «non ha trovato problemi» e non propone uno stato.
	$pieno = cb_esito_histeon( array(), 0, 0, array( 'checked' => 3, 'total' => 3 ) );
	uguale( array( 'controllate' => 3, 'totali' => 3 ), BozzaAccessibilita::esito( $pieno )['pagine'] );
	uguale( false, BozzaAccessibilita::incompleto( BozzaAccessibilita::esito( $pieno ) ) );
	uguale( null, BozzaAccessibilita::bozza( BozzaAccessibilita::esito( $pieno ), false )['stato'] );
	$html = cb_riquadro( $pieno );
	contiene( 'Il controllo non ha trovato problemi di accessibilità.', $html );
	non_contiene( $frase, $html );
	non_contiene( 'data-cb-stato', $html );
} );

prova( 'la bozza scrive che la valutazione è fatta con strumenti automatici e controlli a mano (M-CB1)', function () {
	$html = cb_riquadro( cb_esito_histeon( array( 'img-alt' => 1 ) ) );
	contiene( 'data-cb-valutazione="strumenti"', $html );
	contiene( 'strumenti automatici e controlli a mano', $html );
	$dati = array( 'valutazione' => BozzaAccessibilita::VALUTAZIONE, 'data' => '12 novembre 2026', 'stato' => 'parziale' );
	contiene( 'La dichiarazione è stata preparata il 12 novembre 2026 con un&#039;autovalutazione, fatta con strumenti automatici e controlli a mano.', \ConsensoBridge\Documenti::accessibilita( $dati ) );
	uguale( 'strumenti', \ConsensoBridge\Impostazioni::pulisci( array( 'acc_valutazione' => 'strumenti' ) )['acc_valutazione'] );
} );

prova( 'xb_last_checks() che lancia un errore: nessun riquadro', function () {
	uguale( '', cb_riquadro( new RuntimeException( 'rotto' ) ) );
} );

prova( 'i testi dell\'esito escono passati da esc_html/esc_attr', function () {
	// Le frasi sono del plugin, ma la data e i numeri vengono dall'esito: niente di grezzo arriva nell'HTML.
	$html = cb_riquadro( cb_esito_histeon( array( '<b>img-alt</b>' => 1, 'img-alt' => 1 ) ) );
	non_contiene( '<b>', $html );
	contiene( 'data-cb-contenuti="[&quot;Alcune immagini non hanno un testo alternativo.&quot;]"', $html );
} );
