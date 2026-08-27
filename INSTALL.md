# Whisker — Installation Guide

## Quick Install (5 minutes)

1. **Upload** — Extract the ZIP and upload all files to your web root
   (e.g. `public_html/` on cPanel or `/var/www/html/` on VPS)

2. **Permissions** — Ensure these directories are writable (chmod 755 or 775):
   - `config/`
   - `storage/`
   - `storage/uploads/`
   - `storage/uploads/products/`
   - `storage/cache/`
   - `storage/logs/`

3. **Run Installer** — Visit `https://yourdomain.com/install/` and follow the wizard

4. **Done!** — Access your admin panel at `https://yourdomain.com/admin`


## The Installer, Step by Step

| Step | What it asks |
|---|---|
| 1. Requirements | Checks PHP version, extensions and folder permissions |
| 2. Database | Host, database name, username, password |
| 3. Your Store | Store name, URL, currency, timezone, currency switcher, **cookie banner** |
| 4. Admin Account | Your login |
| 5. Payment Gateway | Optional — set one up now or skip |
| 6. Complete | Delete the `install/` folder when prompted |

### Cookie banner

Step 3 asks whether to show a cookie banner. Turn it on if you sell to the
EU or UK, where asking before setting optional cookies is required. Choosing
a European timezone switches it on for you; you can always change it back.

Shoppers see Reject optional, Customise and Accept all with equal weight,
and Customise lets them grant analytics and marketing separately. Cookies
needed for the cart, sign-in and checkout are never optional.

Everything about the banner — wording, policy link, which categories to
offer — lives in **Settings → Privacy** afterwards. Raise the policy version
there whenever your cookie policy changes and everyone is asked again.


## Server Requirements

- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite enabled
- PHP Extensions: PDO, pdo_mysql, mbstring, curl, openssl, json


## Compression & Caching

Whisker ships two `.htaccess` files that turn these on, and on Apache they
work with no setup at all:

- **`.htaccess`** — compresses HTML, CSS, JS, JSON, XML and SVG, and caches
  uploaded images for a week.
- **`assets/.htaccess`** — caches Whisker's own CSS, JS and images for a year.

Caching Whisker's assets that hard is safe because every link to them carries
`?v=<modification time>`. Editing a file changes its URL, so browsers pick up
the change on the very next page load — there is no cache to clear.

A typical storefront page drops from around 110 KB to roughly 30 KB with
compression on. It is the single biggest speed change you can make.

### Checking whether it is working

```bash
curl -s -o /dev/null -D - -H "Accept-Encoding: gzip" https://yourdomain.com/ | grep -i content-encoding
```

`content-encoding: gzip` (or `br`) means it is on. **No output at all means it
is off** — read on.

> Use `-D -` as above, not `curl -I`. `-I` sends a HEAD request, and because
> there is no body to compress Apache leaves the header off — so a working
> setup looks broken. Compare sizes if you want to see the difference for
> yourself:
>
> ```bash
> curl -s https://yourdomain.com/ | wc -c                              # uncompressed
> curl -s -H "Accept-Encoding: gzip" -o /dev/null -w '%{size_download}\n' https://yourdomain.com/
> ```

### If you cannot use .htaccess

`.htaccess` is ignored on nginx, and on Apache hosts that set
`AllowOverride None`. Whisker still runs; it just serves everything
uncompressed. Use whichever of these matches your server.

**nginx** — add to your `server` block, then `nginx -t && systemctl reload nginx`:

```nginx
gzip              on;
gzip_vary         on;
gzip_min_length   1024;
gzip_proxied      any;
gzip_types        text/plain text/css text/xml text/javascript
                  application/javascript application/json application/xml
                  application/rss+xml image/svg+xml;

# Whisker's assets carry ?v=<mtime>, so they can be cached hard.
location /assets/ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

**Apache with `AllowOverride None`** — either set `AllowOverride All` for the
directory, or copy the `# Compression` and `# Caching` blocks out of Whisker's
`.htaccess` into your `<VirtualHost>` or `<Directory>` block, where they work
unchanged.

**LiteSpeed / OpenLiteSpeed** — reads `.htaccess`, but only where
`Rewrite → Enable Rewrite` is on for the virtual host. Turn that on and the
shipped files apply as they do on Apache.

