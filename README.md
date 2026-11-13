# Consenso Bridge: release

Questo repository contiene solo le release firmate di **Consenso Bridge**, plugin WordPress (estensione per Complianz con banner, informativa e dichiarazione di accessibilità). Il codice sorgente è altrove.

Ogni release ha:

- `consenso-bridge-X.Y.Z.zip`, il plugin da installare;
- `consenso-bridge-X.Y.Z.zip.minisig`, la sua firma minisign.

Dalla prima versione con l'aggiornatore il plugin si aggiorna da sé dall'amministrazione di WordPress. Prima di
installare scarica ZIP e firma e li controlla con la chiave di rilascio: un pacchetto senza firma valida non viene
mai installato. Nessun dato del sito viene inviato.

Chiave pubblica di rilascio (id `7245FEACC0E94D12`):

```
RWQSTenArP5FclHgI0qEErGDYUEQ6gL4cEhpgec49VKtqKfgl2TIAn5W
```

Per verificare a mano: `minisign -Vm consenso-bridge-X.Y.Z.zip -P RWQSTenArP5FclHgI0qEErGDYUEQ6gL4cEhpgec49VKtqKfgl2TIAn5W`.

Licenza del plugin: GPL-2.0-or-later.
