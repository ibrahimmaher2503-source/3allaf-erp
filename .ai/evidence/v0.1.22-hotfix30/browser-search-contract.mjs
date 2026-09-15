import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';

const [compiledAsset, renderedHtml, supplierId = '7'] = process.argv.slice(2);
assert.ok(compiledAsset && renderedHtml, 'Expected compiled asset and rendered HTML paths.');
const html = await readFile(renderedHtml, 'utf8');
for (const marker of ['data-product-line-editor', 'data-product-lookup']) {
    assert.match(html, new RegExp(marker));
}
assert.match(html, /data-order-lines-scroll|data-invoice-lines-scroll/);

class FakeEvent {
    constructor(type, options = {}) {
        Object.assign(this, { type, bubbles: Boolean(options.bubbles), detail: options.detail, key: options.key, defaultPrevented: false, propagationStopped: false, target: null });
    }
    preventDefault() { this.defaultPrevented = true; }
    stopPropagation() { this.propagationStopped = true; }
}
class FakeCustomEvent extends FakeEvent {}
class Classes {
    constructor(value = '') { this.names = new Set(String(value).split(/\s+/).filter(Boolean)); }
    add(...names) { names.forEach((name) => this.names.add(name)); }
    remove(...names) { names.forEach((name) => this.names.delete(name)); }
    contains(name) { return this.names.has(name); }
}
class FakeElement {
    constructor(tag = 'div', attributes = {}) {
        this.tagName = tag.toUpperCase();
        this.attributes = new Map();
        this.dataset = {};
        this.classList = new Classes(attributes.class);
        this.style = {};
        this.children = [];
        this.parentNode = null;
        this.listeners = new Map();
        this.value = attributes.value ?? '';
        this.isConnected = true;
        this._innerHTML = '';
        Object.entries(attributes).forEach(([name, value]) => this.setAttribute(name, value));
    }
    addEventListener(type, callback) { this.listeners.set(type, [...(this.listeners.get(type) ?? []), callback]); }
    removeEventListener(type, callback) { this.listeners.set(type, (this.listeners.get(type) ?? []).filter((item) => item !== callback)); }
    dispatchEvent(event) {
        if (!event.target) event.target = this;
        for (const callback of this.listeners.get(event.type) ?? []) callback.call(this, event);
        if (event.bubbles && !event.propagationStopped) this.parentNode?.dispatchEvent(event);
        return !event.defaultPrevented;
    }
    appendChild(child) {
        child.parentNode?.removeChild?.(child);
        child.parentNode = this;
        child.isConnected = true;
        this.children.push(child);
        return child;
    }
    removeChild(child) { this.children = this.children.filter((item) => item !== child); child.parentNode = null; return child; }
    remove() { this.parentNode?.removeChild(this); this.isConnected = false; }
    cloneNode() {
        const clone = new FakeElement(this.tagName.toLowerCase(), { class: [...this.classList.names].join(' ') });
        for (const [name, value] of this.attributes) clone.setAttribute(name, value);
        return clone;
    }
    setAttribute(name, value) {
        const text = String(value);
        this.attributes.set(name, text);
        if (name === 'class') this.classList = new Classes(text);
        if (name === 'id') this.id = text;
        if (name === 'role') this.role = text;
        if (name === 'dir') this.dir = text;
        if (name === 'value') this.value = text;
        if (name.startsWith('data-')) this.dataset[name.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase())] = text;
    }
    getAttribute(name) { return this.attributes.get(name) ?? null; }
    removeAttribute(name) {
        this.attributes.delete(name);
        if (name.startsWith('data-')) delete this.dataset[name.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase())];
    }
    matches(selector) {
        if (selector === '[data-product-lookup]') return 'productLookup' in this.dataset;
        if (selector === '[data-product-line-editor]') return 'productLineEditor' in this.dataset;
        if (selector === '[data-line-quantity]') return 'lineQuantity' in this.dataset;
        if (selector === '[data-line-price], [data-line-step], [data-line-final]') return 'linePrice' in this.dataset || 'lineStep' in this.dataset || 'lineFinal' in this.dataset;
        if (selector.includes('[data-product-search]')) return 'productSearch' in this.dataset;
        if (selector === 'textarea, button') return ['TEXTAREA', 'BUTTON'].includes(this.tagName);
        if (selector === 'input, select') return ['INPUT', 'SELECT'].includes(this.tagName);
        return false;
    }
    closest(selector) {
        let node = this;
        while (node) {
            if (selector === '[data-product-lookup]' && 'productLookup' in node.dataset) return node;
            if (selector === '[data-product-line]' && 'productLine' in node.dataset) return node;
            if (selector === '[data-product-line-editor]' && 'productLineEditor' in node.dataset) return node;
            if (selector === 'button' && node.tagName === 'BUTTON') return node;
            node = node.parentNode;
        }
        return null;
    }
    contains(candidate) {
        for (let node = candidate; node; node = node.parentNode) if (node === this) return true;
        return false;
    }
    focus() { document.activeElement = this; this.focused = true; }
    click() { this.dispatchEvent(new FakeEvent('click', { bubbles: true })); }
    checkValidity() { return true; }
    getBoundingClientRect() { return { left: 120, top: 180, right: 600, bottom: 220, width: 480, height: 40 }; }
    getClientRects() { return this.isConnected ? [this.getBoundingClientRect()] : []; }
    scrollIntoView() { this.scrolledIntoView = true; }
    querySelector(selector) {
        if (selector.startsWith('#')) return this.children.find((child) => child.id === selector.slice(1)) ?? null;
        return this._queries?.get(selector) ?? null;
    }
    querySelectorAll(selector) {
        if (selector === '[data-result-index]' || selector === '[role="option"]') return this.children.filter((child) => 'resultIndex' in child.dataset);
        return this._queryAll?.get(selector) ?? [];
    }
    set innerHTML(value) {
        this._innerHTML = String(value);
        this.children = [];
        for (const match of this._innerHTML.matchAll(/<button\b[^>]*id="([^"]+)"[^>]*data-result-index="(\d+)"[^>]*>/g)) {
            this.appendChild(new FakeElement('button', { id: match[1], role: 'option', 'data-result-index': match[2] }));
        }
    }
    get innerHTML() { return this._innerHTML; }
}

