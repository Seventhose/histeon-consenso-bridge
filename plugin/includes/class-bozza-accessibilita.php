<?php
/**
 * ConsensoBridge\BozzaAccessibilita: dall'ultimo esito dei controlli di Histeon (`xb_last_checks()`), una bozza per la
 * dichiarazione di accessibilità, mostrata in un riquadro delle impostazioni con il pulsante «Usa come bozza».
 *
 * La regola: un controllo automatico trova solo una parte dei problemi, quindi la bozza non propone mai «conforme».
 * Non cambia lo stato già scelto; se non è scelto propone al più «parzialmente conforme», e solo quando l'esito ha
 * problemi di accessibilità, risultati che il plugin non sa descrivere, pagine non controllate o un esito che non
 * dice quante pagine ha guardato. Scrive che la valutazione è fatta con strumenti automatici e controlli a mano
 * (VALUTAZIONE). La bozza riempie il modulo nel browser e non salva niente: salva l'utente con «Salva».
 */

namespace ConsensoBridge;

defined( 'ABSPATH' ) || exit;

final class BozzaAccessibilita {

	/** Voci di regole lette dall'esito: il resto si scarta. */
	const MAX_REGOLE = 40;

	/** Conteggi oltre questo numero non sono un esito credibile: l'esito si scarta. */
	const MAX_CONTEGGIO = 1000000;

	/** L'unico stato che la bozza può proporre. */
	const STATO_PROPOSTO = 'parziale';

	/** Il tipo di valutazione della bozza: la dichiarazione dice «fatta con strumenti automatici e controlli a mano». */
	const VALUTAZIONE = 'strumenti';

	/**
	 * L'esito ripulito dell'ultimo controllo di Histeon, o null se Histeon non c'è, non ha un esito o l'esito non ha la
	 * forma attesa. $funzione serve alle prove; nel plugin è sempre quella di Histeon.
	 */
	public static function leggi( string $funzione = 'xb_last_checks' ): ?array {
		if ( ! function_exists( $funzione ) ) {
			return null;
		}
		try {
			$grezzo = $funzione();
		} catch ( \Throwable $e ) {
			return null;
		}
		return self::esito( $grezzo );
	}

	/**
	 * Ripulisce un esito che viene da un altro plugin, quindi non fidato: `errors` e `warnings` interi da 0, `at` data
	 * ISO 8601, `rules` nome → intero da 1. Restano solo le regole di accessibilità con una frase, fra le prime
	 * MAX_REGOLE voci. Null se la forma non è quella attesa.
	 *
	 * `non_descritti`: i risultati (errori più avvisi) che nessuna regola conosciuta spiega: regole nuove di Histeon,
	 * voci oltre MAX_REGOLE o un elenco vuoto con errori. `pagine`: quante pagine il controllo ha guardato su quante,
	 * se Histeon lo dice (`pages: {checked, total}`); null se manca o non ha la forma attesa.
	 *
	 * @return array{data: string, errori: int, avvisi: int, regole: array<string, int>, non_descritti: int, pagine: array{controllate: int, totali: int}|null}|null
	 */
	public static function esito( mixed $grezzo ): ?array {
		if ( ! is_array( $grezzo ) ) {
			return null;
		}
		$errori = $grezzo['errors'] ?? null;
		$avvisi = $grezzo['warnings'] ?? null;
		$regole = $grezzo['rules'] ?? array();
		$data   = self::data( $grezzo['at'] ?? null );
		if ( ! self::conteggio( $errori ) || ! self::conteggio( $avvisi ) || ! is_array( $regole ) || null === $data ) {
			return null;
		}

		$accessibilita = array();
		$descritti     = 0;
		foreach ( array_slice( $regole, 0, self::MAX_REGOLE, true ) as $regola => $numero ) {
			if ( ! is_string( $regola ) || ! self::conteggio( $numero ) || $numero < 1 || ! RegoleAccessibilita::conosciuta( $regola ) ) {
				continue;
			}
			$descritti += $numero;
			if ( null !== RegoleAccessibilita::frase( $regola ) ) {
				$accessibilita[ $regola ] = $numero;
			}
		}

		return array(
			'data'          => $data,
			'errori'        => $errori,
			'avvisi'        => $avvisi,
			'regole'        => $accessibilita,
			'non_descritti' => max( 0, $errori + $avvisi - $descritti ),
			'pagine'        => self::pagine( $grezzo['pages'] ?? null ),
		);
	}

	/**
	 * Vero se l'esito non basta a dire che non ci sono problemi: risultati che il plugin non sa descrivere, pagine
	 * del sito che il controllo non ha guardato, o nessun conteggio delle pagine (`pagine` null: `pages` assente, non
	 * valido o con `total` a 0). Allora la bozza non è mai migliore di STATO_PROPOSTO.
	 *
	 * @param array{non_descritti?: int, pagine?: array{controllate: int, totali: int}|null} $esito Da esito().
	 */
	public static function incompleto( array $esito ): bool {
		$pagine = $esito['pagine'] ?? null;
		return ( $esito['non_descritti'] ?? 0 ) > 0 || ! is_array( $pagine ) || $pagine['controllate'] < $pagine['totali'];
	}

