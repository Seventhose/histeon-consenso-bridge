<?php
/** ConsensoBridge\Documenti: l'HTML dell'informativa sulla privacy e della dichiarazione di accessibilità. */

namespace ConsensoBridge;

defined( 'ABSPATH' ) || exit;

final class Documenti {

	const GARANTE_URL = 'https://www.garanteprivacy.it';
	const AGID_URL    = 'https://www.agid.gov.it';

	/** Le righe non vuote di un testo, senza spazi ai bordi. */
	public static function paragrafi( string $testo ): array {
		$righe = array_map( 'trim', explode( "\n", str_replace( "\r", '', $testo ) ) );
		return array_values( array_filter( $righe, static function ( $riga ) {
			return '' !== $riga;
		} ) );
	}

	/** Il documento sulla privacy. Chiavi mancanti = vuote. */
	public static function privacy( array $d ): string {
		$titolare   = self::valore( $d, 'titolare' );
		$sito       = self::valore( $d, 'sito' );
		$indirizzo  = self::valore( $d, 'indirizzo' );
		$paese      = self::valore( $d, 'paese' );
		$email      = self::valore( $d, 'email' );
		$telefono   = self::valore( $d, 'telefono' );
		$url_cookie = self::valore( $d, 'url_cookie' );
		$servizi    = self::elenco_valori( $d, 'servizi' );
		$chi        = '' !== $titolare ? $titolare : $sito;

		$sezioni = array();

		// 1. Titolare del trattamento.
		$parti  = array_filter( array( $chi, $indirizzo, $paese ), static function ( $parte ) {
			return '' !== $parte;
		} );
		$corpo  = self::paragrafo( esc_html( sprintf( __( 'Il titolare del trattamento dei dati personali raccolti da questo sito è %s.', 'consenso-bridge' ), implode( ', ', $parti ) ) ) );
		if ( '' !== $email ) {
			$frase = esc_html( __( 'Per qualsiasi domanda sui tuoi dati puoi scrivere a ', 'consenso-bridge' ) ) . self::link( 'mailto:' . $email, $email );
			if ( '' !== $telefono ) {
				$frase .= esc_html( sprintf( __( ' o telefonare al %s', 'consenso-bridge' ), $telefono ) );
			}
			$corpo .= self::paragrafo( $frase . esc_html( '.' ) );
		}
		$sezioni[] = self::sezione( __( 'Titolare del trattamento', 'consenso-bridge' ), $corpo );

		// 2. Dati trattati e perché.
		$corpo  = self::paragrafo( esc_html( __( 'Dati di navigazione. I sistemi che fanno funzionare il sito registrano alcuni dati, come l\'indirizzo IP, la data e l\'ora delle richieste e le pagine visitate. Servono a far funzionare il sito e a proteggerlo; la base giuridica è il legittimo interesse del titolare (art. 6, par. 1, lett. f del Regolamento UE 2016/679, «GDPR»).', 'consenso-bridge' ) ) );
		$corpo .= self::paragrafo( esc_html( __( 'Dati che ci mandi. Se ci scrivi per email o con un modulo del sito, usiamo i tuoi dati solo per risponderti e per dare seguito alla tua richiesta; la base giuridica è l\'esecuzione di misure precontrattuali o di un contratto (art. 6, par. 1, lett. b GDPR).', 'consenso-bridge' ) ) );
		$cookie = esc_html( __( 'Cookie e strumenti simili. Li usiamo come spiegato nella ', 'consenso-bridge' ) );
		$url_ok = esc_url( $url_cookie );
		$cookie .= ( '' !== $url_ok && '#' !== $url_ok )
			? self::link( $url_cookie, __( 'Cookie Policy', 'consenso-bridge' ) )
			: esc_html( __( 'Cookie Policy', 'consenso-bridge' ) );
		$cookie .= esc_html( __( '. Quelli che non servono al funzionamento del sito partono solo con il tuo consenso (art. 6, par. 1, lett. a GDPR), che puoi ritirare in ogni momento.', 'consenso-bridge' ) );
		$corpo .= self::paragrafo( $cookie );
		foreach ( self::paragrafi( self::valore( $d, 'finalita' ) ) as $riga ) {
			$corpo .= self::paragrafo( esc_html( $riga ) );
		}
		$sezioni[] = self::sezione( __( 'Quali dati trattiamo e perché', 'consenso-bridge' ), $corpo );

		// 3. Comunicazione a terzi.
		$corpo = self::paragrafo( esc_html( __( 'I dati sono trattati da chi gestisce il sito e dai fornitori che lo aiutano a farlo funzionare, come il servizio di hosting e quello di posta elettronica, nominati responsabili del trattamento. Non vendiamo i tuoi dati.', 'consenso-bridge' ) ) );
		if ( array() !== $servizi ) {
			$corpo .= self::paragrafo( esc_html( __( 'Il sito usa anche questi servizi di terze parti, che possono trattare dati dei visitatori:', 'consenso-bridge' ) ) );
			$corpo .= self::elenco( $servizi );
		} else {
			$corpo .= self::paragrafo( esc_html( __( 'Il sito non usa servizi di terze parti che trattano dati dei visitatori.', 'consenso-bridge' ) ) );
		}
		$sezioni[] = self::sezione( __( 'A chi comunichiamo i dati', 'consenso-bridge' ), $corpo );

		// 4. Trasferimenti extra UE, solo se ci sono servizi di terze parti.
		if ( array() !== $servizi ) {
			$corpo     = self::paragrafo( esc_html( __( 'Alcuni di questi servizi possono trasferire dati fuori dall\'Unione europea. Avviene solo verso paesi con una decisione di adeguatezza della Commissione europea o con le clausole contrattuali tipo (artt. 45 e 46 GDPR).', 'consenso-bridge' ) ) );
			$sezioni[] = self::sezione( __( 'Trasferimenti fuori dall\'Unione europea', 'consenso-bridge' ), $corpo );
		}

		// 5. Conservazione.
		$righe = self::paragrafi( self::valore( $d, 'conservazione' ) );
		$corpo = '';
		if ( array() !== $righe ) {
			foreach ( $righe as $riga ) {
				$corpo .= self::paragrafo( esc_html( $riga ) );
			}
		} else {
			$corpo = self::paragrafo( esc_html( __( 'I dati di navigazione si conservano per il tempo che serve alla sicurezza del sito. I messaggi si conservano per il tempo necessario a rispondere e poi solo quanto chiede la legge. Le scelte sui cookie durano quanto indicato nella Cookie Policy.', 'consenso-bridge' ) ) );
		}
		$sezioni[] = self::sezione( __( 'Per quanto tempo conserviamo i dati', 'consenso-bridge' ), $corpo );

		// 6. Diritti.
		$diritti = esc_html( __( 'Puoi chiedere di accedere ai tuoi dati, di correggerli o cancellarli, di limitarne il trattamento e di riceverli in un formato leggibile, e puoi opporti al trattamento (artt. 15-22 GDPR).', 'consenso-bridge' ) );
		if ( '' !== $email ) {
			$diritti .= esc_html( __( ' Per farlo scrivi a ', 'consenso-bridge' ) ) . self::link( 'mailto:' . $email, $email ) . esc_html( '.' );
		}
		$corpo  = self::paragrafo( $diritti );
		$corpo .= self::paragrafo( esc_html( __( 'Se il trattamento si basa sul consenso, puoi ritirarlo in ogni momento; quello che è stato fatto prima resta lecito.', 'consenso-bridge' ) ) );
		$corpo .= self::paragrafo( esc_html( __( 'Puoi anche proporre reclamo al Garante per la protezione dei dati personali (', 'consenso-bridge' ) ) . self::link( self::GARANTE_URL, 'www.garanteprivacy.it' ) . esc_html( ').' ) );
		$sezioni[] = self::sezione( __( 'I tuoi diritti', 'consenso-bridge' ), $corpo );

		// 7. Aggiornamenti, solo con la data.
		$aggiornato = self::valore( $d, 'aggiornato' );
		if ( '' !== $aggiornato ) {
			$corpo     = self::paragrafo( esc_html( sprintf( __( 'Questa informativa è stata aggiornata il %s.', 'consenso-bridge' ), $aggiornato ) ) );
			$sezioni[] = self::sezione( __( 'Aggiornamenti', 'consenso-bridge' ), $corpo );
		}

		return self::contenitore( 'privacy', implode( '', $sezioni ) );
	}

