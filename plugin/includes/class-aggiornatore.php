<?php
/**
 * Aggiornamenti firmati dall'amministrazione di WordPress (e quindi da MainWP), lo stesso schema di Lingua Bridge e
 * Histeon: nessuna licenza, nessuna telemetria, nessun pacchetto installato senza una firma minisign valida.
 *
 * Copia comune a più plugin, ciascuno col suo namespace e il suo dominio delle traduzioni: le modifiche si fanno nella
 * versione di riferimento e si riportano in tutte le copie (prove in tests/wp/aggiornatore-run.php).
 *
 * - Il plugin lo avvia con i suoi parametri (Aggiornatore::avvia): prefisso delle costanti, file principale,
 *   versione, nome dell'asset, nome leggibile, fonte e chiave pubblica di rilascio.
 * - In wp-config.php: `<PREFISSO>_UPDATES` false lo spegne; `<PREFISSO>_UPDATE_SOURCE` e
 *   `<PREFISSO>_UPDATE_PUBLIC_KEY` sostituiscono fonte e chiave. Fonte vuota o non valida: spento.
 * - La fonte dice soltanto quale release c'è; la firma si controlla comunque sul sito, quindi il canale non deve
 *   essere fidato. Due fonti:
 *     `github:proprietario/nome` (o `proprietario/nome`): l'API pubblica delle release di GitHub;
 *     `https://…`: un manifesto JSON {version, zip, sig, url?, draft?, prerelease?}, o un oggetto con un manifesto
 *     per asset ({"<asset>": {…}}), così un indirizzo solo serve più plugin.
 * - Una richiesta anonima alla fonte al massimo ogni 12 ore, con un User-Agent senza il sito: niente del sito esce.
 * - Conta solo una release che non è bozza né pre-release, con versione X.Y.Z, lo ZIP <asset>-X.Y.Z.zip e accanto
 *   <asset>-X.Y.Z.zip.minisig, tutti in https (con GitHub, solo da https://github.com/).
 * - Prima che WordPress installi lo ZIP, lo scarica con la sua firma e li controlla con la chiave di rilascio
 *   (minisign: Ed25519 sul BLAKE2b-512 del file e sul commento fidato); il nome del file firmato deve essere proprio
 *   quell'asset. Qualsiasi cosa non torni ferma l'aggiornamento e cancella il file scaricato.
 */

namespace ConsensoBridge;

defined( 'ABSPATH' ) || exit;

final class Aggiornatore {
	const MAX_ZIP       = 62914560;
	const MAX_SIG       = 4096;
	const MAX_MANIFESTO = 65536;
	const OBBLIGATORI   = array( 'prefisso', 'file', 'versione', 'asset', 'nome', 'fonte', 'chiave' );

	/** @var array<string, self> Gli aggiornatori avviati, per asset. */
	private static $istanze = array();

	/** @var array<string, mixed> */
	private $p;

	/**
	 * @param array{prefisso: string, file: string, versione: string, asset: string, nome: string, fonte: string, chiave: string, max_zip?: int} $parametri
	 */
	public function __construct( array $parametri ) {
		foreach ( self::OBBLIGATORI as $k ) {
			if ( ! isset( $parametri[ $k ] ) || ! is_string( $parametri[ $k ] ) ) {
				throw new \InvalidArgumentException( "Aggiornatore: manca il parametro $k." );
			}
		}
		if ( 1 !== preg_match( '/^[A-Z][A-Z0-9_]*$/', $parametri['prefisso'] ) || 1 !== preg_match( '/^[a-z0-9][a-z0-9-]*$/', $parametri['asset'] ) ) {
			throw new \InvalidArgumentException( 'Aggiornatore: prefisso o asset non validi.' );
		}
		$parametri['max_zip'] = isset( $parametri['max_zip'] ) ? max( 1, (int) $parametri['max_zip'] ) : self::MAX_ZIP;
		$this->p              = $parametri;
	}

