<?php
/** ConsensoBridge\RegoleAccessibilita: da una regola dei controlli di Histeon, una frase in parole semplici per la dichiarazione. */

namespace ConsensoBridge;

defined( 'ABSPATH' ) || exit;

final class RegoleAccessibilita {

	/**
	 * Le regole dei controlli di Histeon che non riguardano l'accessibilità (immagini, link, ricerca, peso): note, ma
	 * senza una frase. Con quelle di frase() fanno l'elenco intero di Histeon (`ReleaseChecks::RULES`); la prova
	 * regole-accessibilita le confronta con il file del builder.
	 */
	const ALTRE = array( 'img-src', 'img-size', 'img-big', 'link-href', 'link-noopener', 'untranslated', 'description-missing', 'canonical-missing', 'noindex', 'site-hidden', 'link-broken', 'page-heavy' );

	/**
	 * Vero se la regola è una di quelle che il plugin conosce, di accessibilità o no. I risultati di una regola che non
	 * conosce (Histeon ne ha aggiunta una dopo) la bozza non li descrive e non li dà mai per assenti.
	 */
	public static function conosciuta( string $regola ): bool {
		return null !== self::frase( $regola ) || in_array( $regola, self::ALTRE, true );
	}

	/**
	 * La frase per una regola dei controlli di Histeon, o null se la regola non riguarda l'accessibilità o non è nota.
	 * Una frase per regola, al plurale: dice che cosa può incontrare un visitatore, senza gergo e senza sigle.
	 */
	public static function frase( string $regola ): ?string {
		return match ( $regola ) {
			'h1-missing'    => __( 'Alcune pagine non hanno un titolo principale.', 'consenso-bridge' ),
			'h1-many'       => __( 'Alcune pagine hanno più di un titolo principale.', 'consenso-bridge' ),
			'heading-skip'  => __( 'In alcune pagine i titoli saltano un livello, per esempio dal titolo principale a un sottotitolo di terzo livello.', 'consenso-bridge' ),
			'heading-empty' => __( 'Alcuni titoli sono vuoti.', 'consenso-bridge' ),
			'img-alt'       => __( 'Alcune immagini non hanno un testo alternativo.', 'consenso-bridge' ),
			'link-name'     => __( 'Alcuni link non hanno un testo che dica dove portano.', 'consenso-bridge' ),
			'link-vague'    => __( 'Alcuni link hanno un testo generico, come «clicca qui», che non dice dove portano.', 'consenso-bridge' ),
			'button-name'   => __( 'Alcuni pulsanti non hanno un testo che ne dica la funzione.', 'consenso-bridge' ),
			'field-label'   => __( 'Alcuni campi dei moduli non hanno un’etichetta.', 'consenso-bridge' ),
			'iframe-title'  => __( 'Alcuni contenuti inseriti da altri siti, come video o mappe, non hanno un titolo che ne descriva il contenuto.', 'consenso-bridge' ),
			'contrast'      => __( 'In alcuni punti il testo ha poco contrasto con lo sfondo.', 'consenso-bridge' ),
			'lang-missing'  => __( 'Alcune pagine non dichiarano la lingua in cui sono scritte.', 'consenso-bridge' ),
			'title-missing' => __( 'Alcune pagine non hanno il titolo che compare nella scheda del browser e nei risultati dei motori di ricerca.', 'consenso-bridge' ),
			default         => null,
		};
	}
}
