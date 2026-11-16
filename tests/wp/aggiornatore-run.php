<?php
/**
 * Prove dell'aggiornatore firmato (class-aggiornatore.php), dentro un WordPress vero: `wp eval-file` da dev/test.sh.
 * La rete è sostituita da pre_http_request: nessuna richiesta esce. Una riga PASS/FAIL per controllo, in fondo OK o
 * FAILURES: N (ed esce con 1).
 *
 * Copia comune a più plugin, ciascuno col suo namespace: le modifiche si fanno nella versione di riferimento e si
 * riportano in tutte le copie.
 */

require_once ABSPATH . 'wp-admin/includes/file.php';
use ConsensoBridge\Aggiornatore;

$fallite   = 0;
$controlla = static function ( bool $ok, string $nome ) use ( &$fallite ): void {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . 'aggiornatore: ' . $nome . "\n";
	$fallite += $ok ? 0 : 1;
};

// La chiave di rilascio delle prove: nata qui, buttata alla fine.
$coppia = sodium_crypto_sign_keypair();
$id     = random_bytes( 8 );
$chiave = base64_encode( 'Ed' . $id . sodium_crypto_sign_publickey( $coppia ) );
$firma  = static function ( string $dati, string $nome, string $segreta = '' ) use ( $coppia, $id ): string {
	$sk      = '' === $segreta ? sodium_crypto_sign_secretkey( $coppia ) : $segreta;
	$sig     = sodium_crypto_sign_detached( sodium_crypto_generichash( $dati, '', 64 ), $sk );
	$comment = "timestamp:1\tfile:$nome\thashed";
	return "untrusted comment: prova\n" . base64_encode( 'ED' . $id . $sig ) . "\ntrusted comment: $comment\n" . base64_encode( sodium_crypto_sign_detached( $sig . $comment, $sk ) ) . "\n";
};

// La rete finta: indirizzo → corpo (stringa) o codice HTTP (intero). Ogni richiesta resta in $richieste.
$richieste = array();
$rete      = static function ( array $corpi ) use ( &$richieste ): void {
	remove_all_filters( 'pre_http_request' );
	add_filter(
		'pre_http_request',
		static function ( $pre, array $args, string $url ) use ( $corpi, &$richieste ) {
			$richieste[] = array( 'url' => $url, 'args' => $args );
			if ( ! isset( $corpi[ $url ] ) ) {
				return new WP_Error( 'bloccata', 'nessuna rete nelle prove' );
			}
			$corpo  = is_string( $corpi[ $url ] ) ? $corpi[ $url ] : '';
			$codice = is_int( $corpi[ $url ] ) ? $corpi[ $url ] : 200;
			if ( ! empty( $args['filename'] ) ) {
				file_put_contents( $args['filename'], $corpo ); // phpcs:ignore -- lo scaricamento a file.
			}
			return array( 'headers' => array(), 'body' => $corpo, 'response' => array( 'code' => $codice, 'message' => 'X' ), 'cookies' => array(), 'filename' => $args['filename'] ?? null );
		},
		10,
		3
	);
};

$vero = current( Aggiornatore::istanze() );
if ( ! $vero instanceof Aggiornatore ) {
	echo "FAIL aggiornatore: il plugin non avvia l'aggiornatore\nFAILURES: 1\n";
	exit( 1 );
}
$asset  = $vero->parametri()['asset'];
$plugin = $vero->basename();

/** Un aggiornatore di prova sul plugin vero: stessa cartella e stesso asset, versione 1.0.0, fonte e chiave delle prove. */
$prova = static function ( array $altro = array() ) use ( $vero, $chiave ): Aggiornatore {
	$a = new Aggiornatore( array_merge( $vero->parametri(), array( 'prefisso' => 'PROVA_AGG', 'versione' => '1.0.0', 'fonte' => 'github:acme/prova', 'chiave' => $chiave, 'max_zip' => 4096 ), $altro ) );
	delete_site_transient( $a->cache() );
	return $a;
};

