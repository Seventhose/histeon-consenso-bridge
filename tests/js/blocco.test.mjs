// Il blocco di video e mappe nel browser: plugin/assets/js/blocco.js su tests/js/pagina.html, con un finto Complianz e
// il contratto dei facade di Histeon (evento annullabile «xb:incorpora» con detail { tipo, fornitore, src, carica }).
// Playwright e axe-core non si installano: CB_PLAYWRIGHT e CB_AXE, altrimenti quelli del builder accanto a questo repo.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const qui = dirname(fileURLToPath(import.meta.url));
const require = createRequire(import.meta.url);
const builder = join(qui, '../../../builder/node_modules');
const percorsoPlaywright = process.env.CB_PLAYWRIGHT || join(builder, 'playwright');
const percorsoAxe = process.env.CB_AXE || join(builder, 'axe-core/axe.min.js');
const { chromium } = require(percorsoPlaywright);
const pagina = pathToFileURL(join(qui, 'pagina.html')).href;

let browser;
before(async () => { browser = await chromium.launch(); });
after(async () => { await browser?.close(); });

async function apri({ complianz = true, consenso = false } = {}) {
  const page = await browser.newPage();
  page.setDefaultTimeout(3000); // un elemento che non arriva fa fallire subito, non dopo 30 s
  const errori = [];
  page.on('pageerror', (e) => errori.push(String(e)));
  await page.goto(pagina);
  if (complianz) await page.evaluate(() => window.provaInstallaComplianz());
  await page.evaluate((c) => { window.provaConsenso = c; }, consenso);
  return { page, errori };
}

test('il file blocco.js esiste', () => {
  assert.ok(existsSync(join(qui, '../../plugin/assets/js/blocco.js')));
});

test('senza Complianz il video si carica subito', async () => {
  const { page, errori } = await apri({ complianz: false });
  await page.click('#video');
  assert.equal(await page.locator('.prova-frame').count(), 1);
  assert.equal(await page.locator('.consenso-blocco').count(), 0);
  assert.deepEqual(errori, []);
  await page.close();
});

test('senza window.consensoBridge non blocca niente', async () => {
  const { page } = await apri();
  await page.evaluate(() => { delete window.consensoBridge; });
  await page.click('#video');
  assert.equal(await page.locator('.prova-frame').count(), 1);
  await page.close();
});

test('con il consenso il video si carica subito', async () => {
  const { page } = await apri({ consenso: true });
  await page.click('#video');
  assert.equal(await page.locator('.prova-frame').count(), 1);
  assert.equal(await page.locator('.consenso-blocco').count(), 0);
  await page.close();
});

test('senza consenso: avviso BEM dopo il link, testo col fornitore, fuoco su Accetta', async () => {
  const { page, errori } = await apri();
  await page.click('#video');
  assert.equal(await page.locator('.prova-frame').count(), 0);
  const avviso = page.locator('.consenso-blocco');
  assert.equal(await avviso.count(), 1);
  assert.equal(await page.evaluate(() => document.getElementById('video').nextElementSibling.className), 'consenso-blocco');
  assert.equal(await avviso.getAttribute('role'), 'group');
  const idTesto = await avviso.getAttribute('aria-labelledby');
  assert.ok(idTesto, 'aria-labelledby');
  const testo = page.locator('.consenso-blocco__testo');
  assert.equal(await testo.getAttribute('id'), idTesto);
  assert.equal(await testo.textContent(), 'Questo video è fornito da YouTube: caricandolo, il tuo browser contatta i suoi server. Per guardarlo serve il tuo consenso ai cookie di marketing.');
  const accetta = page.locator('.consenso-blocco__pulsante--accetta');
  const preferenze = page.locator('.consenso-blocco__pulsante--preferenze');
  assert.equal(await accetta.textContent(), 'Accetta e mostra');
  assert.equal(await preferenze.textContent(), 'Gestisci le preferenze');
  assert.equal(await accetta.getAttribute('type'), 'button');
  assert.equal(await page.evaluate(() => document.activeElement.classList.contains('consenso-blocco__pulsante--accetta')), true);
  assert.equal(await page.locator('.consenso-blocco__azioni .consenso-blocco__pulsante').count(), 2);
  assert.equal(await page.getAttribute('#video', 'data-consenso-attesa'), '1');
  assert.deepEqual(errori, []);
  await page.close();
});