	/** Crea l'aggiornatore del plugin e lo aggancia a WordPress (solo per il plugin autonomo). */
	public static function avvia( array $parametri ): self {
		$a                                   = new self( $parametri );
		self::$istanze[ $parametri['asset'] ] = $a;
		if ( $a->autonomo() ) {
			add_filter( 'pre_set_site_transient_update_plugins', array( $a, 'offer' ) );
			add_filter( 'upgrader_pre_download', array( $a, 'download' ), 10, 4 );
		}
		return $a;
	}

	/** @return array<string, self> */
	public static function istanze(): array {
		return self::$istanze;
	}

	/** @return array<string, mixed> */
	public function parametri(): array {
		return $this->p;
	}

	public function cache(): string {
		return str_replace( '-', '_', $this->p['asset'] ) . '_aggiornamento';
	}

	/** Il plugin come lo conosce WordPress: plugins/<cartella>/<file>.php. */
	public function basename(): string {
		return plugin_basename( $this->p['file'] );
	}

	/** Solo il plugin autonomo, non una copia dentro un altro plugin (che si aggiorna con quello). */
	public function autonomo(): bool {
		return function_exists( 'plugin_basename' ) && 1 === substr_count( $this->basename(), '/' );
	}

	/**
	 * Le scelte, con wp-config.php che vince.
	 *
	 * @return array{attivo: bool, fonte: string, tipo: string, chiave: string}
	 */
	public function impostazioni(): array {
		$prefisso = $this->p['prefisso'];
		$fonte    = defined( $prefisso . '_UPDATE_SOURCE' ) ? (string) constant( $prefisso . '_UPDATE_SOURCE' ) : $this->p['fonte'];
		$chiave   = defined( $prefisso . '_UPDATE_PUBLIC_KEY' ) ? (string) constant( $prefisso . '_UPDATE_PUBLIC_KEY' ) : $this->p['chiave'];
		$acceso   = ! defined( $prefisso . '_UPDATES' ) || false !== constant( $prefisso . '_UPDATES' );
		$tipo     = '';
		if ( 1 === preg_match( '#^(?:github:)?([A-Za-z0-9](?:[A-Za-z0-9-]{0,38})/[A-Za-z0-9._-]{1,100})$#', $fonte, $m ) ) {
			$tipo  = 'github';
			$fonte = 'github:' . $m[1];
		} elseif ( self::https( $fonte ) ) {
			$tipo = 'manifesto';
		}
		return array(
			'attivo' => $acceso && '' !== $tipo && '' !== trim( $chiave ),
			'fonte'  => $fonte,
			'tipo'   => $tipo,
			'chiave' => $chiave,
		);
	}

	/**
	 * L'ultima release firmata dalla fonte, in cache 12 ore.
	 *
	 * @return array{version: string, zip: string, sig: string, url: string}|null
	 */
	public function ultima(): ?array {
		$s = $this->impostazioni();
		if ( ! $s['attivo'] ) {
			return null;
		}
		$cache = get_site_transient( $this->cache() );
		if ( is_array( $cache ) && isset( $cache['fonte'] ) && $cache['fonte'] === $s['fonte'] ) {
			return isset( $cache['release'] ) && is_array( $cache['release'] ) ? $cache['release'] : null;
		}
		$github  = 'github' === $s['tipo'];
		$url     = $github ? 'https://api.github.com/repos/' . substr( $s['fonte'], 7 ) . '/releases/latest' : $s['fonte'];
		$headers = $github ? array( 'Accept' => 'application/vnd.github+json' ) : array( 'Accept' => 'application/json' );
		$risposta = wp_safe_remote_get( $url, array( 'timeout' => 10, 'redirection' => 3, 'limit_response_size' => self::MAX_MANIFESTO, 'user-agent' => $this->agente(), 'headers' => $headers ) );
		$release  = null;
		if ( ! is_wp_error( $risposta ) && 200 === wp_remote_retrieve_response_code( $risposta ) ) {
			$dati    = json_decode( wp_remote_retrieve_body( $risposta ), true );
			$release = $github ? $this->leggi_github( $dati ) : $this->leggi_manifesto( $dati );
		}
		set_site_transient( $this->cache(), array( 'fonte' => $s['fonte'], 'release' => $release ), 12 * HOUR_IN_SECONDS );
		return $release;
	}

