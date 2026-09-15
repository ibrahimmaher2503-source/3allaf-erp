const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);

class ProductLookup {
    constructor(root) {
        const currentInput = root.querySelector('[data-product-search]');
        const currentResultsAnchor = root.querySelector('[data-product-results]');
        if (root._productLookup?.input === currentInput && root._productLookup?.resultsAnchor === currentResultsAnchor) return root._productLookup;
        root._productLookup?.destroy();
        root.dataset.productLookupReady = 'true';
        this.root = root;
        this.input = currentInput;
        this.hidden = root.querySelector('[data-product-id]');
        this.resultsAnchor = currentResultsAnchor;
        this.results = this.createResultsOverlay();
        this.error = root.querySelector('[data-product-error]');
        this.items = [];
        this.active = -1;
        this.explicitHighlight = false;
        this.lastResolvedTerm = null;
        this.timer = null;
        this.controller = null;
        this.pendingKey = null;
        this.pendingRequest = null;
        this.requestSequence = 0;
        this.stream = null;
        this.cameraFrame = null;
        this.cameraTrigger = root.querySelector('[data-camera-trigger]');
        this.cameraPanel = root.querySelector('[data-camera-panel]');
        this.cameraVideo = root.querySelector('[data-camera-video]');
        this.cameraMessage = root.querySelector('[data-camera-message]');
        this.listId = `product-results-${Math.random().toString(36).slice(2)}`;
        this.results.id = this.listId;
        this.input.setAttribute('aria-controls', this.listId);
        this.input.addEventListener('input', () => this.queue());
        this.input.addEventListener('keydown', (event) => this.keydown(event));
        this.input.addEventListener('blur', () => setTimeout(() => this.close(), 120));
        this.cameraTrigger?.addEventListener('click', () => this.openCamera());
        root.querySelector('[data-camera-close]')?.addEventListener('click', () => this.closeCamera());
        this.outsidePointer = (event) => {
            if (!this.root.contains(event.target) && !this.results.contains(event.target)) this.close();
        };
        this.reposition = () => {
            if (!this.results.classList.contains('hidden')) this.positionResultsOverlay();
        };
        document.addEventListener('pointerdown', this.outsidePointer, true);
        window.addEventListener('resize', this.reposition, { passive: true });
        window.addEventListener('scroll', this.reposition, { passive: true, capture: true });
        root._productLookup = this;
    }

    createResultsOverlay() {
        const overlay = this.resultsAnchor.cloneNode(false);
        overlay.removeAttribute('data-product-results');
        overlay.dataset.productResultsOverlay = 'true';
        overlay.classList.add('hidden');
        overlay.setAttribute('role', 'listbox');
        overlay.setAttribute('aria-live', 'polite');
        overlay.style.position = 'fixed';
        overlay.style.zIndex = '10000';
        overlay.style.margin = '0';
        document.body.appendChild(overlay);
        this.resultsAnchor.setAttribute('aria-hidden', 'true');

        return overlay;
    }

    positionResultsOverlay() {
        const rect = this.input.getBoundingClientRect();
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
        const spaceBelow = viewportHeight - rect.bottom - 8;
        const spaceAbove = rect.top - 8;
        const openAbove = spaceBelow < 160 && spaceAbove > spaceBelow;
        const available = Math.max(96, Math.min(256, openAbove ? spaceAbove : spaceBelow));
        this.results.style.left = `${Math.max(4, rect.left)}px`;
        this.results.style.width = `${Math.max(240, rect.width)}px`;
        this.results.style.maxHeight = `${available}px`;
        this.results.style.top = openAbove ? 'auto' : `${rect.bottom + 4}px`;
        this.results.style.bottom = openAbove ? `${viewportHeight - rect.top + 4}px` : 'auto';
        this.results.setAttribute('dir', this.input.getAttribute('dir') || document.documentElement.dir || 'ltr');
    }

