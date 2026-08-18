![Version](https://img.shields.io/badge/version-1.3.2-8b5cf6?style=for-the-badge)
![License](https://img.shields.io/badge/license-Whisker%20Free-f59e0b?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)

# 🐱 Whisker — Self-Hosted E-Commerce Cart

**A lightweight, self-hosted e-commerce platform for small businesses.**
Beautiful storefront. Powerful admin panel. Built-in AI chatbot. Zero monthly fees.

🌐 **[Live Demo](https://whisker.lohit.me)** · 📖 **[Documentation](https://github.com/WhiskerEnt/Whisker-Cart/wiki)** · 📧 **[mail@lohit.me](mailto:mail@lohit.me)**

> **Current release: v1.3.2.** &nbsp;·&nbsp; **v1.4.0 lands 22 August 2026** — the features below marked as new are part of it. A large release. It adds **refunds from the admin panel** (gateway-backed, with a reference and a receipt email), **reviews and ratings**, **customer questions and answers**, **shipping zones**, **shipping destination control**, **cookie consent**, **guest order tracking**, **instant product search**, and **pickup point / locker delivery** — plus an opt-in multi-currency switcher, admin-managed branding, and a rebuilt order confirmation page. Recommended for all installs.

---

## Why Whisker?

Most e-commerce platforms are either expensive (Shopify charges ₹2,000+/month), bloated (WooCommerce needs WordPress + dozens of plugins), or complex (Magento requires a DevOps team). Whisker is none of those.

Upload it to any ₹99/month shared hosting, run the 6-step installer, and you have a professional store running in 5 minutes. No Composer, no Node, no command line. Just PHP + MySQL.

**100+ stores deployed in the first month. One user processing 500+ orders/day.**

---

## What Makes Whisker Different

**🤖 Built-in AI Chatbot** — Your customers get instant answers without leaving the storefront. Order tracking, support tickets, policy lookups, product questions — all handled by a chatbot widget that works out of the box. No API keys, no third-party service, no monthly fees. No other lightweight cart has this.

**🌍 Global Tax Engine** — GST (India, with CGST+SGST/IGST split), VAT (all 27 EU countries + UK), US Sales Tax (all 50 states, nexus-based). Custom rates per country/state. Tax breakdown on invoices. Works automatically based on customer address.

**📦 Zero Dependencies** — No Composer, no Node, no framework. Pure PHP. Upload to any hosting and it works. The entire cart is ~290KB zipped.

**🇮🇳 India-First Payments** — Razorpay (UPI, cards, netbanking) is a first-class citizen, not a third-party plugin. Plus Stripe, CCAvenue, and crypto via NOWPayments.

**⭐ Social Proof Built In** — Star ratings with verified-purchase badges, and a question-and-answer thread on every product. Decide who may review: anyone, customers who bought it, or customers who received it. Questions come to you first — a question only ever appears on a product page together with your answer, so a sales page never shows an unanswered one. Ratings feed Google rich snippets automatically. No plugin, no third-party review service, no per-review fee.

**🔄 One-Click Updates** — Built-in auto-updater checks for new versions, creates a backup (code + database), verifies SHA256 integrity, applies updates, and runs database migrations automatically. Rollback to any previous version if something goes wrong.

**🔒 Security-First** — Full file-by-file security audit covering all 119 PHP files. Rate limiting on all forms, CSRF on every action, webhook signature verification on all payment gateways, GD image re-encoding to prevent upload attacks, atomic stock deduction to prevent overselling.

---

## Features

### Storefront
- Two homepage layouts: **Classic** (simple hero + grid) or **Modern** (hero banner, category grid, sale section, carousel)
- Responsive mobile-first design with 5 color themes
- Product catalog with nested categories and search
- **Shop page** with pagination, category filters, and sorting (price, name, date)
- Product variants (Size × Color) with individual SKU, price, stock, and images per combination
- Multi-currency display (30+ currencies via Frankfurter API with 6-hour cache)
- Shopping cart drawer with real-time updates
- Guest checkout + customer accounts with saved addresses
- **Instant product search** — type-ahead dropdown with relevance ranking across name, SKU and category
- **Pickup point / locker delivery** — customers collect from admin-configured pickup locations (InPost, Mondial Relay, DHL Packstation-style), with per-location fee override and destination-based tax
- **Ratings and reviews** — star ratings with a score breakdown, verified-purchase badges, and public replies from the store. The shop chooses who may review: anyone, customers who bought the product, or customers who received it
- **Questions and answers** — a shopper asks about a product, the shop answers, and the pair appears on the page for the next person with the same question
- **Guest order tracking** — order number plus email, no account needed
- **Shipping destinations** — checkout only offers the countries the shop posts to, and refuses any other
- **Cookie consent** — optional banner with per-category choices (necessary, analytics, marketing), an equally easy refusal, and a recorded answer
- Coupon codes (percentage & fixed, min order, usage limits, expiry)
- Contact form with admin email notifications
- **AI chatbot widget** — order tracking, ticket creation, FAQ, policy lookups
- Image carousel with thumbnails

### Tax Engine
- **India GST** — CGST + SGST (intra-state) or IGST (inter-state) at 18%, 12%, or 5%
- **EU VAT** — per-country rates for all 27 member states
- **UK VAT** — 20% standard, 5% reduced
- **US Sales Tax** — state-level rates, nexus-based
- Custom tax rates per country/state via admin panel
- Per-product tax class (standard, reduced, zero, exempt)
- Tax breakdown on invoices (shows each tax line individually)
- Automatic fallback to global rate for unlisted countries

### Admin Panel
- Dashboard with revenue charts, order stats, and trend data
- Product management with drag-drop image upload
- Category management (nested, with sort order)
- Order management with status tracking, shipping info, and tracking numbers
- **Refunds** — full or partial, issued through the payment gateway, with a `RFN-…` reference alongside the gateway's own refund id and an email to the customer. A call the gateway never confirmed is held as unresolved rather than reported as failed, so a timeout cannot lead to refunding twice
- **Review moderation** — approve, reject, unpublish or reply, with a waiting count in the sidebar
- **Question queue** — write an answer and publish it in one step; nothing reaches a product page unanswered
- **Shipping zones** — different rates for different countries, by flat rate, free, free over a threshold, per item, or by weight
- Invoice/receipt generation with tax breakdown, store logo, address, and GSTIN/VAT number
- Customer management with order history and spend totals
- Coupon system with usage tracking
- **CSV import** — categories, products, and variants in a single file
- Email template editor with variable placeholders, seeded with working templates for welcome, order confirmation, payment pending, cancellation, payment receipt, refund and shipping mail
- Page/policy editor (Privacy, Terms, About — any custom page)
- **Abandoned cart tracking** with email reminders
- **Support ticket system** with admin replies and status tracking
- SEO settings with live Google preview
- Sitemap & robots.txt generator
- Shipping carrier & rate configuration
- **Shipping destination control** — domestic only, a chosen list of countries, or everywhere
- Pickup point / locker management (locations, fees, opening hours)
- **Auto-updater** with backup, SHA256 verification, auto-migrations, and one-click rollback
- **Homepage style picker** — switch between Classic and Modern layouts
- **Admin forgot password** — email-based reset with rate limiting
- **Low stock email alerts** — daily notification when products hit ≤5 stock

### Payments

**Setting up a gateway.** Open **Admin → Payment Gateways**, expand **Configure** on the gateway you want, paste its credentials, and press **Test connection** — Whisker calls the provider and tells you whether the keys are accepted before you take a single order. Tick **Test Mode** while you are trying things out; the test credentials are stored separately from the live ones.

**Webhook URL.** Each gateway card shows the exact URL to paste into the provider's dashboard, with a copy button:

```
https://yourstore.com/webhook/{gateway}/callback
```

| Gateway | Where the keys live | What to subscribe to |
|---|---|---|
| **Stripe** | Developers → API keys (or the Workbench panel) | Add a webhook endpoint for `checkout.session.completed`; paste the `whsec_…` signing secret into Whisker |
| **Razorpay** | Account & Settings → API Keys | Settings → Webhooks, subscribe to `payment.captured`, and use the same secret in both places |
| **CCAvenue** | Merchant panel → Settings → API Keys | Set the URL above as your Response URL |
| **NOWPayments** | Settings → API keys | Settings → IPN — set the URL above as the callback and paste the IPN secret |

Whisker refuses to process a webhook when its secret is missing, rather than trusting an unsigned request, so payments will not be confirmed until the secret is saved.


- **Razorpay** — UPI, Cards, Netbanking (webhook signature verified)
- **Stripe** — 150+ countries (webhook signature + replay protection)
- **CCAvenue** — Indian payment gateway
- **NOWPayments** — Bitcoin, Ethereum, 300+ cryptocurrencies (webhook signature verified)
- Payment amount verification against order total
- Idempotent webhook processing (no duplicate order credits)

### SEO
- Auto-generated meta tags (title, description, keywords)
- Open Graph + Twitter Cards for social sharing
- JSON-LD product schema for Google rich snippets
- Aggregate rating in product schema, published only once real reviews exist
- Sitemap.xml generator
- Robots.txt generator
- Per-product and per-category SEO overrides
- Google/Bing verification meta tags

### Security
- Bcrypt password hashing (cost 12)
- CSRF protection on all forms (62 verification points)
- 100% PDO prepared statements
- Session fingerprinting (IP + User-Agent) with 15-min timeout
- Rate limiting on login, registration, forgot password, contact, chatbot, coupons, password change, order tracking, reviews and questions (15 endpoints)
- XSS output escaping via `View::e()` plus HtmlSanitizer for admin-authored HTML
- File upload validation (MIME + extension whitelist + GD re-encoding)
- Content-Security-Policy, HSTS, X-Frame-Options, X-Content-Type-Options headers
- PHP execution blocked in uploads directory
- Webhook signature verification on all payment gateways (HMAC + timing-safe compare + replay protection where the gateway supports it)
- SSRF protection on admin-controlled outbound calls (port whitelist + private-IP rejection)
- Atomic stock deduction (prevents overselling under concurrency)
- Atomic compare-and-set on order cancellation (no double restocks)
- Non-blocking checkout emails on PHP-FPM servers
- Timing-safe login (prevents user enumeration)
- URL-encoded slugs in all templates (prevents slug injection)
- Update download host check with strict dot-boundary validation
- Refunds are POST-only, CSRF-checked before any money moves, capped at the order total, and idempotent per attempt
- Database errors are logged rather than shown to shoppers, so table and constraint names never reach a storefront page
- Shipping destinations are enforced server-side, not only in the checkout dropdown

### Performance
- **Settings cache** — all settings loaded once per request (1 query instead of 10+)
- **Currency cache** — exchange rates cached 6 hours
- **Tax rate cache** — loaded once per request from DB
- **Atomic stock** — `WHERE stock_quantity >= ?` prevents race conditions
- **Non-blocking emails** — `fastcgi_finish_request()` on PHP-FPM
- Runs on shared hosting, handles 500+ orders/day on a decent VPS

---

## Requirements

| Requirement | Minimum |
|-------------|---------|
| PHP | 8.0+ |
| MySQL | 5.7+ / MariaDB 10.3+ |
| Web Server | Apache with `mod_rewrite` |
| PHP Extensions | PDO, pdo_mysql, mbstring, curl, openssl, json, GD |

---

## Installation

1. Download the latest release ZIP
2. Extract and upload to your web server
3. Visit `https://yourdomain.com/install/` in your browser
4. Follow the 6-step wizard:
   - **Step 1:** Environment check (PHP version, extensions, permissions)
   - **Step 2:** Database connection (with live test button)
   - **Step 3:** Store name, URL, currency, timezone
   - **Step 4:** Admin account (password strength enforced)
   - **Step 5:** Payment gateway setup (optional, configure later)
   - **Step 6:** Done! 🎉
5. Log into your admin panel at `https://yourdomain.com/admin`

No command line. No Composer. No SSH. Works on any cPanel hosting.

---

## Updating

Updates are handled from the admin dashboard:

1. A notification banner appears when a new version is available
2. Choose your database backup level (schema only, full dump, or none)
3. Click **Update Now** — Whisker backs up your files, downloads the update, verifies integrity, and applies it
4. Database migrations run automatically as part of the update step
5. If anything goes wrong, click **Restore** to rollback to the previous version

Your config files, database credentials, and product images are never touched during updates.

See the [Upgrading wiki page](https://github.com/WhiskerEnt/Whisker-Cart/wiki/Upgrading) for manual upgrade instructions.

---

## The Numbers

- 137 PHP files, 32 database tables
- 0 external dependencies in the shipped product
- 145 automated tests, run on every push
- 5 minute install
- 100+ stores deployed
- Works on PHP 8.0+ and any shared hosting

---

## Documentation

📖 **[Full documentation on the Wiki](https://github.com/WhiskerEnt/Whisker-Cart/wiki)**

- [Installation Guide](https://github.com/WhiskerEnt/Whisker-Cart/wiki/Installation-Guide)
- [Upgrading](https://github.com/WhiskerEnt/Whisker-Cart/wiki/Upgrading)
- [Auto-Updater](https://github.com/WhiskerEnt/Whisker-Cart/wiki/Auto-Updater)
- [Configuration & Settings](https://github.com/WhiskerEnt/Whisker-Cart/wiki/Configuration-and-Settings)
- [Product Management](https://github.com/WhiskerEnt/Whisker-Cart/wiki/Product-Management)
- [Payment Gateway Setup](https://github.com/WhiskerEnt/Whisker-Cart/wiki/Payment-Gateway-Setup)
- [Refunds](https://github.com/WhiskerEnt/Whisker-Cart/wiki/Refunds)
- [Shipping and Delivery](https://github.com/WhiskerEnt/Whisker-Cart/wiki/Shipping-and-Delivery)
- [Reviews and Questions](https://github.com/WhiskerEnt/Whisker-Cart/wiki/Reviews-and-Questions)
- [Email and Templates](https://github.com/WhiskerEnt/Whisker-Cart/wiki/Email-and-Templates)
- [Privacy and Cookie Consent](https://github.com/WhiskerEnt/Whisker-Cart/wiki/Privacy-and-Cookie-Consent)
- [Security](https://github.com/WhiskerEnt/Whisker-Cart/wiki/Security)
- [Performance](https://github.com/WhiskerEnt/Whisker-Cart/wiki/Performance)

---

## Premium (Coming Soon)

- Revenue analytics dashboard
- REST API
- Advanced admin roles and audit log
- Upsells and cross-sells
- POS for in-store sales
- Multi-vendor marketplace

---

## Custom Development

Need custom features, payment integrations, theme customization, or deployment help?

📧 **Contact: [mail@lohit.me](mailto:mail@lohit.me)**

---

## License

Whisker Free Edition is released under the Whisker Free License v1.0. Free to use for personal and commercial projects. Redistribution is not permitted. See [LICENSE](LICENSE) for full terms.

---

**🐱 Whisker v1.3.2** · Built by Lohit T
📧 [mail@lohit.me](mailto:mail@lohit.me)
