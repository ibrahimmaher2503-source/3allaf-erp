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
    dataset: {}, classList: new Classes(), value: '', disabled: false, innerHTML: '', isConnected: true,
    setAttribute(name, value) { this[name] = String(value); },
    removeAttribute(name) { delete this[name]; },
    querySelectorAll() { return []; },
    querySelector() { return null; },
    getClientRects() { return [{}]; },
    focus() { this.focused = true; },
    ...initial,
});

const lookupFixture = () => {
    const map = new Map();
    const installControls = () => {
        const controls = {
            input: element(), hidden: element(),
            results: element({ classList: new Classes('hidden') }),
            error: element({ classList: new Classes('hidden') }), cameraTrigger: element(),
            cameraPanel: element({ classList: new Classes('hidden') }),
            cameraVideo: element({ async play() {}, srcObject: null }),
            cameraMessage: element({ classList: new Classes('hidden') }), cameraClose: element(),
        };
        map.clear();
        map.set('[data-product-search]', controls.input);
        map.set('[data-product-id]', controls.hidden);
        map.set('[data-product-results]', controls.results);
        map.set('[data-product-error]', controls.error);
        map.set('[data-camera-trigger]', controls.cameraTrigger);
        map.set('[data-camera-panel]', controls.cameraPanel);
        map.set('[data-camera-video]', controls.cameraVideo);
        map.set('[data-camera-message]', controls.cameraMessage);
        map.set('[data-camera-close]', controls.cameraClose);
        return controls;
    };
    const root = element({
        dataset: {
            searchUrl: '/transaction-products/search', valueField: 'id', context: 'purchasing', supplierId: '7', supplierOnly: '1',
            messageEmpty: 'empty', messageLoading: 'loading', messageSupplierRequired: 'supplier required', messageSupplierEmpty: 'supplier empty',
            labelUnit: 'Unit', labelSupplierCode: 'Supplier code', labelLastPrice: 'Last price', labelFallbackPrice: 'Fallback', labelNoPrice: 'No price',
            cameraUnsupported: 'unsupported', cameraError: 'camera error',
        },
        querySelector(selector) { return map.get(selector) ?? null; },
        closest() { return null; },
    });
    const controls = installControls();
    return { root, map, installControls, controls, lookup: new ProductLookup(root) };
};

globalThis.document = { documentElement: { lang: 'en' } };
globalThis.requestAnimationFrame = (callback) => { callback(); return 1; };
globalThis.cancelAnimationFrame = () => {};
globalThis.window = {};

test('full-page lookup survives a Livewire child morph and renders incremental results without a hidden-model request', async () => {
    const fixture = lookupFixture();
    let hiddenInputs = 0;
    fixture.controls.hidden.addEventListener('input', () => hiddenInputs++);
    fixture.controls.input.value = '001';
    globalThis.fetch = async () => ({ ok: true, json: async () => ({ data: [
        { id: 1, item_code: 'A001', model_number: 'M001', name_ar: 'منتج 001', name_en: 'Product 001', barcodes: ['1001'], unit_of_measure: 'piece', exact_unique: false },
        { id: 2, item_code: 'B001', model_number: 'M101', name_ar: 'منتج آخر', name_en: 'Another product', barcodes: ['2001'], unit_of_measure: 'piece', exact_unique: false },
    ], meta: {} }) });
    fixture.lookup.queue();
    await new Promise((resolve) => setTimeout(resolve, 150));
    assert.equal(hiddenInputs, 0, 'typing with no selected product must not trigger a Livewire morph');
    assert.equal(fixture.controls.results.classList.contains('hidden'), false);
    assert.match(fixture.controls.results.innerHTML, /Product 001/);
    assert.match(fixture.controls.results.innerHTML, /Another product/);

    const replacement = fixture.installControls();
    const rebound = new ProductLookup(fixture.root);
    assert.notEqual(rebound, fixture.lookup);
    assert.equal(rebound.input, replacement.input);
    replacement.input.value = 'منتج';
    replacement.input.dispatchEvent(new Event('input'));
    await new Promise((resolve) => setTimeout(resolve, 150));
    assert.equal(replacement.results.classList.contains('hidden'), false);
    assert.match(replacement.results.innerHTML, /Product 001/);
});

test('stale and duplicate requests cannot replace narrowed results', async () => {
    const { lookup, controls } = lookupFixture();
    const resolvers = [];
    let fetches = 0;
    globalThis.fetch = (url) => {
        fetches++;
        return new Promise((resolve) => resolvers.push({ url: String(url), resolve }));
    };
    lookup.render = () => {};
    controls.input.value = '001';
    const broadA = lookup.search('001');
    const broadB = lookup.search('001');
    assert.equal(fetches, 1);
    controls.input.value = '0012';
    const narrow = lookup.search('0012');
    assert.equal(fetches, 2);
    resolvers[1].resolve({ ok: true, json: async () => ({ data: [{ id: 2, exact_unique: false }] }) });
    await narrow;
    resolvers[0].resolve({ ok: true, json: async () => ({ data: [{ id: 1, exact_unique: false }] }) });
    await Promise.all([broadA, broadB]);
    assert.deepEqual(lookup.items.map((item) => item.id), [2]);
    assert.match(resolvers[1].url, /supplier_only=1/);
});

test('multiple-match Enter never selects implicitly; arrows select explicitly and parent submit stays blocked', () => {
    const { lookup, controls } = lookupFixture();
    const selected = [];
    lookup.select = (item) => selected.push(item.id);
    lookup.items = [{ id: 10 }, { id: 11 }];
    lookup.lastResolvedTerm = '001';
    controls.input.value = '001';
    controls.results.classList.remove('hidden');
    lookup.syncActive = () => {};
    const enter = { key: 'Enter', preventDefault() { this.prevented = true; }, stopPropagation() { this.stopped = true; } };
    lookup.keydown(enter);
    assert.equal(enter.prevented, true);
    assert.equal(enter.stopped, true);
    assert.deepEqual(selected, []);
    lookup.keydown({ key: 'ArrowDown', preventDefault() {} });
    lookup.keydown({ key: 'Enter', preventDefault() {}, stopPropagation() {} });
    assert.deepEqual(selected, [10]);

    const listeners = {};
    const next = element();
    const line = { querySelector: () => next };
    const quantity = { matches: (selector) => selector === '[data-line-quantity]', closest: () => line };
    const editor = { dataset: {}, addEventListener: (name, callback) => { listeners[name] = callback; }, querySelectorAll: () => [], querySelector: () => null };
    initializeEditor(editor);
    const lineEnter = { key: 'Enter', target: quantity, preventDefault() { this.prevented = true; }, stopPropagation() { this.stopped = true; } };
    listeners.keydown(lineEnter);
    assert.equal(lineEnter.prevented, true);
    assert.equal(lineEnter.stopped, true);
    assert.equal(next.focused, true);
});

test('only an exact unique identifier auto-selects and camera cleanup stops every track', async () => {
    const { lookup, controls } = lookupFixture();
    const selected = [];
    lookup.select = (item) => selected.push(item.id);
    globalThis.fetch = async () => ({ ok: true, json: async () => ({ data: [{ id: 9, exact_unique: true, item_code: 'SKU-9', barcodes: ['6220000000099'] }] }) });
    controls.input.value = '6220000000099';
    await lookup.search(controls.input.value, true);
    assert.deepEqual(selected, [9]);
    let stopped = 0;
    lookup.stream = { getTracks: () => [{ stop: () => stopped++ }, { stop: () => stopped++ }] };
    lookup.closeCamera();
    assert.equal(stopped, 2);
});
