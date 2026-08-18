/**
 * WHISKER Store — Frontend JS
 * Cart drawer, AJAX cart, carousel, confetti
 */
const WhiskerStore = {
    _base: null,
    _csrf: null,

    init() {
        this.hideLoader();
        this.bindCartToggle();
        this.bindAddToCart();
        this.loadCart();
        this.initCarousel();
    },

    hideLoader() {
        const l = document.querySelector('.wk-page-loader');
        if (l) {
            const hide = () => setTimeout(() => l.classList.add('done'), 300);
            if (document.readyState === 'complete') hide();
            else window.addEventListener('load', hide);
        }
    },

    base(path) {
        if (!this._base) {
            const meta = document.querySelector('meta[name="wk-base"]');
            this._base = meta ? meta.content.replace(/\/+$/, '') : '';
        }
        return this._base + '/' + path.replace(/^\/+/, '');
    },

    // ── CSRF token (L1) ─────────────────────
    // Read from <meta name="wk-csrf"> set by store layout. Cached after
    // first read. Cart/state-changing POSTs MUST include this — either
    // appended to FormData as 'wk_csrf' or sent as X-CSRF-Token header.
    csrf() {
        if (this._csrf === null) {
            const meta = document.querySelector('meta[name="wk-csrf"]');
            this._csrf = meta ? meta.content : '';
        }
        return this._csrf;
    },

    // Attach wk_csrf to a FormData and return it (chainable).
    withCsrf(form) {
        const token = this.csrf();
        if (token && !form.has('wk_csrf')) form.append('wk_csrf', token);
        return form;
    },

    // Cart-state POST helper. Auto-attaches CSRF and handles 403 (token
    // rotated after login/logout) by reloading so the page picks up the
    // new token from the meta tag. Callers receive the parsed JSON body
    // or null if the page is reloading.
    async cartFetch(path, form) {
        const res = await fetch(this.base(path), { method: 'POST', body: this.withCsrf(form) });
        if (res.status === 403) {
            // Stale CSRF (most likely auth-state change in another tab).
            // Reload to refresh the token. The page reload is a one-shot
            // remediation — repeated 403s would loop, but verifyCsrf only
            // ever fails on bad/missing token, not on a fresh one.
            window.location.reload();
            return null;
        }
        try { return await res.json(); } catch (e) { return null; }
    },

    // ── Cart Drawer ──────────────────────────
    bindCartToggle() {
        document.querySelectorAll('[data-cart-open]').forEach(el =>
            el.addEventListener('click', (e) => { e.preventDefault(); this.openCart(); }));
        document.querySelectorAll('[data-cart-close]').forEach(el =>
            el.addEventListener('click', (e) => { e.preventDefault(); this.closeCart(); }));
        document.querySelector('.wk-cart-overlay')?.addEventListener('click', () => this.closeCart());
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') this.closeCart(); });
    },
    openCart() {
        document.querySelector('.wk-cart-drawer')?.classList.add('open');
        document.querySelector('.wk-cart-overlay')?.classList.add('open');
        document.body.style.overflow = 'hidden';
    },
    closeCart() {
        document.querySelector('.wk-cart-drawer')?.classList.remove('open');
        document.querySelector('.wk-cart-overlay')?.classList.remove('open');
        document.body.style.overflow = '';
    },

    // ── Add to Cart (event delegation — works everywhere) ──
    bindAddToCart() {
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-add-to-cart]');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            this.addToCart(btn);
        });
    },

    async addToCart(btn) {
        if (btn.disabled) return;
        const pid = btn.dataset.addToCart;
        const qtyInput = document.querySelector('#product-qty');
        const quantity = qtyInput ? parseInt(qtyInput.value) || 1 : (parseInt(btn.dataset.quantity) || 1);
        const origHTML = btn.innerHTML;

        btn.disabled = true;
        btn.textContent = 'Adding...';

        try {
            const form = new FormData();
            form.append('product_id', pid);
            form.append('quantity', quantity);

            // Include variant combo if selected
            const variantCombo = btn.dataset.variantCombo;
            if (variantCombo) {
                form.append('variant_combo_id', variantCombo);
            }

            const data = await this.cartFetch('cart/add', form);
            if (data === null) return; // reloading from stale CSRF

            if (data.success) {
                btn.innerHTML = '✓ Added!';
                btn.classList.add('added');
                this.bumpBadge();
                this.loadCart();
                setTimeout(() => { btn.innerHTML = origHTML; btn.classList.remove('added'); btn.disabled = false; }, 1500);
            } else {
                btn.textContent = data.message || 'Error';
                setTimeout(() => { btn.innerHTML = origHTML; btn.disabled = false; }, 2000);
            }
        } catch (err) {
            console.error('Add to cart error:', err);
            btn.textContent = 'Error';
            setTimeout(() => { btn.innerHTML = origHTML; btn.disabled = false; }, 2000);
        }
    },

    bumpBadge() {
        document.querySelectorAll('.wk-cart-count').forEach(b => {
            b.classList.add('bump');
            setTimeout(() => b.classList.remove('bump'), 300);
        });
    },

    // ── Load Cart ────────────────────────────
    async loadCart() {
        try {
            const res = await fetch(this.base('cart'));
            const data = await res.json();
            if (data.success) {
                this.renderItems(data.items);
                this.updateCount(data.count);
                // Prefer the server-formatted subtotal — it carries the store's
                // configured currency symbol (and display-currency conversion).
                this.updateTotal(data.subtotal_formatted != null ? data.subtotal_formatted : this.price(data.subtotal));
            }
        } catch (err) { console.error('Cart load:', err); }
    },

    renderItems(items) {
        const c = document.querySelector('.wk-cart-items');
        if (!c) return;
        if (!items?.length) {
            c.innerHTML = '<div class="wk-cart-empty"><div class="wk-cart-empty-icon">🛒</div><p style="font-weight:800;margin-bottom:4px">Cart is empty</p><p style="font-size:13px">Add products to get started</p></div>';
            return;
        }
        c.innerHTML = items.map(i => {
            const img = i.image ? `<img src="${this.base('storage/uploads/products/' + i.image)}" alt="">` : '<div style="width:100%;height:100%;background:var(--wk-bg);display:flex;align-items:center;justify-content:center;font-size:20px">📦</div>';
            const variantLabel = i.variant_label ? `<div style="font-size:11px;color:var(--wk-muted);font-weight:700;margin-top:2px">${this.esc(i.variant_label)}</div>` : '';
            return `<div class="wk-cart-item" data-item="${i.id}">
                <div class="wk-cart-item-img">${img}</div>
                <div class="wk-cart-item-info">
                    <div class="wk-cart-item-name">${this.esc(i.name)}</div>
                    ${variantLabel}
                    <div class="wk-cart-item-price">${i.line_total_formatted || this.price(i.unit_price * i.quantity)}</div>
                    <div class="wk-qty-ctrl">
                        <button class="wk-qty-btn" onclick="WhiskerStore.updateQty(${i.id},${i.quantity - 1})">−</button>
                        <input class="wk-qty-val" value="${i.quantity}" readonly>
                        <button class="wk-qty-btn" onclick="WhiskerStore.updateQty(${i.id},${i.quantity + 1})">+</button>
                    </div>
                </div>
                <button onclick="WhiskerStore.removeItem(${i.id})" style="background:none;border:none;color:var(--wk-muted);cursor:pointer;font-size:16px;align-self:flex-start;padding:4px">×</button>
            </div>`;
        }).join('');
    },

    updateCount(n) {
        document.querySelectorAll('.wk-cart-count').forEach(b => { b.textContent = n || 0; b.style.display = n > 0 ? 'flex' : 'none'; });
    },
    updateTotal(s) {
        // Accepts an already-formatted string (from the cart API) or falls
        // back to numeric formatting for old callers.
        const el = document.querySelector('.wk-cart-total-value');
        if (el) el.textContent = (typeof s === 'string') ? s : this.price(s);
    },

    async updateQty(id, qty) {
        const f = new FormData(); f.append('item_id', id); f.append('quantity', Math.max(0, qty));
        const data = await this.cartFetch(qty <= 0 ? 'cart/remove' : 'cart/update', f);
        if (data === null) return;
        this.loadCart();
    },
    async removeItem(id) {
        const el = document.querySelector(`[data-item="${id}"]`);
        if (el) { el.style.transition = 'all .3s'; el.style.opacity = '0'; el.style.transform = 'translateX(40px)'; }
        setTimeout(async () => {
            const f = new FormData(); f.append('item_id', id);
            const data = await this.cartFetch('cart/remove', f);
            if (data === null) return;
            this.loadCart();
        }, 300);
    },

    // ── Carousel ─────────────────────────────
    initCarousel() {
        const track = document.querySelector('.wk-carousel-track');
        if (!track || track.children.length <= 1) return;

        const slides = track.children;
        const total = slides.length;
        let current = 0;
        let timer = null;
        const wrap = track.closest('.wk-hero-carousel') || track.closest('.wk-carousel');
        const dots = wrap?.querySelector('.wk-carousel-dots');
        const counter = wrap?.querySelector('.wk-hero-counter-current');

        if (dots) {
            for (let i = 0; i < total; i++) {
                const d = document.createElement('button');
                d.className = 'wk-carousel-dot' + (i === 0 ? ' active' : '');
                d.onclick = () => go(i);
                dots.appendChild(d);
            }
        }

        function go(i) {
            current = ((i % total) + total) % total;
            track.style.transform = `translateX(-${current * 100}%)`;
            dots?.querySelectorAll('.wk-carousel-dot').forEach((d, j) => d.classList.toggle('active', j === current));
            if (counter) counter.textContent = current + 1;
        }

        wrap?.querySelector('.wk-carousel-prev')?.addEventListener('click', () => go(current - 1));
        wrap?.querySelector('.wk-carousel-next')?.addEventListener('click', () => go(current + 1));

        function start() { stop(); timer = setInterval(() => go(current + 1), 5000); }
        function stop() { if (timer) clearInterval(timer); }

        wrap?.addEventListener('mouseenter', stop);
        wrap?.addEventListener('mouseleave', start);
        wrap?.addEventListener('touchstart', stop, { passive: true });
        wrap?.addEventListener('touchend', () => setTimeout(start, 3000), { passive: true });

        start();
    },

    // ── Confetti ─────────────────────────────
    confetti() {
        const cols = ['#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#3b82f6'];
        const cvs = document.createElement('canvas');
        cvs.style.cssText = 'position:fixed;inset:0;z-index:9999;pointer-events:none';
        document.body.appendChild(cvs);
        const ctx = cvs.getContext('2d'); cvs.width = innerWidth; cvs.height = innerHeight;
        const p = Array.from({ length: 120 }, () => ({
            x: Math.random() * cvs.width, y: -Math.random() * cvs.height,
            w: 4 + Math.random() * 6, h: 3 + Math.random() * 4,
            color: cols[Math.floor(Math.random() * cols.length)],
            vy: 2 + Math.random() * 3, vx: (Math.random() - .5) * 2,
            rot: Math.random() * 360, vr: (Math.random() - .5) * 8, op: 1,
        }));
        let f = 0;
        (function draw() {
            ctx.clearRect(0, 0, cvs.width, cvs.height); let alive = false;
            p.forEach(c => { if (c.op <= 0) return; alive = true; c.x += c.vx; c.y += c.vy; c.rot += c.vr; c.vy += .04;
                if (c.y > cvs.height + 20) c.op -= .02; ctx.save(); ctx.translate(c.x, c.y); ctx.rotate(c.rot * Math.PI / 180);
                ctx.globalAlpha = Math.max(0, c.op); ctx.fillStyle = c.color; ctx.fillRect(-c.w / 2, -c.h / 2, c.w, c.h); ctx.restore(); });
            if (alive && f < 300) { f++; requestAnimationFrame(draw); } else cvs.remove();
        })();
    },

    price(a, s = null) {
        // Symbol comes from <meta name="wk-symbol"> (set by the layout from
        // store settings) — never hardcode a currency here.
        if (s == null) {
            const m = document.querySelector('meta[name="wk-symbol"]');
            s = m && m.content ? m.content : '₹';
        }
        return s + parseFloat(a || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    },
    esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => WhiskerStore.init());
