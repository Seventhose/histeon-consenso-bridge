<?php
/**
 * Plugin Name: Consenso Bridge
 * Description: Estensione per Complianz: banner con le variabili del sito (ACSS o Histeon), informativa sulla privacy e dichiarazione di accessibilità, blocco facoltativo di video e mappe di Histeon fino al consenso.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Seventhose
 * License: GPL-2.0-or-later
 * Text Domain: consenso-bridge
 * Domain Path: /languages
 * Update URI: https://github.com/Seventhose/histeon-consenso-bridge
 */

namespace ConsensoBridge;

defined( 'ABSPATH' ) || exit;

define( 'CONSENSO_BRIDGE_VERSION', '0.1.0' );
define( 'CONSENSO_BRIDGE_FILE', __FILE__ );
define( 'CONSENSO_BRIDGE_DIR', __DIR__ . '/' );

foreach ( array( 'impostazioni', 'framework', 'banner', 'ponti', 'documenti', 'pagine', 'blocco', 'regole-accessibilita', 'bozza-accessibilita', 'admin', 'plugin', 'aggiornatore' ) as $nome ) {
	require_once CONSENSO_BRIDGE_DIR . 'includes/class-' . $nome . '.php';
}

// Aggiornamenti firmati dalle release pubbliche di Seventhose/histeon-consenso-bridge (ZIP e firma minisign).
// CONSENSO_BRIDGE_UPDATES false in wp-config.php lo spegne.
Aggiornatore::avvia(
	array(
		'prefisso' => 'CONSENSO_BRIDGE',
		'file'     => __FILE__,
		'versione' => CONSENSO_BRIDGE_VERSION,
		'asset'    => 'consenso-bridge',
		'nome'     => 'Consenso Bridge',
		'fonte'    => 'github:Seventhose/histeon-consenso-bridge',
		'chiave'   => 'RWQSTenArP5FclHgI0qEErGDYUEQ6gL4cEhpgec49VKtqKfgl2TIAn5W',
	)
);

add_action( 'plugins_loaded', array( Plugin::class, 'init' ) );
