import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const sidebarSource = fs.readFileSync('resources/js/sidebar-navigation-state.js', 'utf8');
const cssSource = fs.readFileSync('resources/css/app.css', 'utf8');
const layoutSource = fs.readFileSync('resources/views/layouts/app/sidebar.blade.php', 'utf8');
const themeBootstrap = fs.readFileSync('resources/views/partials/theme-bootstrap.blade.php', 'utf8');
const assistantSource = fs.readFileSync('resources/js/dashboard-assistant.js', 'utf8');
const manifest = JSON.parse(fs.readFileSync('public/build/manifest.json', 'utf8'));

assert.equal(sidebarSource.includes('scrollIntoView'), false, 'active navigation may not use scrollIntoView');
for (const forbidden of [
    'document.documentElement', 'document.body', 'window.scrollTo', 'window.scrollBy',
    '.style.', '.classList.', "querySelector('html", 'querySelector("html',
    "querySelector('body", 'querySelector("body', '.app-layout', '.app-main', '.app-topbar',
]) {
    assert.equal(sidebarSource.includes(forbidden), false, `sidebar state source contains forbidden shell mutation: ${forbidden}`);
}
assert.equal(sidebarSource.includes('activeSection'), false, 'active section may not be persisted');
assert.match(sidebarSource, /SIDEBAR_SCROLL_SELECTOR\s*=\s*'\[data-sidebar-scroll\]'/);
assert.equal((sidebarSource.match(/\.scrollTop\s*=/g) || []).length, 2);
for (const line of sidebarSource.split('\n').filter((line) => line.includes('.scrollTop ='))) {
    assert.match(line, /^\s*navigation\.scrollTop\s*=/, 'scrollTop assignment escaped the internal navigation container');
}

