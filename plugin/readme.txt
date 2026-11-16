=== Consenso Bridge ===
Contributors: seventhose
Requires at least: 6.5
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Estensione per Complianz: banner con le variabili del sito (ACSS o Histeon), informativa sulla privacy e dichiarazione di accessibilità.

== Descrizione ==

Consenso Bridge estende Complianz sui siti fatti con Etch + ACSS o con Histeon.

* Banner con le variabili del sito: colori, bordi e testi del banner di Complianz prendono quelli di ACSS o di Histeon.
* Con la scelta «Solo testi» il banner prende dal sito solo le dimensioni dei testi e tiene i colori impostati in Complianz: per i siti dove i colori del marchio non sono nelle variabili del framework.
* Due documenti in più: informativa sulla privacy e dichiarazione di accessibilità. Sono pagine con lo shortcode [consenso_documento tipo="privacy"] e [consenso_documento tipo="accessibilita"].
* Blocco facoltativo di video e mappe di Histeon fino al consenso ai cookie di marketing. Il blocco è spento di serie.
* Testi tradotti con Lingua Bridge; con Static Bridge le pagine statiche si rigenerano quando cambiano le impostazioni.

Consenso Bridge è un'estensione indipendente: non è sviluppato né approvato dai titolari di Complianz.

Il blocco di video e mappe è spento di serie. Gli iframe di YouTube, Vimeo e Google Maps negli altri contenuti li blocca già Complianz, se li hai indicati fra i servizi.

== Requisiti ==

* Complianz 7.5 o successivo.
* Lingua Bridge, per tradurre i testi delle impostazioni (facoltativo).

== Installazione ==

1. Carica la cartella consenso-bridge in wp-content/plugins, oppure installa lo ZIP dal pannello dei plugin.
2. Attiva il plugin.
3. Vai in Complianz → Consenso Bridge e scegli il framework del sito.

== Changelog ==

= 0.1.0 =
* Prima versione: banner con le variabili di ACSS o Histeon, informativa sulla privacy e dichiarazione di accessibilità, blocco facoltativo di video e mappe di Histeon.
