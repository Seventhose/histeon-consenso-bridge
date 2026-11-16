<?php
/** ConsensoBridge\Impostazioni: l'opzione unica del plugin, i valori di serie, la pulizia del modulo, i testi traducibili. */

use ConsensoBridge\Impostazioni;

prova( 'nome dell\'opzione e dominio dei testi', function () {
	uguale( 'consenso_bridge_impostazioni', Impostazioni::OPZIONE );
	uguale( 'consenso-bridge', Impostazioni::DOMINIO );
} );

prova( 'valori di serie: blocco spento, framework automatico', function () {
	uguale(
		array(
			'framework'       => 'auto',
			'blocco'          => false,
			'acc_stato'       => 'parziale',
			'acc_contenuti'   => '',
			'acc_valutazione' => 'autovalutazione',
			'acc_data'        => '',
			'acc_email'       => '',
			'pr_finalita'     => '',
			'pr_conservazione' => '',
			'pagina_privacy'  => 0,
			'pagina_accessibilita' => 0,
			'aggiornato'      => '',
		),
		Impostazioni::predefinite()
	);
} );

prova( 'tutte(): l\'opzione salvata sopra i valori di serie, chiavi sconosciute tolte', function () {
	update_option( Impostazioni::OPZIONE, array( 'blocco' => true, 'framework' => 'acss', 'altro' => 'x' ) );
	$tutte = Impostazioni::tutte();
	uguale( true, $tutte['blocco'] );
	uguale( 'acss', $tutte['framework'] );
	uguale( 'parziale', $tutte['acc_stato'] );
	vero( ! array_key_exists( 'altro', $tutte ), 'la chiave sconosciuta non deve restare' );
	uguale( array_keys( Impostazioni::predefinite() ), array_keys( $tutte ), 'stesse chiavi, stesso ordine' );
} );

prova( 'tutte(): un\'opzione rotta (non array) dà i valori di serie', function () {
	update_option( Impostazioni::OPZIONE, 'rotta' );
	uguale( Impostazioni::predefinite(), Impostazioni::tutte() );
} );

prova( 'valore(): una chiave, null se non esiste', function () {
	update_option( Impostazioni::OPZIONE, array( 'acc_email' => 'a@b.it' ) );
	uguale( 'a@b.it', Impostazioni::valore( 'acc_email' ) );
	uguale( false, Impostazioni::valore( 'blocco' ) );
	uguale( null, Impostazioni::valore( 'nessuna' ) );
} );

prova( 'pulisci(): il modulo inviato, con le caselle non spuntate a false', function () {
	$pulito = Impostazioni::pulisci(
		array(
			'framework'       => 'histeon',
			'blocco'          => '1',
			'acc_stato'       => 'conforme',
			'acc_contenuti'   => "Video senza sottotitoli\n<script>x</script>PDF scansionati",
			'acc_valutazione' => 'terzi',
			'acc_data'        => '2026-11-11',
			'acc_email'       => ' accessibilita@example.test ',
			'pr_finalita'     => 'Newsletter <b>mensile</b>',
			'pr_conservazione' => 'Dodici mesi',
			'pagina_privacy'  => '12',
			'pagina_accessibilita' => '-3',
			'aggiornato'      => '2020-01-01',
			'estranea'        => 'x',
		)
	);
	uguale( 'histeon', $pulito['framework'] );
	uguale( true, $pulito['blocco'] );
	uguale( 'conforme', $pulito['acc_stato'] );
	uguale( "Video senza sottotitoli\nxPDF scansionati", $pulito['acc_contenuti'] );
	uguale( 'terzi', $pulito['acc_valutazione'] );
	uguale( '2026-11-11', $pulito['acc_data'] );
	uguale( 'accessibilita@example.test', $pulito['acc_email'] );
	uguale( 'Newsletter mensile', $pulito['pr_finalita'] );
	uguale( 'Dodici mesi', $pulito['pr_conservazione'] );
	uguale( 12, $pulito['pagina_privacy'] );
	uguale( 3, $pulito['pagina_accessibilita'] );
	uguale( '2020-01-01', $pulito['aggiornato'] );
	vero( ! array_key_exists( 'estranea', $pulito ), 'chiave estranea tolta' );
	uguale( array_keys( Impostazioni::predefinite() ), array_keys( $pulito ), 'stesse chiavi, stesso ordine' );
} );

