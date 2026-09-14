import assert from 'node:assert/strict';
import test from 'node:test';

class FakeElement {
    constructor() {
        this.dataset = {};
        this.style = {
            setProperty: (name, value) => {
                this.style[name] = value;
            },
        };
        this.attributes = new Map();
        this.hidden = false;
        this.isConnected = true;
        this.listeners = new Map();
        this.closestMap = {};
        this.textContent = '';
    }

    setAttribute(name, value) {
        this.attributes.set(name, String(value));
    }

    removeAttribute(name) {
        this.attributes.delete(name);
    }

    getAttribute(name) {
        return this.attributes.get(name) || null;
    }

    closest(selector) {
        return this.closestMap[selector] || null;
    }

    contains(element) {
        return element === this || this.containsElements?.includes(element) || false;
    }

    querySelector(selector) {
        return this.queries?.[selector] || null;
    }

    querySelectorAll() {
        return [];
    }

    addEventListener(name, callback) {
        this.listeners.set(name, callback);
    }

    removeEventListener(name) {
        this.listeners.delete(name);
    }

    focus() {
        this.focused = true;
        if (globalThis.document) globalThis.document.activeElement = this;
    }

    dispatchEvent() {}
}

class FakeDocument {
    constructor(elements) {
        this.body = new FakeElement();
        this.documentElement = new FakeElement();
        this.documentElement.clientWidth = 390;
        this.readyState = 'loading';
        this.elements = elements;
        this.listeners = new Map();
    }

    addEventListener(name, callback) {
        this.listeners.set(name, callback);
    }

    querySelector(selector) {
        return this.elements[selector] || null;
    }

    querySelectorAll(selector) {
        return selector === '[wire\\:click]' ? this.all || [] : [];
    }
}

test('sidebar closes and restores page scrolling when resizing from mobile to desktop', async () => {
    const sidebar = new FakeElement();
    const backdrop = new FakeElement();
    const toggle = new FakeElement();
    const document = new FakeDocument({
        '[data-sidebar]': sidebar,
        '[data-sidebar-backdrop], .sidebar-backdrop': backdrop,
        '[data-sidebar-toggle]': toggle,
    });
    let mobile = true;
    const windowListeners = new Map();

    globalThis.Element = FakeElement;
    globalThis.HTMLElement = FakeElement;
    globalThis.Document = FakeDocument;
    globalThis.DocumentFragment = FakeElement;
    globalThis.document = document;
    globalThis.localStorage = {
        getItem: () => null,
        setItem: () => {},
    };
    globalThis.MutationObserver = class {
        observe() {}
    };
    globalThis.requestAnimationFrame = (callback) => callback();
    globalThis.window = {
        innerWidth: 390,
        addEventListener: (name, callback) => windowListeners.set(name, callback),
        matchMedia: () => ({ matches: mobile }),
    };

    const { BusinessOverlayController, BusinessSidebarController } = await import(`../resources/js/business.js?sidebar-test=${Date.now()}`);
    const controller = new BusinessSidebarController();
    controller.start();
    controller.open(toggle);

    assert.equal(sidebar.dataset.sidebarOpen, 'true');
    assert.equal(document.body.style.overflow, 'hidden');
    assert.equal(backdrop.hidden, false);
    assert.equal(toggle.attributes.get('aria-expanded'), 'true');

    mobile = false;
    window.innerWidth = 1024;
    document.documentElement.clientWidth = 1024;
    windowListeners.get('resize')();

    assert.equal(sidebar.dataset.sidebarOpen, 'false');
    assert.equal(document.body.style.overflow, undefined);
    assert.equal(document.body.style.paddingRight, undefined);
    assert.equal(backdrop.hidden, true);
    assert.equal(backdrop.attributes.get('aria-hidden'), 'true');
    assert.equal(toggle.attributes.get('aria-expanded'), 'false');
    assert.equal(sidebar.attributes.get('aria-hidden'), 'false');
    assert.equal(sidebar.attributes.has('inert'), false);

    mobile = true;
    window.innerWidth = 390;
    document.documentElement.clientWidth = 390;
    windowListeners.get('resize')();

    assert.equal(sidebar.dataset.sidebarOpen, 'false');
    assert.equal(sidebar.attributes.get('aria-hidden'), 'true');
    assert.equal(sidebar.attributes.has('inert'), true);

    const body = new FakeElement();
    body.scrollHeight = 600;
    body.clientHeight = 300;
    body.scrollTop = 0;
    const cue = new FakeElement();
    const overlay = new FakeElement();
    overlay.queries = {
        '[data-overlay-scroll-body]': body,
        '[data-overlay-scroll-cue]': cue,
        '.overlay-footer': null,
    };
    const overlayController = new BusinessOverlayController();
    overlayController.updateScrollCue(overlay);

    assert.equal(cue.hidden, false, 'the cue is shown while the body can scroll');
    body.scrollTop = 300;
    overlayController.updateScrollCue(overlay);
    assert.equal(cue.hidden, true, 'the cue hides when the body reaches the end');
});

