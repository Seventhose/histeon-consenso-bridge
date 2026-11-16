# Sicurezza

Consenso Bridge gira dentro WP accanto a Complianz: cambia l'aspetto del banner dei cookie, scrive l'informativa sulla privacy e la dichiarazione di accessibilità dai dati inseriti da un amministratore, collega quelle pagine nelle opzioni di Complianz, blocca facoltativamente video e mappe fino al consenso e si aggiorna da release firmate. Una falla può toccare siti veri. Se ne trovi una, segnalala in privato, non in una issue pubblica.

## Come segnalare

- Sul repository pubblico usa **Security → Report a vulnerability** (segnalazione privata di GitHub). Solo i manutentori la vedono.
- Scrivi la versione di Consenso Bridge, cosa può fare chi sfrutta la falla, con quale ruolo WP, e i passi per riprodurla.
- Non provarla su siti che non sono tuoi.

Rispondiamo il prima possibile, diciamo se la confermiamo e, quando la correzione è pronta, la pubblichiamo con una nota nel changelog (`plugin/readme.txt`). Chi l'ha segnalata è citato, se lo desidera.

## Versioni coperte

Solo l'ultima release. Le versioni precedenti non ricevono correzioni: aggiorna.

## Cosa è una falla

Le impostazioni del plugin sono riservate agli amministratori (`manage_options`). In breve, è una falla:

- chi ha meno permessi riesce a cambiare le impostazioni o a creare le pagine dei documenti;
- contenuto salvato da un utente che esegue codice nel browser di un altro (XSS), per esempio dai testi dei documenti o del blocco;
- richieste fatte a nome di un amministratore senza il suo consenso (CSRF);
- un video o una mappa bloccati che si caricano prima del consenso;
- un pacchetto installato dall'aggiornatore senza una firma valida della chiave di rilascio.

Non sono falle di Consenso Bridge ciò che può già fare un amministratore, né i problemi di Complianz, di altri plugin o dei temi.
