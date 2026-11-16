<?php
/**
 * Banco dei test senza WordPress: le poche funzioni di WordPress che le classi pure usano, con le opzioni e i filtri
 * in memoria, e gli strumenti di verifica. Lo usa tests/php/run.php.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['cb_opzioni']      = array();
$GLOBALS['cb_filtri']       = array();
$GLOBALS['cb_azioni_fatte'] = array();

function __( $testo, $dominio = null ) { return (string) $testo; }
function _x( $testo, $contesto, $dominio = null ) { return (string) $testo; }
function _n( $uno, $molti, $n, $dominio = null ) { return 1 === (int) $n ? (string) $uno : (string) $molti; }
function esc_html( $testo ) { return htmlspecialchars( (string) $testo, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $testo ) { return htmlspecialchars( (string) $testo, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $testo, $dominio = null ) { return esc_html( $testo ); }
function esc_url( $url ) {
	$url = trim( (string) $url );
	return preg_match( '#^(https?:|mailto:|/)#i', $url ) ? htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) : '';
}
function sanitize_text_field( $testo ) { return trim( (string) preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $testo ) ) ); }
function sanitize_textarea_field( $testo ) { return trim( strip_tags( str_replace( "\r", '', (string) $testo ) ) ); }
function sanitize_email( $email ) {
	$email = trim( (string) $email );
	return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
}
function absint( $n ) { return abs( (int) $n ); }
function wp_parse_args( $args, $predefiniti = array() ) { return array_merge( (array) $predefiniti, (array) $args ); }
function wp_json_encode( $valore ) { return json_encode( $valore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
function get_option( $nome, $predefinito = false ) { return $GLOBALS['cb_opzioni'][ $nome ] ?? $predefinito; }
function update_option( $nome, $valore, $autoload = null ) { $GLOBALS['cb_opzioni'][ $nome ] = $valore; return true; }
function delete_option( $nome ) { unset( $GLOBALS['cb_opzioni'][ $nome ] ); return true; }
function add_filter( $gancio, $richiamo, $priorita = 10, $argomenti = 1 ) {
	$GLOBALS['cb_filtri'][ $gancio ][ $priorita ][] = array( $richiamo, (int) $argomenti );
	return true;
}
function add_action( $gancio, $richiamo, $priorita = 10, $argomenti = 1 ) { return add_filter( $gancio, $richiamo, $priorita, $argomenti ); }
function has_filter( $gancio, $richiamo = false ) {
	if ( empty( $GLOBALS['cb_filtri'][ $gancio ] ) ) {
		return false;
	}
	if ( false === $richiamo ) {
		return true;
	}
	foreach ( $GLOBALS['cb_filtri'][ $gancio ] as $priorita => $voci ) {
		foreach ( $voci as $voce ) {
			if ( $voce[0] === $richiamo ) {
				return $priorita;
			}
		}
	}
	return false;
}
function has_action( $gancio, $richiamo = false ) { return has_filter( $gancio, $richiamo ); }
function apply_filters( $gancio, $valore, ...$altri ) {
	if ( empty( $GLOBALS['cb_filtri'][ $gancio ] ) ) {
		return $valore;
	}
	$per_priorita = $GLOBALS['cb_filtri'][ $gancio ];
	ksort( $per_priorita );
	foreach ( $per_priorita as $voci ) {
		foreach ( $voci as $voce ) {
			$valore = call_user_func_array( $voce[0], array_slice( array_merge( array( $valore ), $altri ), 0, $voce[1] ) );
		}
	}
	return $valore;
}
function do_action( $gancio, ...$argomenti ) {
	$GLOBALS['cb_azioni_fatte'][] = array( $gancio, $argomenti );
	if ( empty( $GLOBALS['cb_filtri'][ $gancio ] ) ) {
		return;
	}
	$per_priorita = $GLOBALS['cb_filtri'][ $gancio ];
	ksort( $per_priorita );
	foreach ( $per_priorita as $voci ) {
		foreach ( $voci as $voce ) {
			call_user_func_array( $voce[0], array_slice( $argomenti, 0, $voce[1] ) );
		}
	}
}

/** Riporta opzioni, filtri e azioni a vuoto: lo fa run.php prima di ogni prova. */
function cb_azzera(): void {
	$GLOBALS['cb_opzioni']      = array();
	$GLOBALS['cb_filtri']       = array();
	$GLOBALS['cb_azioni_fatte'] = array();
}

/** Le azioni lanciate con quel nome, ognuna come lista dei suoi argomenti. */
function cb_azioni( string $gancio ): array {
	$trovate = array();
	foreach ( $GLOBALS['cb_azioni_fatte'] as $voce ) {
		if ( $voce[0] === $gancio ) {
			$trovate[] = $voce[1];
		}
	}
	return $trovate;
}

final class ProvaFallita extends Exception {}

$GLOBALS['cb_prove'] = array();
function prova( string $nome, callable $corpo ): void { $GLOBALS['cb_prove'][] = array( $nome, $corpo ); }
function uguale( $atteso, $reale, string $messaggio = '' ): void {
	if ( $atteso !== $reale ) {
		throw new ProvaFallita( ( '' !== $messaggio ? $messaggio . ': ' : '' ) . 'atteso ' . var_export( $atteso, true ) . ', ottenuto ' . var_export( $reale, true ) );
	}
}
function vero( $condizione, string $messaggio ): void {
	if ( true !== $condizione ) {
		throw new ProvaFallita( $messaggio );
	}
}
function contiene( string $ago, string $pagliaio, string $messaggio = '' ): void {
	if ( false === strpos( $pagliaio, $ago ) ) {
		throw new ProvaFallita( ( '' !== $messaggio ? $messaggio . ': ' : '' ) . 'manca ' . var_export( $ago, true ) . ' in ' . var_export( mb_substr( $pagliaio, 0, 600 ), true ) );
	}
}
function non_contiene( string $ago, string $pagliaio, string $messaggio = '' ): void {
	if ( false !== strpos( $pagliaio, $ago ) ) {
		throw new ProvaFallita( ( '' !== $messaggio ? $messaggio . ': ' : '' ) . 'non doveva esserci ' . var_export( $ago, true ) );
	}
}