prova( 'pulisci(): valori fuori elenco tornano a quelli di serie, casella assente = false', function () {
	$pulito = Impostazioni::pulisci(
		array(
			'framework'       => 'bootstrap',
			'acc_stato'       => 'quasi',
			'acc_valutazione' => 'boh',
			'acc_data'        => '11/11/2026',
			'acc_email'       => 'non-una-email',
		)
	);
	uguale( 'auto', $pulito['framework'] );
	uguale( false, $pulito['blocco'] );
	uguale( 'testi', Impostazioni::pulisci( array( 'framework' => 'testi' ) )['framework'], '«Solo testi» è una scelta valida' );
	uguale( 'parziale', $pulito['acc_stato'] );
	uguale( 'autovalutazione', $pulito['acc_valutazione'] );
	uguale( '', $pulito['acc_data'] );
	uguale( '', $pulito['acc_email'] );
	uguale( 0, $pulito['pagina_privacy'] );
} );

prova( 'pulisci(): blocco vero solo con true, 1, "1", "on", "si"', function () {
	foreach ( array( true, 1, '1', 'on', 'si' ) as $si ) {
		uguale( true, Impostazioni::pulisci( array( 'blocco' => $si ) )['blocco'], var_export( $si, true ) );
	}
	foreach ( array( false, 0, '0', '', 'no', 'off', null ) as $no ) {
		uguale( false, Impostazioni::pulisci( array( 'blocco' => $no ) )['blocco'], var_export( $no, true ) );
	}
} );

prova( 'salva(): pulisce, mette la data di oggi in aggiornato, scrive l\'opzione e la restituisce', function () {
	$salvate = Impostazioni::salva( array( 'blocco' => 'on', 'acc_stato' => 'non_conforme', 'aggiornato' => '1999-01-01' ), '2026-11-11' );
	uguale( true, $salvate['blocco'] );
	uguale( 'non_conforme', $salvate['acc_stato'] );
	uguale( '2026-11-11', $salvate['aggiornato'] );
	uguale( $salvate, get_option( Impostazioni::OPZIONE ) );
} );

prova( 'salva(): le pagine già create restano se il modulo non le manda', function () {
	update_option( Impostazioni::OPZIONE, array( 'pagina_privacy' => 7, 'pagina_accessibilita' => 9 ) );
	$salvate = Impostazioni::salva( array( 'acc_stato' => 'conforme' ), '2026-11-11' );
	uguale( 7, $salvate['pagina_privacy'] );
	uguale( 9, $salvate['pagina_accessibilita'] );
} );

prova( 'salva(): i testi liberi non vuoti si registrano come stringhe da tradurre (API di WPML)', function () {
	Impostazioni::salva( array( 'acc_contenuti' => 'Mappe senza alternativa', 'pr_finalita' => '', 'pr_conservazione' => 'Due anni' ), '2026-11-11' );
	uguale(
		array(
			array( 'consenso-bridge', 'acc_contenuti', 'Mappe senza alternativa' ),
			array( 'consenso-bridge', 'pr_conservazione', 'Due anni' ),
		),
		cb_azioni( 'wpml_register_single_string' )
	);
} );

prova( 'TESTI_LIBERI: le chiavi che si traducono', function () {
	uguale( array( 'acc_contenuti', 'pr_finalita', 'pr_conservazione' ), Impostazioni::TESTI_LIBERI );
} );

prova( 'traduci(): passa dal filtro wpml_translate_single_string col dominio del plugin', function () {
	update_option( Impostazioni::OPZIONE, array( 'acc_contenuti' => 'Mappe' ) );
	add_filter(
		'wpml_translate_single_string',
		static fn( $valore, $dominio, $nome ) => 'consenso-bridge' === $dominio && 'acc_contenuti' === $nome ? 'Maps' : $valore,
		10,
		3
	);
	uguale( 'Maps', Impostazioni::traduci( 'acc_contenuti' ) );
	uguale( '', Impostazioni::traduci( 'pr_finalita' ) );
} );
