/**
 * WHISKER Admin — JavaScript
 */
const Whisker = {

    csrfToken: null,

    init() {
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        this.hideLoader();
        this.initFlashDismiss();
        this.initMicroInteractions();
    },

    // ── Pictures, before they are sent ────────────
    // A photo straight off a phone is often 10 to 15 MB, and many hosts take
    // 2 MB per upload. Anything larger than the server accepts, or wider than
    // the shop keeps, is redrawn here first: longest edge at most 2400px,
    // saved as WebP at a quality the eye cannot tell from the original. The
    // browser has already turned it the right way up. Smaller files go as
    // they are, and the server does the rest.
    MAX_EDGE: 2400,

    maxUploadBytes() {
        const n = parseInt(document.querySelector('meta[name="wk-max-upload"]')?.content || '', 10);
        return n > 0 ? n : 2 * 1024 * 1024;
    },

    async prepareImage(file) {
        const limit = this.maxUploadBytes();
        // GIFs may be animated, and a canvas would keep only the first frame.
        if (!/^image\/(jpeg|png|webp)$/.test(file.type) || typeof createImageBitmap !== 'function') return file;

        let bitmap;
        try { bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' }); }
        catch (e) { return file; }

        const longest = Math.max(bitmap.width, bitmap.height);
        if (longest <= this.MAX_EDGE && file.size <= limit) { bitmap.close?.(); return file; }

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const encode = (type, quality) => new Promise((done) => canvas.toBlob(done, type, quality));
        const draw = (edge) => {
            const scale = Math.min(1, edge / longest);
            canvas.width = Math.max(1, Math.round(bitmap.width * scale));
            canvas.height = Math.max(1, Math.round(bitmap.height * scale));
            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        };

        // Highest quality first; step down only as far as the server needs.
        const attempts = [[this.MAX_EDGE, 0.95], [this.MAX_EDGE, 0.9], [this.MAX_EDGE, 0.85], [2000, 0.85], [1600, 0.85]];
        let blob = null, drawnAt = 0;
        for (const [edge, quality] of attempts) {
            if (edge !== drawnAt) { draw(edge); drawnAt = edge; }
            blob = await encode('image/webp', quality);
            // A browser that cannot write WebP hands back a PNG instead.
            if (blob && blob.type !== 'image/webp') blob = await encode('image/jpeg', quality);
            if (blob && blob.size <= limit) break;
        }
        bitmap.close?.();
        if (!blob) return file;

        const ext = blob.type === 'image/webp' ? 'webp' : 'jpg';
        const name = (file.name.replace(/\.[^.]+$/, '') || 'photo') + '.' + ext;
        return new File([blob], name, { type: blob.type, lastModified: Date.now() });
    },

    /** "15.2 MB", for messages. */
    sizeLabel(bytes) {
        return bytes >= 1048576 ? (bytes / 1048576).toFixed(1).replace(/\.0$/, '') + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
    },

    // ── Page Loader ───────────────────────────────
    hideLoader() {
        const loader = document.getElementById('pageLoader');
        if (loader) {
            setTimeout(() => loader.classList.add('hidden'), 500);
        }
    },

    // ── Flash auto-dismiss ────────────────────────
    initFlashDismiss() {
        document.querySelectorAll('.wk-flash').forEach(el => {
            setTimeout(() => {
                el.style.transition = 'opacity .3s, transform .3s';
                el.style.opacity = '0';
                el.style.transform = 'translateY(-8px)';
                setTimeout(() => el.remove(), 300);
            }, 5000);
        });
    },

    // ── Micro-interactions ────────────────────────
    initMicroInteractions() {
        // Button press effect
        document.querySelectorAll('.wk-btn').forEach(btn => {
            btn.addEventListener('mousedown', () => {
                btn.style.transform = 'scale(.97)';
            });
            btn.addEventListener('mouseup', () => {
                btn.style.transform = '';
            });
            btn.addEventListener('mouseleave', () => {
                btn.style.transform = '';
            });
        });

        // Stat card hover ripple
        document.querySelectorAll('.wk-stat').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-2px)';
            });
            card.addEventListener('mouseleave', () => {
                card.style.transform = '';
            });
        });
    },

    // ── Toast Notification ────────────────────────
    toast(message, type = 'success') {
        let container = document.getElementById('wkToasts');
        if (!container) {
            container = document.createElement('div');
            container.id = 'wkToasts';
            container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9000;display:flex;flex-direction:column;gap:10px';
            document.body.appendChild(container);
        }

        const colors = {
            success: { bg: '#d1fae5', color: '#10b981', border: '#10b981' },
            error:   { bg: '#fee2e2', color: '#ef4444', border: '#ef4444' },
            warning: { bg: '#fef3c7', color: '#f59e0b', border: '#f59e0b' },
            info:    { bg: '#dbeafe', color: '#3b82f6', border: '#3b82f6' },
        };
        const c = colors[type] || colors.success;

        const toast = document.createElement('div');
        toast.style.cssText = `
            background:${c.bg}; color:${c.color};
            border-left:3px solid ${c.border};
            padding:14px 18px; border-radius:8px;
            min-width:280px; font-family:'Nunito',sans-serif;
            font-size:14px; font-weight:700;
            box-shadow:0 8px 30px rgba(0,0,0,.08);
            display:flex; align-items:center; gap:10px;
            animation:toastIn .3s ease;
        `;
        const iconTpl = document.getElementById('wk-icon-toast-' + (type in colors ? type : 'success'));
        toast.innerHTML = `<span style="display:inline-flex">${iconTpl ? iconTpl.innerHTML : ''}</span> ${message}`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.transition = 'opacity .3s, transform .3s';
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(30px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    },

    // ── AJAX Helper ───────────────────────────────
    async request(url, options = {}) {
        const defaults = {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        };
        if (options.body && !(options.body instanceof FormData)) {
            defaults.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }
        try {
            const res = await fetch(url, { ...defaults, ...options });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Request failed');
            return data;
        } catch (err) {
            this.toast(err.message, 'error');
            throw err;
        }
    },

    // ── Confirm dialog ────────────────────────────
    // NOTE: no JS price formatter here on purpose — admin prices are rendered
    // server-side via \Core\View::price(), which uses the store's configured
    // currency symbol. (A dead price() helper with a hardcoded ₹ default was
    // removed in 1.3.2.)
    confirm(message) {
        return window.confirm(message);
    }
};

// Add toast animation
const style = document.createElement('style');
style.textContent = `
    @keyframes toastIn { from { opacity:0; transform:translateX(30px); } to { opacity:1; transform:translateX(0); } }
`;
document.head.appendChild(style);

// Boot
document.addEventListener('DOMContentLoaded', () => Whisker.init());