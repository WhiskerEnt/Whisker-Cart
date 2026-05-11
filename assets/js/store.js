/**
 * WHISKER Store — Frontend JS
 * Cart drawer, AJAX cart, carousel, confetti
 */
const WhiskerStore = {
    _base: null,

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

            const res = await fetch(this.base('cart/add'), { method: 'POST', body: form });
            const data = await res.json();

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
                this.updateTotal(data.subtotal);
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
                    <div class="wk-cart-item-price">${this.price(i.unit_price * i.quantity)}</div>
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
        const el = document.querySelector('.wk-cart-total-value'); if (el) el.textContent = this.price(s);
    },

    async updateQty(id, qty) {
        const f = new FormData(); f.append('item_id', id); f.append('quantity', Math.max(0, qty));
        await fetch(this.base(qty <= 0 ? 'cart/remove' : 'cart/update'), { method: 'POST', body: f });
        this.loadCart();
    },
    async removeItem(id) {
        const el = document.querySelector(`[data-item="${id}"]`);
        if (el) { el.style.transition = 'all .3s'; el.style.opacity = '0'; el.style.transform = 'translateX(40px)'; }
        setTimeout(async () => {
            const f = new FormData(); f.append('item_id', id);
            await fetch(this.base('cart/remove'), { method: 'POST', body: f });
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

    price(a, s = '₹') { return s + parseFloat(a || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); },
    esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => WhiskerStore.init());