const hotfix23Marker = "/* Hotfix23: retain Hotfix20's desktop grid and scroll only the explicit menu list. */";
const hotfix23Block = cssSource.slice(cssSource.indexOf(hotfix23Marker));
assert.ok(hotfix23Block.startsWith(hotfix23Marker));
assert.equal(hotfix23Block.includes('.app-layout__content'), false);
assert.equal(hotfix23Block.includes('margin'), false);
assert.equal(hotfix23Block.includes('padding'), false);
assert.equal(hotfix23Block.includes('position'), false);
assert.equal(hotfix23Block.includes('transform'), false);
assert.equal(hotfix23Block.includes('body.app-layout {'), false);
assert.match(hotfix23Block, /body\.app-layout \.app-sidebar \[data-sidebar-scroll\][^{]*\{[^}]*overflow-y:auto!important/);

const compactCss = cssSource.replace(/\s+/g, '');
assert.ok(compactCss.includes('.app-layout{display:grid;grid-template-columns:272pxminmax(0,1fr)'), 'Hotfix20 272px desktop grid is missing');
assert.ok(compactCss.includes('.app-layout>.app-layout__content{grid-column:2!important;grid-row:1!important;grid-area:1/2!important}'), 'Hotfix20 content grid placement is missing');
assert.ok(compactCss.includes('body.app-layout.app-sidebar{display:block!important;position:sticky!important;'), 'Hotfix20 sticky sidebar geometry is missing');
assert.ok(compactCss.includes('html[data-sidebar-mode="collapsed"].app-layout{grid-template-columns:5remminmax(0,1fr)'), 'collapsed desktop geometry is missing');
assert.ok(cssSource.includes('@media(max-width:1023px)'), 'mobile breakpoint is missing');
assert.ok(cssSource.includes('[dir="rtl"] .app-sidebar') && cssSource.includes('[dir="ltr"] .app-sidebar'), 'RTL/LTR sidebar positioning is missing');
assert.equal(layoutSource.includes('data-sidebar-scroll'), false, 'the layout wrapper must not duplicate the component-owned scroller');
assert.ok(themeBootstrap.includes('sidebarMode') && assistantSource.includes('sidebar_mode'), 'collapsed sidebar persistence is missing');

const cssEntry = manifest['resources/css/app.css']?.file;
const jsEntry = manifest['resources/js/app.js']?.file;
assert.ok(cssEntry && jsEntry, 'final Vite app entries are missing');
const builtCss = fs.readFileSync(path.join('public/build', cssEntry), 'utf8');
const builtJs = fs.readFileSync(path.join('public/build', jsEntry), 'utf8');
assert.ok(builtCss.includes('[data-sidebar-scroll]'), 'final CSS omitted the explicit scroller rule');
const builtScrollIntoViewCalls = builtJs.match(/\.scrollIntoView\(/g) || [];
assert.equal(builtScrollIntoViewCalls.length, 1, 'final JavaScript must retain only the guided-tour scrollIntoView');
assert.equal(/app-navigation__item[^;]{0,360}scrollIntoView/.test(builtJs), false, 'final JavaScript contains active-navigation scrollIntoView');
assert.ok(builtJs.includes('rajeh_sidebar_navigation_v2'), 'final JavaScript omitted Hotfix23 sidebar persistence');

const eventTarget = { addEventListener() {} };
const forbiddenMutations = [];
const protectedShell = (name, initial = {}) => new Proxy(initial, {
    set(target, property, value) {
        forbiddenMutations.push(`${name}.${String(property)}=${String(value)}`);
        target[property] = value;
        return true;
    },
});
globalThis.window = {
    ...eventTarget,
    localStorage: null,
    scrollX: 0,
    scrollY: 0,
};
globalThis.document = {
    ...eventTarget,
    documentElement: protectedShell('documentElement', { scrollTop: 0 }),
    body: protectedShell('body', { scrollTop: 0 }),
    querySelectorAll() { return []; },
};
globalThis.requestAnimationFrame = (callback) => { callback(); return 1; };
globalThis.cancelAnimationFrame = () => {};

const values = new Map();
const storage = {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, value),
};
window.localStorage = storage;

const sidebar = await import('../../../resources/js/sidebar-navigation-state.js');
const makePage = ({ scrollTop = 0, activeVisible = true, open = [] } = {}) => {
    const scrollWrites = [];
    let internalScrollTop = scrollTop;
    const details = ['group:top', 'group:lower', 'item:setup', 'subcategory:finance'].map((key) => ({
        dataset: { navigationState: key },
        open: open.includes(key),
        contains: () => ['group:lower', 'item:setup', 'subcategory:finance'].includes(key),
    }));
    const active = {
        getBoundingClientRect: () => activeVisible ? ({ top: 240, bottom: 280 }) : ({ top: 860, bottom: 900 }),
    };
    const navigation = {
        scrollHeight: 1400,
        clientHeight: 600,
        dataset: {},
        get scrollTop() { return internalScrollTop; },
        set scrollTop(value) { internalScrollTop = value; scrollWrites.push(value); },
        querySelector: (selector) => selector.includes('aria-current') ? active : null,
        querySelectorAll: (selector) => selector.includes('details')
            ? (selector.includes('[open]') ? details.filter((item) => item.open) : details)
            : [],
        getBoundingClientRect: () => ({ top: 100, bottom: 700 }),
    };
    const root = {
        querySelectorAll: (selector) => selector === sidebar.SIDEBAR_SCROLL_SELECTOR ? [navigation] : [],
    };
    return { root, navigation, details, scrollWrites };
};

const sourcePage = makePage({ scrollTop: 520, open: ['group:lower', 'item:setup', 'subcategory:finance'] });
const captured = sidebar.captureSidebarState(sourcePage.root, storage);
assert.deepEqual(Object.keys(captured).sort(), ['open', 'scrollTop', 'version']);
assert.equal(captured.scrollTop, 520);

const documentBefore = {
    windowX: window.scrollX,
    windowY: window.scrollY,
    documentTop: document.documentElement.scrollTop,
    bodyTop: document.body.scrollTop,
};
const restoredVisible = makePage({ activeVisible: true });
sidebar.restoreSidebarState(restoredVisible.root, storage);
assert.equal(restoredVisible.navigation.scrollTop, 520, 'a correctly restored position was reset');
assert.deepEqual(restoredVisible.details.filter((item) => item.open).map((item) => item.dataset.navigationState), ['group:lower', 'item:setup', 'subcategory:finance']);

const restoredOffscreen = makePage({ activeVisible: false });
sidebar.restoreSidebarState(restoredOffscreen.root, storage);
assert.equal(restoredOffscreen.navigation.scrollTop, 720, 'off-screen active item was not revealed inside the sidebar');
assert.ok(restoredOffscreen.scrollWrites.every((value) => Number.isFinite(value)), 'internal scroller received an invalid position');

const documentAfter = {
    windowX: window.scrollX,
    windowY: window.scrollY,
    documentTop: document.documentElement.scrollTop,
    bodyTop: document.body.scrollTop,
};
assert.deepEqual(documentAfter, documentBefore, 'sidebar restoration changed document/body scroll');
assert.deepEqual(forbiddenMutations, [], 'sidebar restoration mutated a protected document or shell object');

const ambiguousRoot = { querySelectorAll: () => [restoredVisible.navigation, restoredOffscreen.navigation] };
assert.equal(sidebar.restoreSidebarState(ambiguousRoot, storage), null, 'multiple sidebar scrollers must fail closed');

console.log('HOTFIX23_DOCUMENT_SCROLL_INVARIANT=PASS scrollY=0');
console.log('HOTFIX23_UNIQUE_INTERNAL_SCROLL_CONTRACT=PASS selector=[data-sidebar-scroll]');
console.log('HOTFIX23_ACTIVE_REVEAL_CONTRACT=PASS scrollIntoView=absent');
console.log('HOTFIX23_HOTFIX20_GEOMETRY_CONTRACT=PASS');
console.log('HOTFIX23_RESPONSIVE_DIRECTION_CONTRACT=PASS desktop=expanded,collapsed directions=rtl,ltr mobile=preserved');
console.log(`HOTFIX23_FINAL_ASSET_CONTRACT=PASS css=${cssEntry} js=${jsEntry}`);