const decode = (value) => String(value ?? '').replaceAll('&quot;', '"').replaceAll('&#039;', "'").replaceAll('&amp;', '&').replaceAll('&lt;', '<').replaceAll('&gt;', '>');
const openingTag = html.match(/<div\b(?=[^>]*data-product-lookup)[^>]*>/s)?.[0];
assert.ok(openingTag, 'Rendered create page omitted the lookup root.');
const attributeValue = (name, fallback = '') => decode(openingTag.match(new RegExp(name + '="([^"]*)"'))?.[1] ?? fallback);

const makeRow = () => {
    const editor = new FakeElement('form', { 'data-product-line-editor': '', 'data-autofocus': 'true' });
    const line = new FakeElement('div', { 'data-product-line': '' });
    const root = new FakeElement('div', {
        'data-product-lookup': '', 'data-search-url': attributeValue('data-search-url', '/transaction-products/search'),
        'data-value-field': 'id', 'data-context': 'purchasing', 'data-supplier-id': supplierId,
        'data-supplier-only': attributeValue('data-supplier-only', '1'),
        'data-message-empty': attributeValue('data-message-empty', 'No matching products.'),
        'data-message-loading': attributeValue('data-message-loading', 'Searching products…'),
        'data-message-error': attributeValue('data-message-error', 'Product search could not be completed. Try again.'),
        'data-message-supplier-required': 'Supplier required', 'data-message-supplier-empty': 'Supplier empty',
        'data-label-unit': 'Unit', 'data-label-supplier-code': 'Supplier code',
        'data-label-last-price': 'Last supplier price', 'data-label-fallback-price': 'Fallback product cost',
        'data-label-no-price': 'No price', 'data-camera-unsupported': 'unsupported', 'data-camera-error': 'camera error',
    });
    const input = new FakeElement('input', { dir: attributeValue('dir', 'rtl'), 'data-product-search': '' });
    const hidden = new FakeElement('input', { 'data-product-id': '' });
    const anchor = new FakeElement('div', { class: 'absolute hidden', role: 'listbox', 'data-product-results': '' });
    const error = new FakeElement('p', { class: 'hidden', 'data-product-error': '' });
    const camera = new FakeElement('button', { 'data-camera-trigger': '' });
    const cameraPanel = new FakeElement('div', { class: 'hidden', 'data-camera-panel': '' });
    const cameraVideo = new FakeElement('video', { 'data-camera-video': '' });
    cameraVideo.play = async () => {};
    const cameraMessage = new FakeElement('p', { class: 'hidden', 'data-camera-message': '' });
    const cameraClose = new FakeElement('button', { 'data-camera-close': '' });
    const quantity = new FakeElement('input', { value: '2', 'data-line-quantity': '' });
    const price = new FakeElement('input', { value: '', 'data-line-price': '', 'data-price-kind': 'cost' });
    const basePrice = new FakeElement('input', { value: '25', 'data-line-step': '' });
    const discountType = new FakeElement('select', { value: '', 'data-line-step': '', 'data-line-discount-type': '' });
    const discount = new FakeElement('input', { value: '0', 'data-line-step': '', 'data-line-discount': '' });
    const tax = new FakeElement('input', { value: '0', 'data-line-final': '', 'data-line-tax': '' });
    const priceSource = new FakeElement('p', { 'data-price-source': '' });
    const total = new FakeElement('output', { 'data-line-total': '' });
    const editorTotal = new FakeElement('output', { 'data-editor-total': '' });
    const add = new FakeElement('button', { 'data-add-line': '' });
    root._queries = new Map([
        ['[data-product-search]', input], ['[data-product-id]', hidden], ['[data-product-results]', anchor],
        ['[data-product-error]', error], ['[data-camera-trigger]', camera], ['[data-camera-panel]', cameraPanel],
        ['[data-camera-video]', cameraVideo], ['[data-camera-message]', cameraMessage], ['[data-camera-close]', cameraClose],
    ]);
    root._queryAll = new Map();
    line._queries = new Map([
        ['[data-product-id]', hidden], ['[data-product-search]', input], ['[data-line-quantity]', quantity],
        ['[data-line-price]', price], ['[data-line-price]:not(:disabled)', price], ['[data-price-source]', priceSource], ['[data-line-total]', total],
        ['[data-line-price]:not(:disabled):not([readonly]), [data-line-step]:not(:disabled):not([readonly]), [data-line-final]:not(:disabled):not([readonly])', price],
    ]);
    line._queryAll = new Map([
        ['[name$="[description_ar]"]', []], ['[name$="[description_en]"], [name$="[description]"]', []],
        ['[data-line-price]:not(:disabled):not([readonly]), [data-line-step]:not(:disabled):not([readonly]), [data-line-final]:not(:disabled):not([readonly])', [price, basePrice, discountType, discount, tax]],
        ['[data-product-lookup]', [root]],
    ]);
    editor._queries = new Map([['[data-add-line]', add], ['[data-product-search]', input], ['[data-editor-total]', editorTotal]]);
    editor._queryAll = new Map([['[data-product-line]', [line]], ['[data-product-lookup]', [root]], ['[data-line-total]', [total]], ['[data-editor-total]', [editorTotal]]]);
    root.appendChild(input); root.appendChild(hidden); root.appendChild(anchor); root.appendChild(error);
    line.appendChild(root); line.appendChild(quantity); line.appendChild(price); line.appendChild(basePrice); line.appendChild(discountType); line.appendChild(discount); line.appendChild(tax); line.appendChild(priceSource); line.appendChild(total);
    editor.appendChild(line); editor.appendChild(editorTotal); editor.appendChild(add);
    return { editor, line, root, input, hidden, anchor, quantity, price, basePrice, discountType, discount, tax, priceSource, total, editorTotal, add };
};

