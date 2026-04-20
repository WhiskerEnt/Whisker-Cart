![Version](https://img.shields.io/badge/version-1.1.0-8b5cf6?style=for-the-badge)
![License](https://img.shields.io/badge/license-Whisker%20Free-f59e0b?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)

# 🐱 Whisker — Self-Hosted E-Commerce Cart

**A lightweight, self-hosted e-commerce platform for small businesses.**
Beautiful storefront. Powerful admin panel. Built-in AI chatbot. Zero monthly fees.

🌐 **[Live Demo](https://whisker.lohit.me)** · 📖 **[Documentation](https://github.com/WhiskerEnt/Whisker-Cart/wiki)** · 📧 **[mail@lohit.me](mailto:mail@lohit.me)**

---

## Why Whisker?

Most e-commerce platforms are either expensive (Shopify charges ₹2,000+/month), bloated (WooCommerce needs WordPress + dozens of plugins), or complex (Magento requires a DevOps team). Whisker is none of those.

Upload it to any ₹99/month shared hosting, run the 6-step installer, and you have a professional store running in 5 minutes. No Composer, no Node, no command line. Just PHP + MySQL.

**100+ stores deployed in the first month. One user processing 500+ orders/day.**

---

## What Makes Whisker Different

**🤖 Built-in AI Chatbot** — Your customers get instant answers without leaving the storefront. Order tracking, support tickets, policy lookups, product questions — all handled by a chatbot widget that works out of the box. No API keys, no third-party service, no monthly fees. No other lightweight cart has this.

**📦 Zero Dependencies** — No Composer, no Node, no framework. Pure PHP. Upload to any hosting and it works. The entire cart is ~270KB zipped.

**🇮🇳 India-First Payments** — Razorpay (UPI, cards, netbanking) is a first-class citizen, not a third-party plugin. Plus Stripe, CCAvenue, and crypto via NOWPayments.

**🔄 One-Click Updates** — Built-in auto-updater checks for new versions, creates a backup (code + database), verifies SHA256 integrity, and applies updates from your admin dashboard. Rollback to any previous version if something goes wrong.

**🔒 Security-First** — 45-point security audit. Rate limiting on all forms, CSRF on every action, webhook signature verification on all payment gateways, GD image re-encoding to prevent upload attacks, atomic stock deduction to prevent overselling. Not bolted on later — built from the start.

---

## Features

### Storefront
- Responsive mobile-first design with 5 color themes
- Product catalog with nested categories and search
- **Shop page** with pagination, category filters, and sorting (price, name, date)
- Product variants (Size × Color) with individual SKU, price, stock, and images per combination
- Multi-currency display (30+ currencies via Frankfurter API with 6-hour cache)
- Shopping cart drawer with real-time updates
- Guest checkout + customer accounts with saved addresses
- Coupon codes (percentage & fixed, min order, usage limits, expiry)
- Contact form with admin email notifications
- **AI chatbot widget** — order tracking, ticket creation, FAQ, policy lookups
- Image carousel with thumbnails

### Admin Panel
- Dashboard with revenue charts, order stats, and trend data
- Product management with drag-drop image upload
- Category management (nested, with sort order)
- Order management with status tracking, shipping info, and tracking numbers
- Invoice/receipt PDF generation
- Customer management with order history and spend totals
- Coupon system with usage tracking
- **CSV import** — categories, products, and variants in a single file
- Email template editor with variable placeholders
- Page/policy editor (Privacy, Terms, About — any custom page)
- **Abandoned cart tracking** with email reminders
- **Support ticket system** with admin replies and status tracking
- SEO settings with live Google preview
- Sitemap & robots.txt generator
- Shipping carrier & rate configuration
- **Auto-updater** with backup, SHA256 verification, and one-click rollback

### Payments
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
- Sitemap.xml generator
- Robots.txt generator
- Per-product and per-category SEO overrides
- Google/Bing verification meta tags

### Security
- Bcrypt password hashing (cost 12)
- CSRF protection on all forms (41 verification points)
- 100% PDO prepared statements
- Session fingerprinting (IP + User-Agent) with 15-min timeout
- Rate limiting on login, registration, forgot password, contact, chatbot, coupons (7 endpoints)
- XSS output escaping via `View::e()`
- File upload validation (MIME + extension whitelist + GD re-encoding)
- Content-Security-Policy, HSTS, X-Frame-Options, X-Content-Type-Options headers
- PHP execution blocked in uploads directory
- Webhook signature verification on all payment gateways
- Atomic stock deduction (prevents overselling under concurrency)
- Non-blocking checkout emails on PHP-FPM servers
- Timing-safe login (prevents user enumeration)

### Performance
- **Settings cache** — all settings loaded once per request (1 query instead of 10+)
- **Currency cache** — exchange rates cached 6 hours
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

Starting with v1.1.0, updates are handled from the admin dashboard:

1. A notification banner appears when a new version is available
2. Choose your database backup level (schema only, full dump, or none)
3. Click **Update Now** — Whisker backs up your files, downloads the update, verifies integrity, and applies it
4. If anything goes wrong, click **Restore** to rollback to the previous version

Your config files, database credentials, and product images are never touched during updates.

See the [Upgrading wiki page](https://github.com/WhiskerEnt/Whisker-Cart/wiki/Upgrading) for manual upgrade instructions.

---

## The Numbers

- ~130 files, 25 database tables
- 0 external dependencies
- 270KB zipped
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
- [Security](https://github.com/WhiskerEnt/Whisker-Cart/wiki/Security)
- [Performance](https://github.com/WhiskerEnt/Whisker-Cart/wiki/Performance)

---

## Premium (Coming Soon)

- Tax engine (GST, VAT, Sales Tax)
- Abandoned cart recovery emails
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

**🐱 Whisker v1.1.0** · Built by Lohit T
📧 [mail@lohit.me](mailto:mail@lohit.me)