	/**
	 * Una release di GitHub come serve all'aggiornatore, o null se manca qualcosa.
	 *
	 * @param mixed $dati La risposta dell'API, decodificata.
	 * @return array{version: string, zip: string, sig: string, url: string}|null
	 */
	public function leggi_github( $dati ): ?array {
		if ( ! is_array( $dati ) || ! empty( $dati['draft'] ) || ! empty( $dati['prerelease'] ) ) {
			return null;
		}
		$versione = self::versione( $dati['tag_name'] ?? '' );
		if ( null === $versione ) {
			return null;
		}
		$assets = array();
		foreach ( isset( $dati['assets'] ) && is_array( $dati['assets'] ) ? $dati['assets'] : array() as $asset ) {
			$nome = is_array( $asset ) && isset( $asset['name'] ) ? (string) $asset['name'] : '';
			$url  = is_array( $asset ) && isset( $asset['browser_download_url'] ) ? (string) $asset['browser_download_url'] : '';
			if ( 0 === strpos( $url, 'https://github.com/' ) ) {
				$assets[ $nome ] = $url;
			}
		}
		$nome = $this->nome_zip( $versione );
		if ( ! isset( $assets[ $nome ], $assets[ $nome . '.minisig' ] ) ) {
			return null;
		}
		$pagina = isset( $dati['html_url'] ) ? (string) $dati['html_url'] : '';
		return array(
			'version' => $versione,
			'zip'     => $assets[ $nome ],
			'sig'     => $assets[ $nome . '.minisig' ],
			'url'     => 0 === strpos( $pagina, 'https://github.com/' ) ? $pagina : '',
		);
	}

	/**
	 * Un manifesto come serve all'aggiornatore, o null se manca qualcosa. I nomi dei file devono già essere quelli
	 * dell'asset: la firma li ricontrolla comunque.
	 *
	 * @param mixed $dati Il manifesto decodificato: {version, zip, sig, url?} o {"<asset>": {…}}.
	 * @return array{version: string, zip: string, sig: string, url: string}|null
	 */
	public function leggi_manifesto( $dati ): ?array {
		if ( is_array( $dati ) && isset( $dati[ $this->p['asset'] ] ) && is_array( $dati[ $this->p['asset'] ] ) ) {
			$dati = $dati[ $this->p['asset'] ];
		}
		if ( ! is_array( $dati ) || ! empty( $dati['draft'] ) || ! empty( $dati['prerelease'] ) ) {
			return null;
		}
		$versione = self::versione( $dati['version'] ?? '' );
		$zip      = isset( $dati['zip'] ) ? (string) $dati['zip'] : '';
		$sig      = isset( $dati['sig'] ) ? (string) $dati['sig'] : '';
		if ( null === $versione || ! self::https( $zip ) || ! self::https( $sig ) ) {
			return null;
		}
		$nome = $this->nome_zip( $versione );
		if ( self::nome_file( $zip ) !== $nome || self::nome_file( $sig ) !== $nome . '.minisig' ) {
			return null;
		}
		$pagina = isset( $dati['url'] ) ? (string) $dati['url'] : '';
		return array(
			'version' => $versione,
			'zip'     => $zip,
			'sig'     => $sig,
			'url'     => self::https( $pagina ) ? $pagina : '',
		);
	}

	/**
	 * Il controllo degli aggiornamenti di WordPress: una release firmata più nuova diventa un aggiornamento del
	 * plugin; altrimenti il plugin resta tra quelli che si aggiornano da sé (no_update), così gli aggiornamenti
	 * automatici si possono accendere.
	 *
	 * @param mixed $transient
	 * @return mixed
	 */
	public function offer( $transient ) {
		if ( ! $transient instanceof \stdClass || ! $this->impostazioni()['attivo'] ) {
			return $transient;
		}
		$release = $this->ultima();
		$plugin  = $this->basename();
		$nuova   = null !== $release && version_compare( $release['version'], $this->p['versione'], '>' );
		$voce    = (object) array(
			'id'          => $plugin,
			'slug'        => dirname( $plugin ),
			'plugin'      => $plugin,
			'new_version' => $nuova ? $release['version'] : $this->p['versione'],
			'package'     => $nuova ? $release['zip'] : '',
			'url'         => null !== $release ? $release['url'] : '',
		);
		$campo                = $nuova ? 'response' : 'no_update';
		$transient->$campo    = isset( $transient->$campo ) && is_array( $transient->$campo ) ? $transient->$campo : array();
		$transient->{$campo}[ $plugin ] = $voce;
		if ( ! $nuova && isset( $transient->response ) && is_array( $transient->response ) ) {
			unset( $transient->response[ $plugin ] );
		}
		return $transient;
	}