    queue() {
        if (this.hidden.value !== '') {
            this.hidden.value = '';
            this.hidden.dispatchEvent(new Event('input', { bubbles: true }));
        }
        clearTimeout(this.timer);
        this.controller?.abort();
        this.requestSequence++;
        this.pendingKey = null;
        this.pendingRequest = null;
        const term = this.input.value.trim();
        if (term.length < 1) return this.close();
        if (this.root.dataset.context === 'purchasing' && !this.root.dataset.supplierId) {
            this.renderMessage(this.root.dataset.messageSupplierRequired);
            return;
        }
        this.items = [];
        this.active = -1;
        this.explicitHighlight = false;
        this.renderMessage(this.root.dataset.messageLoading);
        this.timer = setTimeout(() => this.search(term), 120);
    }

    async search(term, commitExact = false) {
        if (!term) return this.close();
        const params = new URLSearchParams({
            q: term,
            context: this.root.dataset.context || 'transaction',
        });
        if (this.root.dataset.supplierId) params.set('supplier_id', this.root.dataset.supplierId);
        if (this.root.dataset.supplierOnly) params.set('supplier_only', this.root.dataset.supplierOnly);
        if (this.root.dataset.currencyCode) params.set('currency_code', this.root.dataset.currencyCode);
        if (this.root.dataset.purchaseInvoiceId) params.set('purchase_invoice_id', this.root.dataset.purchaseInvoiceId);
        const requestKey = params.toString();
        if (requestKey === this.pendingKey && this.pendingRequest) return this.pendingRequest;

        this.controller?.abort();
        this.controller = new AbortController();
        const controller = this.controller;
        const sequence = ++this.requestSequence;
        this.pendingKey = requestKey;
        this.pendingRequest = (async () => {
          try {
            const response = await fetch(`${this.root.dataset.searchUrl}?${requestKey}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            if (!response.ok) throw new Error('lookup');
            const payload = await response.json();
            if (sequence !== this.requestSequence || term !== this.input.value.trim()) return;
            this.items = payload.data ?? [];
            this.lastResolvedTerm = term;
            const uniqueExact = this.items.find((item) => item.exact_unique === true);
            if (uniqueExact) {
                this.select(uniqueExact);
                return;
            }
            if (commitExact && this.items.length === 0) {
                this.root.dispatchEvent(new CustomEvent('product-lookup-empty', { bubbles: true, detail: { term } }));
            }
            this.render(payload.meta ?? {});
        } catch (error) {
            if (error.name !== 'AbortError' && sequence === this.requestSequence) {
                this.renderMessage(this.root.dataset.messageError, 'error');
            }
        } finally {
            if (sequence === this.requestSequence) {
                this.pendingKey = null;
                this.pendingRequest = null;
            }
        }
        })();
        return this.pendingRequest;
    }

    render(meta = {}) {
        this.active = -1;
        this.explicitHighlight = false;
        if (!this.items.length) {
            const message = meta.supplier_required
                ? this.root.dataset.messageSupplierRequired
                : (meta.supplier_products_empty ? this.root.dataset.messageSupplierEmpty : this.root.dataset.messageEmpty);
            this.results.innerHTML = `<p class="p-2 text-xs text-zinc-500" role="status" data-product-state="empty">${escapeHtml(message)}</p>`;
        } else {
            this.results.innerHTML = this.items.map((item, index) => {
                const arabic = item.name_ar || item.name_en;
                const english = item.name_en || item.name_ar;
                const primaryName = document.documentElement.lang === 'ar' ? arabic : english;
                const secondaryName = document.documentElement.lang === 'ar' ? english : arabic;
                const secondaryDirection = document.documentElement.lang === 'ar' ? 'ltr' : 'rtl';
                const names = `<strong class="block">${escapeHtml(primaryName)}</strong>${secondaryName && secondaryName !== primaryName ? `<span class="mt-0.5 block text-xs text-zinc-600 dark:text-zinc-300" dir="${secondaryDirection}">${escapeHtml(secondaryName)}</span>` : ''}`;
                const identity = `${names}<small class="mt-1 block font-mono text-zinc-500" dir="ltr">${escapeHtml(item.item_code)} · ${escapeHtml(item.model_number || '—')} · ${escapeHtml(item.barcodes?.[0] || '—')}</small>`;
                if (this.root.dataset.context !== 'purchasing') {
                    return `<button type="button" role="option" id="${this.listId}-${index}" aria-selected="false" data-result-index="${index}" class="block w-full border-b border-zinc-100 p-3 text-start text-sm last:border-0 hover:bg-cyan-50 focus:bg-cyan-50 dark:border-zinc-800 dark:hover:bg-cyan-950/30">${identity}</button>`;
                }
                const source = item.price_source === 'last_supplier_price'
                    ? this.root.dataset.labelLastPrice
                    : (item.price_source === 'fallback_cost' ? this.root.dataset.labelFallbackPrice : this.root.dataset.labelNoPrice);
                const price = item.unit_cost === null ? source : `${source}: ${item.unit_cost}${item.price_currency ? ` ${item.price_currency}` : ''}${item.price_date ? ` · ${item.price_date}` : ''}`;
                const supplierCode = item.supplier_item_code ? ` · ${this.root.dataset.labelSupplierCode}: ${item.supplier_item_code}` : '';
                return `<button type="button" role="option" id="${this.listId}-${index}" aria-selected="false" data-result-index="${index}" class="block w-full border-b border-zinc-100 p-3 text-start text-sm last:border-0 hover:bg-cyan-50 focus:bg-cyan-50 dark:border-zinc-800 dark:hover:bg-cyan-950/30">${identity}<small class="mt-1 block text-zinc-600 dark:text-zinc-300">${escapeHtml(this.root.dataset.labelUnit)}: ${escapeHtml(item.unit_of_measure || '—')}${escapeHtml(supplierCode)}</small><small class="mt-1 block font-semibold text-cyan-700 dark:text-cyan-300">${escapeHtml(price)}</small></button>`;
            }).join('');
            this.results.querySelectorAll('[data-result-index]').forEach((button) => {
                button.addEventListener('mousedown', (event) => event.preventDefault());
                button.addEventListener('click', () => this.select(this.items[Number(button.dataset.resultIndex)]));
            });
        }
        this.results.classList.remove('hidden');
        this.results.dataset.state = this.items.length ? 'results' : 'empty';
        this.positionResultsOverlay();
        this.input.setAttribute('aria-expanded', 'true');
        this.syncActive();
    }

    keydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            this.close();
            this.closeCamera();
            return;
        }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (this.results.classList.contains('hidden')) return this.search(this.input.value.trim());
            const direction = event.key === 'ArrowDown' ? 1 : -1;
            if (!this.items.length) return;
            this.active = this.active < 0 ? (direction > 0 ? 0 : this.items.length - 1) : Math.max(0, Math.min(this.items.length - 1, this.active + direction));
            this.explicitHighlight = true;
            this.syncActive();
            return;
        }
        if (event.key !== 'Enter') return;
        event.preventDefault();
        event.stopPropagation();
        clearTimeout(this.timer);
        if (!this.results.classList.contains('hidden') && this.explicitHighlight && this.items[this.active]) {
            this.select(this.items[this.active]);
        } else if (this.input.value.trim() && (this.results.classList.contains('hidden') || this.lastResolvedTerm !== this.input.value.trim())) {
            this.search(this.input.value.trim(), true);
        }
    }

    syncActive() {
        this.results.querySelectorAll('[role="option"]').forEach((option, index) => option.setAttribute('aria-selected', String(index === this.active)));
        const option = this.results.querySelector(`#${this.listId}-${this.active}`);
        if (option) {
            this.input.setAttribute('aria-activedescendant', option.id);
            option.scrollIntoView({ block: 'nearest' });
        } else this.input.removeAttribute('aria-activedescendant');
    }

    select(item) {
        clearTimeout(this.timer);
        const selectedValue = String(item[this.root.dataset.valueField || 'id'] ?? item.id);
        if (this.hidden.value === selectedValue) {
            this.close();
            return;
        }
        this.hidden.value = selectedValue;
        this.hidden.dispatchEvent(new Event('input', { bubbles: true }));
        this.hidden.dispatchEvent(new Event('change', { bubbles: true }));
        this.input.value = `${document.documentElement.lang === 'ar' ? (item.name_ar || item.name_en) : (item.name_en || item.name_ar)} · ${item.item_code}`;
        this.input.dataset.selected = 'true';
        this.error?.classList.add('hidden');
        this.close();
        this.closeCamera();
        this.root.dispatchEvent(new CustomEvent('product-selected', { bubbles: true, detail: item }));
        requestAnimationFrame(() => this.root.closest('[data-product-line]')?.querySelector('[data-line-quantity]')?.focus());
    }

    close() {
        this.results.classList.add('hidden');
        delete this.results.dataset.state;
        this.active = -1;
        this.explicitHighlight = false;
        this.input.setAttribute('aria-expanded', 'false');
        this.input.removeAttribute('aria-activedescendant');
    }

    renderMessage(message, state = 'loading') {
        this.items = [];
        this.active = -1;
        const tone = state === 'error' ? 'text-rose-700 dark:text-rose-300' : 'text-zinc-500';
        this.results.innerHTML = `<p class="p-2 text-xs ${tone}" role="status" data-product-state="${state}">${escapeHtml(message)}</p>`;
        this.results.classList.remove('hidden');
        this.results.dataset.state = state;
        this.positionResultsOverlay();
        this.input.setAttribute('aria-expanded', 'true');
    }

    async openCamera() {
        this.cameraMessage?.classList.add('hidden');
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
            this.showCameraMessage(this.root.dataset.cameraUnsupported);
            return;
        }
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            this.cameraVideo.srcObject = this.stream;
            this.cameraPanel.classList.remove('hidden');
            await this.cameraVideo.play();
            this.detectBarcode(new BarcodeDetector());
        } catch {
            this.closeCamera();
            this.showCameraMessage(this.root.dataset.cameraError);
        }
    }

    async detectBarcode(detector) {
        if (!this.stream || !this.root.isConnected) return this.closeCamera();
        try {
            const detections = await detector.detect(this.cameraVideo);
            if (detections[0]?.rawValue) {
                const barcode = detections[0].rawValue.trim();
                this.input.value = barcode;
                this.closeCamera();
                await this.search(barcode, true);
                return;
            }
        } catch {
            this.closeCamera();
            this.showCameraMessage(this.root.dataset.cameraError);
            return;
        }
        this.cameraFrame = requestAnimationFrame(() => this.detectBarcode(detector));
    }

    closeCamera() {
        if (this.cameraFrame) cancelAnimationFrame(this.cameraFrame);
        this.cameraFrame = null;
        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;
        if (this.cameraVideo) this.cameraVideo.srcObject = null;
        this.cameraPanel?.classList.add('hidden');
    }

    showCameraMessage(message) {
        if (!this.cameraMessage) return;
        this.cameraMessage.textContent = message;
        this.cameraMessage.classList.remove('hidden');
    }

    destroy() {
        clearTimeout(this.timer);
        this.controller?.abort();
        this.closeCamera();
        document.removeEventListener('pointerdown', this.outsidePointer, true);
        window.removeEventListener('resize', this.reposition);
        window.removeEventListener('scroll', this.reposition, true);
        this.results.remove();
        delete this.root._productLookup;
        delete this.root.dataset.productLookupReady;
    }

    invalidate() {
        this.controller?.abort();
        this.requestSequence++;
        this.pendingKey = null;
        this.pendingRequest = null;
        this.close();
    }
}

const lineIsBlank = (line) => !(line.querySelector('[data-product-id]')?.value || line.querySelector('[data-product-search]')?.value.trim());

const initializeEditor = (editor) => {
    if (editor.dataset.productLineEditorReady) return;
    editor.dataset.productLineEditorReady = 'true';
    editor.addEventListener('product-selected', (event) => {
        const line = event.target.closest('[data-product-line]');
        if (!line) return;
        line.querySelectorAll('[name$="[description_ar]"]').forEach((field) => { if (!field.value) field.value = event.detail.name_ar || event.detail.name_en || ''; });
        line.querySelectorAll('[name$="[description_en]"], [name$="[description]"]').forEach((field) => { if (!field.value) field.value = event.detail.name_en || event.detail.name_ar || ''; });
        const price = line.querySelector('[data-line-price]');
        const preferred = price?.dataset.priceKind === 'price' ? event.detail.unit_price : event.detail.unit_cost;
        const purchasing = event.target.dataset.context === 'purchasing';
        if (price && !price.disabled && (purchasing || !price.value || Number(price.value) === 0)) {
            price.value = preferred ?? '';
            price.dispatchEvent(new Event('input', { bubbles: true }));
            price.dispatchEvent(new Event('change', { bubbles: true }));
        }
        const source = line.querySelector('[data-price-source]');
        if (source && purchasing) {
            const label = event.detail.price_source === 'last_supplier_price'
                ? event.target.dataset.labelLastPrice
                : (event.detail.price_source === 'fallback_cost' ? event.target.dataset.labelFallbackPrice : event.target.dataset.labelNoPrice);
            source.textContent = `${label}${event.detail.price_date ? ` · ${event.detail.price_date}` : ''}${event.detail.price_currency ? ` · ${event.detail.price_currency}` : ''}`;
        }
        updateTotal(line);
    });
    editor.addEventListener('input', (event) => {
        const line = event.target.closest?.('[data-product-line]');
        if (line) updateTotal(line);
    });
    editor.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.target.matches('[data-product-search], textarea, button')) return;
        const line = event.target.closest('[data-product-line]');
        if (!line) {
            if (event.target.matches('input, select')) {
                event.preventDefault();
                event.stopPropagation();
            }
            return;
        }
        if (event.target.matches('[data-line-quantity]')) {
            event.preventDefault();
            event.stopPropagation();
            const next = line.querySelector('[data-line-price]:not(:disabled):not([readonly]), [data-line-step]:not(:disabled):not([readonly]), [data-line-final]:not(:disabled):not([readonly])');
            next ? next.focus() : commitLine(editor, line);
        } else if (event.target.matches('[data-line-price], [data-line-step], [data-line-final]')) {
            event.preventDefault();
            event.stopPropagation();
            const steps = [...line.querySelectorAll('[data-line-price]:not(:disabled):not([readonly]), [data-line-step]:not(:disabled):not([readonly]), [data-line-final]:not(:disabled):not([readonly])')];
            const next = steps[steps.indexOf(event.target) + 1];
            next ? next.focus() : commitLine(editor, line);
        }
    });
    editor.addEventListener('click', (event) => {
        const remove = event.target.closest?.('[data-remove-line]');
        if (remove) {
            remove.closest('[data-product-line]')?.remove();
            renumberStaticLines(editor);
            return;
        }
        const add = event.target.closest?.('[data-add-line]');
        if (!add) return;
        editor.dataset.focusNextBlank = 'true';
        const template = editor.querySelector('template[data-line-template]');
        if (!template) return;
        const index = Number(editor.dataset.nextIndex || editor.querySelectorAll('[data-product-line]').length);
        const holder = document.createElement('template');
        holder.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index));
        template.before(holder.content);
        editor.dataset.nextIndex = String(index + 1);
        boot();
        focusBlank(editor);
    });
    editor.querySelectorAll('[data-product-line]').forEach(updateTotal);
    focusBlank(editor);
};

