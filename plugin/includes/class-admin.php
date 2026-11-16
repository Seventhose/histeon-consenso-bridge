<?php
/** ConsensoBridge\Admin: la pagina delle impostazioni sotto Complianz, con i moduli di salvataggio e di creazione delle pagine. */

namespace ConsensoBridge;

defined( 'ABSPATH' ) || exit;

final class Admin {

	const PAGINA = 'consenso-bridge';

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_consenso_bridge_salva', array( self::class, 'salva' ) );
		add_action( 'admin_post_consenso_bridge_crea', array( self::class, 'crea' ) );
	}

	/** La voce di menu sotto Complianz; senza Complianz, sotto Impostazioni. */
	public static function menu(): void {
		if ( ! empty( $GLOBALS['admin_page_hooks']['complianz'] ) ) {
			add_submenu_page( 'complianz', 'Consenso Bridge', 'Consenso Bridge', 'manage_options', self::PAGINA, array( self::class, 'pagina' ) );
			return;
		}
		add_options_page( 'Consenso Bridge', 'Consenso Bridge', 'manage_options', self::PAGINA, array( self::class, 'pagina' ) );
	}

	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::PAGINA );
	}

	/** La pagina delle impostazioni: avviso dell'esito, modulo, poi i documenti. */
	public static function pagina(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Consenso Bridge', 'consenso-bridge' ) . '</h1>';
		self::avviso();
		self::modulo_impostazioni();
		self::documenti();
		echo '</div>';
	}

	public static function salva(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		check_admin_referer( 'consenso_bridge_salva' );

		$dati = isset( $_POST['consenso_bridge'] ) && is_array( $_POST['consenso_bridge'] ) ? wp_unslash( $_POST['consenso_bridge'] ) : array();
		Impostazioni::salva( $dati );
		Banner::rigenera();

		wp_safe_redirect( add_query_arg( 'consenso_bridge_esito', 'salvato', self::url() ) );
		exit;
	}

	public static function crea(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		check_admin_referer( 'consenso_bridge_crea' );

		$tipo  = sanitize_key( wp_unslash( $_POST['tipo'] ?? '' ) );
		$esito = Pagine::crea( $tipo );

		if ( is_wp_error( $esito ) ) {
			$url = add_query_arg(
				array(
					'consenso_bridge_esito'     => 'errore',
					'consenso_bridge_messaggio' => rawurlencode( (string) $esito->get_error_code() ),
				),
				self::url()
			);
		} else {
			$url = add_query_arg( 'consenso_bridge_esito', 'creata', self::url() );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/** L'avviso dell'esito dell'ultima azione, se c'è. */
	private static function avviso(): void {
		$esito = isset( $_GET['consenso_bridge_esito'] ) ? sanitize_key( wp_unslash( $_GET['consenso_bridge_esito'] ) ) : '';

		if ( 'salvato' === $esito ) {
			self::notifica( 'success', __( 'Impostazioni salvate.', 'consenso-bridge' ) );
		} elseif ( 'creata' === $esito ) {
			self::notifica( 'success', __( 'Pagina creata.', 'consenso-bridge' ) );
		} elseif ( 'errore' === $esito ) {
			// Nell'indirizzo passa solo il codice dell'errore: il testo lo sceglie il plugin, non chi scrive il link.
			$codice = isset( $_GET['consenso_bridge_messaggio'] ) ? sanitize_key( wp_unslash( $_GET['consenso_bridge_messaggio'] ) ) : '';
			self::notifica( 'error', self::messaggio_errore( $codice ) );
		}
	}

	/** Il testo di un errore di Pagine::crea(), dal suo codice. */
	private static function messaggio_errore( string $codice ): string {
		if ( 'consenso_bridge_privacy_complianz' === $codice ) {
			return __( 'L\'informativa sulla privacy la genera già Complianz.', 'consenso-bridge' );
		}
		if ( 'consenso_bridge_tipo' === $codice ) {
			return __( 'Tipo di documento sconosciuto.', 'consenso-bridge' );
		}
		return __( 'Non sono riuscito a creare la pagina.', 'consenso-bridge' );
	}

	private static function notifica( string $tipo, string $testo ): void {
		echo '<div class="notice notice-' . esc_attr( $tipo ) . ' is-dismissible"><p>' . esc_html( $testo ) . '</p></div>';
	}

	/** Il modulo delle impostazioni, con i valori correnti. */
	private static function modulo_impostazioni(): void {
		$v      = Impostazioni::tutte();
		// Quello che il sito ha, qualunque sia la scelta salvata: serve alle etichette di «Automatico» e «Solo testi».
		$attivo = self::nome_framework( Framework::rileva( 'auto', defined( 'XB_VERSION' ), defined( 'ACSS_PLUGIN_FILE' ) ) );

		// L'ultimo esito dei controlli di Histeon, se c'è: il riquadro «Usa come bozza» prima dei campi dell'accessibilità.
		$esito = BozzaAccessibilita::leggi();
		if ( null !== $esito ) {
			wp_enqueue_script(
				'consenso-bridge-bozza',
				plugins_url( 'assets/js/bozza-accessibilita.js', CONSENSO_BRIDGE_FILE ),
				array(),
				CONSENSO_BRIDGE_VERSION,
				array( 'in_footer' => true )
			);
		}

		$righe = array(
			self::riga(
				'framework',
				__( 'Framework del sito', 'consenso-bridge' ),
				self::selezione(
					'framework',
					array(
						'auto'    => sprintf( __( 'Automatico (ora: %s)', 'consenso-bridge' ), $attivo ),
						'acss'    => __( 'Automatic.css', 'consenso-bridge' ),
						'histeon' => __( 'Histeon', 'consenso-bridge' ),
						'testi'   => sprintf( __( 'Solo testi: dimensioni da %s, colori di Complianz', 'consenso-bridge' ), $attivo ),
						'nessuno' => __( 'Nessuno: lascia i colori di Complianz', 'consenso-bridge' ),
					),
					(string) $v['framework']
				)
			),
			self::riga(
				'blocco',
				__( 'Blocco di video e mappe', 'consenso-bridge' ),
				'<label><input type="checkbox" id="consenso-bridge-blocco" name="consenso_bridge[blocco]" value="1"' . checked( (bool) $v['blocco'], true, false ) . '> ' . esc_html__( 'Blocca video e mappe di Histeon fino al consenso ai cookie di marketing', 'consenso-bridge' ) . '</label>',
				__( 'Gli iframe di YouTube, Vimeo e Google Maps negli altri contenuti (anche in Etch) li blocca già Complianz, se li hai indicati fra i servizi.', 'consenso-bridge' )
			),
			self::riga(
				'pr_finalita',
				__( 'Finalità dei trattamenti', 'consenso-bridge' ),
				'<textarea id="consenso-bridge-pr_finalita" name="consenso_bridge[pr_finalita]" rows="4" class="large-text">' . esc_textarea( (string) $v['pr_finalita'] ) . '</textarea>',
				__( 'Una voce per riga.', 'consenso-bridge' )
			),
			self::riga(
				'pr_conservazione',
				__( 'Tempi di conservazione', 'consenso-bridge' ),
				'<textarea id="consenso-bridge-pr_conservazione" name="consenso_bridge[pr_conservazione]" rows="4" class="large-text">' . esc_textarea( (string) $v['pr_conservazione'] ) . '</textarea>',
				__( 'Una voce per riga.', 'consenso-bridge' )
			),
			BozzaAccessibilita::riquadro(
				$esito,
				BozzaAccessibilita::stato_scelto( get_option( Impostazioni::OPZIONE, array() ) ),
				(string) $v['acc_stato']
			),
			self::riga(
				'acc_stato',
				__( 'Stato di conformità', 'consenso-bridge' ),
				self::selezione(
					'acc_stato',
					array(
						'conforme'       => __( 'Conforme', 'consenso-bridge' ),
						'parziale'       => __( 'Parzialmente conforme', 'consenso-bridge' ),
						'non_conforme'   => __( 'Non conforme', 'consenso-bridge' ),
					),
					(string) $v['acc_stato']
				)
			),
			self::riga(
				'acc_contenuti',
				__( 'Contenuti non accessibili', 'consenso-bridge' ),
				'<textarea id="consenso-bridge-acc_contenuti" name="consenso_bridge[acc_contenuti]" rows="4" class="large-text">' . esc_textarea( (string) $v['acc_contenuti'] ) . '</textarea>',
				__( 'Un contenuto per riga.', 'consenso-bridge' )
			),
			self::riga(
				'acc_valutazione',
				__( 'Tipo di valutazione', 'consenso-bridge' ),
				self::selezione(
					'acc_valutazione',
					array(
						'autovalutazione' => __( 'Autovalutazione', 'consenso-bridge' ),
						'strumenti'       => __( 'Autovalutazione con strumenti automatici e controlli a mano', 'consenso-bridge' ),
						'terzi'           => __( 'Valutazione di terzi', 'consenso-bridge' ),
					),
					(string) $v['acc_valutazione']
				)
			),
			self::riga(
				'acc_data',
				__( 'Data della dichiarazione', 'consenso-bridge' ),
				'<input type="date" id="consenso-bridge-acc_data" name="consenso_bridge[acc_data]" value="' . esc_attr( (string) $v['acc_data'] ) . '">'
			),
			self::riga(
				'acc_email',
				__( 'Email per le segnalazioni', 'consenso-bridge' ),
				'<input type="email" id="consenso-bridge-acc_email" name="consenso_bridge[acc_email]" class="regular-text" value="' . esc_attr( (string) $v['acc_email'] ) . '">',
				__( 'Se resta vuota vale quella di Complianz.', 'consenso-bridge' )
			),
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="consenso_bridge_salva">';
		wp_nonce_field( 'consenso_bridge_salva' );
		echo '<table class="form-table" role="presentation">' . implode( '', $righe ) . '</table>';
		submit_button( __( 'Salva', 'consenso-bridge' ) );
		echo '</form>';
	}

	/** Per ogni documento: il link alla pagina se c'è, altrimenti il pulsante per crearla. */
	private static function documenti(): void {
		echo '<h2>' . esc_html__( 'Documenti', 'consenso-bridge' ) . '</h2>';

		foreach ( Pagine::TIPI as $tipo ) {
			$id     = (int) Impostazioni::valore( 'pagina_' . $tipo );
			$titolo = Pagine::titolo( $tipo );

			echo '<div class="consenso-bridge-documento">';
			if ( self::pagina_pubblicata( $id ) ) {
				echo '<a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( sprintf( __( 'Apri: %s', 'consenso-bridge' ), $titolo ) ) . '</a>';
			} elseif ( 'privacy' === $tipo && Pagine::privacy_gestita_da_complianz( defined( 'cmplz_premium' ), self::opzione_complianz( 'privacy-statement' ) ) ) {
				echo esc_html__( 'L\'informativa sulla privacy la genera già Complianz.', 'consenso-bridge' );
			} else {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				echo '<input type="hidden" name="action" value="consenso_bridge_crea">';
				wp_nonce_field( 'consenso_bridge_crea' );
				echo '<input type="hidden" name="tipo" value="' . esc_attr( $tipo ) . '">';
				submit_button( sprintf( __( 'Crea la pagina: %s', 'consenso-bridge' ), $titolo ), 'secondary', 'crea-' . $tipo, false );
				echo '</form>';
			}
			echo '</div>';
		}
	}

	private static function riga( string $campo, string $etichetta, string $controllo, string $descrizione = '' ): string {
		$nota = '' !== $descrizione ? '<p class="description">' . esc_html( $descrizione ) . '</p>' : '';

		return '<tr><th scope="row"><label for="consenso-bridge-' . esc_attr( $campo ) . '">' . esc_html( $etichetta ) . '</label></th><td>' . $controllo . $nota . '</td></tr>';
	}

	private static function selezione( string $campo, array $scelte, string $attuale ): string {
		$html = '<select id="consenso-bridge-' . esc_attr( $campo ) . '" name="consenso_bridge[' . esc_attr( $campo ) . ']">';
		foreach ( $scelte as $valore => $etichetta ) {
			$html .= '<option value="' . esc_attr( (string) $valore ) . '"' . selected( $attuale, (string) $valore, false ) . '>' . esc_html( $etichetta ) . '</option>';
		}
		return $html . '</select>';
	}

	/** Il nome leggibile di un framework, come appare nella scelta automatica. */
	private static function nome_framework( string $framework ): string {
		if ( 'acss' === $framework ) {
			return __( 'Automatic.css', 'consenso-bridge' );
		}
		if ( 'histeon' === $framework ) {
			return __( 'Histeon', 'consenso-bridge' );
		}
		return __( 'Nessuno', 'consenso-bridge' );
	}

	/** Vero se l'id è una pagina pubblicata. */
	private static function pagina_pubblicata( int $id ): bool {
		$pagina = $id > 0 ? get_post( $id ) : null;
		return $pagina instanceof \WP_Post && 'page' === $pagina->post_type && 'publish' === $pagina->post_status;
	}

	/** Un dato di Complianz, o stringa vuota se Complianz manca. */
	private static function opzione_complianz( string $nome ) {
		return function_exists( 'cmplz_get_option' ) ? cmplz_get_option( $nome ) : '';
	}
}