class FakeDocument extends FakeElement {
    constructor() {
        super('document');
        this.documentElement = new FakeElement('html', { lang: 'ar', dir: 'rtl' });
        Object.assign(this.documentElement, { lang: 'ar', dir: 'rtl', clientHeight: 800 });
        this.body = new FakeElement('body');
        this.appendChild(this.body);
        this.activeElement = null;
        this.rows = [];
    }
    querySelectorAll(selector) {
        if (selector === '[data-product-lookup]') return this.rows.map((row) => row.root);
        if (selector === '[data-product-line-editor]') return this.rows.map((row) => row.editor);
        return [];
    }
    querySelector(selector) {
        if (selector.includes('[data-product-lookup]')) return this.rows[0]?.input ?? null;
        return null;
    }
    getElementById(id) { return this.body.children.find((child) => child.id === id) ?? null; }
}

const document = new FakeDocument();
const windowTarget = new FakeElement('window');
const hooks = new Map();
Object.assign(windowTarget, {
    document, innerHeight: 800, location: { protocol: 'https:' },
    localStorage: { getItem: () => null, setItem: () => {} },
    Livewire: { hook(name, callback) { hooks.set(name, callback); } },
});
globalThis.Event = FakeEvent;
globalThis.CustomEvent = FakeCustomEvent;
globalThis.Element = FakeElement;
globalThis.HTMLFormElement = class extends FakeElement {};
globalThis.document = document;
globalThis.window = windowTarget;
Object.defineProperty(globalThis, 'navigator', { configurable: true, value: {} });
globalThis.indexedDB = {
    open() {
        const request = {};
        const database = {
            transaction() {
                return { objectStore: () => ({
                    clear: () => { const operation = {}; queueMicrotask(() => operation.onsuccess?.()); return operation; },
                    getAll: () => { const operation = { result: [] }; queueMicrotask(() => operation.onsuccess?.()); return operation; },
                }) };
            },
            close() {},
        };
        queueMicrotask(() => { request.result = database; request.onsuccess?.(); });
        return request;
    },
};
globalThis.requestAnimationFrame = (callback) => { callback(); return 1; };
globalThis.cancelAnimationFrame = () => {};
class FakeMutationObserver {
    constructor(callback) { FakeMutationObserver.callbacks.push(callback); }
    observe() {}
}
FakeMutationObserver.callbacks = [];
globalThis.MutationObserver = FakeMutationObserver;

