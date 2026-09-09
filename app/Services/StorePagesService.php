<?php
namespace App\Services;

use Core\Database;

/**
 * The pages a shop is expected to have, and which of them this one does.
 *
 * Whisker never said what was missing. A shop could run for months with no
 * refund policy, and the first anyone heard of it was a chargeback or a
 * gateway review. Several parts of the shop already key off these pages —
 * checkout asks shoppers to accept the terms, the footer lists whatever is
 * published — so their absence is silent rather than visible.
 *
 * The starter text is a skeleton with the decisions left blank. Nothing here
 * writes a policy on the shopkeeper's behalf: a refund window or a retention
 * period invented by software and published unread is worse than an empty
 * page, because it looks settled. Pages are created as drafts for the same
 * reason.
 */
class StorePagesService
{
    /**
     * @return array<int,array{slug:string,title:string,why:string,used_by:?string}>
     */
    public static function recommended(): array
    {
        return [
            [
                'slug'    => 'terms-and-conditions',
                'title'   => 'Terms & Conditions',
                'why'     => 'The agreement between you and the shopper. Most payment providers expect one before they will settle disputes in your favour.',
                'used_by' => 'Checkout asks shoppers to tick that they agree to this. Without the page, nobody is asked.',
            ],
            [
                'slug'    => 'privacy-policy',
                'title'   => 'Privacy Policy',
                'why'     => 'What you collect, why, and how long you keep it. Required almost everywhere you can legally take an order.',
                'used_by' => 'Linked from the cookie banner when consent is switched on.',
            ],
            [
                'slug'    => 'refund-policy',
                'title'   => 'Refund Policy',
                'why'     => 'When somebody can have their money back, and how long it takes. The single most common thing shoppers look for before a first order.',
                'used_by' => null,
            ],
            [
                'slug'    => 'shipping-policy',
                'title'   => 'Shipping & Delivery',
                'why'     => 'What delivery costs, where you send to, and how long it takes. Answers the question that otherwise arrives as an email.',
                'used_by' => null,
            ],
            [
                'slug'    => 'exchange-policy',
                'title'   => 'Exchange Policy',
                'why'     => 'Whether a wrong size can be swapped rather than refunded. Separate from refunds because the answer is usually different.',
                'used_by' => null,
            ],
            [
                'slug'    => 'about-us',
                'title'   => 'About Us',
                'why'     => 'Who is behind the shop. A first-time buyer deciding whether to trust you tends to look for this before they look at anything else.',
                'used_by' => null,
            ],
            [
                'slug'    => 'faq',
                'title'   => 'Frequently Asked Questions',
                'why'     => 'The questions you answer over and over, answered once.',
                'used_by' => null,
            ],
        ];
    }

    /**
     * Each recommended page with what the shop has done about it.
     *
     * @return array<int,array{slug:string,title:string,why:string,used_by:?string,
     *                         state:string,id:?int,page_title:?string}>
     *         state is one of published, draft, missing
     */
    public static function status(): array
    {
        $existing = [];
        try {
            foreach (Database::fetchAll("SELECT id, slug, title, is_active FROM wk_pages") as $row) {
                $existing[$row['slug']] = $row;
            }
        } catch (\Exception $e) {
            // No pages table yet — everything reads as missing, which is true.
        }

        $out = [];
        foreach (self::recommended() as $page) {
            $have = $existing[$page['slug']] ?? null;
            $out[] = $page + [
                'state'      => $have === null ? 'missing' : ((int) $have['is_active'] === 1 ? 'published' : 'draft'),
                'id'         => $have ? (int) $have['id'] : null,
                'page_title' => $have ? (string) $have['title'] : null,
            ];
        }
        return $out;
    }

    /** How many of the recommended pages are live, for the summary line. */
    public static function summary(): array
    {
        $counts = ['published' => 0, 'draft' => 0, 'missing' => 0];
        foreach (self::status() as $page) $counts[$page['state']]++;
        return $counts;
    }

    /** One recommended page by slug, or null if it is not one of ours. */
    public static function find(string $slug): ?array
    {
        foreach (self::recommended() as $page) {
            if ($page['slug'] === $slug) return $page;
        }
        return null;
    }

    /**
     * A starting point for a page, with the decisions left as prompts.
     *
     * Square brackets mark everything the shopkeeper has to replace. They are
     * deliberately conspicuous: a placeholder that reads like finished prose
     * is one that gets published unread.
     */
    public static function starter(string $slug): string
    {
        $shop = '';
        try {
            $shop = (string) (Database::setting('general', 'site_name', '') ?? '');
        } catch (\Exception $e) {}
        $shop = $shop !== '' ? $shop : '[your shop name]';

        $prompts = self::prompts()[$slug] ?? ['[Write this page.]'];

        $html  = "<p><em>This is a starting point. Replace everything in square brackets, "
               . "delete what does not apply, and publish it when it says what you mean. "
               . "Nothing here is legal advice.</em></p>\n";

        foreach ($prompts as $heading => $body) {
            if (is_int($heading)) {
                $html .= '<p>' . $body . "</p>\n";
                continue;
            }
            $html .= '<h2>' . $heading . "</h2>\n<p>" . str_replace('{shop}', $shop, $body) . "</p>\n";
        }
        return $html;
    }

