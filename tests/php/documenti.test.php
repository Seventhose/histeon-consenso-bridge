<?php
/** ConsensoBridge\Documenti: l'HTML dell'informativa sulla privacy e della dichiarazione di accessibilità. */

use ConsensoBridge\Documenti;

function cb_privacy_piena(): array {
	return array(
		'titolare'      => 'Edizioni Prova S.r.l.',
		'indirizzo'     => 'Via Roma 1, Milano',
		'paese'         => 'Italia',
		'email'         => 'privacy@example.test',
		'telefono'      => '+39 02 1234',
		'sito'          => 'Edizioni Prova',
		'url_cookie'    => 'https://example.test/cookie-policy/',
		'servizi'       => array( 'YouTube', 'Google Maps' ),
		'finalita'      => "Newsletter mensile\n\nInvio dei libri ordinati",
		'conservazione' => '',
		'aggiornato'    => '11 novembre 2026',
	);
}

/** I titoli h2 del documento, in ordine. */
function cb_titoli( string $html ): array {
	preg_match_all( '#<h2 class="consenso-documento__titolo">(.*?)</h2>#', $html, $m );
	return $m[1];
}

prova( 'paragrafi(): righe non vuote, spazi ai bordi tolti', function () {
	uguale( array( 'uno', 'due', 'tre' ), Documenti::paragrafi( "  uno \n\n due\r\ntre\n  \n" ) );
	uguale( array(), Documenti::paragrafi( " \n " ) );
} );

prova( 'privacy: contenitore BEM e sezioni nell\'ordine', function () {
	$html = Documenti::privacy( cb_privacy_piena() );
	uguale( 0, strpos( $html, '<div class="consenso-documento consenso-documento--privacy">' ) );
	uguale( '</div>', substr( rtrim( $html ), -6 ) );
	uguale(
		array(
			'Titolare del trattamento',
			'Quali dati trattiamo e perché',
			'A chi comunichiamo i dati',
			'Trasferimenti fuori dall&#039;Unione europea',
			'Per quanto tempo conserviamo i dati',
			'I tuoi diritti',
			'Aggiornamenti',
		),
		cb_titoli( $html )
	);
	uguale( 7, substr_count( $html, '<section class="consenso-documento__sezione">' ) );
} );

prova( 'privacy: titolare, indirizzo e contatti', function () {
	$html = Documenti::privacy( cb_privacy_piena() );
	contiene( '<p class="consenso-documento__testo">Il titolare del trattamento dei dati personali raccolti da questo sito è Edizioni Prova S.r.l., Via Roma 1, Milano, Italia.</p>', $html );
	contiene( '<p class="consenso-documento__testo">Per qualsiasi domanda sui tuoi dati puoi scrivere a <a class="consenso-documento__link" href="mailto:privacy@example.test">privacy@example.test</a> o telefonare al +39 02 1234.</p>', $html );
} );

prova( 'privacy: senza titolare vale il nome del sito; senza email niente frase dei contatti', function () {
	$dati = cb_privacy_piena();
	$dati['titolare'] = '';
	$dati['indirizzo'] = '';
	$dati['paese'] = '';
	$dati['email'] = '';
	$html = Documenti::privacy( $dati );
	contiene( 'raccolti da questo sito è Edizioni Prova.</p>', $html );
	non_contiene( 'Per qualsiasi domanda', $html );
	non_contiene( 'mailto:', $html );
} );

prova( 'privacy: le basi giuridiche e il link alla Cookie Policy', function () {
	$html = Documenti::privacy( cb_privacy_piena() );
	contiene( 'la base giuridica è il legittimo interesse del titolare (art. 6, par. 1, lett. f del Regolamento UE 2016/679, «GDPR»).', $html );
	contiene( 'la base giuridica è l&#039;esecuzione di misure precontrattuali o di un contratto (art. 6, par. 1, lett. b GDPR).', $html );
	contiene( 'Li usiamo come spiegato nella <a class="consenso-documento__link" href="https://example.test/cookie-policy/">Cookie Policy</a>.', $html );
	contiene( 'partono solo con il tuo consenso (art. 6, par. 1, lett. a GDPR), che puoi ritirare in ogni momento.', $html );
} );

prova( 'privacy: senza indirizzo della Cookie Policy (o con «#») niente link', function () {
	foreach ( array( '', '#' ) as $url ) {
		$dati = cb_privacy_piena();
		$dati['url_cookie'] = $url;
		$html = Documenti::privacy( $dati );
		contiene( 'Li usiamo come spiegato nella Cookie Policy.', $html );
		non_contiene( 'href="#"', $html );
	}
} );

prova( 'privacy: le finalità in più, un paragrafo per riga, dopo i cookie', function () {
	$html = Documenti::privacy( cb_privacy_piena() );
	contiene( '<p class="consenso-documento__testo">Newsletter mensile</p>', $html );
	contiene( '<p class="consenso-documento__testo">Invio dei libri ordinati</p>', $html );
	vero( strpos( $html, 'Newsletter mensile' ) > strpos( $html, 'Cookie Policy' ), 'dopo i cookie' );
	vero( strpos( $html, 'Newsletter mensile' ) < strpos( $html, 'A chi comunichiamo' ), 'nella stessa sezione' );
} );

