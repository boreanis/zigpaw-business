// Business has an explicit appearance preference. System is the default, with a
// small persisted override for operators who work across mixed-lighting screens.
const themeKey = 'zigpaw-business-theme';
const themeModes = ['system', 'light', 'dark'];

const updateThemeControls = (mode) => {
    document.querySelectorAll('[data-theme-toggle]').forEach((control) => {
        const label = control.querySelector('[data-theme-label]');
        if (label) label.textContent = mode.charAt(0).toUpperCase() + mode.slice(1);
        control.setAttribute('aria-label', `Change appearance (currently ${mode})`);
        control.dataset.theme = mode;
    });
};

const applyTheme = (mode) => {
    if (mode === 'system') document.documentElement.removeAttribute('data-theme');
    else document.documentElement.dataset.theme = mode;
    localStorage.setItem(themeKey, mode);
    updateThemeControls(mode);
};

document.addEventListener('click', (event) => {
    const control = event.target instanceof Element
        ? event.target.closest('[data-theme-toggle]')
        : null;
    if (!control) return;
    const current = localStorage.getItem(themeKey) || 'system';
    applyTheme(themeModes[(themeModes.indexOf(current) + 1) % themeModes.length]);
});

applyTheme(localStorage.getItem(themeKey) || 'system');