	/**
	 * Prima che WordPress installi lo ZIP di questo plugin: lo scarica con la firma e verifica. Gli altri pacchetti
	 * restano a WordPress; un aggiornamento di questo plugin il cui pacchetto non è più l'ultima release firmata è
	 * rifiutato.
	 *
	 * @param mixed $reply
	 * @param mixed $package
	 * @param mixed $upgrader
	 * @param mixed $hook_extra
	 * @return mixed
	 */
	public function download( $reply, $package, $upgrader = null, $hook_extra = array() ) {
		if ( false !== $reply || ! is_string( $package ) || ! $this->impostazioni()['attivo'] ) {
			return $reply;
		}
		$nostro  = is_array( $hook_extra ) && isset( $hook_extra['plugin'] ) && $this->basename() === $hook_extra['plugin'];
		$release = $this->ultima();
		if ( null === $release || $package !== $release['zip'] ) {
			return $nostro ? $this->errore( __( 'il pacchetto non è più l’ultima release firmata. Controlla di nuovo gli aggiornamenti.', 'consenso-bridge' ) ) : $reply;
		}
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$nome = $this->nome_zip( $release['version'] );
		$zip  = wp_tempnam( $nome );
		try {
			$risposta = wp_safe_remote_get( $package, array( 'timeout' => 120, 'stream' => true, 'filename' => $zip, 'limit_response_size' => $this->p['max_zip'] + 1, 'user-agent' => $this->agente() ) );
			if ( is_wp_error( $risposta ) || 200 !== wp_remote_retrieve_response_code( $risposta ) ) {
				throw new \InvalidArgumentException( __( 'Pacchetto non scaricato.', 'consenso-bridge' ) );
			}
			clearstatcache( true, $zip );
			if ( (int) filesize( $zip ) > $this->p['max_zip'] ) {
				throw new \InvalidArgumentException( __( 'Pacchetto troppo grande.', 'consenso-bridge' ) );
			}
			$sig = wp_safe_remote_get( $release['sig'], array( 'timeout' => 20, 'limit_response_size' => self::MAX_SIG, 'user-agent' => $this->agente() ) );
			if ( is_wp_error( $sig ) || 200 !== wp_remote_retrieve_response_code( $sig ) ) {
				throw new \InvalidArgumentException( __( 'Firma della release non scaricata.', 'consenso-bridge' ) );
			}
			$commento = self::verify( $zip, (string) wp_remote_retrieve_body( $sig ), $this->impostazioni()['chiave'] );
			$firmato  = preg_match( '/(?:^|\t)file:([^\t]+)/', $commento, $m ) ? $m[1] : '';
			if ( '' === $firmato || $firmato !== self::nome_file( $package ) || $nome !== $firmato ) {
				throw new \InvalidArgumentException( __( 'La firma è di un altro pacchetto.', 'consenso-bridge' ) );
			}
		} catch ( \InvalidArgumentException $e ) {
			wp_delete_file( $zip );
			return $this->errore( $e->getMessage() );
		}
		return $zip;
	}