	/** La dichiarazione di accessibilità. Stato sconosciuto = parziale. */
	public static function accessibilita( array $d ): string {
		$titolare = self::valore( $d, 'titolare' );
		$sito     = self::valore( $d, 'sito' );
		$url      = self::valore( $d, 'url' );
		$email    = self::valore( $d, 'email' );
		$chi      = '' !== $titolare ? $titolare : $sito;

		$stato_letto = self::valore( $d, 'stato' );
		$stato       = in_array( $stato_letto, array( 'conforme', 'non_conforme' ), true ) ? $stato_letto : 'parziale';

		$sezioni = array();

		// 1. Impegno.
		$url_ok = esc_url( $url );
		$sito_html = ( '' !== $url_ok ) ? self::link( $url, $sito ) : esc_html( $sito );
		$corpo = self::paragrafo(
			esc_html( sprintf( __( '%s si impegna a rendere accessibile il sito ', 'consenso-bridge' ), $chi ) )
			. $sito_html
			. esc_html( __( '. Questa dichiarazione usa come riferimento la norma europea EN 301 549 e le Linee guida per l\'accessibilità dei contenuti web (WCAG) 2.1, livello AA.', 'consenso-bridge' ) )
		);
		$sezioni[] = self::sezione( __( 'Impegno', 'consenso-bridge' ), $corpo );

		// 2. Stato di conformità.
		if ( 'conforme' === $stato ) {
			$frase = __( 'Il sito è conforme ai requisiti.', 'consenso-bridge' );
		} elseif ( 'non_conforme' === $stato ) {
			$frase = __( 'Il sito non è ancora conforme ai requisiti.', 'consenso-bridge' );
		} else {
			$frase = __( 'Il sito è parzialmente conforme ai requisiti: alcuni contenuti, elencati sotto, non sono ancora accessibili.', 'consenso-bridge' );
		}
		$sezioni[] = self::sezione( __( 'Stato di conformità', 'consenso-bridge' ), self::paragrafo( esc_html( $frase ) ) );

		// 3. Contenuti non accessibili.
		$contenuti = self::paragrafi( self::valore( $d, 'contenuti' ) );
		if ( array() !== $contenuti ) {
			$sezioni[] = self::sezione( __( 'Contenuti non accessibili', 'consenso-bridge' ), self::elenco( $contenuti ) );
		} elseif ( 'conforme' !== $stato ) {
			$corpo     = self::paragrafo( esc_html( __( 'Stiamo verificando quali contenuti non sono accessibili e li elencheremo qui.', 'consenso-bridge' ) ) );
			$sezioni[] = self::sezione( __( 'Contenuti non accessibili', 'consenso-bridge' ), $corpo );
		}

		// 4. Come è stata preparata.
		$data  = self::valore( $d, 'data' );
		$testo = __( 'La dichiarazione è stata preparata', 'consenso-bridge' );
		if ( '' !== $data ) {
			$testo .= sprintf( __( ' il %s', 'consenso-bridge' ), $data );
		}
		$testo .= ' ' . match ( self::valore( $d, 'valutazione' ) ) {
			'terzi'     => __( 'con una valutazione affidata a terzi.', 'consenso-bridge' ),
			'strumenti' => __( 'con un\'autovalutazione, fatta con strumenti automatici e controlli a mano.', 'consenso-bridge' ),
			default     => __( 'con un\'autovalutazione.', 'consenso-bridge' ),
		};
		$sezioni[] = self::sezione( __( 'Come è stata preparata questa dichiarazione', 'consenso-bridge' ), self::paragrafo( esc_html( $testo ) ) );

		// 5. Segnalazioni.
		$segnalazioni = esc_html( __( 'Se trovi un contenuto che non riesci a usare o vuoi segnalare un problema di accessibilità, ', 'consenso-bridge' ) );
		if ( '' !== $email ) {
			$segnalazioni .= esc_html( __( 'scrivi a ', 'consenso-bridge' ) ) . self::link( 'mailto:' . $email, $email ) . esc_html( '.' );
		} else {
			$segnalazioni .= esc_html( __( 'usa i contatti indicati nel sito.', 'consenso-bridge' ) );
		}
		$segnalazioni .= esc_html( __( ' Ti rispondiamo entro 30 giorni.', 'consenso-bridge' ) );
		$sezioni[] = self::sezione( __( 'Segnalazioni', 'consenso-bridge' ), self::paragrafo( $segnalazioni ) );

		// 6. Procedura di attuazione.
		$agid = esc_html( __( 'Se non ricevi risposta o la risposta non ti soddisfa, puoi rivolgerti all\'Agenzia per l\'Italia Digitale (AgID), che vigila sull\'accessibilità: ', 'consenso-bridge' ) )
			. self::link( self::AGID_URL, 'www.agid.gov.it' )
			. esc_html( '.' );
		$sezioni[] = self::sezione( __( 'Procedura di attuazione', 'consenso-bridge' ), self::paragrafo( $agid ) );

		return self::contenitore( 'accessibilita', implode( '', $sezioni ) );
	}