/**
 * Header type-ahead search.
 *
 * Debounces keystrokes, asks /search/suggest for ranked matches, and renders
 * a dropdown. Stale responses are discarded so a slow request can't overwrite
 * results for a newer query. Keyboard: arrows move, Enter opens, Escape closes.
 */
const WhiskerSearch = {
    minChars: 1,
    debounceMs: 160,
    _timer: null,
    _seq: 0,
    _items: [],
    _active: -1,

    init() {
        this.input = document.getElementById('wkSearchInput');
        this.panel = document.getElementById('wkSearchResults');
        if (!this.input || !this.panel) return;

        this.input.addEventListener('input', () => this.onType());
        this.input.addEventListener('keydown', (e) => this.onKey(e));
        this.input.addEventListener('focus', () => { if (this._items.length) this.open(); });
        document.addEventListener('click', (e) => {
            if (!this.panel.contains(e.target) && e.target !== this.input) this.close();
        });
    },

    onType() {
        clearTimeout(this._timer);
        const q = this.input.value.trim();
        if (q.length < this.minChars) { this._items = []; this.close(); return; }
        this._timer = setTimeout(() => this.fetch(q), this.debounceMs);
    },

    async fetch(q) {
        const seq = ++this._seq;
        let data;
        try {
            const res = await fetch(WhiskerStore.base('search/suggest?q=' + encodeURIComponent(q)));
            data = await res.json();
        } catch (err) {
            return; // network hiccup — leave the last result up
        }
        if (seq !== this._seq) return;               // a newer keystroke already fired
        // Render outside the network try/catch so a rendering mistake surfaces
        // in the console instead of looking like a failed request.
        this.render(data, q);
    },

    render(data, q) {
        const results = data.results || [];
        this._items = results;
        this._active = -1;

        if (!results.length) {
            this.panel.innerHTML = '<div class="wk-search-empty">No products match “' + WhiskerStore.esc(q) + '”</div>';
            this.open();
            return;
        }

        const rows = results.map((r, i) => {
            const thumb = r.image
                ? '<img class="wk-search-thumb" src="' + WhiskerStore.esc(r.image) + '" alt="">'
                : '<div class="wk-search-thumb"></div>';
            const meta = r.in_stock
                ? WhiskerStore.esc(r.category || '')
                : '<span class="wk-search-oos">Out of stock</span>';
            return '<a class="wk-search-row" role="option" data-i="' + i + '" href="' + WhiskerStore.esc(r.url) + '">' +
                   thumb +
                   '<div class="wk-search-info">' +
                     '<div class="wk-search-name">' + this.highlight(r.name, q) + '</div>' +
                     '<div class="wk-search-meta">' + meta + '</div>' +
                   '</div>' +
                   '<div class="wk-search-price">' + WhiskerStore.esc(r.price) + '</div>' +
                   '</a>';
        }).join('');

        const more = data.more_url
            ? '<a class="wk-search-foot" href="' + WhiskerStore.esc(data.more_url) + '">See all results →</a>'
            : '';
        this.panel.innerHTML = rows + more;
        this.open();
    },

    // Escape first, then wrap matches — never inject the raw query.
    highlight(name, q) {
        const safe = WhiskerStore.esc(name);
        const needle = WhiskerStore.esc(q).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        if (!needle) return safe;
        try {
            return safe.replace(new RegExp('(' + needle + ')', 'ig'), '<mark>$1</mark>');
        } catch (e) { return safe; }
    },

    onKey(e) {
        const rows = this.panel.querySelectorAll('.wk-search-row');
        if (e.key === 'Escape') { this.close(); this.input.blur(); return; }
        if (e.key === 'Enter') {
            if (this._active >= 0 && rows[this._active]) { e.preventDefault(); rows[this._active].click(); }
            else if (this.input.value.trim()) {
                e.preventDefault();
                window.location.href = WhiskerStore.base('search?q=' + encodeURIComponent(this.input.value.trim()));
            }
            return;
        }
        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
        if (!rows.length) return;
        e.preventDefault();
        this._active += (e.key === 'ArrowDown' ? 1 : -1);
        if (this._active < 0) this._active = rows.length - 1;
        if (this._active >= rows.length) this._active = 0;
        rows.forEach((r, i) => r.classList.toggle('active', i === this._active));
        rows[this._active].scrollIntoView({ block: 'nearest' });
    },

    open()  { this.panel.hidden = false; this.input.setAttribute('aria-expanded', 'true'); },
    close() { this.panel.hidden = true;  this.input.setAttribute('aria-expanded', 'false'); this._active = -1; },
};

