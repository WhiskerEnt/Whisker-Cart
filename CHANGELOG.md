# Changelog

All notable changes to Whisker are recorded here. Versions follow
[semantic versioning](https://semver.org/).

## v1.4.2 (15 November 2026)

Database migrations run automatically when you next open the admin dashboard.
No manual SQL is needed. Existing shops keep the signup form they had.

### The homepage

- **The homepage slider is never empty.** It used to show only products
  ticked Featured, a box that was off by default and said nothing about the
  homepage, so a shop that just added products got a plain banner with its
  name on it. The slider now fills its places from what the shop already
  has: products you chose, then best-sellers, then products added in the
  last 60 days, then products on sale, then a pick that changes once a day.
  Products with a photo come first, and nothing out of stock is picked
  automatically. Choose six or more yourself and only your choices appear.
- **"Featured" is now "Show in homepage slider"**, explains what it does,
  and comes first on the product form. The product list and the homepage
  settings say where the slider's products are coming from, and when you
  are signed in, the storefront shows the same in a note shoppers never see.
- **The Classic homepage's featured row works.** Its carousel had no styles
  and showed one product at a time across the whole page; it is now a row
  of product cards, the same as the Modern homepage's sale row.

### The contact button

- **Redesigned, and moved to a bottom corner.** The column of round icons
  sat halfway down the side of the screen, on top of the homepage slider's
  arrow, and every page gave up 72 pixels of width to keep clear of it. It
  is now a sheet of frosted glass in the bottom corner that stays put as
  the page scrolls. Shops that show every channel get a small bar of tiles,
  each naming itself on hover; shops that collapse it get a "Contact us"
  button opening a card with a row per channel. On a phone it is always the
  one button, so it never covers the page, and the button folds down to its
  icon while the shopper scrolls down.
- It steps out of the way of the cookie banner, the cart and the chat
  window, and on the right-hand side it waits above the chat button. The
  glass takes the shop's own colours, so it suits every theme.

### Product pictures

- **Photos of 10 to 15 MB upload.** Many hosts accept 2 MB per upload, and
  Whisker's own limit was 5 MB; on the product edit page a larger file was
  skipped without a word. Large photos are now made ready in your browser
  before they are sent: brought down to 2400 pixels on the longest side and
  saved as WebP at a quality the eye cannot tell from the original. A 16 MB
  photo arrives as about 1 MB. When a picture still cannot be accepted, the
  page says why and what the server's limit is.
- **Sharper pictures.** Pictures are kept at up to 2400 pixels instead of
  1600, so zooming in on a product shows detail, and saved at a higher
  quality. Logos and other PNG graphics are converted to lossless WebP,
  which keeps every pixel. Product cards and thumbnails are served a smaller
  copy, so pages are no heavier for it.
- **Phone photos are the right way up.** A phone saves a portrait photo
  sideways and records the turn separately; that record was being discarded
  without the turn being applied.
- **Large photos no longer run the server out of memory.** Opening a
  6000 by 4000 pixel photo takes over 100 MB; Whisker now asks for the
  memory it needs, where the host allows, instead of quietly storing the
  photo unconverted.
- **Pictures are shown whole.** Product cards and the product page cropped
  every photo to fill a fixed frame, cutting off the top and bottom of tall
  photos and the sides of wide ones. The whole picture now shows, on a plain
  border. Shops that prefer full frames can switch back under Settings,
  Appearance.
- Deleting a picture now deletes its WebP copies as well.

### Payments

- **Gateways are asked for the exact total.** Stripe and Razorpay converted
  the total to minor units by multiplying by 100 and truncating, which sent
  about one in twenty totals a cent short (0.29 became 28 cents). Currencies
  with no minor unit, such as yen, were charged a hundred times over, and the
  paid amount was read back the same way so the mismatch check could not see
  it. Both now use the same conversion the refund path already used.
- **The fallback currency is the shop's own.** Where a gateway had no order
  currency to go on it assumed USD or INR; it now uses the currency set in
  Settings.

### Money

- **Arithmetic is done in whole cents.** Prices come out of the database as
  decimals that binary floating point cannot hold exactly. A basket of 0.10
  and 0.70 came to 0.7999999999999999 and missed a 0.80 free-delivery
  threshold, and a weight-based delivery charge went into the total
  unrounded. Every sum, percentage and comparison in the cart, checkout,
  tax, shipping and refunds now rounds to the cent, so a total is always
  the sum of the parts as printed.

### Accounts

- **Shops choose what the signup form asks for.** A new screen, Signup &
  Sign-in, sets first name, last name, phone and username each to not
  asked, optional or required. Email and password are always required.
  Switching a field off hides it from the signup and profile forms and
  keeps whatever customers already gave.
- **Shops can add their own fields**: short or long text, a number, a
  dropdown, a tick box or a date, each required or optional, on the signup
  form or on the profile only. Customers can change their answers on their
  profile, and the shopkeeper sees them on the customer's page. Answers
  are removed when a customer's data is erased.
- **Customers can sign in with a username**, when the shop turns it on.
  Usernames are unique regardless of letter case and cannot contain @, so
  one is never mistaken for an email address.
- **A rejected signup keeps what was typed**, except the password, so a
  single mistake no longer means filling the form in again.
- **Mistakes no longer use up the signup allowance.** Three accounts per
  connection per hour was counted on every submission, so three typos
  locked a real customer out for an hour. It now counts accounts created,
  with a separate, generous limit on submissions.
- **A registered customer is no longer told to set a password they already
  have.** Whether an account has a password was recorded twice: in the
  password itself, and in a separate row that only the set-password and
  reset flows ever wrote. Registration wrote the first and not the second,
  so every account made through the registration form was shown a
  "Set Your Password" notice saying it had been created during checkout,
  and the form it offered could never succeed, because it carried no
  current password for the server to check. There is now one answer,
  read from the account.

### Installing with Composer

- **`composer create-project whiskerent/whisker-cart`** installs the same
  files the zip does, checks PHP and the required extensions, and hands
  over to `/install/`. Inside any shop, `composer require` makes a library
  available to custom code and plugins; Whisker loads `vendor/` whenever it
  exists. Nothing changes for zip installs, and an update never touches
  `vendor/` or a shop's own `composer.json`.

### Server

- **The installer and the dashboard say when private files are readable
  from the internet.** The shipped `.htaccess` keeps `config/`, `app/` and
  the database schema off the web, but nginx never reads it and Apache
  ignores it under `AllowOverride None`, and until now nothing said so.
  Four files that must be refused are requested over HTTP; a warning with
  the fix appears if any come back.
- **nginx gets the full set of rules** in the install guide: routing, the
  access denials that `.htaccess` provides on Apache, and compression.

### Appearance

- **Icons are drawn, not typed.** Every emoji that stood in for an icon in
  the storefront, the admin and the installer is replaced by an icon from
  the Lucide set, drawn inline in the colour of the text beside it. Email
  keeps its emoji, because mail clients strip inline images.
- **The admin's two-column forms collapse on a phone.** They used to stay
  two columns however narrow the screen.
- Around a quarter of the inline styles in the views have moved into
  stylesheet classes, with a test that keeps the count from rising again.

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
