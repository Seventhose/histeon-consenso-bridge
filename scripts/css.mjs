#!/usr/bin/env node
// Compila plugin/assets/scss/consenso-bridge.scss in plugin/assets/css/consenso-bridge.css con un sass già installato:
// CB_SASS, altrimenti quello di ../../strumenti accanto a questo repo. `--controlla` non scrive: esce con 1 se il CSS nel repo
// non è quello che uscirebbe dal sorgente.
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const radice = join(dirname(fileURLToPath(import.meta.url)), '..');
const sorgente = join(radice, 'plugin/assets/scss/consenso-bridge.scss');
const uscita = join(radice, 'plugin/assets/css/consenso-bridge.css');
const sass = process.env.CB_SASS || join(radice, '../../strumenti/node_modules/.bin/sass');

if (!existsSync(sorgente)) {
  console.error('✗ manca plugin/assets/scss/consenso-bridge.scss');
  process.exit(1);
}
if (!existsSync(sass)) {
  console.error(`✗ sass non trovato: ${sass} (CB_SASS per indicarne un altro)`);
  process.exit(2);
}
const css = execFileSync(sass, ['--no-source-map', '--style=expanded', sorgente], { encoding: 'utf8' }).trimEnd() + '\n';
const testa = '/* Generato da scripts/css.mjs da assets/scss/consenso-bridge.scss: non modificare a mano. */\n';
if (process.argv.includes('--controlla')) {
  const attuale = existsSync(uscita) ? readFileSync(uscita, 'utf8') : '';
  if (attuale !== testa + css) {
    console.error('✗ plugin/assets/css/consenso-bridge.css non è aggiornato: lancia node scripts/css.mjs');
    process.exit(1);
  }
  console.log('✓ CSS aggiornato');
} else {
  writeFileSync(uscita, testa + css);
  console.log('✓ plugin/assets/css/consenso-bridge.css');
}