document.addEventListener('DOMContentLoaded', () => WhiskerSearch.init());


/**
 * Header navigation overflow.
 *
 * Categories are laid out on one row; whatever does not fit moves into a
 * "More" dropdown. A shop with a handful of categories shows them all, and a
 * shop with many still gets a tidy single-row header instead of a wrapped one.
 */
const WhiskerNav = {
    init() {
        this.nav  = document.querySelector('.wk-header-nav');
        this.more = document.getElementById('wkNavMore');
        this.menu = document.getElementById('wkNavMoreMenu');
        if (!this.nav || !this.more || !this.menu) return;

        // Everything except the overflow control itself, in source order.
        this.items = Array.prototype.filter.call(
            this.nav.children, (el) => el !== this.more
        );
        // Take control of wrapping now that we can measure.
        this.nav.style.flexWrap = 'nowrap';

        this.layout();
        let t = null;
        window.addEventListener('resize', () => {
            clearTimeout(t);
            t = setTimeout(() => this.layout(), 120);
        });
    },

    layout() {
        // Start from a clean slate so widening the window restores items.
        this.items.forEach((el) => { el.hidden = false; });
        this.menu.innerHTML = '';
        this.more.hidden = true;

        const fits = () => this.nav.scrollWidth <= this.nav.clientWidth + 1;
        if (fits()) return;

        // Move items from the end into the dropdown until the row fits.
        // Home / Shop All stay put — they are the primary links.
        const movable = this.items.slice(2);
        for (let i = movable.length - 1; i >= 0; i--) {
            const el = movable[i];
            el.hidden = true;
            this.more.hidden = false;
            const link = el.matches('a') ? el : el.querySelector('a');
            if (link) {
                const a = document.createElement('a');
                a.href = link.getAttribute('href');
                a.textContent = link.textContent.replace(/\s*▼\s*$/, '').trim();
                this.menu.insertBefore(a, this.menu.firstChild);
            }
            if (fits()) break;
        }
    },
};

document.addEventListener('DOMContentLoaded', () => WhiskerNav.init());