const products = [
    { id: 41, item_code: 'SKU-001', model_number: 'MOD-001', name_ar: 'منتج أول', name_en: 'First product', barcodes: ['10001'], unit_of_measure: 'piece', supplier_item_code: 'SUP-001', unit_cost: '12.5000', price_source: 'last_supplier_price', price_currency: 'SAR', exact_unique: false },
    { id: 42, item_code: 'SKU-0012', model_number: 'MOD-101', name_ar: 'منتج ثان', name_en: 'Second product', barcodes: ['20001'], unit_of_measure: 'piece', supplier_item_code: 'SUP-012', unit_cost: '15.0000', price_source: 'fallback_cost', price_currency: 'SAR', exact_unique: false },
];
const requests = [];
globalThis.fetch = async (url, options) => {
    const requestUrl = String(url);
    requests.push({ url: requestUrl, options });
    if (requestUrl.includes('request-error')) return { ok: false, json: async () => ({}) };
    const query = new URL(requestUrl, 'https://example.test').searchParams.get('q');
    const data = query === 'none' ? [] : (query === '0012' ? [products[1]] : (query === '10001' ? [{ ...products[0], exact_unique: true }] : products));
    return { ok: true, json: async () => ({ data, meta: {} }) };
};

const first = makeRow();
document.rows.push(first);
document.appendChild(first.editor);
await import(pathToFileURL(compiledAsset).href + '?contract=' + Date.now());
document.dispatchEvent(new FakeEvent('livewire:init'));
document.dispatchEvent(new FakeEvent('livewire:initialized'));
const wait = () => new Promise((resolve) => setTimeout(resolve, 150));
const overlayFor = (row) => document.body.children.find((child) => child.id === row.input.getAttribute('aria-controls'));
assert.ok(overlayFor(first), 'Initial load did not initialize the rendered lookup.');