	/**
	 * Se il file è firmato dalla chiave pubblica minisign con questo testo .minisig; restituisce il commento fidato.
	 *
	 * @throws \InvalidArgumentException Col motivo.
	 */
	public static function verify( string $file, string $signature, string $public_key ): string {
		$raw = base64_decode( trim( $public_key ), true );
		if ( false === $raw || 42 !== strlen( $raw ) || 'Ed' !== substr( $raw, 0, 2 ) ) {
			throw new \InvalidArgumentException( __( 'Chiave pubblica minisign non valida.', 'consenso-bridge' ) );
		}
		$id  = substr( $raw, 2, 8 );
		$key = substr( $raw, 10, 32 );
		if ( strlen( $signature ) > self::MAX_SIG ) {
			throw new \InvalidArgumentException( __( 'Firma troppo lunga.', 'consenso-bridge' ) );
		}
		$lines = array_values( array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $signature ) ) ), 'strlen' ) );
		if ( 4 !== count( $lines ) || 0 !== strpos( $lines[0], 'untrusted comment:' ) || 0 !== strpos( $lines[2], 'trusted comment: ' ) ) {
			throw new \InvalidArgumentException( __( 'Firma minisign illeggibile.', 'consenso-bridge' ) );
		}
		$sig    = base64_decode( $lines[1], true );
		$global = base64_decode( $lines[3], true );
		if ( false === $sig || 74 !== strlen( $sig ) || false === $global || 64 !== strlen( $global ) ) {
			throw new \InvalidArgumentException( __( 'Firma minisign illeggibile.', 'consenso-bridge' ) );
		}
		if ( 'ED' !== substr( $sig, 0, 2 ) ) {
			throw new \InvalidArgumentException( __( 'Algoritmo di firma sconosciuto.', 'consenso-bridge' ) );
		}
		if ( ! hash_equals( $id, substr( $sig, 2, 8 ) ) ) {
			throw new \InvalidArgumentException( __( 'Firmato con un’altra chiave.', 'consenso-bridge' ) );
		}
		$ed25519 = substr( $sig, 10, 64 );
		if ( ! sodium_crypto_sign_verify_detached( $ed25519, self::blake2b( $file ), $key ) ) {
			throw new \InvalidArgumentException( __( 'La firma non corrisponde al file.', 'consenso-bridge' ) );
		}
		$comment = substr( $lines[2], strlen( 'trusted comment: ' ) );
		if ( ! sodium_crypto_sign_verify_detached( $global, $ed25519 . $comment, $key ) ) {
			throw new \InvalidArgumentException( __( 'Il commento firmato è stato alterato.', 'consenso-bridge' ) );
		}
		return $comment;
	}

	private function errore( string $motivo ): \WP_Error {
		/* translators: 1: nome del plugin, 2: motivo */
		return new \WP_Error( str_replace( '-', '_', $this->p['asset'] ) . '_update_signature', sprintf( __( 'Aggiornamento di %1$s fermato: %2$s', 'consenso-bridge' ), $this->p['nome'], $motivo ) );
	}

	/** Il nome del plugin e niente del sito (WordPress di serie ci mette l'indirizzo del sito). */
	private function agente(): string {
		return str_replace( ' ', '-', $this->p['nome'] ) . '-aggiornatore';
	}

	private function nome_zip( string $versione ): string {
		return $this->p['asset'] . '-' . $versione . '.zip';
	}

	private static function versione( $testo ): ?string {
		return is_string( $testo ) && 1 === preg_match( '/^v?(\d+\.\d+\.\d+)$/', $testo, $m ) ? $m[1] : null;
	}

	private static function https( string $url ): bool {
		return 0 === strpos( $url, 'https://' ) && false !== filter_var( $url, FILTER_VALIDATE_URL );
	}

	private static function nome_file( string $url ): string {
		return rawurldecode( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
	}

	/** BLAKE2b-512 di un file, letto a pezzi. */
	private static function blake2b( string $file ): string {
		$handle = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			throw new \InvalidArgumentException( __( 'File da verificare non leggibile.', 'consenso-bridge' ) );
		}
		$state = sodium_crypto_generichash_init( '', 64 );
		try {
			while ( ! feof( $handle ) ) {
				$chunk = fread( $handle, 65536 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
				if ( false === $chunk ) {
					break;
				}
				sodium_crypto_generichash_update( $state, $chunk );
			}
		} finally {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
		return sodium_crypto_generichash_final( $state, 64 );
	}
}