/** Una release di GitHub come la dà l'API, con lo ZIP e la firma accanto. */
$base    = static fn ( string $v ): string => "https://github.com/acme/prova/releases/download/v$v/";
$api     = 'https://api.github.com/repos/acme/prova/releases/latest';
$github  = static function ( string $v, string $zip, ?string $sig, array $extra = array(), string $nome = '' ) use ( $rete, $base, $api, $asset ): void {
	$nome   = '' === $nome ? "$asset-$v.zip" : $nome;
	$assets = array( array( 'name' => $nome, 'browser_download_url' => $base( $v ) . $nome ) );
	if ( null !== $sig ) {
		$assets[] = array( 'name' => "$nome.minisig", 'browser_download_url' => $base( $v ) . "$nome.minisig" );
	}
	$dati = array_merge( array( 'tag_name' => "v$v", 'html_url' => "https://github.com/acme/prova/releases/tag/v$v", 'assets' => $assets ), $extra );
	$rete(
		array(
			$api                             => (string) wp_json_encode( $dati ),
			$base( $v ) . $nome              => $zip,
			$base( $v ) . "$nome.minisig"    => $sig ?? 404,
		)
	);
};
$offerta = static fn ( Aggiornatore $a ) => $a->offer( (object) array( 'response' => array() ) );
$scarica = static fn ( Aggiornatore $a, string $pacchetto ) => $a->download( false, $pacchetto, null, array( 'plugin' => $a->basename() ) );
$rifiuto = static fn ( $r, string $parola ): bool => is_wp_error( $r ) && str_contains( $r->get_error_message(), $parola );