first.input.value = 'منتج';
first.input.dispatchEvent(new FakeEvent('input', { bubbles: true }));
assert.equal(overlayFor(first).dataset.state, 'loading');
assert.equal(overlayFor(first).classList.contains('hidden'), false);
await wait();
assert.equal(requests.length, 1);
assert.match(requests[0].url, /q=%D9%85%D9%86%D8%AA%D8%AC/);
assert.match(requests[0].url, /supplier_only=1/);
assert.equal(requests[0].options.credentials, 'same-origin');
assert.equal(overlayFor(first).dataset.state, 'results');
assert.equal(overlayFor(first).querySelectorAll('[role="option"]').length, 2);
assert.equal(overlayFor(first).parentNode, document.body);
assert.equal(overlayFor(first).style.position, 'fixed');
assert.ok(Number(overlayFor(first).style.zIndex) > 20);

first.input.dispatchEvent(new FakeEvent('keydown', { key: 'Enter', bubbles: true }));
assert.equal(first.hidden.value, '');
assert.equal(overlayFor(first).classList.contains('hidden'), false);
first.input.dispatchEvent(new FakeEvent('keydown', { key: 'ArrowDown', bubbles: true }));
first.input.dispatchEvent(new FakeEvent('keydown', { key: 'Enter', bubbles: true }));
assert.equal(first.hidden.value, '41');
assert.equal(first.price.value, '12.5000');
assert.equal(first.total.textContent, '25.00');
assert.match(first.priceSource.textContent, /Last supplier price/);
assert.equal(document.activeElement, first.quantity);
assert.equal(first.editorTotal.textContent, '25.00');

let committedLines = 0;
first.add.addEventListener('click', () => queueMicrotask(() => {
    committedLines++;
    const nextRow = makeRow();
    nextRow.editor = first.editor;
    first.editor.appendChild(nextRow.line);
    first.editor._queryAll.get('[data-product-line]').push(nextRow.line);
    first.editor._queryAll.get('[data-product-lookup]').push(nextRow.root);
    first.editor._queryAll.get('[data-line-total]').push(nextRow.total);
    document.rows.push(nextRow);
    hooks.get('morph.added')?.({ el: first.editor });
}));
first.quantity.dispatchEvent(new FakeEvent('keydown', { key: 'Enter', bubbles: true }));
assert.equal(document.activeElement, first.price);
for (const [field, next] of [[first.price, first.basePrice], [first.basePrice, first.discountType], [first.discountType, first.discount], [first.discount, first.tax]]) {
    field.dispatchEvent(new FakeEvent('keydown', { key: 'Enter', bubbles: true }));
    assert.equal(document.activeElement, next);
}
const finalEnter = new FakeEvent('keydown', { key: 'Enter', bubbles: true });
first.tax.dispatchEvent(finalEnter);
first.tax.dispatchEvent(new FakeEvent('keydown', { key: 'Enter', bubbles: true }));
await Promise.resolve();
assert.equal(finalEnter.defaultPrevented, true);
assert.equal(finalEnter.propagationStopped, true);
assert.equal(committedLines, 1, 'Repeated Enter created duplicate rows.');
assert.equal(document.activeElement, document.rows.at(-1).input, 'Next-row product search did not receive focus.');
const metadata = new FakeElement('input');
first.editor.appendChild(metadata);
const metadataEnter = new FakeEvent('keydown', { key: 'Enter', bubbles: true });
metadata.dispatchEvent(metadataEnter);
assert.equal(metadataEnter.defaultPrevented, true, 'Metadata Enter could submit the parent invoice.');

