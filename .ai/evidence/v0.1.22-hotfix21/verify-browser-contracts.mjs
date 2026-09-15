import assert from 'node:assert/strict';

const eventTarget = { addEventListener() {} };
globalThis.window = { ...eventTarget, localStorage: null };
globalThis.document = { ...eventTarget, querySelector() { return null; } };
globalThis.requestAnimationFrame = (callback) => { callback(); return 1; };
globalThis.cancelAnimationFrame = () => {};

const memoryStorage = () => {
    const values = new Map();
    return { getItem: (key) => values.get(key) ?? null, setItem: (key, value) => values.set(key, value) };
};
const storage = memoryStorage();
window.localStorage = storage;

const payment = await import('../../../resources/js/pos-payment-calculator.js');
const result = (received) => payment.summarizePaymentParts('25.00', [{ methodId: '1', isCash: true, tendered: received }]);
assert.deepEqual(result('20'), { valid: false, remaining: 500n, change: 0n });
assert.deepEqual(result('25'), { valid: true, remaining: 0n, change: 0n });
assert.deepEqual(result('1000'), { valid: true, remaining: 0n, change: 97500n });
assert.deepEqual(payment.summarizePaymentParts('25.00', [
    { methodId: '2', isCash: false, amount: '10.00' },
    { methodId: '1', isCash: true, tendered: '20.00' },
]), { valid: true, remaining: 0n, change: 500n });
assert.equal(payment.summarizePaymentParts('25.00', [
    { methodId: '2', isCash: false, amount: '10.00' },
    { methodId: '2', isCash: false, amount: '15.00' },
]).valid, false);
for (const bad of ['0', '-1', 'bad', '1.001']) {
    assert.equal(payment.summarizePaymentParts('25.00', [{ methodId: '2', isCash: false, amount: bad }]).valid, false);
}

const sidebar = await import('../../../resources/js/sidebar-navigation-state.js');
const makePage = ({ scrollTop, activeGroup, activeVisible = true, open = [] }) => {
    const details = ['group:top', 'group:lower', 'item:setup', 'subcategory:finance'].map((key) => ({
        dataset: { navigationState: key, navigationGroup: key.split(':')[1] },
        open: open.includes(key),
        contains: () => key === `group:${activeGroup}` || (activeGroup === 'lower' && ['item:setup', 'subcategory:finance'].includes(key)),
    }));
    let scrolled = false;
    const active = {
        closest: () => details.find((item) => item.dataset.navigationState === `group:${activeGroup}`),
        getBoundingClientRect: () => activeVisible ? ({ top: 200, bottom: 240 }) : ({ top: 900, bottom: 940 }),
        scrollIntoView: (options) => { assert.deepEqual(options, { block: 'nearest', inline: 'nearest' }); scrolled = true; },
    };
    const navigation = {
        scrollTop, scrollHeight: 1400, clientHeight: 600, dataset: {},
        querySelector: (selector) => selector.includes('aria-current') ? active : null,
        querySelectorAll: (selector) => selector.includes('details') ? (selector.includes('[open]') ? details.filter((item) => item.open) : details) : [],
        getBoundingClientRect: () => ({ top: 100, bottom: 700 }),
    };
    return { root: { querySelector: () => navigation }, navigation, details, wasScrolled: () => scrolled };
};

const first = makePage({ scrollTop: 520, activeGroup: 'lower', open: ['group:lower', 'item:setup', 'subcategory:finance'] });
sidebar.captureSidebarState(first.root, storage);
const second = makePage({ scrollTop: 0, activeGroup: 'lower', open: [] });
sidebar.restoreSidebarState(second.root, storage);
assert.equal(second.navigation.scrollTop, 520);
assert.equal(second.wasScrolled(), false);
assert.deepEqual(second.details.filter((item) => item.open).map((item) => item.dataset.navigationState), ['group:lower', 'item:setup', 'subcategory:finance']);
second.navigation.scrollTop = 610;
second.details.find((item) => item.dataset.navigationState === 'subcategory:finance').open = false;
sidebar.captureSidebarState(second.root, storage);
const third = makePage({ scrollTop: 0, activeGroup: 'lower', open: [] });
sidebar.restoreSidebarState(third.root, storage);
assert.equal(third.navigation.scrollTop, 610);
assert.equal(third.details.find((item) => item.dataset.navigationState === 'subcategory:finance').open, true, 'active ancestor remains expanded');

console.log('BROWSER_PAYMENT_CONTRACT=PASS');
console.log('SIDEBAR_MULTI_PAGE_RESTORATION=PASS');
