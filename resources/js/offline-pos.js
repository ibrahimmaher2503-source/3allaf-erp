const DATABASE_NAME = 'toyjoy-offline-pos';
const STORE_NAME = 'queue';
const DATABASE_VERSION = 1;

const forbiddenKey = /cost|margin|wallet|loyalty|customer|expected.?cash|evidence|document|sale.?number/i;
const allowedRecordKeys = new Set([
    'localUuid', 'deviceReference', 'policyVersion', 'schemaVersion',
    'capturedAt', 'priceCachedAt', 'lines', 'payment',
]);
const allowedLineKeys = new Set(['productId', 'quantity', 'unitPrice', 'priceVersionId']);
const allowedPaymentKeys = new Set(['paymentMethodId', 'amount']);

function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DATABASE_NAME, DATABASE_VERSION);
        request.onerror = () => reject(request.error);
        request.onupgradeneeded = () => {
            const database = request.result;
            if (!database.objectStoreNames.contains(STORE_NAME)) database.createObjectStore(STORE_NAME, { keyPath: 'localUuid' });
        };
        request.onsuccess = () => resolve(request.result);
    });
}

function objectWithOnly(value, allowed) {
    return value && typeof value === 'object' && !Array.isArray(value)
        && Object.keys(value).every((key) => allowed.has(key) && !forbiddenKey.test(key));
}

function sanitizeRecord(record) {
    if (!objectWithOnly(record, allowedRecordKeys)
        || !record.localUuid || !record.deviceReference || !record.policyVersion || !record.schemaVersion
        || !Array.isArray(record.lines) || !objectWithOnly(record.payment, allowedPaymentKeys)
        || !record.lines.every((line) => objectWithOnly(line, allowedLineKeys))) {
        throw new Error('The offline record is outside the restricted queue allowlist.');
    }

    return {
        localUuid: String(record.localUuid),
        deviceReference: String(record.deviceReference),
        policyVersion: String(record.policyVersion),
        schemaVersion: String(record.schemaVersion),
        capturedAt: String(record.capturedAt),
        priceCachedAt: String(record.priceCachedAt),
        lines: record.lines.map((line) => ({
            productId: Number(line.productId), quantity: String(line.quantity),
            unitPrice: String(line.unitPrice), priceVersionId: Number(line.priceVersionId),
        })),
        payment: { paymentMethodId: Number(record.payment.paymentMethodId), amount: String(record.payment.amount) },
    };
}

async function clearQueue() {
    const database = await openDatabase();
    await new Promise((resolve, reject) => {
        const request = database.transaction(STORE_NAME, 'readwrite').objectStore(STORE_NAME).clear();
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve();
    });
    database.close();
}

async function queue(record) {
    const safeRecord = sanitizeRecord(record);
    const database = await openDatabase();
    await new Promise((resolve, reject) => {
        const request = database.transaction(STORE_NAME, 'readwrite').objectStore(STORE_NAME).put(safeRecord);
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve();
    });
    database.close();
    return safeRecord;
}

async function records() {
    const database = await openDatabase();
    const result = await new Promise((resolve, reject) => {
        const request = database.transaction(STORE_NAME, 'readonly').objectStore(STORE_NAME).getAll();
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
    });
    database.close();
    return result;
}

function currentPolicyElement() {
    return document.querySelector('[data-offline-policy]');
}

async function clearWhenPolicyIsInvalid() {
    const policy = currentPolicyElement();
    if (!policy || policy.dataset.offlineEnabled !== 'true') return clearQueue();
    const queued = await records();
    if (queued.some((record) => record.schemaVersion !== policy.dataset.offlineSchema)) await clearQueue();
}

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form instanceof HTMLFormElement && /\/logout\/?$/.test(form.action)) void clearQueue();
});
document.addEventListener('toyjoy:offline-revoked', () => void clearQueue());
document.addEventListener('livewire:navigated', () => void clearWhenPolicyIsInvalid());
window.addEventListener('storage', (event) => {
    if (event.key === 'toyjoy-offline-revoked') void clearQueue();
});

if ('indexedDB' in window) {
    void clearWhenPolicyIsInvalid();
    window.ToyJoyOfflinePos = Object.freeze({ queue, records, clear: clearQueue, databaseName: DATABASE_NAME });
}