const renumberStaticLines = (editor) => {
    editor.querySelectorAll('[data-product-line]').forEach((line, index) => {
        line.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replace(/\[(\d+)\]/, `[${index}]`);
        });
    });
    editor.dataset.nextIndex = String(editor.querySelectorAll('[data-product-line]').length);
};

const updateTotal = (line) => {
    const quantity = Number(line.querySelector('[data-line-quantity]')?.value || 0);
    const price = Number(line.querySelector('[data-line-price]')?.value || 0);
    const discountType = line.querySelector('[data-line-discount-type]')?.value || '';
    const discountValue = Number(line.querySelector('[data-line-discount]')?.value || 0);
    const taxRate = Number(line.querySelector('[data-line-tax]')?.value || 0);
    const total = line.querySelector('[data-line-total]');
    const gross = quantity * price;
    const discount = discountType === 'percentage' ? gross * discountValue / 100 : (discountType === 'amount' ? discountValue : 0);
    const taxable = Math.max(0, gross - discount);
    const result = taxable + (taxable * taxRate / 100);
    if (total) total.textContent = Number.isFinite(result) ? result.toFixed(2) : '0.00';
    updateEditorTotal(line.closest('[data-product-line-editor]'));
};

const updateEditorTotal = (editor) => {
    if (!editor) return;
    const total = [...editor.querySelectorAll('[data-line-total]')]
        .reduce((sum, output) => sum + Number(output.textContent || 0), 0);
    editor.querySelectorAll('[data-editor-total]').forEach((output) => {
        output.textContent = Number.isFinite(total) ? total.toFixed(2) : '0.00';
    });
};

