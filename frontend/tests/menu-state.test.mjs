import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const source = (await readFile(new URL('../assets/js/core/ui.js', import.meta.url), 'utf8')).replace(/^import .*;\r?\n/gm, '').replace(/\bexport /g, '');
class Node {
  constructor(attrs = {}) { this.attrs = attrs; this.events = new Map(); this.classes = new Set();
    this.classList = { remove: key => this.classes.delete(key), toggle: (key, yes) => yes ? this.classes.add(key) : this.classes.delete(key) }; }
  getAttribute(key) { return this.attrs[key]; }
  setAttribute(key, value) { this.attrs[key] = value; }
  addEventListener(type, callback) { const list = this.events.get(type) || []; list.push(callback); this.events.set(type, list); }
  emit(type, event = {}) { for (const callback of this.events.get(type) || []) callback(event); }
  querySelectorAll() { return []; }
}

test('刷新會員導覽後手機選單只切換一次，Escape與回頂端仍正常', () => {
  const button = new Node({ 'aria-label': '開啟導覽選單', 'aria-expanded': 'false' });
  const menu = new Node(); const back = new Node(); const documentEvents = new Node(); let scrolls = 0;
  const sandbox = { document: { querySelector: selector => ({ '.menu-toggle': button, '.primary-nav': menu, '.back-top': back })[selector], addEventListener: (...args) => documentEvents.addEventListener(...args) },
    window: { scrollTo() { scrolls++; } }, matchMedia: () => ({ matches: true }) };
  vm.createContext(sandbox); vm.runInContext(source, sandbox);
  sandbox.initializeMenus(); sandbox.initializeMenus();
  button.emit('click'); assert.equal(button.getAttribute('aria-expanded'), 'true'); assert.equal(menu.classes.has('open'), true);
  documentEvents.emit('keydown', { key: 'Escape' }); assert.equal(button.getAttribute('aria-expanded'), 'false');
  assert.equal(button.getAttribute('aria-label'), '開啟導覽選單');
  back.emit('click', { preventDefault() {} }); assert.equal(scrolls, 1);
});