prova( 'privacy: i servizi di terze parti in elenco, e i trasferimenti', function () {
	$html = Documenti::privacy( cb_privacy_piena() );
	contiene( '<p class="consenso-documento__testo">Il sito usa anche questi servizi di terze parti, che possono trattare dati dei visitatori:</p><ul class="consenso-documento__elenco"><li class="consenso-documento__voce">YouTube</li><li class="consenso-documento__voce">Google Maps</li></ul>', $html );
	contiene( 'Avviene solo verso paesi con una decisione di adeguatezza della Commissione europea o con le clausole contrattuali tipo (artt. 45 e 46 GDPR).', $html );
	contiene( 'nominati responsabili del trattamento. Non vendiamo i tuoi dati.', $html );
} );

prova( 'privacy: senza servizi una frase sola e niente sezione dei trasferimenti', function () {
	$dati = cb_privacy_piena();
	$dati['servizi'] = array();
	$html = Documenti::privacy( $dati );
	contiene( '<p class="consenso-documento__testo">Il sito non usa servizi di terze parti che trattano dati dei visitatori.</p>', $html );
	non_contiene( 'Trasferimenti fuori', $html );
	non_contiene( 'consenso-documento__elenco', $html );
} );

prova( 'privacy: conservazione di serie o scritta dall\'utente', function () {
	$html = Documenti::privacy( cb_privacy_piena() );
	contiene( 'I dati di navigazione si conservano per il tempo che serve alla sicurezza del sito.', $html );
	$dati = cb_privacy_piena();
	$dati['conservazione'] = "Ordini: dieci anni\nMessaggi: un anno";
	$html = Documenti::privacy( $dati );
	non_contiene( 'I dati di navigazione si conservano', $html );
	contiene( '<p class="consenso-documento__testo">Ordini: dieci anni</p><p class="consenso-documento__testo">Messaggi: un anno</p>', $html );
} );

prova( 'privacy: diritti, consenso, Garante', function () {
	$html = Documenti::privacy( cb_privacy_piena() );
	contiene( 'e puoi opporti al trattamento (artt. 15-22 GDPR). Per farlo scrivi a <a class="consenso-documento__link" href="mailto:privacy@example.test">privacy@example.test</a>.</p>', $html );
	contiene( 'Se il trattamento si basa sul consenso, puoi ritirarlo in ogni momento; quello che è stato fatto prima resta lecito.', $html );
	contiene( 'Puoi anche proporre reclamo al Garante per la protezione dei dati personali (<a class="consenso-documento__link" href="https://www.garanteprivacy.it">www.garanteprivacy.it</a>).', $html );
} );

prova( 'privacy: data di aggiornamento solo se c\'è', function () {
	contiene( '<p class="consenso-documento__testo">Questa informativa è stata aggiornata il 11 novembre 2026.</p>', Documenti::privacy( cb_privacy_piena() ) );
	$dati = cb_privacy_piena();
	$dati['aggiornato'] = '';
	$html = Documenti::privacy( $dati );
	non_contiene( 'Aggiornamenti', $html );
	non_contiene( 'è stata aggiornata', $html );
} );

prova( 'privacy: tutto quello che arriva dall\'esterno è escapato', function () {
	$dati = cb_privacy_piena();
	$dati['titolare'] = '<script>alert(1)</script>';
	$dati['servizi'] = array( '<b>X</b>' );
	$dati['finalita'] = '<img src=x onerror=alert(1)>';
	$dati['url_cookie'] = 'javascript:alert(1)';
	$html = Documenti::privacy( $dati );
	non_contiene( '<script>', $html );
	non_contiene( '<b>X</b>', $html );
	non_contiene( '<img', $html );
	non_contiene( 'javascript:', $html );
	contiene( '&lt;script&gt;', $html );
} );

prova( 'privacy: chiavi mancanti non danno errori', function () {
	$html = Documenti::privacy( array( 'sito' => 'S' ) );
	contiene( 'consenso-documento--privacy', $html );
	contiene( 'raccolti da questo sito è S.</p>', $html );
} );

function cb_accessibilita_piena(): array {
	return array(
		'titolare'    => 'Edizioni Prova S.r.l.',
		'sito'        => 'Edizioni Prova',
		'url'         => 'https://example.test/',
		'stato'       => 'parziale',
		'contenuti'   => "Alcuni PDF scansionati\nVideo senza sottotitoli",
		'valutazione' => 'autovalutazione',
		'data'        => '11 novembre 2026',
		'email'       => 'accessibilita@example.test',
	);
}

prova( 'accessibilità: contenitore BEM e sezioni nell\'ordine', function () {
	$html = Documenti::accessibilita( cb_accessibilita_piena() );
	uguale( 0, strpos( $html, '<div class="consenso-documento consenso-documento--accessibilita">' ) );
	uguale(
		array(
			'Impegno',
			'Stato di conformità',
			'Contenuti non accessibili',
			'Come è stata preparata questa dichiarazione',
			'Segnalazioni',
			'Procedura di attuazione',
		),
		cb_titoli( $html )
	);
} );

