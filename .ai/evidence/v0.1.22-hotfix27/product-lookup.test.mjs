import assert from 'node:assert/strict';
import test from 'node:test';

const { ProductLookup, initializeEditor } = await import('../../../resources/js/keyboard-product-lines.js');

class Classes {
    constructor(...names) { this.names = new Set(names); }
    add(name) { this.names.add(name); }
    remove(name) { this.names.delete(name); }
    contains(name) { return this.names.has(name); }
}

const element = (initial = {}) => Object.assign(new EventTarget(), {
    dataset: {},
    classList: new Classes(),
    value: '',
    disabled: false,
    innerHTML: '',
    setAttribute(name, value) { this[name] = String(value); },
    removeAttribute(name) { delete this[name]; },
    querySelectorAll() { return []; },
    querySelector() { return null; },
    focus() { this.focused = true; },
    ...initial,
});

const lookupFixture = () => {
    const input = element({ value: '' });
    const hidden = element();
    const results = element({ classList: new Classes('hidden') });
    const error = element({ classList: new Classes('hidden') });
    const cameraTrigger = element();
    const cameraPanel = element({ classList: new Classes('hidden') });
    const cameraVideo = element({ async play() {}, srcObject: null });
    const cameraMessage = element({ classList: new Classes('hidden') });
    const cameraClose = element();
    const map = new Map([
        ['[data-product-search]', input], ['[data-product-id]', hidden], ['[data-product-results]', results],
        ['[data-product-error]', error], ['[data-camera-trigger]', cameraTrigger], ['[data-camera-panel]', cameraPanel],
        ['[data-camera-video]', cameraVideo], ['[data-camera-message]', cameraMessage], ['[data-camera-close]', cameraClose],
    ]);
    const root = element({
        dataset: {
            searchUrl: '/transaction-products/search', valueField: 'id', context: 'purchasing', supplierId: '7', supplierOnly: '1',
            messageEmpty: 'empty', messageLoading: 'loading', messageSupplierRequired: 'supplier required', messageSupplierEmpty: 'supplier empty',
            labelUnit: 'Unit', labelSupplierCode: 'Supplier code', labelLastPrice: 'Last price', labelFallbackPrice: 'Fallback', labelNoPrice: 'No price',
            cameraUnsupported: 'unsupported', cameraError: 'camera error',
        },
        isConnected: true,
        querySelector(selector) { return map.get(selector) ?? null; },
        closest() { return null; },
    });
    const lookup = new ProductLookup(root);

    return { lookup, root, input, hidden, results, cameraPanel, cameraVideo, cameraMessage };
};

globalThis.document = { documentElement: { lang: 'en' } };
globalThis.requestAnimationFrame = (callback) => { callback(); return 1; };
globalThis.cancelAnimationFrame = () => {};
globalThis.window = {};

test('incremental requests are deduplicated and stale responses cannot replace narrowed results', async () => {
    const { lookup, input } = lookupFixture();
    const resolvers = [];
    let fetches = 0;
    globalThis.fetch = (url) => {
        fetches++;
        return new Promise((resolve) => resolvers.push({ url: String(url), resolve }));
    };
    lookup.render = () => {};
    input.value = '001';
    const broadA = lookup.search('001');
    const broadB = lookup.search('001');
    assert.equal(fetches, 1);
    input.value = '0012';
    const narrow = lookup.search('0012');
    assert.equal(fetches, 2);
    resolvers[1].resolve({ ok: true, json: async () => ({ data: [{ id: 2, exact: false }] }) });
    await narrow;
    resolvers[0].resolve({ ok: true, json: async () => ({ data: [{ id: 1, exact: false }] }) });
    await Promise.all([broadA, broadB]);
    assert.deepEqual(lookup.items.map((item) => item.id), [2]);
    assert.match(resolvers[1].url, /supplier_id=7/);
    assert.match(resolvers[1].url, /supplier_only=1/);
});

test('only a unique exact identifier auto-selects and multiple matches require explicit highlighting', async () => {
    const { lookup, input, results } = lookupFixture();
    const selected = [];
    lookup.select = (item) => selected.push(item.id);
    globalThis.fetch = async () => ({ ok: true, json: async () => ({ data: [{ id: 9, exact: true, exact_unique: true, item_code: 'SKU-9', barcodes: ['6220000000099'] }] }) });
    input.value = '6220000000099';
    await lookup.search(input.value);
    assert.deepEqual(selected, [9]);

    lookup.items = [{ id: 10 }, { id: 11 }];
    lookup.active = -1;
    lookup.explicitHighlight = false;
    lookup.lastResolvedTerm = '001';
    input.value = '001';
    results.classList.remove('hidden');
    lookup.syncActive = () => {};
    const unhighlightedEnter = { key: 'Enter', prevented: false, stopped: false, preventDefault() { this.prevented = true; }, stopPropagation() { this.stopped = true; } };
    lookup.keydown(unhighlightedEnter);
    assert.equal(unhighlightedEnter.prevented, true);
    assert.equal(unhighlightedEnter.stopped, true);
    assert.deepEqual(selected, [9]);
    assert.equal(results.classList.contains('hidden'), false);

    const down = { key: 'ArrowDown', prevented: false, preventDefault() { this.prevented = true; } };
    lookup.keydown(down);
    assert.equal(down.prevented, true);
    assert.equal(lookup.active, 0);
    lookup.active = 1;
    const up = { key: 'ArrowUp', prevented: false, preventDefault() { this.prevented = true; } };
    lookup.keydown(up);
    assert.equal(up.prevented, true);
    assert.equal(lookup.active, 0);
    lookup.active = 1;
    const enter = { key: 'Enter', prevented: false, stopped: false, preventDefault() { this.prevented = true; }, stopPropagation() { this.stopped = true; } };
    lookup.keydown(enter);
    assert.equal(enter.prevented, true);
    assert.equal(enter.stopped, true);
    assert.equal(selected.at(-1), 11);

    results.classList.remove('hidden');
    const escape = { key: 'Escape', prevented: false, preventDefault() { this.prevented = true; } };
    lookup.keydown(escape);
    assert.equal(escape.prevented, true);
    assert.equal(results.classList.contains('hidden'), true);

    const listeners = {};
    const next = element();
    const line = { querySelector: () => next };
    const quantity = { matches: (selector) => selector === '[data-line-quantity]', closest: () => line };
    const editor = { dataset: {}, addEventListener: (name, callback) => { listeners[name] = callback; }, querySelectorAll: () => [], querySelector: () => null };
    initializeEditor(editor);
    const lineEnter = { key: 'Enter', target: quantity, prevented: false, stopped: false, preventDefault() { this.prevented = true; }, stopPropagation() { this.stopped = true; } };
    listeners.keydown(lineEnter);
    assert.equal(lineEnter.prevented, true);
    assert.equal(lineEnter.stopped, true);
    assert.equal(next.focused, true);
});

test('camera fallback is local and every active track stops on close', async () => {
    const { lookup, cameraMessage, cameraPanel } = lookupFixture();
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { mediaDevices: { getUserMedia: async () => { throw new Error('must not open'); } } } });
    delete globalThis.window.BarcodeDetector;
    await lookup.openCamera();
    assert.equal(cameraMessage.textContent, 'unsupported');
    assert.equal(cameraMessage.classList.contains('hidden'), false);

    let stopped = 0;
    lookup.stream = { getTracks: () => [{ stop: () => stopped++ }, { stop: () => stopped++ }] };
    lookup.closeCamera();
    assert.equal(stopped, 2);
    assert.equal(cameraPanel.classList.contains('hidden'), true);
});