const commitLine = (editor, line) => {
    if (line.dataset.committing === 'true') return;
    const product = line.querySelector('[data-product-id]');
    const quantity = line.querySelector('[data-line-quantity]');
    const price = line.querySelector('[data-line-price]:not(:disabled)');
    const productValue = product?.value || line.dataset.productValue;
    if (!productValue || !quantity?.checkValidity() || (price && !price.checkValidity())) {
        line.querySelector('[data-product-error]')?.classList.toggle('hidden', Boolean(productValue));
        (!productValue ? line.querySelector('[data-product-search]') : (!quantity?.checkValidity() ? quantity : price))?.focus();
        return;
    }
    const lines = [...editor.querySelectorAll('[data-product-line]')];
    const laterBlank = lines.slice(lines.indexOf(line) + 1).find(lineIsBlank);
    if (laterBlank) return laterBlank.querySelector('[data-product-search]')?.focus();
    const add = editor.querySelector('[data-add-line]');
    if (add && !add.disabled) {
        line.dataset.committing = 'true';
        editor.dataset.focusNextBlank = 'true';
        add.click();
    } else {
        editor.querySelector('[data-product-search]')?.focus();
    }
};

const posKeyboardNavigation = (event) => {
    const search = document.getElementById('live-pos-product-search');
    const productButtons = [...document.querySelectorAll('[data-pos-product-grid] article button:not(:disabled)')];
    if (!search || !productButtons.length) return;

    if (event.target === search && event.key === 'ArrowDown') {
        event.preventDefault();
        productButtons[0].focus();
        return;
    }

    const current = productButtons.indexOf(event.target.closest?.('button'));
    if (current < 0) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        search.focus();
    } else if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
        event.preventDefault();
        productButtons[Math.min(productButtons.length - 1, current + 1)].focus();
    } else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
        event.preventDefault();
        (current === 0 ? search : productButtons[current - 1]).focus();
    }
};

