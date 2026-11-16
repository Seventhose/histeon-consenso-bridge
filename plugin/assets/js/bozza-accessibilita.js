// «Usa come bozza» nelle impostazioni di Consenso Bridge: mette nei campi della dichiarazione di accessibilità la bozza
// preparata dal PHP negli attributi data- del pulsante. Non salva niente: salva l'utente con «Salva».
// Ogni testo entra con value o textContent, mai come HTML. Lo stato si cambia solo verso «parziale», mai «conforme».
(function () {
	'use strict';

	function campo(nome) {
		return document.getElementById('consenso-bridge-' + nome);
	}

	function righe(json) {
		var lista;
		try {
			lista = JSON.parse(json || '');
		} catch (e) {
			return null;
		}
		if (!Array.isArray(lista)) {
			return null;
		}
		return lista.filter(function (riga) { return typeof riga === 'string'; }).join('\n');
	}

	function usa(pulsante) {
		var d = pulsante.dataset;

		var data = campo('acc_data');
		if (data && /^\d{4}-\d{2}-\d{2}$/.test(d.cbData || '')) {
			data.value = d.cbData;
		}

		var valutazione = campo('acc_valutazione');
		if (valutazione && d.cbValutazione === 'strumenti') {
			valutazione.value = 'strumenti';
		}

		var contenuti = campo('acc_contenuti');
		var testo = d.cbContenuti ? righe(d.cbContenuti) : null;
		if (contenuti && testo) {
			contenuti.value = testo;
		}

		var stato = campo('acc_stato');
		if (stato && d.cbStato === 'parziale') {
			stato.value = 'parziale';
		}

		var riquadro = pulsante.closest('.consenso-bozza__riquadro');
		var esito = riquadro ? riquadro.querySelector('.consenso-bozza__esito') : null;
		if (esito) {
			esito.textContent = d.cbFatto || '';
		}
	}

	document.addEventListener('click', function (evento) {
		var pulsante = evento.target && evento.target.closest ? evento.target.closest('.consenso-bozza__pulsante') : null;
		if (pulsante) {
			usa(pulsante);
		}
	});
})();
