// Blocco di video e mappe di Histeon finché l'utente non dà il consenso ai cookie di marketing (Complianz).
(function () {
	'use strict';

	var attesa = []; // voci { link, avviso, carica } in attesa di consenso
	var contatore = 0;

	function consentito(cfg) {
		return typeof window.cmplz_has_consent === 'function' && !!window.cmplz_has_consent(cfg.categoria);
	}

	function testo(cfg, tipo, fornitore) {
		var modelli = cfg.testi || {};
		var modello = modelli[tipo] || modelli.video || '';
		return modello.replace('%s', function () { return fornitore; });
	}

	function crea(cfg, detail, link, carica) {
		contatore++;
		var idTesto = 'consenso-blocco-' + contatore;

		var avviso = document.createElement('div');
		avviso.className = 'consenso-blocco';
		avviso.setAttribute('role', 'group');
		avviso.setAttribute('aria-labelledby', idTesto);

		var p = document.createElement('p');
		p.className = 'consenso-blocco__testo';
		p.id = idTesto;
		p.textContent = testo(cfg, detail.tipo, detail.fornitore);

		var azioni = document.createElement('div');
		azioni.className = 'consenso-blocco__azioni';

		var accetta = document.createElement('button');
		accetta.type = 'button';
		accetta.className = 'consenso-blocco__pulsante consenso-blocco__pulsante--accetta';
		accetta.textContent = (cfg.testi || {}).accetta || '';

		var preferenze = document.createElement('button');
		preferenze.type = 'button';
		preferenze.className = 'consenso-blocco__pulsante consenso-blocco__pulsante--preferenze';
		preferenze.textContent = (cfg.testi || {}).preferenze || '';

		azioni.appendChild(accetta);
		azioni.appendChild(preferenze);
		avviso.appendChild(p);
		avviso.appendChild(azioni);

		var voce = { link: link, avviso: avviso, carica: carica };

		accetta.addEventListener('click', function () {
			var i = attesa.indexOf(voce);
			if (i < 0) return; // già sbloccato
			attesa.splice(i, 1);
			avviso.remove();
			link.removeAttribute('data-consenso-attesa');
			if (typeof window.cmplz_set_consent === 'function') {
				window.cmplz_set_consent(cfg.categoria, 'allow');
			}
			carica();
		});

		preferenze.addEventListener('click', function () {
			var gestisci = document.querySelector('.cmplz-manage-consent');
			if (gestisci) gestisci.click();
		});

		link.after(avviso);
		link.setAttribute('data-consenso-attesa', '1');
		attesa.push(voce);
		accetta.focus();
	}

	document.addEventListener('xb:incorpora', function (evento) {
		var cfg = window.consensoBridge;
		if (!cfg || typeof window.cmplz_has_consent !== 'function' || consentito(cfg)) return;

		evento.preventDefault();
		var link = evento.target;
		var detail = evento.detail;

		if (link.getAttribute('data-consenso-attesa') === '1') {
			var esistente = attesa.filter(function (v) { return v.link === link; })[0];
			if (esistente) {
				esistente.avviso.querySelector('.consenso-blocco__pulsante--accetta').focus();
				return;
			}
		}
		crea(cfg, detail, link, detail.carica);
	});

	document.addEventListener('cmplz_status_change', function () {
		var cfg = window.consensoBridge;
		if (!cfg || !consentito(cfg)) return;
		var coda = attesa.splice(0);
		coda.forEach(function (voce) {
			voce.avviso.remove();
			voce.link.removeAttribute('data-consenso-attesa');
			voce.carica();
		});
	});
})();
