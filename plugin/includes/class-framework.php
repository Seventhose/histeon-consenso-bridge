<?php
/** Il framework del sito (ACSS, Histeon o nessuno) e le variabili di Complianz che ne prendono i colori e le misure. */

namespace ConsensoBridge;

defined( 'ABSPATH' ) || exit;

final class Framework {

	/** Sceglie il framework: la scelta esplicita vale, altrimenti (anche con «Solo testi») Histeon prima di ACSS, poi nessuno. */
	public static function rileva( string $scelta, bool $histeon, bool $acss ): string {
		if ( in_array( $scelta, array( 'acss', 'histeon', 'nessuno' ), true ) ) {
			return $scelta;
		}
		if ( $histeon ) {
			return 'histeon';
		}
		if ( $acss ) {
			return 'acss';
		}
		return 'nessuno';
	}

	/** Il framework attivo sul sito, secondo l'impostazione salvata. */
	public static function attivo(): string {
		return self::rileva(
			(string) Impostazioni::valore( 'framework' ),
			defined( 'XB_VERSION' ),
			defined( 'ACSS_PLUGIN_FILE' )
		);
	}

	/** Vero con la scelta «Solo testi»: dal framework solo le dimensioni dei testi, i colori restano quelli di Complianz. */
	public static function solo_testi(): bool {
		return 'testi' === Impostazioni::valore( 'framework' );
	}

	/** Le variabili di Complianz (cmplz_*) e i token del framework che le sostituiscono. */
	public static function mappa( string $framework ): array {
		$mappa = array();

		if ( 'acss' === $framework ) {
			$mappa = array(
				'cmplz_banner_background_color'             => 'var(--body-bg-color)',
				'cmplz_banner_border_color'                 => 'var(--border-color-dark)',
				'cmplz_banner_border_width'                 => 'var(--border-width)',
				'cmplz_banner_border_radius'                => 'var(--radius)',
				'cmplz_text_color'                          => 'var(--text-color)',
				'cmplz_hyperlink_color'                     => 'var(--link-color)',
				'cmplz_title_font_size'                     => 'var(--h4)',
				'cmplz_text_font_size'                      => 'var(--text-m)',
				'cmplz_link_font_size'                      => 'var(--text-m)',
				'cmplz_category_body_font_size'             => 'var(--text-m)',
				'cmplz_category_header_title_font_size'     => 'var(--text-m)',
				'cmplz_category_header_active_font_size'    => 'var(--text-m)',
				'cmplz_category_header_always_active_color' => 'var(--primary-dark)',
				'cmplz_button_font_size'                    => 'var(--text-m)',
				'cmplz_button_border_radius'                => 'var(--btn-radius)',
				'cmplz_button_accept_background_color'      => 'var(--primary-dark)',
				'cmplz_button_accept_border_color'          => 'var(--primary-dark)',
				'cmplz_button_accept_text_color'            => 'var(--white)',
				'cmplz_button_deny_background_color'        => 'var(--body-bg-color)',
				'cmplz_button_deny_border_color'            => 'var(--border-color-dark)',
				'cmplz_button_deny_text_color'              => 'var(--text-color)',
				'cmplz_button_settings_background_color'    => 'var(--body-bg-color)',
				'cmplz_button_settings_border_color'        => 'var(--border-color-dark)',
				'cmplz_button_settings_text_color'          => 'var(--text-color)',
				'cmplz_slider_active_color'                 => 'var(--primary)',
				'cmplz_slider_inactive_color'               => 'var(--neutral-light)',
				'cmplz_slider_bullet_color'                 => 'var(--white)',
			);
		} elseif ( 'histeon' === $framework ) {
			$mappa = array(
				'cmplz_banner_background_color'             => 'var(--xb-surface)',
				'cmplz_banner_border_color'                 => 'var(--xb-border-color)',
				'cmplz_banner_border_width'                 => 'var(--xb-border-width)',
				'cmplz_banner_border_radius'                => 'var(--xb-card-radius)',
				'cmplz_text_color'                          => 'var(--xb-text)',
				'cmplz_hyperlink_color'                     => 'var(--xb-link)',
				'cmplz_title_font_size'                     => 'var(--xb-text-l)',
				'cmplz_text_font_size'                      => 'var(--xb-text-m)',
				'cmplz_link_font_size'                      => 'var(--xb-text-m)',
				'cmplz_category_body_font_size'             => 'var(--xb-text-m)',
				'cmplz_category_header_title_font_size'     => 'var(--xb-text-m)',
				'cmplz_category_header_active_font_size'    => 'var(--xb-text-m)',
				'cmplz_category_header_always_active_color' => 'var(--xb-primary)',
				'cmplz_button_font_size'                    => 'var(--xb-text-m)',
				'cmplz_button_border_radius'                => 'var(--xb-btn-radius)',
				'cmplz_button_accept_background_color'      => 'var(--xb-primary)',
				'cmplz_button_accept_border_color'          => 'var(--xb-primary)',
				'cmplz_button_accept_text_color'            => 'var(--xb-on-primary)',
				'cmplz_button_deny_background_color'        => 'var(--xb-surface)',
				'cmplz_button_deny_border_color'            => 'var(--xb-border-color)',
				'cmplz_button_deny_text_color'              => 'var(--xb-text)',
				'cmplz_button_settings_background_color'    => 'var(--xb-surface)',
				'cmplz_button_settings_border_color'        => 'var(--xb-border-color)',
				'cmplz_button_settings_text_color'          => 'var(--xb-text)',
				'cmplz_slider_active_color'                 => 'var(--xb-primary)',
				'cmplz_slider_inactive_color'               => 'var(--xb-border-color)',
				'cmplz_slider_bullet_color'                 => 'var(--xb-surface)',
			);
		}

		return (array) apply_filters( 'consenso_bridge_mappa', $mappa, $framework );
	}

	/** Il CSS con le variabili di Complianz (con $solo_testi, solo le *_font_size); valori pericolosi e nomi strani scartati. */
	public static function css( string $framework, bool $solo_testi = false ): string {
		$righe = array();
		foreach ( self::mappa( $framework ) as $chiave => $valore ) {
			if ( ! is_string( $chiave ) || 1 !== preg_match( $solo_testi ? '/^cmplz_[a-z_]+_font_size$/' : '/^cmplz_[a-z_]+$/', $chiave ) ) {
				continue;
			}
			if ( ! is_string( $valore ) || 1 === preg_match( '/[;{}<>\\\\]/', $valore ) ) {
				continue;
			}
			$righe[] = "\t--" . $chiave . ': ' . $valore . ";\n";
		}

		if ( ! $righe ) {
			return '';
		}
		return ":root {\n" . implode( '', $righe ) . "}\n";
	}
}