	/**
	 * Vero se lo stato di conformità è già stato scelto e salvato (le impostazioni lette con get_option): solo allora la
	 * bozza non lo tocca per nessun motivo.
	 */
	public static function stato_scelto( mixed $salvate ): bool {
		return is_array( $salvate ) && is_string( $salvate['acc_stato'] ?? null ) && in_array( $salvate['acc_stato'], array( 'conforme', 'parziale', 'non_conforme' ), true );
	}

	/**
	 * La bozza per i campi del modulo. `contenuti` e `stato` a null vogliono dire «non toccare il campo».
	 * Mai «conforme»: lo stato è al più STATO_PROPOSTO, solo con problemi (o con un esito incompleto()) e solo se non
	 * è già scelto.
	 *
	 * @param array{data: string, errori: int, avvisi: int, regole: array<string, int>, non_descritti?: int, pagine?: array{controllate: int, totali: int}|null} $esito Da esito().
	 * @return array{data: string, valutazione: string, contenuti: list<string>|null, stato: string|null}
	 */
	public static function bozza( array $esito, bool $stato_scelto ): array {
		$frasi    = self::frasi( $esito );
		$problemi = array() !== $frasi || self::incompleto( $esito );

		return array(
			'data'        => $esito['data'],
			'valutazione' => self::VALUTAZIONE,
			'contenuti'   => array() !== $frasi ? $frasi : null,
			'stato'       => $problemi && ! $stato_scelto ? self::STATO_PROPOSTO : null,
		);
	}

	/**
	 * La riga della tabella delle impostazioni con il riquadro, o stringa vuota senza esito. La bozza va nel pulsante
	 * in attributi data-; lo script la mette nei campi solo con value e textContent.
	 *
	 * @param array{data: string, errori: int, avvisi: int, regole: array<string, int>, non_descritti?: int, pagine?: array{controllate: int, totali: int}|null}|null $esito Da esito().
	 */
	public static function riquadro( ?array $esito, bool $stato_scelto, string $stato_attuale ): string {
		if ( null === $esito ) {
			return '';
		}
		$bozza = self::bozza( $esito, $stato_scelto );
		$frasi = self::frasi( $esito );

		$titolo = sprintf(
			/* translators: 1: data, 2: «N errori», 3: «M avvisi». */
			__( 'Dall\'ultimo controllo di Histeon, del %1$s: %2$s e %3$s', 'consenso-bridge' ),
			self::data_leggibile( $esito['data'] ),
			sprintf( _n( '%d errore', '%d errori', $esito['errori'], 'consenso-bridge' ), $esito['errori'] ),
			sprintf( _n( '%d avviso', '%d avvisi', $esito['avvisi'], 'consenso-bridge' ), $esito['avvisi'] )
		);

		$html = '<p class="consenso-bozza__titolo"><strong>' . esc_html( $titolo ) . '</strong></p>';
		if ( array() !== $frasi ) {
			$html .= '<p class="consenso-bozza__testo">' . esc_html__( 'Problemi di accessibilità trovati:', 'consenso-bridge' ) . '</p>';
			$html .= '<ul class="consenso-bozza__elenco">';
			foreach ( $frasi as $frase ) {
				$html .= '<li class="consenso-bozza__voce">' . esc_html( $frase ) . '</li>';
			}
			$html .= '</ul>';
		}
		$non_descritti = (int) ( $esito['non_descritti'] ?? 0 );
		if ( $non_descritti > 0 ) {
			$html .= '<p class="consenso-bozza__testo">' . esc_html(
				sprintf(
					/* translators: %d: quanti risultati del controllo il plugin non sa descrivere. */
					_n( '%d problema non descritto: viene da una regola di Histeon che Consenso Bridge non sa ancora spiegare. Guardalo nel controllo di Histeon.', '%d problemi non descritti: vengono da regole di Histeon che Consenso Bridge non sa ancora spiegare. Guardali nel controllo di Histeon.', $non_descritti, 'consenso-bridge' ),
					$non_descritti
				)
			) . '</p>';
		}
		$pagine = $esito['pagine'] ?? null;
		if ( ! is_array( $pagine ) ) {
			$html .= '<p class="consenso-bozza__testo">' . esc_html__( 'Il controllo non dice quante pagine ha guardato: delle pagine non dice nulla.', 'consenso-bridge' ) . '</p>';
		} elseif ( $pagine['controllate'] < $pagine['totali'] ) {
			$html .= '<p class="consenso-bozza__testo">' . esc_html(
				sprintf(
					/* translators: 1: pagine controllate, 2: pagine del sito. */
					__( 'Il controllo ha guardato %1$d pagine su %2$d: delle altre non dice nulla.', 'consenso-bridge' ),
					$pagine['controllate'],
					$pagine['totali']
				)
			) . '</p>';
		}
		if ( array() === $frasi && ! self::incompleto( $esito ) ) {
			$html .= '<p class="consenso-bozza__testo">' . esc_html__( 'Il controllo non ha trovato problemi di accessibilità. La bozza scrive solo la data e il tipo di valutazione.', 'consenso-bridge' ) . '</p>';
		}
		$html .= '<p class="consenso-bozza__nota">' . esc_html__( 'Un controllo automatico trova solo una parte dei problemi: non basta a dichiarare il sito conforme. La bozza scrive che la valutazione è fatta con strumenti automatici e controlli a mano: fai i controlli a mano prima di salvare.', 'consenso-bridge' ) . '</p>';
		if ( ( array() !== $frasi || self::incompleto( $esito ) ) && $stato_scelto && 'conforme' === $stato_attuale ) {
			$html .= '<p class="consenso-bozza__nota consenso-bozza__nota--attenzione">' . esc_html__( 'Lo stato salvato è «Conforme», ma il controllo ha trovato problemi o non ha guardato tutto il sito: la bozza non lo cambia, controllalo tu.', 'consenso-bridge' ) . '</p>';
		}

		$attributi = array(
			'data-cb-data'        => $bozza['data'],
			'data-cb-valutazione' => $bozza['valutazione'],
			'data-cb-fatto'       => __( 'Bozza inserita nel modulo. Non è ancora salvata: controllala e premi «Salva».', 'consenso-bridge' ),
		);
		if ( null !== $bozza['contenuti'] ) {
			$attributi['data-cb-contenuti'] = (string) wp_json_encode( $bozza['contenuti'] );
		}
		if ( null !== $bozza['stato'] ) {
			$attributi['data-cb-stato'] = $bozza['stato'];
		}
		$attr = '';
		foreach ( $attributi as $nome => $valore ) {
			$attr .= ' ' . $nome . '="' . esc_attr( $valore ) . '"';
		}
		$html .= '<p class="consenso-bozza__azioni"><button type="button" class="button consenso-bozza__pulsante"' . $attr . '>' . esc_html__( 'Usa come bozza', 'consenso-bridge' ) . '</button></p>';
		$html .= '<p class="consenso-bozza__esito" role="status"></p>';

		return '<tr class="consenso-bozza"><th scope="row">' . esc_html__( 'Controllo di Histeon', 'consenso-bridge' ) . '</th><td><div class="consenso-bozza__riquadro notice notice-info inline">' . $html . '</div></td></tr>';
	}