const overlaySelector = '[data-overlay]';
const focusableSelector = [
    'a[href]',
    'area[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    'iframe',
    '[contenteditable="true"]',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

const isFocusable = (element) => {
    if (!(element instanceof HTMLElement) || element.hidden) return false;
    if (element.closest('[inert]')) return false;

    const styles = window.getComputedStyle(element);
    return styles.display !== 'none' && styles.visibility !== 'hidden';
};

class BusinessSidebarController {
    constructor() {
        this.sidebar = null;
        this.backdrop = null;
        this.toggle = null;
        this.restoreTarget = null;
        this.previousBodyOverflow = null;
        this.previousBodyPadding = null;
        this.bodyLocked = false;
        this.syncFrame = null;
        this.observer = new MutationObserver(() => {
            if (this.syncFrame !== null) return;
            this.syncFrame = requestAnimationFrame(() => {
                this.syncFrame = null;
                this.sync();
            });
        });
        this.handleClick = this.handleClick.bind(this);
        this.handleKeydown = this.handleKeydown.bind(this);
    }

    start() {
        document.addEventListener('click', this.handleClick);
        document.addEventListener('keydown', this.handleKeydown);
        document.addEventListener('livewire:navigating', () => this.close(false));
        document.addEventListener('livewire:navigated', () => this.sync());
        window.addEventListener('resize', () => this.syncVisibility());
        this.sync();
        this.observer.observe(document.body, { childList: true, subtree: true });
    }

    sync() {
        this.sidebar = document.querySelector('[data-sidebar]');
        this.backdrop = document.querySelector('[data-sidebar-backdrop], .sidebar-backdrop');
        this.toggle = document.querySelector('[data-sidebar-toggle]');
        this.syncVisibility();
    }

    syncVisibility() {
        if (!this.sidebar) return;
        const mobile = window.matchMedia('(max-width: 760px)').matches;
        const hidden = mobile && !this.isOpen();
        this.sidebar.setAttribute('aria-hidden', hidden ? 'true' : 'false');

        // Moving the navigation off canvas is only visual. `inert` also removes
        // its links and controls from the keyboard and accessibility trees.
        if (hidden) this.sidebar.setAttribute('inert', '');
        else this.sidebar.removeAttribute('inert');
    }

    handleClick(event) {
        if (!(event.target instanceof Element)) return;

        const toggle = event.target.closest('[data-sidebar-toggle]');
        if (toggle) {
            event.preventDefault();
            this.open(toggle);
            return;
        }

        if (event.target.closest('[data-sidebar-close]')) {
            event.preventDefault();
            this.close();
            return;
        }

        if (event.target.closest('[data-sidebar] .business-nav-item')) this.close(false);
    }

    handleKeydown(event) {
        if (!this.isOpen()) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            this.close();
            return;
        }

        if (event.key !== 'Tab' || !this.sidebar) return;
        const focusable = [...this.sidebar.querySelectorAll(focusableSelector)].filter(isFocusable);
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && (document.activeElement === first || !this.sidebar.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    isOpen() {
        return this.sidebar?.dataset.sidebarOpen === 'true';
    }

    open(trigger = null) {
        this.sync();
        if (!this.sidebar || this.isOpen()) return;

        this.restoreTarget = trigger instanceof HTMLElement ? trigger : document.activeElement;
        this.sidebar.dataset.sidebarOpen = 'true';
        this.sidebar.setAttribute('aria-hidden', 'false');
        this.sidebar.removeAttribute('inert');
        if (this.backdrop) {
            this.backdrop.hidden = false;
            this.backdrop.setAttribute('aria-hidden', 'false');
        }
        this.toggle?.setAttribute('aria-expanded', 'true');
        this.lockBody();
        requestAnimationFrame(() => {
            const initial = this.sidebar?.querySelector('[data-sidebar-close]')
                || [...(this.sidebar?.querySelectorAll(focusableSelector) || [])].find(isFocusable);
            initial?.focus({ preventScroll: true });
        });
    }

    close(restoreFocus = true) {
        this.sync();
        if (!this.sidebar || !this.isOpen()) return;

        this.sidebar.dataset.sidebarOpen = 'false';
        this.syncVisibility();
        if (this.backdrop) {
            this.backdrop.hidden = true;
            this.backdrop.setAttribute('aria-hidden', 'true');
        }
        this.toggle?.setAttribute('aria-expanded', 'false');
        this.unlockBody();
        const restoreTarget = this.restoreTarget;
        this.restoreTarget = null;
        if (restoreFocus && restoreTarget instanceof HTMLElement && restoreTarget.isConnected) {
            requestAnimationFrame(() => restoreTarget.focus({ preventScroll: true }));
        }
    }

    lockBody() {
        if (this.bodyLocked || document.body.dataset.overlayLocked === 'true') return;
        this.previousBodyOverflow = document.body.style.overflow;
        this.previousBodyPadding = document.body.style.paddingRight;
        document.body.style.overflow = 'hidden';
        document.body.style.paddingRight = `${Math.max(0, window.innerWidth - document.documentElement.clientWidth)}px`;
        this.bodyLocked = true;
    }

    unlockBody() {
        if (!this.bodyLocked) return;
        document.body.style.overflow = this.previousBodyOverflow;
        document.body.style.paddingRight = this.previousBodyPadding;
        this.previousBodyOverflow = null;
        this.previousBodyPadding = null;
        this.bodyLocked = false;
    }
}

class BusinessOverlayController {
    constructor() {
        this.openOverlays = [];
        this.restoreTargets = new WeakMap();
        this.pendingClose = new WeakSet();
        this.previousBodyStyles = null;
        this.observer = new MutationObserver((mutations) => this.handleMutations(mutations));
        this.scrollCueObservers = new WeakMap();

        this.handleClick = this.handleClick.bind(this);
        this.handleKeydown = this.handleKeydown.bind(this);
    }

    start() {
        document.addEventListener('click', this.handleClick);
        document.addEventListener('keydown', this.handleKeydown);
        document.addEventListener('livewire:navigating', () => this.closeAll(false));

        this.sync(document);
        this.observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['data-overlay-open-state'],
        });
    }

    handleClick(event) {
        if (!(event.target instanceof Element)) return;

        const openControl = event.target.closest('[data-overlay-open]');
        if (openControl) {
            const overlay = document.getElementById(openControl.dataset.overlayOpen);
            if (overlay?.matches(overlaySelector)) {
                event.preventDefault();
                this.pendingClose.delete(overlay);
                this.open(overlay, openControl);
            }

            return;
        }

        const closeControl = event.target.closest('[data-overlay-close]');
        if (closeControl) {
            const overlay = closeControl.closest(overlaySelector);
            if (overlay) this.close(overlay);
            return;
        }

        const scrollCue = event.target.closest('[data-overlay-scroll-cue]');
        if (scrollCue) {
            const body = scrollCue.closest(overlaySelector)?.querySelector('[data-overlay-scroll-body]');
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            body?.scrollBy({
                top: Math.max(180, body.clientHeight * .72),
                behavior: reduceMotion ? 'auto' : 'smooth',
            });
            return;
        }

        const overlay = event.target.matches?.(overlaySelector) ? event.target : null;
        if (overlay && overlay.dataset.overlayBackdropClose !== 'false') {
            this.requestClose(overlay);
        }
    }

    handleKeydown(event) {
        const overlay = this.top();
        if (!overlay) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            this.requestClose(overlay);
            return;
        }

        if (event.key !== 'Tab') return;

        const focusable = [...overlay.querySelectorAll(focusableSelector)].filter(isFocusable);
        if (focusable.length === 0) {
            event.preventDefault();
            overlay.querySelector('[data-overlay-panel]')?.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const active = document.activeElement;

        if (event.shiftKey && (active === first || !overlay.contains(active))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && active === last) {
            event.preventDefault();
            first.focus();
        }
    }

    handleMutations(mutations) {
        mutations.forEach((mutation) => {
            if (mutation.type === 'attributes') {
                this.syncOverlay(mutation.target);
                return;
            }

            mutation.removedNodes.forEach((node) => this.cleanupRemovedNode(node));
            mutation.addedNodes.forEach((node) => this.sync(node));
        });

        this.openOverlays.forEach((overlay) => this.updateScrollCue(overlay));
    }

    sync(root) {
        if (!(root instanceof Element || root instanceof Document || root instanceof DocumentFragment)) return;

        if (root instanceof Element && root.matches(overlaySelector)) this.syncOverlay(root);
        root.querySelectorAll?.(overlaySelector).forEach((overlay) => this.syncOverlay(overlay));
    }

    syncOverlay(overlay) {
        if (!(overlay instanceof HTMLElement)) return;

        this.bindScrollCue(overlay);

        if (overlay.dataset.overlayOpenState === 'true' && !this.pendingClose.has(overlay)) {
            this.open(overlay);
        } else if (overlay.dataset.overlayOpenState !== 'true') {
            this.pendingClose.delete(overlay);
            this.close(overlay, false);
        }
    }

    open(overlay, trigger = null) {
        if (this.openOverlays.includes(overlay)) return;

        this.restoreTargets.set(
            overlay,
            trigger instanceof HTMLElement ? trigger : document.activeElement,
        );
        overlay.hidden = false;
        overlay.removeAttribute('inert');
        overlay.setAttribute('aria-hidden', 'false');
        overlay.dataset.overlayVisible = 'true';
        this.openOverlays.push(overlay);
        this.updateStacking();
        this.lockBody();

        requestAnimationFrame(() => requestAnimationFrame(() => {
            if (!this.openOverlays.includes(overlay)) return;
            const initialFocus = overlay.querySelector('[data-overlay-initial-focus]')
                || [...overlay.querySelectorAll(focusableSelector)].find(isFocusable)
                || overlay.querySelector('[data-overlay-panel]');
            initialFocus?.focus({ preventScroll: true });
        }));

        overlay.dispatchEvent(new CustomEvent('business:overlay-opened', { bubbles: true }));
        requestAnimationFrame(() => this.updateScrollCue(overlay));
    }

    requestClose(overlay) {
        const closeControl = overlay.querySelector('[data-overlay-close]');
        if (closeControl) closeControl.click();
        else this.close(overlay);
    }

    close(overlay, restoreFocus = true) {
        const index = this.openOverlays.indexOf(overlay);
        if (index === -1) {
            overlay.hidden = true;
            overlay.setAttribute('inert', '');
            overlay.setAttribute('aria-hidden', 'true');
            delete overlay.dataset.overlayVisible;
            return;
        }

        this.pendingClose.add(overlay);
        this.openOverlays.splice(index, 1);
        overlay.hidden = true;
        overlay.setAttribute('inert', '');
        overlay.setAttribute('aria-hidden', 'true');
        delete overlay.dataset.overlayVisible;
        this.updateStacking();
        if (this.openOverlays.length === 0) this.unlockBody();

        const restoreTarget = this.restoreTargets.get(overlay);
        this.restoreTargets.delete(overlay);
        if (restoreFocus && restoreTarget instanceof HTMLElement && restoreTarget.isConnected) {
            requestAnimationFrame(() => restoreTarget.focus({ preventScroll: true }));
        }

        overlay.dispatchEvent(new CustomEvent('business:overlay-closed', { bubbles: true }));
    }

    cleanupRemovedNode(node) {
        if (!(node instanceof Element)) return;
        const overlays = node.matches(overlaySelector)
            ? [node]
            : [...node.querySelectorAll(overlaySelector)];
        overlays.forEach((overlay) => {
            if (this.openOverlays.includes(overlay)) this.close(overlay, false);
            this.unbindScrollCue(overlay);
        });
    }

    closeAll(restoreFocus = false) {
        [...this.openOverlays].reverse().forEach((overlay) => this.close(overlay, restoreFocus));
    }

    top() {
        return this.openOverlays[this.openOverlays.length - 1] || null;
    }

    updateStacking() {
        this.openOverlays.forEach((overlay, index) => {
            overlay.style.zIndex = String(40 + index);
        });
    }

    bindScrollCue(overlay) {
        if (this.scrollCueObservers.has(overlay)) return;

        const body = overlay.querySelector('[data-overlay-scroll-body]');
        const cue = overlay.querySelector('[data-overlay-scroll-cue]');
        if (!(body instanceof HTMLElement) || !(cue instanceof HTMLElement)) return;

        const update = () => this.updateScrollCue(overlay);
        body.addEventListener('scroll', update, { passive: true });

        const resizeObserver = typeof ResizeObserver === 'function'
            ? new ResizeObserver(update)
            : null;
        resizeObserver?.observe(body);
        this.scrollCueObservers.set(overlay, { body, update, resizeObserver });
        requestAnimationFrame(update);
    }

    unbindScrollCue(overlay) {
        const binding = this.scrollCueObservers.get(overlay);
        if (!binding) return;

        binding.body.removeEventListener('scroll', binding.update);
        binding.resizeObserver?.disconnect();
        this.scrollCueObservers.delete(overlay);
    }

    updateScrollCue(overlay) {
        const body = overlay.querySelector('[data-overlay-scroll-body]');
        const cue = overlay.querySelector('[data-overlay-scroll-cue]');
        if (!(body instanceof HTMLElement) || !(cue instanceof HTMLElement)) return;

        const footer = overlay.querySelector('.overlay-footer');
        overlay.style.setProperty('--overlay-footer-height', `${footer?.offsetHeight || 0}px`);
        const remaining = body.scrollHeight - body.clientHeight - body.scrollTop;
        cue.hidden = body.scrollHeight <= body.clientHeight + 8 || remaining <= 8;
    }

    lockBody() {
        if (this.previousBodyStyles) return;

        const scrollbarWidth = Math.max(0, window.innerWidth - document.documentElement.clientWidth);
        this.previousBodyStyles = {
            overflow: document.body.style.overflow,
            paddingRight: document.body.style.paddingRight,
        };
        document.body.style.overflow = 'hidden';
        if (scrollbarWidth > 0) document.body.style.paddingRight = `${scrollbarWidth}px`;
        document.body.dataset.overlayLocked = 'true';
    }

    unlockBody() {
        if (!this.previousBodyStyles) return;

        document.body.style.overflow = this.previousBodyStyles.overflow;
        document.body.style.paddingRight = this.previousBodyStyles.paddingRight;
        delete document.body.dataset.overlayLocked;
        this.previousBodyStyles = null;
    }
}