	private static function contenitore( string $tipo, string $sezioni ): string {
		return '<div class="consenso-documento consenso-documento--' . $tipo . '">' . $sezioni . '</div>';
	}

	/** Titolo già tradotto (non escapato) e corpo già escapato. */
	private static function sezione( string $titolo, string $corpo ): string {
		return '<section class="consenso-documento__sezione"><h2 class="consenso-documento__titolo">' . esc_html( $titolo ) . '</h2>' . $corpo . '</section>';
	}

	/** Il corpo di testo è già escapato. */
	private static function paragrafo( string $html ): string {
		return '<p class="consenso-documento__testo">' . $html . '</p>';
	}

	private static function elenco( array $voci ): string {
		$html = '<ul class="consenso-documento__elenco">';
		foreach ( $voci as $voce ) {
			$html .= '<li class="consenso-documento__voce">' . esc_html( $voce ) . '</li>';
		}
		return $html . '</ul>';
	}

	/** Il testo è grezzo: qui lo escapo. */
	private static function link( string $url, string $testo ): string {
		return '<a class="consenso-documento__link" href="' . esc_url( $url ) . '">' . esc_html( $testo ) . '</a>';
	}

	private static function valore( array $d, string $chiave ): string {
		return isset( $d[ $chiave ] ) && is_scalar( $d[ $chiave ] ) ? trim( (string) $d[ $chiave ] ) : '';
	}

	/** Le voci non vuote di una lista di stringhe. */
	private static function elenco_valori( array $d, string $chiave ): array {
		$voci = isset( $d[ $chiave ] ) && is_array( $d[ $chiave ] ) ? $d[ $chiave ] : array();
		$voci = array_map( static function ( $voce ) {
			return is_scalar( $voce ) ? trim( (string) $voce ) : '';
		}, $voci );
		return array_values( array_filter( $voci, static function ( $voce ) {
			return '' !== $voce;
		} ) );
	}
}
