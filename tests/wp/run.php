<?php
/**
 * Consenso Bridge su un WordPress vero (dev/test.sh): Complianz 7.5.7 e Lingua Bridge attivi. Una riga PASS/FAIL per
 * controllo, in fondo OK o FAILURES: N (ed esce con 1). Rimette a posto opzioni e pagine alla fine.
 */

use ConsensoBridge\Admin;
use ConsensoBridge\Banner;
use ConsensoBridge\Blocco;
use ConsensoBridge\Framework;
use ConsensoBridge\Impostazioni;
use ConsensoBridge\Pagine;
use ConsensoBridge\Ponti;

$fallite = 0;
$controlla = static function ( bool $ok, string $nome ) use ( &$fallite ): void {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $nome . "\n";
	$fallite += $ok ? 0 : 1;
};
wp_set_current_user( 1 );

$salvate         = get_option( 'consenso_bridge_impostazioni', null );
$cmplz_salvate   = get_option( 'cmplz_options', array() );
$cmplz_pagina    = get_option( 'cmplz_privacy-statement_custom_page', null );
$create          = array();
$ripristina_enqueue = static function (): void {
	$GLOBALS['wp_scripts'] = null;
	$GLOBALS['wp_styles']  = null;
	wp_scripts();
	wp_styles();
};