class BusinessToastController {
    constructor() {
        this.timers = new WeakMap();
        this.observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => this.scan(node)));
        });

        this.handleClick = this.handleClick.bind(this);
    }

    start() {
        document.addEventListener('click', this.handleClick);
        window.addEventListener('business:toast', (event) => this.create(event.detail || {}));
        this.scan(document);
        this.observer.observe(document.body, { childList: true, subtree: true });
    }

    scan(root) {
        if (!(root instanceof Element || root instanceof Document || root instanceof DocumentFragment)) return;
        if (root instanceof Element && root.matches('[data-toast]')) this.schedule(root);
        root.querySelectorAll?.('[data-toast]').forEach((toast) => this.schedule(toast));
    }

    schedule(toast) {
        if (this.timers.has(toast)) return;
        const timeout = Number.parseInt(toast.dataset.toastTimeout || '6000', 10);
        if (!Number.isFinite(timeout) || timeout <= 0) return;

        const timer = window.setTimeout(() => this.requestClose(toast), timeout);
        this.timers.set(toast, timer);
    }

    handleClick(event) {
        if (!(event.target instanceof Element)) return;

        const closeControl = event.target.closest('[data-toast-close]');
        if (!closeControl) return;
        const toast = closeControl.closest('[data-toast]');
        if (toast) this.close(toast);
    }

    requestClose(toast) {
        const closeControl = toast.querySelector('[data-toast-close]');
        if (closeControl) closeControl.click();
        else this.close(toast);
    }

    close(toast) {
        const timer = this.timers.get(toast);
        if (timer) window.clearTimeout(timer);
        this.timers.delete(toast);
        toast.dataset.toastLeaving = 'true';
        window.setTimeout(() => {
            toast.hidden = true;
            toast.dispatchEvent(new CustomEvent('business:toast-closed', { bubbles: true }));
        }, 160);
    }

    create(detail) {
        const region = document.querySelector('[data-toast-region]');
        if (!region) return;

        const toast = document.createElement('article');
        const tone = detail.tone === 'error' ? 'error' : 'success';
        toast.className = `toast${tone === 'error' ? ' toast-error' : ''}`;
        toast.dataset.toast = '';
        toast.dataset.toastTimeout = String(detail.timeout || 6000);
        toast.setAttribute('role', tone === 'error' ? 'alert' : 'status');

        const marker = document.createElement('span');
        marker.className = 'toast-check';
        marker.setAttribute('aria-hidden', 'true');
        marker.textContent = tone === 'error' ? '!' : '✓';

        const message = document.createElement('span');
        message.className = 'toast-message';
        message.textContent = String(detail.message || '');

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'toast-close';
        close.dataset.toastClose = '';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.textContent = '×';

        toast.append(marker, message, close);
        region.append(toast);
        this.schedule(toast);
    }
}

const startBusinessInteractions = () => {
    if (!document.body || document.body.dataset.businessInteractions === 'ready') return;
    document.body.dataset.businessInteractions = 'ready';
    updateThemeControls(localStorage.getItem(themeKey) || 'system');
    new BusinessSidebarController().start();
    new BusinessOverlayController().start();
    new BusinessToastController().start();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startBusinessInteractions, { once: true });
} else {
    startBusinessInteractions();
}