prova( 'accessibilità: impegno con il link al sito e il riferimento alle norme', function () {
	$html = Documenti::accessibilita( cb_accessibilita_piena() );
	contiene( '<p class="consenso-documento__testo">Edizioni Prova S.r.l. si impegna a rendere accessibile il sito <a class="consenso-documento__link" href="https://example.test/">Edizioni Prova</a>. Questa dichiarazione usa come riferimento la norma europea EN 301 549 e le Linee guida per l&#039;accessibilità dei contenuti web (WCAG) 2.1, livello AA.</p>', $html );
	$dati = cb_accessibilita_piena();
	$dati['url'] = '';
	$dati['titolare'] = '';
	contiene( 'Edizioni Prova si impegna a rendere accessibile il sito Edizioni Prova. Questa', Documenti::accessibilita( $dati ) );
} );

prova( 'accessibilità: le tre frasi dello stato', function () {
	$attese = array(
		'conforme'     => 'Il sito è conforme ai requisiti.',
		'parziale'     => 'Il sito è parzialmente conforme ai requisiti: alcuni contenuti, elencati sotto, non sono ancora accessibili.',
		'non_conforme' => 'Il sito non è ancora conforme ai requisiti.',
		'strano'       => 'Il sito è parzialmente conforme ai requisiti: alcuni contenuti, elencati sotto, non sono ancora accessibili.',
	);
	foreach ( $attese as $stato => $frase ) {
		$dati = cb_accessibilita_piena();
		$dati['stato'] = $stato;
		contiene( '<p class="consenso-documento__testo">' . $frase . '</p>', Documenti::accessibilita( $dati ), $stato );
	}
} );

prova( 'accessibilità: contenuti non accessibili in elenco, escapati', function () {
	$dati = cb_accessibilita_piena();
	$dati['contenuti'] .= "\n<em>Mappe</em>";
	$html = Documenti::accessibilita( $dati );
	contiene( '<ul class="consenso-documento__elenco"><li class="consenso-documento__voce">Alcuni PDF scansionati</li><li class="consenso-documento__voce">Video senza sottotitoli</li><li class="consenso-documento__voce">&lt;em&gt;Mappe&lt;/em&gt;</li></ul>', $html );
} );

prova( 'accessibilità: senza contenuti elencati', function () {
	$dati = cb_accessibilita_piena();
	$dati['contenuti'] = '';
	contiene( '<p class="consenso-documento__testo">Stiamo verificando quali contenuti non sono accessibili e li elencheremo qui.</p>', Documenti::accessibilita( $dati ) );
	$dati['stato'] = 'conforme';
	non_contiene( 'Contenuti non accessibili', Documenti::accessibilita( $dati ), 'conforme e senza contenuti: niente sezione' );
} );

prova( 'accessibilità: come è stata preparata', function () {
	$html = Documenti::accessibilita( cb_accessibilita_piena() );
	contiene( '<p class="consenso-documento__testo">La dichiarazione è stata preparata il 11 novembre 2026 con un&#039;autovalutazione.</p>', $html );
	$dati = cb_accessibilita_piena();
	$dati['valutazione'] = 'terzi';
	$dati['data'] = '';
	contiene( '<p class="consenso-documento__testo">La dichiarazione è stata preparata con una valutazione affidata a terzi.</p>', Documenti::accessibilita( $dati ) );
} );

prova( 'accessibilità: segnalazioni con o senza email, e AgID', function () {
	$html = Documenti::accessibilita( cb_accessibilita_piena() );
	contiene( '<p class="consenso-documento__testo">Se trovi un contenuto che non riesci a usare o vuoi segnalare un problema di accessibilità, scrivi a <a class="consenso-documento__link" href="mailto:accessibilita@example.test">accessibilita@example.test</a>. Ti rispondiamo entro 30 giorni.</p>', $html );
	contiene( '<p class="consenso-documento__testo">Se non ricevi risposta o la risposta non ti soddisfa, puoi rivolgerti all&#039;Agenzia per l&#039;Italia Digitale (AgID), che vigila sull&#039;accessibilità: <a class="consenso-documento__link" href="https://www.agid.gov.it">www.agid.gov.it</a>.</p>', $html );
	$dati = cb_accessibilita_piena();
	$dati['email'] = '';
	contiene( 'problema di accessibilità, usa i contatti indicati nel sito. Ti rispondiamo entro 30 giorni.</p>', Documenti::accessibilita( $dati ) );
} );

prova( 'accessibilità: niente HTML dall\'esterno', function () {
	$dati = cb_accessibilita_piena();
	$dati['sito'] = '<script>x</script>';
	$dati['url'] = 'javascript:alert(1)';
	$html = Documenti::accessibilita( $dati );
	non_contiene( '<script>', $html );
	non_contiene( 'javascript:', $html );
} );