try {
	// Il plugin e i suoi agganci.
	$controlla( defined( 'CONSENSO_BRIDGE_VERSION' ) && '0.1.0' === CONSENSO_BRIDGE_VERSION, 'versione 0.1.0' );
	$controlla( defined( 'CONSENSO_BRIDGE_FILE' ) && is_file( CONSENSO_BRIDGE_FILE ) && 'consenso-bridge.php' === basename( CONSENSO_BRIDGE_FILE ), 'CONSENSO_BRIDGE_FILE' );
	$controlla( 20 === has_filter( 'cmplz_cookiebanner_css', array( Banner::class, 'aggiungi' ) ), 'banner agganciato a Complianz' );
	$controlla( shortcode_exists( 'consenso_documento' ), 'shortcode consenso_documento' );
	$controlla( in_array( 'consenso-bridge', (array) apply_filters( 'lingua_bridge_wpml_string_domains', array( 'complianz' ) ), true ), 'Lingua Bridge traduce il dominio consenso-bridge' );
	$controlla( in_array( 'consenso_bridge_impostazioni', (array) apply_filters( 'static_bridge_complianz_options', array() ), true ), 'Static Bridge sorveglia l\'opzione' );
	$controlla( 10 === has_action( 'wp_enqueue_scripts', array( Blocco::class, 'carica' ) ), 'blocco agganciato a wp_enqueue_scripts' );

	// Il CSS del banner: rigenerato da Complianz con le variabili di ACSS.
	delete_option( 'consenso_bridge_impostazioni' );
	$controlla( 'nessuno' === Framework::attivo(), 'sulla pila di prova nessun framework' );
	update_option( 'consenso_bridge_impostazioni', array( 'framework' => 'acss' ) );
	$generati = 0;
	$conta = static function () use ( &$generati ) { ++$generati; };
	add_action( 'cmplz_after_css_generation', $conta );
	$rigenerati = Banner::rigenera();
	remove_action( 'cmplz_after_css_generation', $conta );
	$controlla( $rigenerati >= 1 && $generati >= 1, 'rigenera(): Complianz riscrive il CSS dei banner (' . $rigenerati . ' banner, ' . $generati . ' file)' );
	$css = '';
	foreach ( glob( cmplz_upload_dir( 'css' ) . 'banner-[0-9]*-*.css' ) ?: array() as $file ) {
		$css .= (string) file_get_contents( $file );
	}
	$controlla( false !== strpos( $css, '--cmplz_button_accept_background_color: var(--primary-dark);' ), 'il file del banner ha le variabili di ACSS' );
	$controlla( strpos( $css, '--cmplz_text_color: var(--text-color);' ) > strpos( $css, '--cmplz_text_color:' ), 'le nostre variabili dopo quelle di Complianz' );
	update_option( 'consenso_bridge_impostazioni', array( 'framework' => 'nessuno' ) );
	Banner::rigenera();
	$css = '';
	foreach ( glob( cmplz_upload_dir( 'css' ) . 'banner-[0-9]*-*.css' ) ?: array() as $file ) {
		$css .= (string) file_get_contents( $file );
	}
	$controlla( false === strpos( $css, 'var(--primary)' ), 'con «nessuno» il banner torna quello di Complianz' );

	// Il blocco: spento di serie, acceso col flag.
	delete_option( 'consenso_bridge_impostazioni' );
	$ripristina_enqueue();
	do_action( 'wp_enqueue_scripts' );
	$controlla( ! wp_script_is( 'consenso-bridge-blocco', 'enqueued' ), 'blocco spento di serie: niente script' );
	$controlla( wp_style_is( 'consenso-bridge', 'enqueued' ), 'stile caricato con Complianz attivo' );
	$controlla( str_ends_with( (string) wp_styles()->registered['consenso-bridge']->src, '/consenso-bridge/assets/css/consenso-bridge.css' ), 'indirizzo dello stile' );
	update_option( 'consenso_bridge_impostazioni', array( 'blocco' => true ) );
	$ripristina_enqueue();
	do_action( 'wp_enqueue_scripts' );
	$controlla( wp_script_is( 'consenso-bridge-blocco', 'enqueued' ), 'blocco acceso: script caricato' );
	$registrato = wp_scripts()->registered['consenso-bridge-blocco'] ?? null;
	$controlla( $registrato && str_ends_with( (string) $registrato->src, '/consenso-bridge/assets/js/blocco.js' ), 'indirizzo dello script' );
	$controlla( 'defer' === wp_scripts()->get_data( 'consenso-bridge-blocco', 'strategy' ) && 1 === (int) wp_scripts()->get_data( 'consenso-bridge-blocco', 'group' ), 'script in fondo e defer' );
	$prima = implode( "\n", (array) wp_scripts()->get_data( 'consenso-bridge-blocco', 'before' ) );
	$controlla( false !== strpos( $prima, 'window.consensoBridge = {"categoria":"marketing"' ), 'configurazione prima dello script' );
	$ripristina_enqueue();

	// La privacy: pagina creata dai dati di Complianz e collegata a Complianz.
	$o = is_array( $cmplz_salvate ) ? $cmplz_salvate : array();
	$o['organisation_name']           = 'Prova S.r.l.';
	$o['address_company']             = "Via Roma 1\nMilano";
	$o['email_company']               = 'privacy@esempio.test';
	$o['uses_thirdparty_services']    = 'yes';
	$o['thirdparty_services_on_site'] = array( 'youtube', 'google-maps' );
	$o['privacy-statement']           = 'none';
	update_option( 'cmplz_options', $o );
	delete_option( 'consenso_bridge_impostazioni' );
	// Complianz tiene i link del banner in cache (page_links_<banner>_<lingua>, 10 minuti): la pagina nuova va linkata subito.
	$cache = get_option( 'cmplz_transients', array() );
	$cache = is_array( $cache ) ? $cache : array();
	$cache['page_links_1_it_IT'] = array( 'value' => array( 'vecchio' ), 'expires' => time() + 600 );
	$cache['cb_altro']           = array( 'value' => 1, 'expires' => time() + 600 );
	update_option( 'cmplz_transients', $cache, false );
	$id = Pagine::crea( 'privacy' );
	$cache = get_option( 'cmplz_transients', array() );
	$controlla( ! isset( $cache['page_links_1_it_IT'] ) && isset( $cache['cb_altro'] ), 'crea(privacy) svuota solo la cache dei link del banner' );
	$controlla( is_int( $id ) && $id > 0, 'crea(privacy) dà un id' );
	if ( is_int( $id ) && $id > 0 ) {
		$create[] = $id;
		$controlla( 'page' === get_post_type( $id ) && 'publish' === get_post_status( $id ), 'pagina pubblicata' );
		$controlla( 'Informativa sulla privacy' === get_the_title( $id ), 'titolo della pagina privacy' );
		$controlla( Pagine::contenuto( 'privacy' ) === get_post_field( 'post_content', $id ), 'contenuto: lo shortcode' );
		$controlla( $id === Impostazioni::valore( 'pagina_privacy' ), 'id salvato nelle impostazioni' );
		$controlla( 'custom' === ( get_option( 'cmplz_options' )['privacy-statement'] ?? '' ), 'Complianz: privacy = pagina esistente' );
		$controlla( $id === (int) get_option( 'cmplz_privacy-statement_custom_page' ), 'Complianz: punta alla pagina' );
		$controlla( $id === Pagine::crea( 'privacy' ), 'una seconda volta non la duplica' );
		$html = do_shortcode( '[consenso_documento tipo="privacy"]' );
		$controlla( false !== strpos( $html, 'consenso-documento--privacy' ), 'shortcode: documento privacy' );
		$controlla( false !== strpos( $html, 'raccolti da questo sito è Prova S.r.l., Via Roma 1, Milano' ), 'shortcode: titolare e indirizzo da Complianz' );
		$controlla( false !== strpos( $html, 'mailto:privacy@esempio.test' ), 'shortcode: email da Complianz' );
		$controlla( false !== strpos( $html, '<li class="consenso-documento__voce">YouTube</li><li class="consenso-documento__voce">Google Maps</li>' ), 'shortcode: servizi con le etichette di Complianz' );
	}
	$controlla( '' === do_shortcode( '[consenso_documento tipo="altro"]' ), 'shortcode con un tipo sconosciuto: vuoto' );
	$controlla( is_wp_error( Pagine::crea( 'altro' ) ), 'crea() con un tipo sconosciuto: errore' );

	// La dichiarazione di accessibilità, con i testi dell'utente registrati per la traduzione.
	$registrate = array();
	$spia = static function ( $dominio, $nome, $valore ) use ( &$registrate ) { $registrate[] = array( $dominio, $nome, $valore ); };
	add_action( 'wpml_register_single_string', $spia, 1, 3 );
	Impostazioni::salva( array( 'acc_stato' => 'parziale', 'acc_contenuti' => "CB PDF scansionati\nCB Video senza sottotitoli", 'acc_email' => 'accessibilita@esempio.test', 'acc_data' => '2026-11-11' ) );
	remove_action( 'wpml_register_single_string', $spia, 1 );
	$controlla( in_array( array( 'consenso-bridge', 'acc_contenuti', "CB PDF scansionati\nCB Video senza sottotitoli" ), $registrate, true ), 'testo libero registrato per la traduzione' );
	$controlla( (int) Impostazioni::valore( 'pagina_privacy' ) === ( $create[0] ?? -1 ), 'salva() tiene la pagina privacy' );
	$id2 = Pagine::crea( 'accessibilita' );
	$controlla( is_int( $id2 ) && $id2 > 0, 'crea(accessibilita) dà un id' );
	if ( is_int( $id2 ) && $id2 > 0 ) {
		$create[] = $id2;
		$controlla( 'Dichiarazione di accessibilità' === get_the_title( $id2 ), 'titolo della dichiarazione' );
		$html = do_shortcode( '[consenso_documento tipo="accessibilita"]' );
		$controlla( false !== strpos( $html, 'Il sito è parzialmente conforme ai requisiti' ), 'shortcode: stato' );
		$controlla( false !== strpos( $html, '<li class="consenso-documento__voce">CB PDF scansionati</li>' ), 'shortcode: contenuti non accessibili' );
		$controlla( false !== strpos( $html, 'mailto:accessibilita@esempio.test' ), 'shortcode: email delle segnalazioni' );
		$controlla( false !== strpos( $html, 'Prova S.r.l. si impegna a rendere accessibile il sito' ), 'shortcode: titolare da Complianz' );
		$controlla( 1 === preg_match( '/preparata il [^<]*2026 con un/', $html ), 'shortcode: data leggibile' );
	}
	update_option( 'cmplz_options', array_merge( $o, array( 'email_company' => 'titolare@esempio.test' ) ) );
	$senza_email = Impostazioni::tutte();
	$senza_email['acc_email'] = '';
	update_option( 'consenso_bridge_impostazioni', $senza_email );
	$controlla( false !== strpos( do_shortcode( '[consenso_documento tipo="accessibilita"]' ), 'mailto:titolare@esempio.test' ), 'senza email propria vale quella di Complianz' );

	// L'amministrazione: la privacy c'è (link), la dichiarazione no (pulsante per crearla).
	$senza_email['pagina_accessibilita'] = 0;
	update_option( 'consenso_bridge_impostazioni', $senza_email );
	Admin::init();
	$controlla( 10 === has_action( 'admin_menu', array( Admin::class, 'menu' ) ), 'admin: menu' );
	$controlla( 10 === has_action( 'admin_post_consenso_bridge_salva', array( Admin::class, 'salva' ) ), 'admin: salvataggio' );
	$controlla( 10 === has_action( 'admin_post_consenso_bridge_crea', array( Admin::class, 'crea' ) ), 'admin: creazione delle pagine' );
	ob_start();
	Admin::pagina();
	$pagina = (string) ob_get_clean();
	foreach ( array( 'framework', 'blocco', 'acc_stato', 'acc_contenuti', 'acc_valutazione', 'acc_data', 'acc_email', 'pr_finalita', 'pr_conservazione' ) as $campo ) {
		$controlla( false !== strpos( $pagina, 'name="consenso_bridge[' . $campo . ']"' ), 'admin: campo ' . $campo );
	}
	$controlla( false !== strpos( $pagina, '<option value="testi"' ), 'admin: la scelta «Solo testi»' );
	$controlla( false !== strpos( $pagina, 'value="consenso_bridge_salva"' ) && false !== strpos( $pagina, 'name="_wpnonce"' ), 'admin: modulo con nonce' );
	$controlla( false !== strpos( $pagina, 'value="consenso_bridge_crea"' ), 'admin: pulsanti per creare le pagine' );
	$controlla( false !== strpos( $pagina, get_permalink( $create[0] ?? 0 ) ?: 'nessuna' ), 'admin: link alla pagina privacy creata' );
	wp_set_current_user( 0 );
	ob_start();
	Admin::pagina();
	$controlla( '' === trim( (string) ob_get_clean() ), 'admin: niente per chi non può gestire le opzioni' );
	wp_set_current_user( 1 );
} catch ( Throwable $e ) {
	$controlla( false, 'eccezione: ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' );
} finally {
	foreach ( $create as $id ) {
		wp_delete_post( $id, true );
	}
	null === $salvate ? delete_option( 'consenso_bridge_impostazioni' ) : update_option( 'consenso_bridge_impostazioni', $salvate );
	update_option( 'cmplz_options', $cmplz_salvate );
	null === $cmplz_pagina ? delete_option( 'cmplz_privacy-statement_custom_page' ) : update_option( 'cmplz_privacy-statement_custom_page', $cmplz_pagina );
	if ( class_exists( Banner::class ) ) {
		Banner::rigenera();
	}
}

echo $fallite ? "FAILURES: $fallite\n" : "OK\n";
if ( $fallite ) {
	exit( 1 );
}
