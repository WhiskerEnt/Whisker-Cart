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

        const icons = { success: '✓', error: '✗', warning: '⚠', info: 'ℹ' };
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
        toast.innerHTML = `<span style="font-size:16px">${icons[type]}</span> ${message}`;
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