**cPanel** — most cPanel hosts enable compression already. If yours does not,
look for *Optimize Website* in the control panel and choose "Compress all
content".

**Shared hosting with none of the above** — ask your host to enable
`mod_deflate`. If they will not, the last resort is PHP-level compression:
add `zlib.output_compression = On` to a `.user.ini` file in the Whisker
folder. It is slower than letting the web server do it, but far better than
sending everything uncompressed.

> If your host puts a CDN or reverse proxy in front of the site
> (Cloudflare and similar), compression is usually applied there instead, and
> the `curl` check above will already show `gzip` or `br`.


## Subfolder Installation

If installing in a subfolder (e.g. `https://example.com/shop/`):
- Upload files to the subfolder
- In Step 3 of the installer, enter the full URL including the folder
  (e.g. `https://example.com/shop`)


## Re-running the Installer

The installer is locked after installation. To re-run:
1. Delete `storage/.installed`
2. Visit `https://yourdomain.com/install/`


## Database

The installer creates 25 tables with the `wk_` prefix:
- Admins, Settings, Categories, Products, Product Images, Product Variants
- Variant Groups, Variant Options, Variant Combos
- Customers, Customer Addresses, Carts, Cart Items
- Orders, Order Items, Invoices
- Payment Gateways, Payment Transactions, Coupons
- Shipping Carriers, Tickets, Ticket Replies
- Pages, Email Templates, Contact Messages


## CSV Import

Go to Admin → CSV Import to bulk-import products:

**All-in-One CSV** — Single file with `row_type` column:
- `category` rows: name, parent, description
- `product` rows: sku, name, category, price, stock, description
- `variant` rows: product_sku, variant_group, options (comma-separated)
- `combo` rows: product_sku, combo_sku, combo_price, combo_stock

Download sample CSVs from the import page.


## Payment Gateways

Configure in Admin → Payment Gateways:
- **Razorpay** — API Key + Secret (test mode supported)
- **Stripe** — Publishable Key + Secret Key
- **CCAvenue** — Merchant ID + Access Code + Working Key
- **NOWPayments** — API Key + IPN Secret


## SEO

Admin → SEO to configure:
- Site-wide meta title, description, keywords
- Title format (Page — Site Name, or Site Name — Page)
- Twitter/X handle, Google/Bing verification codes
- Sitemap.xml and robots.txt generation
- Schema.org structured data (JSON-LD) for products

Per-product and per-category SEO overrides are available in the edit forms.
Leave fields empty for auto-generation from product content.


## Troubleshooting

**500 Internal Server Error**
- Check `config/config.php` exists and has correct `base_url`
- Check `.htaccess` exists with rewrite rules
- Verify `mod_rewrite` is enabled: `a2enmod rewrite && service apache2 restart`

**CSS not loading / unstyled pages**
- Check `base_url` in `config/config.php` — it should be your exact domain
  (e.g. `https://yourdomain.com` not `https://yourdomain.com/install`)

**"Already Installed" but need to reinstall**
- Delete `storage/.installed` and visit `/install/` again

**Images not uploading**
- Check `storage/uploads/products/` is writable (chmod 775)
- Check PHP `upload_max_filesize` and `post_max_size` in php.ini (recommend 10M+)


## File Structure

```
whisker/
├── app/                    # Application code
│   ├── Controllers/        # Admin + Store controllers
│   ├── Middleware/          # Auth, CSRF, Guest middleware
│   └── Services/           # Currency, Email, Invoice, SEO, Variant
├── assets/                 # CSS, JS, images (publicly accessible)
├── config/                 # App config + database config + routes
├── core/                   # Framework core (Router, Database, Session, etc.)
├── install/                # Web installer
├── plugins/                # Payment gateway plugins
├── sql/                    # Database schema
├── storage/                # Uploads, cache, logs (writable)
├── views/                  # Admin + Store + Install views
├── .htaccess               # Apache rewrite + security rules
├── index.php               # Front controller
├── LICENSE                 # Whisker Free License
└── README.md               # Project documentation
```


## Support & Custom Development

📧 **mail@lohit.me**

Need payment integrations, custom themes, API connections, multi-vendor setup,
or deployment assistance? I offer paid development services.

---

Whisker v1.0.0 · Built by Lohit T · mail@lohit.me
