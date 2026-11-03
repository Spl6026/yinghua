import test from 'node:test';
import assert from 'node:assert/strict';
import { loadWithSandbox } from './support/sandbox-module.mjs';

async function setup(submit = async () => {}) {
  const nodes = new Map();
  const document = { activeElement: null, body: { append() {} } };
  function element(tag, attrs = {}, text = '') {
    const handlers = new Map();
    const node = {
      tag,
      attrs: { ...attrs },
      textContent: text,
      children: [],
      isConnected: true,
      value: '',
      hidden: false,
      disabled: false,
      append(...children) {
        for (const child of children) {
          child.parent = this;
          this.children.push(child);
        }
      },
      setAttribute(name, value) {
        this.attrs[name] = value;
      },
      removeAttribute(name) {
        delete this.attrs[name];
      },
      addEventListener(name, fn) {
        handlers.set(name, fn);
      },
      focus() {
        document.activeElement = this;
      },
      showModal() {
        this.open = true;
      },
      close() {
        this.open = false;
        handlers.get('close')?.();
      },
      closest() {
        let n = this;
        while (n) {
          if (n.hidden) return n;
          n = n.parent;
        }
        return null;
      },
      querySelectorAll() {
        return this.children.flatMap(n => (['input', 'button'].includes(n.tag) ? [n] : n.querySelectorAll()));
      },
      fire(name, event) {
        return handlers.get(name)?.(event);
      },
    };
    if (attrs.id) nodes.set(attrs.id, node);
    return node;
  }
  const context = { document, element };
  Object.assign(context, await loadWithSandbox('components/session-prompt.js', context));
  const trigger = element('textarea');
  trigger.focus();
  const prompt = context.sessionPrompt(
    submit,
    () => {},
    () => {}
  );
  const dialog = nodes.get('session-prompt'),
    title = nodes.get('session-prompt-title');
  const controls = () => dialog.querySelectorAll().filter(n => !n.disabled && !n.closest('[hidden]'));
  const tab = shiftKey => {
    let prevented = false;
    dialog.fire('keydown', {
      key: 'Tab',
      shiftKey,
      preventDefault() {
        prevented = true;
      },
    });
    return prevented;
  };
  return { prompt, document, nodes, trigger, dialog, title, controls, tab };
}

test('到期預警先聚焦完整訊息，首次正反向 Tab 進入框內操作', async () => {
  const s = await setup();
  s.prompt.show('warn', { identifier: 'test-account' });
  assert.equal(s.document.activeElement, s.title);
  assert.equal(s.title.attrs['aria-describedby'], 'session-prompt-message session-prompt-owner');
  assert.equal(s.nodes.get('session-prompt-owner').textContent, '登入帳號：test-account');
  assert.equal(s.tab(false), true);
  assert.equal(s.document.activeElement, s.controls()[0]);
  s.title.focus();
  s.tab(true);
  assert.equal(s.document.activeElement, s.controls().at(-1));
});

test('警示轉為到期時重新聚焦訊息，密碼成為第一個操作且兩端循環', async () => {
  const s = await setup();
  s.prompt.show('warn', {});
  s.tab(false);
  s.prompt.show('expired', {});
  assert.equal(s.document.activeElement, s.title);
  assert.equal(s.title.textContent, '登入已到期');
  s.tab(false);
  assert.equal(s.document.activeElement, s.nodes.get('session-current-password'));
  s.tab(true);
  assert.equal(s.document.activeElement, s.controls().at(-1));
  s.tab(false);
  assert.equal(s.document.activeElement, s.nodes.get('session-current-password'));
});

test('驗證失敗保留錯誤關聯並清除密碼，關閉返回原輸入點', async () => {
  const s = await setup(async () => {
    throw new Error('帳號或密碼不正確。');
  });
  s.prompt.show('expired', {});
  const password = s.nodes.get('session-current-password');
  password.value = 'existing-test-password';
  await s.dialog.children[0].fire('submit', { preventDefault() {} });
  assert.equal(password.value, '');
  assert.equal(password.attrs['aria-invalid'], 'true');
  assert.equal(s.document.activeElement, s.nodes.get('session-prompt-error'));
  assert.equal(s.nodes.get('session-prompt-error').textContent, '帳號或密碼不正確。');
  assert.equal(password.attrs['aria-describedby'], 'session-prompt-error');
  assert.equal(s.nodes.get('session-prompt-error').attrs.role, 'alert');
  assert.equal(s.nodes.get('session-prompt-status').textContent, '');
  s.tab(false);
  assert.equal(s.document.activeElement, password);
  s.prompt.close();
  assert.equal(s.document.activeElement, s.trigger);
});
