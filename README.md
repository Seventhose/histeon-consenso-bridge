# Consenso Bridge

Plugin per WP che si integra con Complianz: il banner dei cookie prende le variabili del sito (ACSS o Histeon), due documenti in più (informativa sulla privacy e dichiarazione di accessibilità) e un blocco facoltativo di video e mappe di Histeon fino al consenso ai cookie di marketing.

Consenso Bridge è un'estensione indipendente: non è sviluppato né approvato dai titolari di Complianz (vedi [TRADEMARKS.md](TRADEMARKS.md)).

Questo repository pubblica le **release** e i **sorgenti** della versione più recente, un commit per versione; lo sviluppo quotidiano avviene altrove.

## Requisiti

- WP 6.5 o successivo, PHP 8.1 o successivo.
- Complianz 7.5 o successivo.
- Lingua Bridge, per tradurre i testi delle impostazioni (facoltativo).

## Release firmate

Ogni release ha:

- `consenso-bridge-X.Y.Z.zip`, il plugin da installare;
- `consenso-bridge-X.Y.Z.zip.minisig`, la sua firma minisign.

Il plugin si aggiorna dall'amministrazione di WP: prima di installare scarica ZIP e firma e li controlla con la chiave di rilascio, e un pacchetto senza firma valida non viene mai installato. Nessun dato del sito viene inviato. `CONSENSO_BRIDGE_UPDATES` a `false` in `wp-config.php` spegne gli aggiornamenti.

Chiave pubblica di rilascio (id `7245FEACC0E94D12`):

```
RWQSTenArP5FclHgI0qEErGDYUEQ6gL4cEhpgec49VKtqKfgl2TIAn5W
```

Per verificare a mano:

```
minisign -Vm consenso-bridge-X.Y.Z.zip -P RWQSTenArP5FclHgI0qEErGDYUEQ6gL4cEhpgec49VKtqKfgl2TIAn5W
```

## Sorgenti

- `plugin/`: il plugin (lo ZIP della release è questa cartella).
- `tests/`: prove unitarie in PHP (`php tests/php/run.php`), prova del blocco nel browser (`node --test tests/js/blocco.test.mjs`, con Playwright e axe-core indicati da `CB_PLAYWRIGHT` e `CB_AXE`) e prove dentro un WP vero (`dev/test.sh`, con Docker e una copia di Complianz indicata da `CB_COMPLIANZ`).
- `scripts/`: compilazione del CSS (`node scripts/css.mjs`, con un sass indicato da `CB_SASS`) e controllo dei segreti.

## Licenza

GPL-2.0-or-later: il testo è in [LICENSE.txt](LICENSE.txt).

- Segnalare una falla, in privato: [SECURITY.md](SECURITY.md).
- Il nome Histeon e i marchi di altri: [TRADEMARKS.md](TRADEMARKS.md).