    /** @return array<string,array<string,string>> */
    private static function prompts(): array
    {
        return [
            'terms-and-conditions' => [
                'Who we are'          => '{shop} is operated by [legal entity name] at [registered address]. You can reach us at [contact email].',
                'Placing an order'    => 'An order is an offer to buy. We accept it when [we confirm it by email / we despatch it]. We may decline an order if [reasons — stock, pricing errors, delivery area].',
                'Prices and payment'  => 'Prices are shown in [currency] and [include / exclude] tax. Payment is taken [when the order is placed / when it ships].',
                'Delivery'            => 'We deliver to [where]. See our Shipping & Delivery page for times and costs.',
                'Cancelling'          => 'You may cancel [when — before despatch, within N hours]. See our Refund Policy for what happens next.',
                'Liability'           => '[State the limits of your liability. This is the clause a lawyer should look at.]',
                'Governing law'       => 'These terms are governed by the law of [country/state], and disputes are heard in [jurisdiction].',
            ],
            'privacy-policy' => [
                'What we collect'     => 'To take an order we collect [name, email, phone, delivery address]. Payment details are handled by [gateway] and never reach our servers.',
                'Why we collect it'   => 'To fulfil your order, to contact you about it, and to [any other purpose — marketing, analytics]. We rely on [legal basis, if your market needs one].',
                'How long we keep it' => 'Order records are kept for [period — often set by tax law]. Anything else is deleted after [period].',
                'Who else sees it'    => 'We share what is necessary with [couriers, payment provider, email provider]. We do not sell personal data.',
                'Your rights'         => 'You can ask us for a copy of your data, ask us to correct it, or ask us to delete it. Write to [contact email] and we will respond within [period].',
                'Cookies'             => '[If the cookie banner is switched on, say what each category does.]',
            ],
            'refund-policy' => [
                'The short version'   => 'You can return most items within [N] days of delivery for a [full refund / refund minus return postage].',
                'What can be returned'=> 'Items must be [unused, in original packaging, with tags]. We cannot accept [exclusions — perishables, personalised items, underwear].',
                'How to start one'    => 'Email [contact email] with your order number. We will reply with [instructions / a returns label].',
                'Who pays postage'    => 'Return postage is paid by [you / us], except where the item arrived [damaged or incorrect], in which case we cover it.',
                'When you get paid'   => 'Refunds go back to the original payment method within [N] working days of us receiving the return.',
                'Faulty items'        => '[Your obligations here are usually set by law and cannot be reduced by this page.]',
            ],
            'shipping-policy' => [
                'Where we deliver'    => 'We deliver to [countries or regions]. [Anywhere you do not deliver to.]',
                'What it costs'       => 'Delivery is [amount], and free on orders over [amount]. Exact cost is shown at checkout once you enter an address.',
                'How long it takes'   => 'Orders placed before [time] are despatched [same day / next working day]. Delivery then takes [N–N working days].',
                'Tracking'            => 'You will get a tracking link by email when your order leaves us.',
                'If it goes wrong'    => 'If your order has not arrived after [N] days, email [contact email] and we will chase the courier.',
            ],
            'exchange-policy' => [
                'What we exchange'    => 'We exchange [wrong size, wrong colour] within [N] days, subject to the item being [unused, with tags].',
                'How to ask'          => 'Email [contact email] with your order number and what you would like instead.',
                'Cost'                => 'The first exchange is [free / charged at]. Return postage is paid by [you / us].',
                'If it is out of stock' => 'If what you want is unavailable we will [refund you / hold the exchange until it is back].',
            ],
            'about-us' => [
                'Who we are'          => '[Tell people who is behind the shop and why it exists. A first-time buyer is deciding whether to trust you.]',
                'What we sell'        => '[What you stock and what makes it worth buying from you rather than anyone else.]',
                'Where we are'        => '[Where you are based, and where you ship from.]',
                'Get in touch'        => 'Email [contact email]. We answer within [period].',
            ],
            'faq' => [
                'How long does delivery take?' => '[Answer, or point at the Shipping & Delivery page.]',
                'Can I return something?'      => '[Answer, or point at the Refund Policy.]',
                'Do you deliver to [region]?'  => '[Answer.]',
                'How do I track my order?'     => 'Use the tracking link in your despatch email, or the Track Order page.',
                'Something else?'              => 'Email [contact email].',
            ],
        ];
    }
}
