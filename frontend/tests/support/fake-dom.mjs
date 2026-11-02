// 測試用的最小 DOM：只實作頁面程式實際用到的部分，讓測試可以直接載入真正的模組執行。
export class FakeNode {
  constructor(tag = 'div', id = '') {
    this.tagName = tag.toUpperCase();
    this.id = id;
    this.children = [];
    this.attributes = {};
    this.listeners = {};
    this.dataset = {};
    this.style = {};
    this.hidden = false;
    this.disabled = false;
    this.value = '';
    this.textContentValue = '';
  }
  get childNodes() {
    return this.children;
  }
  get textContent() {
    return this.children.length
      ? this.children.map(child => (typeof child === 'string' ? child : child.textContent)).join('')
      : this.textContentValue;
  }
  set textContent(value) {
    this.children = [];
    this.textContentValue = value === undefined || value === null ? '' : String(value);
  }
  append(...nodes) {
    this.children.push(...nodes);
  }
  replaceChildren(...nodes) {
    this.children = nodes;
  }
  setAttribute(name, value) {
    this.attributes[name] = String(value);
    if (name === 'href' || name === 'colspan') this[name] = String(value);
  }
  getAttribute(name) {
    return this.attributes[name] ?? null;
  }
  removeAttribute(name) {
    delete this.attributes[name];
  }
  addEventListener(type, listener) {
    (this.listeners[type] ||= []).push(listener);
  }
  // 依序執行監聽器並等待非同步處理完成，測試可以直接 await 點擊結果。
  async dispatch(type, event = {}) {
    for (const listener of this.listeners[type] || []) await listener({ target: this, ...event });
  }
  focus() {
    globalThis.document.activeElement = this;
  }
  querySelector() {
    return null;
  }
  querySelectorAll() {
    return [];
  }
  /** 遞迴找出符合條件的子節點，方便斷言。 */
  find(predicate) {
    for (const child of this.children) {
      if (typeof child === 'string') continue;
      if (predicate(child)) return child;
      const found = child.find(predicate);
      if (found) return found;
    }
    return null;
  }
}

/** 安裝全域 document／window，回傳依 id 取節點的函式；尚未存在的 id 會自動建立。 */
export function installDom() {
  const nodes = new Map();
  const byId = id => {
    if (!nodes.has(id)) nodes.set(id, new FakeNode('div', id));
    return nodes.get(id);
  };
  globalThis.document = {
    activeElement: null,
    createElement: tag => new FakeNode(tag),
    createTextNode: text => String(text),
    querySelector: selector => (selector.startsWith('#') ? byId(selector.slice(1)) : null),
    querySelectorAll: () => [],
    addEventListener() {},
  };
  globalThis.window = { setTimeout, clearTimeout, addEventListener() {}, location: { search: '' } };
  return byId;
}

/** 等待目前排入的 promise 與計時器都執行完。 */
export async function settle(times = 6) {
  for (let i = 0; i < times; i++) await new Promise(resolve => setImmediate(resolve));
}