first.input.value = '001';
first.input.dispatchEvent(new FakeEvent('input', { bubbles: true }));
assert.equal(overlayFor(first).dataset.state, 'loading');
await wait();
assert.equal(overlayFor(first).querySelectorAll('[role="option"]').length, 2);
first.input.value = '0012';
first.input.dispatchEvent(new FakeEvent('input', { bubbles: true }));
await wait();
assert.equal(overlayFor(first).querySelectorAll('[role="option"]').length, 1);
first.input.dispatchEvent(new FakeEvent('keydown', { key: 'Escape', bubbles: true }));
assert.equal(overlayFor(first).classList.contains('hidden'), true);

first.input.value = '10001';
first.input.dispatchEvent(new FakeEvent('input', { bubbles: true }));
await wait();
assert.equal(first.hidden.value, '41', 'Exact unique scanner identifier was not selected.');

first.root.dataset.supplierOnly = '0';
first.input.value = 'none';
first.input.dispatchEvent(new FakeEvent('input', { bubbles: true }));
await wait();
assert.match(requests.at(-1).url, /supplier_only=0/);
assert.equal(overlayFor(first).dataset.state, 'empty');

first.input.value = 'request-error';
first.input.dispatchEvent(new FakeEvent('input', { bubbles: true }));
await wait();
assert.equal(overlayFor(first).dataset.state, 'error');
document.body.dispatchEvent(new FakeEvent('pointerdown', { bubbles: true }));
assert.equal(overlayFor(first).classList.contains('hidden'), true);

const morphedInput = new FakeElement('input', { dir: 'rtl', 'data-product-search': '' });
first.root._queries.set('[data-product-search]', morphedInput);
first.root.children[0] = morphedInput;
morphedInput.parentNode = first.root;
hooks.get('morph.updated')?.({ el: first.root });
const beforeMorph = requests.length;
morphedInput.value = '001';
morphedInput.dispatchEvent(new FakeEvent('input', { bubbles: true }));
await wait();
assert.equal(requests.length, beforeMorph + 1);

const added = makeRow();
document.rows.push(added);
document.appendChild(added.editor);
hooks.get('morph.added')?.({ el: added.editor });
const beforeAdded = requests.length;
added.input.value = 'منتج';
added.input.dispatchEvent(new FakeEvent('input', { bubbles: true }));
await wait();
assert.equal(requests.length, beforeAdded + 1);
assert.equal(overlayFor(added).dataset.state, 'results');

document.dispatchEvent(new FakeEvent('livewire:navigated'));
const beforeNavigation = requests.length;
added.input.value = '001';
added.input.dispatchEvent(new FakeEvent('input', { bubbles: true }));
await wait();
assert.equal(requests.length, beforeNavigation + 1);

console.log('HOTFIX30_COMPILED_BROWSER_INITIAL_LOAD=PASS');
console.log('HOTFIX30_COMPILED_BROWSER_ARABIC_AND_IDENTIFIER_INPUT=PASS');
console.log('HOTFIX30_LOADING_RESULTS_EMPTY_ERROR_STATES=PASS');
console.log('HOTFIX30_MULTI_RESULT_KEYBOARD_ROW_STATE=PASS');
console.log('HOTFIX30_LIVEWIRE_NAVIGATION_MORPH_ADDED_ROW=PASS');
console.log('HOTFIX30_DROPDOWN_VIEWPORT_OVERLAY_NOT_CLIPPED=PASS');
console.log('HOTFIX31_INVOICE_LINE_COMMIT_NEXT_FOCUS=PASS');
console.log('HOTFIX31_REPEATED_ENTER_PARENT_SUBMIT_PREVENTION=PASS');