const focusBlank = (editor) => {
    if (editor.dataset.focusNextBlank !== 'true' && editor.dataset.autofocus !== 'true') return;
    const blank = [...editor.querySelectorAll('[data-product-line]')].find(lineIsBlank);
    if (blank) {
        editor.dataset.focusNextBlank = 'false';
        editor.dataset.autofocus = 'false';
        requestAnimationFrame(() => blank.querySelector('[data-product-search]')?.focus());
    }
};

const matchingElements = (scope, selector) => [
    ...(scope.matches?.(selector) ? [scope] : []),
    ...scope.querySelectorAll(selector),
];

const initializeCompactFilterRow = (row) => {
    if (row.dataset.compactFilterReady) return;
    row.dataset.compactFilterReady = 'true';
    row.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || !event.target.matches('input, select')) return;
        event.preventDefault();
        event.stopPropagation();
        const controls = [...row.querySelectorAll('input:not(:disabled), select:not(:disabled)')];
        const next = controls[controls.indexOf(event.target) + 1];
        (next || document.querySelector('[data-price-lists-table] a, [data-price-lists-table] button'))?.focus();
    });
};

const boot = (scope = document) => {
    matchingElements(scope, '[data-product-lookup]').forEach((root) => new ProductLookup(root));
    matchingElements(scope, '[data-product-line-editor]').forEach(initializeEditor);
    matchingElements(scope, '[data-product-line-editor]').forEach(focusBlank);
    matchingElements(scope, '[data-compact-filter-row]').forEach(initializeCompactFilterRow);
};

