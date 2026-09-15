const DECIMAL_AMOUNT = /^\d+(?:\.\d{1,2})?$/;

export const decimalToCents = (value) => {
    const normalized = String(value ?? '').trim();
    if (!DECIMAL_AMOUNT.test(normalized)) return null;
    const [whole, fraction = ''] = normalized.split('.');
    return (BigInt(whole) * 100n) + BigInt(fraction.padEnd(2, '0'));
};

export const formatCents = (value) => {
    const cents = value < 0n ? -value : value;
    const formatted = `${cents / 100n}.${String(cents % 100n).padStart(2, '0')}`;
    return value < 0n ? `-${formatted}` : formatted;
};

export const summarizePaymentParts = (payable, parts, { allowOutstanding = false, creditOnly = false } = {}) => {
    const payableCents = decimalToCents(payable);
    if (payableCents === null || payableCents <= 0n || !Array.isArray(parts)) {
        return { valid: false, remaining: payableCents ?? 0n, change: 0n };
    }
    if (parts.length === 0) {
        return { valid: allowOutstanding && creditOnly, remaining: payableCents, change: 0n };
    }
    if (creditOnly) return { valid: false, remaining: payableCents, change: 0n };

    const methodIds = new Set();
    let nonCash = 0n;
    let cashReceived = null;
    let cashCount = 0;

    for (const part of parts) {
        const methodId = String(part.methodId ?? '').trim();
        if (methodId === '' || methodIds.has(methodId)) {
            return { valid: false, remaining: payableCents, change: 0n };
        }
        methodIds.add(methodId);

        if (part.isCash) {
            cashCount += 1;
            cashReceived = decimalToCents(part.tendered);
            if (cashCount > 1 || cashReceived === null || cashReceived <= 0n) {
                return { valid: false, remaining: payableCents - nonCash, change: 0n };
            }
            continue;
        }

        const amount = decimalToCents(part.amount);
        if (amount === null || amount <= 0n) {
            return { valid: false, remaining: payableCents - nonCash, change: 0n };
        }
        nonCash += amount;
        if (nonCash > payableCents) {
            return { valid: false, remaining: 0n, change: 0n };
        }
    }

    const cashDue = payableCents - nonCash;
    if (cashCount === 0) {
        return { valid: allowOutstanding || nonCash === payableCents, remaining: cashDue, change: 0n };
    }
    if (cashDue <= 0n) {
        return { valid: false, remaining: 0n, change: 0n };
    }

    return {
        valid: allowOutstanding || cashReceived >= cashDue,
        remaining: cashReceived < cashDue ? cashDue - cashReceived : 0n,
        change: cashReceived > cashDue ? cashReceived - cashDue : 0n,
    };
};

window.posPaymentCalculator = ({ payable, cashReceived = '', allowOutstanding = false, creditOnly = false }) => ({
    payable: String(payable),
    cashReceived: String(cashReceived),
    allowOutstanding,
    creditOnly,
    revision: 0,
    submitting: false,
    parts() {
        this.revision;
        return [...this.$el.querySelectorAll('[data-payment-part]')].map((part) => ({
            methodId: part.querySelector('[data-payment-method]')?.value ?? '',
            isCash: part.hasAttribute('data-cash-payment'),
            amount: part.querySelector('[data-noncash-amount]')?.value ?? '',
            tendered: part.hasAttribute('data-cash-payment') ? this.cashReceived : null,
        }));
    },
    summary() {
        return summarizePaymentParts(this.payable, this.parts(), { allowOutstanding: this.allowOutstanding, creditOnly: this.creditOnly });
    },
    cashDueCents() {
        const payableCents = decimalToCents(this.payable) ?? 0n;
        const nonCash = this.parts().filter((part) => !part.isCash)
            .reduce((total, part) => total + (decimalToCents(part.amount) ?? 0n), 0n);
        return payableCents > nonCash ? payableCents - nonCash : 0n;
    },
    amountRemaining() { return formatCents(this.summary().remaining); },
    changeDue() { return formatCents(this.summary().change); },
    hasRemaining() { return this.summary().remaining > 0n; },
    hasChange() { return this.summary().change > 0n; },
    isExact() { const summary = this.summary(); return summary.valid && summary.remaining === 0n && summary.change === 0n; },
    canComplete() { return this.summary().valid; },
    useExactCash(id) {
        this.cashReceived = formatCents(this.cashDueCents());
        this.syncCash(id);
    },
    useQuickCash(value, id) {
        this.cashReceived = String(value);
        this.syncCash(id);
    },
    syncCash(id) {
        this.revision += 1;
        this.$nextTick(() => {
            const input = document.getElementById(id);
            input?.dispatchEvent(new Event('input', { bubbles: true }));
            input?.dispatchEvent(new Event('change', { bubbles: true }));
        });
    },
});
