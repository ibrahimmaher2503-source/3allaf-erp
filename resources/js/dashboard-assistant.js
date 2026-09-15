const registerDashboardAssistant = () => {
    if (!window.Alpine || window.__toyJoyDashboardAssistantRegistered) return;

    window.Alpine.data('dashboardAssistant', ({ context, locale, fallback, preferences, progress, preferencesUrl, progressUrl }) => ({
            context, locale, fallback, progress, preferencesUrl, progressUrl,
            defaults: { appearance: 'system', accent_color: 'teal', sidebar_mode: 'expanded', navbar_mode: 'sticky', content_width: 'wide', table_density: 'comfortable', font_scale: 'normal', reduced_motion: false },
            preferences: {},
            darkSidebar: false,
            guideOpen: false, customizerOpen: false, mobileMenuOpen: false,
            saveStatus: 'idle',
            tutorialStatus: 'not_started',
            launcherFocus: null,
            tour: { active: false, steps: [], step: null, visibleIndex: 0, lastFocus: null, currentElement: null, savedStyle: null, cardPosition: null, scrollAdjusting: false },
            _tourScrollHandler: null,
            init() {
                let initialDark = false;
                try {
                    initialDark = localStorage.getItem('toyjoy_ui_dark_sidebar') === 'true';
                } catch (e) {
                    initialDark = document.documentElement.dataset.darkSidebar === 'true';
                }
                this.darkSidebar = initialDark;
                this.preferences = this.restoreShellPreferences(preferences);
                this.progress = progress || {};
                this.tutorialStatus = this.statusForContext();
                this.applyPreferences();
            },
            statusForContext() {
                const screenId = this.context?.screen_id;
                return screenId && this.progress?.[screenId]?.status ? this.progress[screenId].status : 'not_started';
            },
            tutorialStatusText() {
                return {
                    not_started: locale.startsWith('ar') ? 'لم تبدأ الجولة بعد' : 'Tour not started',
                    in_progress: locale.startsWith('ar') ? 'جولة قيد التقدم' : 'Tour in progress',
                    completed: locale.startsWith('ar') ? 'اكتملت الجولة' : 'Tour completed',
                    dismissed: locale.startsWith('ar') ? 'تم تخطي الجولة' : 'Tour dismissed',
                }[this.tutorialStatus] || '';
            },
            tourButtonLabel() {
                return this.tutorialStatus === 'in_progress'
                    ? (locale.startsWith('ar') ? 'متابعة الجولة' : 'Continue tour')
                    : (locale.startsWith('ar') ? 'ابدأ الجولة التفاعلية' : 'Start interactive tour');
            },
            async persistTutorialStatus(status) {
                const screenId = this.context?.screen_id;
                if (!screenId) return;
                this.tutorialStatus = status;
                this.progress = {
                    ...(this.progress || {}),
                    [screenId]: { status, updated_at: new Date().toISOString() },
                };
                try {
                    await fetch(this.progressUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify({ screen_id: screenId, status }),
                    });
                } catch (error) {
                    console.warn('Tutorial progress could not be saved', error);
                }
            },
            restoreShellPreferences(serverPreferences = {}) {
                let stored = null;
                try {
                    stored = JSON.parse(localStorage.getItem('rajeh_shell_preferences_v3') || 'null');
                    if (!stored || stored.version !== 3 || !['expanded', 'collapsed'].includes(stored.sidebar_mode)) {
                        const legacy = JSON.parse(localStorage.getItem('rajeh_shell_preferences_v2') || 'null');
                        stored = legacy?.version === 2 && ['expanded', 'collapsed'].includes(legacy.sidebar_mode)
                            ? { version: 3, sidebar_mode: legacy.sidebar_mode }
                            : null;
                    }
                } catch (ignored) {
                    stored = null;
                }
                const resolved = this.normalizePreferences({ ...serverPreferences, sidebar_mode: stored?.sidebar_mode || serverPreferences?.sidebar_mode || 'expanded' });
                try {
                    localStorage.setItem('rajeh_shell_preferences_v3', JSON.stringify({ version: 3, sidebar_mode: resolved.sidebar_mode }));
                    localStorage.removeItem('rajeh_shell_preferences_v2');
                } catch (ignored) {}
                return resolved;
            },
            normalizePreferences(value = {}) {
                const merged = { ...this.defaults, ...(value || {}) };
                if (!['expanded', 'collapsed'].includes(merged.sidebar_mode)) merged.sidebar_mode = 'expanded';
                merged.reduced_motion = merged.reduced_motion === true || merged.reduced_motion === 1 || merged.reduced_motion === '1' || merged.reduced_motion === 'true';
                return merged;
            },
            saveStatusText() {
                return { saving: locale.startsWith('ar') ? 'جارٍ حفظ إعدادات العرض…' : 'Saving display settings…', saved: locale.startsWith('ar') ? 'تم حفظ إعدادات العرض' : 'Display settings saved', error: locale.startsWith('ar') ? 'تعذر حفظ الإعدادات، لكن تم تطبيقها محلياً' : 'Could not save settings; they remain applied locally' }[this.saveStatus] || '';
            },
            text(value) {
                if (!value) return '';
                if (typeof value === 'string') return value;
                if (Array.isArray(value)) return value.map(v => this.text(v)).filter(Boolean).join(' ');
                if (typeof value === 'object') {
                    const val = value[this.locale] || (this.locale.startsWith('ar') ? value.ar : value.en) || value.ar || value.en;
                    if (typeof val === 'string') return val;
                    if (Array.isArray(val)) return val.map(v => this.text(v)).filter(Boolean).join(' ');
                    if (val) return String(val);
                }
                return String(value);
            },
            openGuide(event) {
                if (event && event.currentTarget) {
                    this.launcherFocus = event.currentTarget;
                } else if (!this.launcherFocus) {
                    this.launcherFocus = document.activeElement;
                }
                this.customizerOpen = false;
                this.guideOpen = true;
            },
            closeGuide() {
                this.guideOpen = false;
                this.restoreLauncherFocus();
            },
            openCustomizer(event) {
                if (event && event.currentTarget) {
                    this.launcherFocus = event.currentTarget;
                } else if (!this.launcherFocus) {
                    this.launcherFocus = document.activeElement;
                }
                this.guideOpen = false;
                this.customizerOpen = true;
            },
            closeCustomizer() {
                this.customizerOpen = false;
                this.restoreLauncherFocus();
            },
            closeAll() {
                const hadOpen = this.guideOpen || this.customizerOpen || this.tour.active || this.mobileMenuOpen;
                this.guideOpen = false;
                this.customizerOpen = false;
                this.mobileMenuOpen = false;
                if (this.tour.active) {
                    this.finishTour('dismissed');
                } else if (hadOpen) {
                    this.restoreLauncherFocus();
                }
            },
            restoreLauncherFocus() {
                if (this.launcherFocus && typeof this.launcherFocus.focus === 'function' && this.launcherFocus.isConnected) {
                    this.launcherFocus.focus();
                }
                this.launcherFocus = null;
            },
            applyPreferences() {
                const root = document.documentElement;
                const systemDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches;
                const appearance = this.preferences.appearance === 'system' ? (systemDark ? 'dark' : 'light') : this.preferences.appearance;
                root.classList.toggle('dark', appearance === 'dark');
                root.style.colorScheme = appearance;
                root.dataset.appearance = this.preferences.appearance;
                root.dataset.accent = this.preferences.accent_color;
                root.dataset.sidebarMode = this.preferences.sidebar_mode;
                root.dataset.contentWidth = this.preferences.content_width;
                root.dataset.tableDensity = this.preferences.table_density;
                root.dataset.fontScale = this.preferences.font_scale;
                root.dataset.reducedMotion = this.preferences.reduced_motion ? 'true' : 'false';
                root.dataset.darkSidebar = this.darkSidebar ? 'true' : 'false';
                const syncSidebar = () => {
                    const body = document.body;
                    if (body) body.style.gridTemplateColumns = '';
                    const sidebar = document.querySelector('.app-sidebar');
                    if (!sidebar) return;
                    sidebar.removeAttribute('data-flux-sidebar-collapsed-desktop');
                    try {
                        localStorage.setItem('rajeh_shell_preferences_v3', JSON.stringify({ version: 3, sidebar_mode: this.preferences.sidebar_mode }));
                    } catch (ignored) {}
                };
                syncSidebar();
                window.requestAnimationFrame?.(syncSidebar);
            },
            toggleDesktopSidebar() {
                this.preferences.sidebar_mode = this.preferences.sidebar_mode === 'collapsed' ? 'expanded' : 'collapsed';
                this.applyPreferences();
                this.savePreferences();
            },
            async savePreferences() {
                this.preferences = this.normalizePreferences(this.preferences);
                this.applyPreferences();
                this.saveStatus = 'saving';
                try {
                    const response = await fetch(this.preferencesUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify(this.preferences)
                    });
                    if (!response.ok) throw new Error('Preference save failed: ' + response.status);
                    const data = await response.json();
                    if (data && typeof data === 'object') {
                        this.preferences = this.normalizePreferences(data);
                        this.applyPreferences();
                    }
                    this.saveStatus = 'saved';
                } catch (error) {
                    console.warn('UI preference save failed', error);
                    this.saveStatus = 'error';
                }
            },
            updateDarkSidebar() {
                const isDark = Boolean(this.darkSidebar);
                document.documentElement.dataset.darkSidebar = isDark ? 'true' : 'false';
                try {
                    if (isDark) {
                        localStorage.setItem('toyjoy_ui_dark_sidebar', 'true');
                    } else {
                        localStorage.removeItem('toyjoy_ui_dark_sidebar');
                    }
                } catch (e) {}
            },
            resetPreferences() {
                this.darkSidebar = false;
                this.updateDarkSidebar();
                this.preferences = { ...this.defaults };
                this.applyPreferences();
                this.savePreferences();
            },
            get tourCardStyle() {
                if (!this.tour.cardPosition) return '';
                return `top: ${this.tour.cardPosition.top}px; left: ${this.tour.cardPosition.left}px; transform: none;`;
            },
            isVisibleTarget(el) {
                if (!el || !el.isConnected) return false;
                const style = window.getComputedStyle(el);
                if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') return false;
                if (el.classList?.contains('sr-only') || (el.matches && el.matches('.sr-only, [class*="sr-only"]'))) return false;
                const rect = el.getBoundingClientRect();
                if (rect.width <= 2 || rect.height <= 2) return false;
                if (typeof el.getClientRects === 'function' && el.getClientRects().length === 0) return false;
                return true;
            },
            isUnsafeParent(el) {
                if (!el || !(el instanceof HTMLElement || el instanceof SVGElement)) return true;
                const tag = el.tagName ? el.tagName.toUpperCase() : '';
                if (tag === 'BODY' || tag === 'HTML' || tag === 'MAIN') return true;
                if (el.matches && el.matches('body, html, main, [role="main"]')) return true;
                if (el.closest && el.closest('.platform-assistant')) return true;
                return false;
            },
            resolveTarget(selectorOrElement) {
                if (!selectorOrElement) return null;
                if (typeof selectorOrElement === 'string' && selectorOrElement.includes(',')) {
                    const selectors = selectorOrElement.split(',').map(s => s.trim()).filter(Boolean);
                    for (const s of selectors) {
                        const target = this.resolveTarget(s);
                        if (target) return target;
                    }
                    return null;
                }
                const el = typeof selectorOrElement === 'string' ? document.querySelector(selectorOrElement) : selectorOrElement;
                if (!el) return null;
                if (this.isUnsafeParent(el)) return null;

                const fluxContainer = el.closest && el.closest('[data-flux-input-file]');
                if (fluxContainer && this.isVisibleTarget(fluxContainer) && !this.isUnsafeParent(fluxContainer)) {
                    if (this.isVisibleTarget(el) || (el.tagName === 'INPUT' && el.type === 'file')) {
                        return fluxContainer;
                    }
                }

                if (this.isVisibleTarget(el)) {
                    return el;
                }

                return null;
            },
            getValidSteps() {
                return (this.context?.tour_steps || []).filter(step => {
                    if (!step || !step.selector) return false;
                    return this.resolveTarget(step.selector) !== null;
                });
            },
            clearHighlight() {
                if (this.tour.currentElement) {
                    const el = this.tour.currentElement;
                    el.classList.remove('platform-assistant__tour-target');
                    if (this.tour.savedStyle) {
                        el.style.position = this.tour.savedStyle.position;
                        el.style.zIndex = this.tour.savedStyle.zIndex;
                    }
                    this.tour.currentElement = null;
                    this.tour.savedStyle = null;
                }
            },
            highlightTarget(element) {
                this.clearHighlight();
                if (!element) return;
                this.tour.currentElement = element;
                this.tour.savedStyle = {
                    position: element.style.position || '',
                    zIndex: element.style.zIndex || '',
                };
                const computedPos = window.getComputedStyle(element).position;
                if (computedPos === 'static') {
                    element.style.position = 'relative';
                }
                element.style.zIndex = '55';
                element.classList.add('platform-assistant__tour-target');
            },
            repositionTour() {
                if (!this.tour.active || !this.tour.step) return;
                const element = this.resolveTarget(this.tour.step.selector);
                if (!element) return;
                
                const card = this.$refs.tourCard;
                if (!card) return;
                
                const targetRect = element.getBoundingClientRect();
                const cardWidth = card.offsetWidth || 380;
                const cardHeight = card.offsetHeight || 200;
                
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;
                const gap = 14;
                const padding = 16;
                
                const spaceBelow = viewportHeight - targetRect.bottom - gap - padding;
                const spaceAbove = targetRect.top - gap - padding;

                // When the target and card can fit together but the current scroll
                // position cannot, move the target upward so the card sits below it
                // instead of clamping the card over the highlighted content.
                const combinedHeight = targetRect.height + cardHeight + gap + (padding * 2);
                if (spaceBelow < cardHeight && spaceAbove < cardHeight && combinedHeight <= viewportHeight && !this.tour.scrollAdjusting) {
                    const desiredTop = Math.max(padding, Math.min(targetRect.top, viewportHeight - targetRect.height - cardHeight - gap - padding));
                    const delta = targetRect.top - desiredTop;
                    if (Math.abs(delta) > 4) {
                        this.tour.scrollAdjusting = true;
                        window.scrollBy({ top: delta, left: 0, behavior: 'auto' });
                        window.requestAnimationFrame(() => {
                            this.tour.scrollAdjusting = false;
                            this.repositionTour();
                        });
                        return;
                    }
                }

                let top;
                if (spaceBelow >= cardHeight) {
                    top = targetRect.bottom + gap;
                } else if (spaceAbove >= cardHeight) {
                    top = targetRect.top - gap - cardHeight;
                } else {
                    if (spaceBelow >= spaceAbove) {
                        top = Math.min(targetRect.bottom + gap, viewportHeight - cardHeight - padding);
                    } else {
                        top = Math.max(padding, targetRect.top - gap - cardHeight);
                    }
                }
                
                const targetCenterX = targetRect.left + (targetRect.width / 2);
                let left = targetCenterX - (cardWidth / 2);
                
                left = Math.max(padding, Math.min(left, viewportWidth - cardWidth - padding));
                top = Math.max(padding, Math.min(top, viewportHeight - cardHeight - padding));
                
                this.tour.cardPosition = { top: Math.round(top), left: Math.round(left) };
            },
            startTour() {
                if (this.tour.active) {
                    this.finishTour();
                }
                this.tour.lastFocus = this.launcherFocus || document.activeElement;
                this.tour.steps = this.getValidSteps();
                if (!this.tour.steps.length) {
                    return;
                }
                this.tour.visibleIndex = 0;
                this.tour.active = true;
                this.guideOpen = false;
                this.persistTutorialStatus('in_progress');
                
                if (!this._tourScrollHandler) {
                    this._tourScrollHandler = () => {
                        if (this.tour.active) {
                            this.repositionTour();
                        }
                    };
                    window.addEventListener('scroll', this._tourScrollHandler, { passive: true, capture: true });
                    window.addEventListener('resize', this._tourScrollHandler, { passive: true });
                }
                
                this.showTourStep(0);
            },
            showTourStep(index) {
                this.clearHighlight();
                this.tour.steps = this.getValidSteps();
                
                if (!this.tour.steps.length || index < 0 || index >= this.tour.steps.length) {
                    return this.finishTour('dismissed');
                }
                
                this.tour.visibleIndex = index + 1;
                this.tour.step = this.tour.steps[index];
                
                const element = this.resolveTarget(this.tour.step.selector);
                if (!element) {
                    return this.nextTour();
                }
                
                element.scrollIntoView({
                    behavior: this.preferences?.reduced_motion ? 'auto' : 'smooth',
                    block: 'center'
                });
                
                this.highlightTarget(element);
                
                this.$nextTick(() => {
                    this.repositionTour();
                    setTimeout(() => this.repositionTour(), 150);
                    setTimeout(() => this.repositionTour(), 350);
                });
            },
            nextTour() {
                if (!this.tour.active) return;
                const nextIndex = this.tour.visibleIndex;
                if (nextIndex >= this.tour.steps.length) {
                    return this.finishTour('completed');
                }
                this.showTourStep(nextIndex);
            },
            previousTour() {
                if (!this.tour.active) return;
                const prevIndex = this.tour.visibleIndex - 2;
                if (prevIndex < 0) return;
                this.showTourStep(prevIndex);
            },
            skipTour() {
                this.finishTour('dismissed');
            },
            finishTour(status = 'dismissed') {
                if (this.tour.active && status) {
                    this.persistTutorialStatus(status);
                }
                this.clearHighlight();
                this.tour.active = false;
                this.tour.step = null;
                this.tour.steps = [];
                this.tour.visibleIndex = 0;
                this.tour.cardPosition = null;
                this.tour.scrollAdjusting = false;
                
                if (this._tourScrollHandler) {
                    window.removeEventListener('scroll', this._tourScrollHandler, { capture: true });
                    window.removeEventListener('resize', this._tourScrollHandler);
                    this._tourScrollHandler = null;
                }
                
                if (this.tour.lastFocus && typeof this.tour.lastFocus.focus === 'function' && this.tour.lastFocus.isConnected) {
                    this.tour.lastFocus.focus();
                } else {
                    this.restoreLauncherFocus();
                }
                this.tour.lastFocus = null;
                this.launcherFocus = null;
            },
        }));
    window.__toyJoyDashboardAssistantRegistered = true;
};

registerDashboardAssistant();
document.addEventListener('alpine:init', registerDashboardAssistant);
document.addEventListener('livewire:navigated', registerDashboardAssistant);

const synchronizeActiveNavigation = () => {
    document.querySelectorAll(".app-navigation__group[data-active-group]").forEach((group) => { group.open = true; });
};
document.addEventListener("DOMContentLoaded", synchronizeActiveNavigation);
document.addEventListener("livewire:navigated", synchronizeActiveNavigation);