const bindLivewireHooks = () => {
    if (!window.Livewire?.hook || window.__toyJoyProductLookupHooksBound) return;
    window.__toyJoyProductLookupHooksBound = true;
    window.Livewire.hook('morph.updated', ({ el }) => boot(el));
    window.Livewire.hook('morph.added', ({ el }) => boot(el));
};

if (typeof document !== 'undefined') {
    new MutationObserver((records) => {
        records.forEach((record) => {
            record.removedNodes.forEach((node) => {
                if (!(node instanceof Element)) return;
                if (node.matches('[data-product-lookup]')) node._productLookup?.destroy();
                node.querySelectorAll?.('[data-product-lookup]').forEach((root) => root._productLookup?.destroy());
            });
            if (record.type === 'attributes' && record.target.matches?.('[data-product-lookup]')) record.target._productLookup?.invalidate();
        });
        records.forEach((record) => boot(record.target));
        requestAnimationFrame(() => document.querySelectorAll('[data-product-lookup]').forEach((root) => {
            if (root.getClientRects().length === 0) root._productLookup?.closeCamera();
        }));
    }).observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-supplier-id', 'data-supplier-only', 'data-currency-code', 'data-purchase-invoice-id', 'hidden', 'class'] });
    document.addEventListener('DOMContentLoaded', () => boot());
    document.addEventListener('livewire:init', bindLivewireHooks);
    document.addEventListener('livewire:initialized', () => { bindLivewireHooks(); boot(); });
    document.addEventListener('livewire:navigated', () => { bindLivewireHooks(); boot(); });
    document.addEventListener('input', (event) => {
        const root = event.target.closest?.('[data-product-lookup]');
        if (root && !root._productLookup) new ProductLookup(root);
    }, true);
    document.addEventListener('keydown', posKeyboardNavigation);
    window.addEventListener('pos-product-search-focus', () => requestAnimationFrame(() => document.getElementById('live-pos-product-search')?.focus()));
    window.addEventListener('purchasing-product-focus', () => requestAnimationFrame(() => document.querySelector('[data-product-lookup][data-context="purchasing"] [data-product-search]:not(:disabled)')?.focus()));
    window.addEventListener('pagehide', () => document.querySelectorAll('[data-product-lookup]').forEach((root) => root._productLookup?.closeCamera()));
    document.addEventListener('livewire:navigating', () => document.querySelectorAll('[data-product-lookup]').forEach((root) => root._productLookup?.closeCamera()));
    boot();
}

export { ProductLookup, boot, initializeEditor };