test('la mappa usa il suo testo', async () => {
  const { page } = await apri();
  await page.click('#mappa');
  assert.equal(await page.locator('.consenso-blocco__testo').textContent(), 'Questa mappa è fornita da OpenStreetMap: caricandola, il tuo browser contatta i suoi server. Per vederla serve il tuo consenso ai cookie di marketing.');
  await page.close();
});

test('un secondo clic non crea un secondo avviso e riporta il fuoco su Accetta', async () => {
  const { page } = await apri();
  await page.click('#video');
  await page.focus('#mappa');
  await page.click('#video');
  assert.equal(await page.locator('.consenso-blocco').count(), 1);
  assert.equal(await page.evaluate(() => document.activeElement.classList.contains('consenso-blocco__pulsante--accetta')), true);
  await page.close();
});

test('Accetta: consenso a Complianz, avviso tolto, video caricato una volta sola', async () => {
  const { page } = await apri();
  await page.click('#video');
  await page.click('.consenso-blocco__pulsante--accetta');
  assert.deepEqual(await page.evaluate(() => window.provaChiamate), [['marketing', 'allow']]);
  assert.equal(await page.locator('.consenso-blocco').count(), 0);
  assert.equal(await page.locator('.prova-frame').count(), 1);
  assert.equal(await page.evaluate(() => window.provaCaricati), 1);
  await page.close();
});

test('Gestisci le preferenze apre la finestra di Complianz e lascia l\'avviso', async () => {
  const { page } = await apri();
  await page.click('#video');
  await page.click('.consenso-blocco__pulsante--preferenze');
  assert.equal(await page.evaluate(() => window.provaPreferenze), 1);
  assert.equal(await page.locator('.consenso-blocco').count(), 1);
  await page.close();
});

test('consenso dato dal banner: i contenuti in attesa si caricano, gli altri no', async () => {
  const { page } = await apri();
  await page.click('#video');
  assert.equal(await page.locator('.consenso-blocco').count(), 1, 'prima l\'avviso');
  await page.evaluate(() => window.cmplz_set_consent('marketing', 'allow'));
  assert.equal(await page.locator('.consenso-blocco').count(), 0);
  assert.equal(await page.locator('.prova-frame').count(), 1, 'solo il video cliccato');
  assert.equal(await page.locator('#mappa').count(), 1, 'la mappa resta un link');
  await page.close();
});

test('un cambio di consenso che nega non carica niente', async () => {
  const { page } = await apri();
  await page.click('#video');
  await page.evaluate(() => window.cmplz_set_consent('marketing', 'deny'));
  assert.equal(await page.locator('.consenso-blocco').count(), 1);
  assert.equal(await page.locator('.prova-frame').count(), 0);
  await page.close();
});

test('axe: nessuna violazione seria con l\'avviso aperto', async () => {
  const { page } = await apri();
  await page.click('#video');
  await page.addScriptTag({ path: percorsoAxe });
  const esito = await page.evaluate(async () => (await window.axe.run(document.querySelector('.consenso-blocco'))).violations
    .filter((v) => v.impact === 'serious' || v.impact === 'critical').map((v) => v.id));
  assert.deepEqual(esito, []);
  await page.close();
});

test('CSS: i pulsanti dell\'avviso almeno 44 px di altezza e testo almeno 15 px', async () => {
  const { page } = await apri();
  await page.click('#video');
  const misure = await page.evaluate(() => [...document.querySelectorAll('.consenso-blocco__pulsante, .consenso-blocco__testo')]
    .map((el) => ({ classe: el.className, alto: el.getBoundingClientRect().height, font: parseFloat(getComputedStyle(el).fontSize) })));
  assert.equal(misure.length, 3, 'testo e due pulsanti');
  for (const m of misure) {
    assert.ok(m.font >= 15, `${m.classe}: testo ${m.font}px`);
    if (m.classe.includes('pulsante')) assert.ok(m.alto >= 44, `${m.classe}: alto ${m.alto}px`);
  }
  await page.close();
});
