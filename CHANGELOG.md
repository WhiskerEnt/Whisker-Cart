# Changelog

All notable changes to Whisker are recorded here. Versions follow
[semantic versioning](https://semver.org/).

## v1.4.1 (10 September 2026)

Database migrations run automatically when you next open the admin dashboard.
No manual SQL is needed.

### The cart

- **The cart now has a page of its own** at `/cart`. Before this it existed
  only as a drawer, which could not be linked to, shared, or returned to with
  the back button. Recovery emails now have somewhere to send people.
- **A signed-in customer's cart follows them.** Previously a cart belonged to
  one browser session, so adding something on a phone and then signing in on a
  laptop showed an empty basket. Carts from two devices are combined, and the
  same product on both sides becomes one line with a larger quantity.
- **Delivery threshold progress.** If a shipping zone sets a free-delivery
  threshold, the cart says how far the basket is from it. Shops without a
  threshold configured show nothing.
- **Suggestions on the cart page**, drawn from the categories already in the
  basket. Never something already in it, and never something out of stock.

### Checkout

- **One order per attempt.** Pressing Pay twice, a retried request, or a back
  button no longer creates a second order. The form carries a token, the order
  records it under a unique index, and a repeated attempt is shown the order it
  already placed. With a payment gateway attached this prevented a second
  charge.
- **The Pay button goes quiet on the first press** and says what is happening,
  so there is a reason not to press it again. It comes back if the page returns
  from browser history.
- **Delivery notes.** An optional box for gate codes, safe places to leave a
  parcel, and when somebody is usually in. Shown to whoever packs the order and
  back to the customer on their own order.
- **Terms acceptance.** Shops that publish a terms page now ask shoppers to
  agree before an order is taken, and record when they did. Shops with no terms
  page are unaffected, because a tick box pointing at a missing page is worse
  than none.
- **A mistyped email address no longer goes through.** Checkout used to blank
  an address it could not parse and take the order anyway, leaving nowhere to
  send the confirmation.

### Products and the storefront

- **Uploaded images are resized and converted.** Every upload is brought down
  to at most 1600px and given a WebP copy alongside the original. Across a
  typical catalogue that is roughly a third of the previous transfer size. The
  original stays, so browsers without WebP support lose nothing.
- **Image dimensions are recorded and written into the page**, so the browser
  holds a picture's space open before it arrives instead of shifting the page
  when it loads.
- **Only what is on screen loads eagerly.** The rest waits until it is scrolled
  towards.
- **Low stock is stated.** Products already carried a low-stock threshold that
  only the admin ever saw. Product pages now say "Only 3 left" when stock is at
  or below it.
- **Breadcrumbs are shown.** The trail was already published as structured data
  for search engines and never displayed to anyone reading the page.
- **Product images open larger**, by click or by keyboard.
- **Recently viewed products** appear on product pages. Kept in the browser, so
  no data leaves the device and no consent is required. Prices are deliberately
  not remembered, because a remembered price goes stale.
- **A country code picker on every phone field**, covering all 249 countries.
  Numbers are stored with the code in front. Codes shared by several countries
  resolve consistently, and the longest match wins, so a +1264 number stays in
  Anguilla.
- **Live checking on email and phone fields**, using the same rules the server
  applies. Common domain misspellings are offered a correction, which is advice
  rather than a refusal.

### Accounts

- **Customers can download their own invoice.** It existed already but only the
  shopkeeper could reach it.
- **Reorder.** A past order can be put back in the basket at today's prices and
  today's stock. Anything no longer for sale is named rather than dropped
  quietly.
- **Sign in and sign up sit under one account menu** in the header, in the same
  place whether or not you are signed in.
- **A live password strength meter** on sign-up, weighting length above
  variety.

### Admin

- **A list of the pages a shop is expected to have**, on the Pages screen, with
  what each is for and which ones other features depend on. Starting one gives
  a draft with the decisions left as prompts. Nothing writes a policy on your
  behalf.
- **The Pages sidebar entry shows a count** of recommended pages that are
  missing or still drafts.
- **Contact bar in the storefront**, with position and collapse behaviour set
  from the admin.
- **Cancellation window and automatic refunds** are configurable, including
  whether the deadline is shown to customers.
- **Abandoned cart recovery** with configurable timing, a reminder schedule, an
  optional coupon, and an unsubscribe link on every reminder.
- **Exit and dwell prompts** for capturing an email or phone number, with an
  optional discount.
- **Product FAQ** on the product form and in bulk upload.

### Speed

- **Compression and caching are on by default.** The shipped `.htaccess` turns
  on gzip and brotli for text and caches assets for a year. A storefront page
  measured 111 KB before and 19 KB after. The installer writes the same rules
  when it generates an `.htaccess`, so a shop installed onto a bare server is
  not left slower.
- **The web font no longer blocks the first paint.** It was being requested
  twice, once from the page head and again by an `@import` inside the
  stylesheet, which cost a second round trip the browser could not start early.
- **Assets are cached for a year** and every asset URL carries the file's
  modification time, so an edited file is a new URL and there is no cache to
  clear.

### Mobile

- **Every page collapses to one column.** Eighteen layouts had their column
  count written onto the element, where a media query could not reach it. The
  product page in particular showed an 80px picture beside a column of text.
- **The carousel can be swiped.** Before this the arrows were the only way
  through it.
- **The header takes one row instead of three**, and the contact bar folds into
  its button, returning about 40px of usable width on a 375px screen.
- **Two products per row** on a phone rather than one.

### Accessibility

- Cart changes are announced, so screen reader users get confirmation that
  adding to the cart did something.
- A skip link, and a main landmark for it to reach.
- Carousel dots are named and large enough to tap.
- Accent colours used for text were below the 4.5:1 contrast floor in every
  theme. Each theme now carries a darker tone for text, leaving fill colours
  alone.

### Security and correctness

- `.git`, `tests/`, `vendor/` and `node_modules/` are blocked from the web. The
  existing hidden-file rule never covered `.git` because it matches on a file's
  own name, and `.git/config` is called `config`.
- The generated `.htaccess` now matches the shipped one. Its copy had fallen
  behind and was missing the content security policy, HSTS, and most of the
  upload restrictions.
- The content security policy gains `object-src`, `base-uri` and
  `frame-ancestors`.
- Uploaded variant swatches are redrawn on upload like every other image, which
  is what removes anything hidden inside the file.
- Password rules are enforced on the server as well as in the browser. The form
  asked for a special character and the server did not.

### Fixed

- Category dropdown menus in the header did not open. A clip applied while the
  navigation measured itself was cutting them off.
- Order cancellation from the admin was a plain status write, so stock was not
  returned and customer totals were not corrected. Both sides now cancel
  through one service.
- A fresh install was missing six tables and eleven columns that only existed
  in migrations. Reviews, questions, refunds, shipping zones, lead capture and
  cart recovery were all affected until an admin opened the dashboard.
- The rollback banner in the admin was styled for a dark theme that does not
  exist, leaving its heading invisible.
- The storefront credit in the footer linked to github.com rather than to the
  project.

---

## v1.4.0 (22 August 2026)

Cookie consent, refunds, product reviews, customer questions, shipping zones
and guest order tracking. See the
[release notes](https://github.com/WhiskerEnt/Whisker-Cart/releases) for
detail.