try {
	// Il plugin vero.
	$controlla( $vero->autonomo() && 1 === substr_count( $plugin, '/' ), 'il plugin autonomo si aggiorna da sé' );
	$controlla( false !== has_filter( 'pre_set_site_transient_update_plugins', array( $vero, 'offer' ) ) && false !== has_filter( 'upgrader_pre_download', array( $vero, 'download' ) ), 'agganciato a WordPress' );
	$uri = (string) get_file_data( $vero->parametri()['file'], array( 'uri' => 'Update URI' ) )['uri'];
	$controlla( '' !== $uri && ! str_contains( $uri, 'wordpress.org' ) && ! str_contains( $uri, 'w.org' ), 'Update URI: mai un aggiornamento dalla directory dei plugin con lo stesso slug' );
	$controlla( '' !== $vero->parametri()['chiave'], 'la chiave di rilascio è nel codice' );
	$controlla( $vero->impostazioni()['attivo'] && in_array( $vero->impostazioni()['tipo'], array( 'github', 'manifesto' ), true ), 'la fonte di serie è valida e accesa' );

	// Una release firmata: offerta, scaricata, verificata.
	$a   = $prova();
	$zip = 'PK pacchetto di prova ' . random_bytes( 64 );
	$github( '9.1.0', $zip, $firma( $zip, "$asset-9.1.0.zip" ) );
	$o = $offerta( $a )->response[ $plugin ] ?? null;
	$controlla( $o && '9.1.0' === $o->new_version && $base( '9.1.0' ) . "$asset-9.1.0.zip" === $o->package && dirname( $plugin ) === $o->slug, 'una release firmata più nuova è offerta' );
	$file = $scarica( $a, $o->package ?? '' );
	$controlla( is_string( $file ) && $zip === file_get_contents( $file ), 'il file verificato va a WordPress' );
	is_string( $file ) && wp_delete_file( $file );
	$ua = implode( ' ', array_map( static fn ( $r ) => (string) ( $r['args']['user-agent'] ?? '' ) . ' ' . wp_json_encode( $r['args']['headers'] ?? array() ), $richieste ) );
	$controlla( '' !== $ua && ! str_contains( $ua, (string) wp_parse_url( home_url(), PHP_URL_HOST ) ), 'nessun dato del sito nelle richieste' );
	$controlla( 'altro' === $a->download( 'altro', $o->package ?? '' ), 'la risposta di un altro gestore resta com\'è' );
	$controlla( false === $a->download( false, 'https://example.com/altro.zip', null, array( 'plugin' => 'altro/altro.php' ) ), 'gli altri pacchetti restano a WordPress' );
	$quante = count( $richieste );
	$a->ultima();
	$controlla( count( $richieste ) === $quante, 'la fonte si interroga al massimo ogni 12 ore' );

	// La versione installata: niente offerta, ma il plugin resta tra quelli aggiornabili (aggiornamenti automatici).
	$a = $prova();
	$github( '1.0.0', $zip, $firma( $zip, "$asset-1.0.0.zip" ) );
	$t = $offerta( $a );
	$controlla( ! isset( $t->response[ $plugin ] ) && '1.0.0' === ( $t->no_update[ $plugin ]->new_version ?? '' ), 'la versione installata non è offerta di nuovo' );

	// Bozze e pre-release: ignorate.
	foreach ( array( 'draft' => 'una bozza', 'prerelease' => 'una pre-release' ) as $campo => $nome ) {
		$a = $prova();
		$github( '9.1.1', $zip, $firma( $zip, "$asset-9.1.1.zip" ), array( $campo => true ) );
		$controlla( null === $a->ultima() && ! isset( $offerta( $a )->response[ $plugin ] ), "$nome è ignorata" );
	}

	// Firma mancante: la release non conta; firma che non si scarica: niente installazione.
	$a = $prova();
	$github( '9.2.0', $zip, null );
	$controlla( null === $a->ultima(), 'senza firma accanto allo ZIP la release non conta' );
	$a = $prova();
	$github( '9.2.0', $zip, $firma( $zip, "$asset-9.2.0.zip" ) );
	$p = $a->ultima()['zip'] ?? '';
	$rete( array( $api => (string) wp_json_encode( array( 'tag_name' => 'v9.2.0', 'assets' => array() ) ), $p => $zip ) );
	$controlla( $rifiuto( $scarica( $a, $p ), 'Firma' ), 'una firma che non si scarica ferma l\'aggiornamento' );

	// Firma sbagliata: file manomesso, altra chiave, firma illeggibile, commento firmato alterato.
	$a = $prova();
	$github( '9.2.1', $zip . ' manomesso', $firma( $zip, "$asset-9.2.1.zip" ) );
	$controlla( $rifiuto( $scarica( $a, $a->ultima()['zip'] ?? '' ), 'non corrisponde' ), 'un pacchetto manomesso è rifiutato' );
	$a = $prova();
	$github( '9.2.2', $zip, $firma( $zip, "$asset-9.2.2.zip", sodium_crypto_sign_secretkey( sodium_crypto_sign_keypair() ) ) );
	$controlla( is_wp_error( $scarica( $a, $a->ultima()['zip'] ?? '' ) ), 'una firma di un\'altra chiave è rifiutata' );
	$a = $prova();
	$github( '9.2.3', $zip, "untrusted comment: x\nnon una firma\n" );
	$controlla( $rifiuto( $scarica( $a, $a->ultima()['zip'] ?? '' ), 'illeggibile' ), 'una firma illeggibile è rifiutata' );
	$a = $prova();
	$github( '9.2.4', $zip, str_replace( 'timestamp:1', 'timestamp:2', $firma( $zip, "$asset-9.2.4.zip" ) ) );
	$controlla( $rifiuto( $scarica( $a, $a->ultima()['zip'] ?? '' ), 'alterato' ), 'un commento firmato alterato è rifiutato' );

	// Nome dell'asset diverso: né offerto né installato.
	foreach ( array( "$asset-1.0.0.zip", "$asset-9.3.0-alt.zip", "non-$asset-9.3.0.zip" ) as $nome ) {
		$a = $prova();
		$github( '9.3.0', $zip, $firma( $zip, $nome ) );
		$controlla( $rifiuto( $scarica( $a, $a->ultima()['zip'] ?? '' ), 'altro pacchetto' ), "la firma vera di un altro pacchetto ($nome) è rifiutata" );
	}
	$a = $prova();
	$github( '9.3.1', $zip, $firma( $zip, "altro-9.3.1.zip" ), array(), 'altro-9.3.1.zip' );
	$controlla( null === $a->ultima(), 'una release con un asset di altro nome non conta' );

	// ZIP oltre il limite: rifiutato prima di controllare la firma.
	$a     = $prova();
	$grosso = str_repeat( 'x', 5000 );
	$github( '9.4.0', $grosso, $firma( $grosso, "$asset-9.4.0.zip" ) );
	$controlla( $rifiuto( $scarica( $a, $a->ultima()['zip'] ?? '' ), 'troppo grande' ), 'uno ZIP oltre il limite è rifiutato' );

	// Tra l'offerta e l'installazione la release cambia: il nostro aggiornamento è rifiutato, mai installato senza verifica.
	$a = $prova();
	$github( '9.5.0', $zip, $firma( $zip, "$asset-9.5.0.zip" ) );
	$p = $offerta( $a )->response[ $plugin ]->package ?? '';
	delete_site_transient( $a->cache() );
	$rete( array( $api => 403 ) );
	$r = $scarica( $a, $p );
	$controlla( is_wp_error( $r ) && str_contains( $r->get_error_code(), 'update_signature' ), 'un\'offerta scaduta è rifiutata' );

	// Solo da github.com con la fonte GitHub.
	$a = $prova();
	$controlla( null === $a->leggi_github( array( 'tag_name' => 'v1.2.3', 'assets' => array( array( 'name' => "$asset-1.2.3.zip", 'browser_download_url' => 'http://male.example/z' ), array( 'name' => "$asset-1.2.3.zip.minisig", 'browser_download_url' => 'https://github.com/a/b/s' ) ) ) ), 'con la fonte GitHub, solo file da github.com' );

	// La fonte «manifesto»: un indirizzo https che dice versione, ZIP e firma.
	$man = 'https://aggiornamenti.example/plugin.json';
	$sv  = 'https://file.example/rilasci/';
	$a   = $prova( array( 'fonte' => $man ) );
	$rete(
		array(
			$man                                 => (string) wp_json_encode( array( $asset => array( 'version' => '9.6.0', 'zip' => $sv . "$asset-9.6.0.zip", 'sig' => $sv . "$asset-9.6.0.zip.minisig", 'url' => 'https://aggiornamenti.example/' ) ) ),
			$sv . "$asset-9.6.0.zip"             => $zip,
			$sv . "$asset-9.6.0.zip.minisig"     => $firma( $zip, "$asset-9.6.0.zip" ),
		)
	);
	$o = $offerta( $a )->response[ $plugin ] ?? null;
	$controlla( $o && '9.6.0' === $o->new_version && $sv . "$asset-9.6.0.zip" === $o->package, 'il manifesto (anche con più plugin) offre la release' );
	$file = $scarica( $a, $o->package ?? '' );
	$controlla( is_string( $file ) && $zip === file_get_contents( $file ), 'dal manifesto il file è verificato con la stessa chiave' );
	is_string( $file ) && wp_delete_file( $file );
	$m = static fn ( array $x ) => $a->leggi_manifesto( array_merge( array( 'version' => '9.6.0', 'zip' => $sv . "$asset-9.6.0.zip", 'sig' => $sv . "$asset-9.6.0.zip.minisig" ), $x ) );
	$controlla( null !== $m( array() ), 'un manifesto semplice si legge' );
	$controlla( null === $m( array( 'zip' => 'http://file.example/rilasci/' . "$asset-9.6.0.zip" ) ), 'manifesto: solo https' );
	$controlla( null === $m( array( 'zip' => $sv . "altro-9.6.0.zip" ) ), 'manifesto: lo ZIP deve avere il nome dell\'asset' );
	$controlla( null === $m( array( 'sig' => $sv . "$asset-9.6.1.zip.minisig" ) ), 'manifesto: la firma deve essere quella dello ZIP' );
	$controlla( null === $m( array( 'draft' => true ) ) && null === $m( array( 'prerelease' => true ) ), 'manifesto: bozze e pre-release ignorate' );
	$controlla( null === $m( array( 'version' => '9.6' ) ), 'manifesto: versione X.Y.Z' );
	$controlla( ! $prova( array( 'fonte' => 'http://aggiornamenti.example/plugin.json' ) )->impostazioni()['attivo'] && ! $prova( array( 'fonte' => 'ftp:qualcosa' ) )->impostazioni()['attivo'] && ! $prova( array( 'fonte' => '' ) )->impostazioni()['attivo'], 'una fonte non valida o vuota spegne l\'aggiornatore' );

	// L'interruttore in wp-config.php e le scelte del sito.
	define( 'PROVA_SPENTO_UPDATES', false );
	$a      = $prova( array( 'prefisso' => 'PROVA_SPENTO' ) );
	$quante = count( $richieste );
	$t      = $offerta( $a );
	$controlla( ! $a->impostazioni()['attivo'] && empty( $t->response ) && empty( $t->no_update ) && count( $richieste ) === $quante, '<PREFISSO>_UPDATES false lo spegne: nessuna richiesta' );
	$controlla( $a->download( false, 'https://example.com/x.zip', null, array( 'plugin' => $plugin ) ) === false, 'spento, lascia a WordPress anche il suo pacchetto' );
	define( 'PROVA_SCELTE_UPDATE_SOURCE', $man );
	define( 'PROVA_SCELTE_UPDATE_PUBLIC_KEY', 'RWQaltrachiave' );
	$s = $prova( array( 'prefisso' => 'PROVA_SCELTE' ) )->impostazioni();
	$controlla( $s['attivo'] && $man === $s['fonte'] && 'manifesto' === $s['tipo'] && 'RWQaltrachiave' === $s['chiave'], 'wp-config sceglie fonte e chiave' );
} finally {
	remove_all_filters( 'pre_http_request' );
}

echo $fallite ? "FAILURES: $fallite\n" : "OK\n";
if ( $fallite ) {
	exit( 1 );
}
