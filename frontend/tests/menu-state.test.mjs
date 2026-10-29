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
  contains() { return false; }
}

test('刷新會員導覽後手機選單只切換一次，Escape與回頂端仍正常', () => {
  const button = new Node({ 'aria-label': '開啟導覽選單', 'aria-expanded': 'false' });
  const menu = new Node(); const back = new Node(); const documentEvents = new Node(); let scrolls = 0;
  const sandbox = { document: { querySelector: selector => ({ '.menu-toggle': button, '.primary-nav': menu, '.back-top': back })[selector], addEventListener: (...args) => documentEvents.addEventListener(...args) },
    window: { scrollTo() { scrolls++; } }, matchMedia: () => ({ matches: true, addEventListener() {} }) };
  vm.createContext(sandbox); vm.runInContext(source, sandbox);
  sandbox.initializeMenus(); sandbox.initializeMenus();
  button.emit('click'); assert.equal(button.getAttribute('aria-expanded'), 'true'); assert.equal(menu.classes.has('open'), true);
  documentEvents.emit('keydown', { key: 'Escape' }); assert.equal(button.getAttribute('aria-expanded'), 'false');
  assert.equal(button.getAttribute('aria-label'), '開啟導覽選單');
  back.emit('click', { preventDefault() {} }); assert.equal(scrolls, 1);
});

for (const [toggle, target] of [['.menu-toggle','.primary-nav'],['.admin-mobile-menu','.admin-sidebar']]) {
  test(`手機選單關閉時不接收焦點，Escape返回按鈕且桌面恢復可操作：${toggle}`, () => {
    const button=new Node({'aria-label':'開啟導覽選單','aria-expanded':'false'});const menu=new Node();const link=new Node();const outside=new Node();const events=new Node();
    let mediaChange;
    const mobile={matches:true,addEventListener(type,callback){assert.equal(type,'change');mediaChange=callback;}};
    const document={activeElement:outside,querySelector:selector=>({[toggle]:button,[target]:menu})[selector],addEventListener:(...args)=>events.addEventListener(...args)};
    menu.contains=node=>node===link;button.focus=()=>{document.activeElement=button;};
    const sandbox={document,matchMedia:()=>mobile};vm.createContext(sandbox);vm.runInContext(source,sandbox);sandbox.initializeMenus();
    assert.equal(menu.inert,true);
    button.emit('click');assert.equal(menu.inert,false);document.activeElement=link;
    events.emit('keydown',{key:'Escape'});assert.equal(document.activeElement,button);assert.equal(menu.inert,true);
    mobile.matches=false;mediaChange();assert.equal(menu.inert,false);
    mobile.matches=true;mediaChange();assert.equal(menu.inert,true);
    document.activeElement=outside;button.emit('click');events.emit('keydown',{key:'Escape'});assert.equal(document.activeElement,outside);
  });
}