	/** Le frasi delle regole di accessibilità trovate, nell'ordine dell'esito. */
	private static function frasi( array $esito ): array {
		$frasi = array();
		foreach ( (array) ( $esito['regole'] ?? array() ) as $regola => $numero ) {
			$frase = is_string( $regola ) && is_int( $numero ) && $numero > 0 ? RegoleAccessibilita::frase( $regola ) : null;
			if ( null !== $frase ) {
				$frasi[] = $frase;
			}
		}
		return $frasi;
	}

	/**
	 * `pages` dell'esito: due interi, `checked` fra 0 e `total`, `total` da 1. Null se manca o ha un'altra forma.
	 *
	 * @return array{controllate: int, totali: int}|null
	 */
	private static function pagine( mixed $pagine ): ?array {
		if ( ! is_array( $pagine ) ) {
			return null;
		}
		$controllate = $pagine['checked'] ?? null;
		$totali      = $pagine['total'] ?? null;
		if ( ! self::conteggio( $controllate ) || ! self::conteggio( $totali ) || $totali < 1 || $controllate > $totali ) {
			return null;
		}
		return array(
			'controllate' => $controllate,
			'totali'      => $totali,
		);
	}

	/** La data di `at` (ISO 8601) nel fuso del sito, come AAAA-MM-GG; null se non è una data. */
	private static function data( mixed $at ): ?string {
		if ( ! is_string( $at ) || strlen( $at ) > 40 ) {
			return null;
		}
		$quando = \DateTimeImmutable::createFromFormat( \DateTimeInterface::ATOM, $at );
		if ( false === $quando ) {
			return null;
		}
		$fuso = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		return $quando->setTimezone( $fuso )->format( 'Y-m-d' );
	}

	private static function data_leggibile( string $ymd ): string {
		return function_exists( 'date_i18n' ) ? Pagine::data_leggibile( $ymd ) : $ymd;
	}

	/** @phpstan-assert-if-true int<0, max> $valore */
	private static function conteggio( mixed $valore ): bool {
		return is_int( $valore ) && $valore >= 0 && $valore <= self::MAX_CONTEGGIO;
	}
}