test('overlay restores focus to a replacement Livewire trigger after close', async () => {
    const oldTrigger = new FakeElement();
    oldTrigger.attributes.set('wire:click', 'startProviderEdit("provider-1")');
    oldTrigger.textContent = 'Edit details';
    oldTrigger.closestMap['[wire\\:click]'] = oldTrigger;
    oldTrigger.isConnected = true;

    const replacementTrigger = new FakeElement();
    replacementTrigger.attributes.set('wire:click', 'startProviderEdit("provider-1")');
    replacementTrigger.textContent = 'Edit details';
    const document = new FakeDocument({});
    document.all = [];
    document.activeElement = oldTrigger;

    globalThis.Element = FakeElement;
    globalThis.HTMLElement = FakeElement;
    globalThis.Document = FakeDocument;
    globalThis.DocumentFragment = FakeElement;
    globalThis.document = document;
    globalThis.localStorage = { getItem: () => null, setItem: () => {} };
    globalThis.MutationObserver = class { observe() {} };
    globalThis.CustomEvent = class { constructor(type, init) { this.type = type; this.detail = init?.detail; } };
    globalThis.requestAnimationFrame = (callback) => callback();
    globalThis.window = {
        innerWidth: 390,
        addEventListener: () => {},
        matchMedia: () => ({ matches: true }),
    };

    const { BusinessOverlayController } = await import(`../resources/js/business.js?focus-test=${Date.now()}`);
    const overlay = new FakeElement();
    const closeControl = new FakeElement();
    const panel = new FakeElement();
    overlay.containsElements = [closeControl, panel];
    overlay.queries = {
        '[data-overlay-scroll-body]': null,
        '[data-overlay-scroll-cue]': null,
        '[data-overlay-panel]': panel,
        '[data-overlay-close]': closeControl,
    };
    const controller = new BusinessOverlayController();
    controller.handleClick({ target: oldTrigger });

    oldTrigger.isConnected = false;
    document.all = [replacementTrigger];
    controller.open(overlay);
    closeControl.click = () => controller.close(overlay);
    controller.handleKeydown({ key: 'Escape', preventDefault: () => {} });

    assert.equal(oldTrigger.focused, undefined);
    assert.equal(replacementTrigger.focused, true);
});

test('overlay resolves a replacement trigger after a Cancel close morph', async () => {
    const oldTrigger = new FakeElement();
    oldTrigger.attributes.set('wire:click', 'startProviderEdit("provider-1")');
    oldTrigger.textContent = 'Edit details';
    oldTrigger.closestMap['[wire\\:click]'] = oldTrigger;

    const replacementTrigger = new FakeElement();
    replacementTrigger.attributes.set('wire:click', 'startProviderEdit("provider-1")');
    replacementTrigger.textContent = 'Edit details';
    const document = new FakeDocument({});
    document.all = [];
    document.activeElement = oldTrigger;

    const frameQueue = [];
    globalThis.Element = FakeElement;
    globalThis.HTMLElement = FakeElement;
    globalThis.Document = FakeDocument;
    globalThis.DocumentFragment = FakeElement;
    globalThis.document = document;
    globalThis.localStorage = { getItem: () => null, setItem: () => {} };
    globalThis.MutationObserver = class { observe() {} };
    globalThis.CustomEvent = class { constructor(type, init) { this.type = type; this.detail = init?.detail; } };
    globalThis.requestAnimationFrame = (callback) => frameQueue.push(callback);
    globalThis.window = {
        innerWidth: 390,
        addEventListener: () => {},
        matchMedia: () => ({ matches: true }),
    };

    const { BusinessOverlayController } = await import(`../resources/js/business.js?cancel-focus-test=${Date.now()}`);
    const overlay = new FakeElement();
    const closeControl = new FakeElement();
    closeControl.closestMap['[data-overlay-close]'] = closeControl;
    closeControl.closestMap['[data-overlay]'] = overlay;
    overlay.containsElements = [closeControl];
    overlay.dataset.overlayOpenState = 'true';
    overlay.queries = {
        '[data-overlay-scroll-body]': null,
        '[data-overlay-scroll-cue]': null,
        '[data-overlay-panel]': new FakeElement(),
        '[data-overlay-close]': closeControl,
    };
    const controller = new BusinessOverlayController();
    controller.handleClick({ target: oldTrigger });
    controller.open(overlay);
    while (frameQueue.length) frameQueue.shift()();

    document.activeElement = closeControl;
    // Cancel is a Livewire-only control: the morph closes the overlay by
    // changing its state, which is handled by syncOverlay rather than click.
    overlay.dataset.overlayOpenState = 'false';
    controller.syncOverlay(overlay);

    // The Livewire morph occurs after close schedules its frame callback.
    oldTrigger.isConnected = false;
    document.all = [replacementTrigger];
    document.activeElement = document.body;
    assert.equal(replacementTrigger.focused, undefined);

    while (frameQueue.length) frameQueue.shift()();

    assert.equal(replacementTrigger.focused, true);
});
