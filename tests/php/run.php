<?php
/**
 * Le prove senza WordPress: `php tests/php/run.php [nome…]`. Senza nomi lancia tutti i tests/php/*.test.php; con dei
 * nomi solo quelli (`php tests/php/run.php impostazioni framework`). Carica le classi di plugin/includes che esistono.
 * Una riga PASS/FAIL per prova, in fondo «N prove, M fallite»; esce con 1 se una fallisce.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$radice = dirname( __DIR__, 2 );
foreach ( glob( $radice . '/plugin/includes/class-*.php' ) ?: array() as $file ) {
	require_once $file;
}

$nomi  = array_slice( $argv, 1 );
$files = $nomi
	? array_map( static fn( $n ) => __DIR__ . '/' . $n . '.test.php', $nomi )
	: ( glob( __DIR__ . '/*.test.php' ) ?: array() );

$fallite = 0;
$totale  = 0;
foreach ( $files as $file ) {
	if ( ! is_file( $file ) ) {
		echo 'FAIL ' . basename( $file ) . ": il file di prova non c'è\n";
		++$fallite;
		continue;
	}
	$GLOBALS['cb_prove'] = array();
	require $file;
	foreach ( $GLOBALS['cb_prove'] as [ $nome, $corpo ] ) {
		++$totale;
		cb_azzera();
		try {
			$corpo();
			echo 'PASS ' . basename( $file, '.test.php' ) . ': ' . $nome . "\n";
		} catch ( Throwable $e ) {
			++$fallite;
			echo 'FAIL ' . basename( $file, '.test.php' ) . ': ' . $nome . ' — ' . $e->getMessage() . "\n";
		}
	}
}
echo $totale . ' prove, ' . $fallite . " fallite\n";
exit( $fallite ? 1 : 0 );
