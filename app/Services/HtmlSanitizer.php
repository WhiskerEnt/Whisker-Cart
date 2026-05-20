<?php
namespace App\Services;

/**
 * WHISKER — Zero-dependency HTML sanitizer.
 *
 * Allows the rich-text tag set used by Whisker's WYSIWYG editor and Markdown-to-HTML
 * email templates while stripping anything that can execute script or smuggle
 * dangerous URI schemes.
 *
 * Goal: prevent stored XSS in admin-authored content (page bodies, email
 * templates) that is then displayed back to other admins or to customers.
 *
 * Strategy:
 *   - Allowlist of tag names. Anything else is removed (the contents are kept).
 *   - Allowlist of attributes per tag. Anything else is removed.
 *   - URL attributes (href, src) are validated against http/https/mailto/tel
 *     and rooted relative paths; javascript:, data: (except images), vbscript:,
 *     and protocol-relative // are rejected.
 *   - All on* event handler attributes are stripped unconditionally.
 *   - style attribute is stripped (CSS can carry expressions / url() with
 *     javascript: in legacy IE and behaves erratically in webmail).
 *
 * This is not a full HTMLPurifier replacement — it deliberately rejects
 * exotic edge cases by stripping them entirely rather than rewriting.
 */
class HtmlSanitizer
{
    /** Tags that are kept. Everything else has its tags stripped but content kept. */
    private const ALLOWED_TAGS = [
        // Block
        'p', 'div', 'br', 'hr',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote', 'pre',
        // Inline
        'span', 'strong', 'b', 'em', 'i', 'u', 's', 'small', 'sub', 'sup', 'code',
        // Lists
        'ul', 'ol', 'li',
        // Links + media
        'a', 'img',
        // Tables (common in email templates)
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
    ];

    /** Per-tag attribute allowlist. Use '*' for attributes allowed on every allowed tag. */
    private const ALLOWED_ATTRIBUTES = [
        '*'     => ['class', 'id', 'title', 'dir', 'lang'],
        'a'     => ['href', 'target', 'rel'],
        'img'   => ['src', 'alt', 'width', 'height'],
        'table' => ['border', 'cellpadding', 'cellspacing', 'width'],
        'td'    => ['colspan', 'rowspan', 'align', 'valign', 'width'],
        'th'    => ['colspan', 'rowspan', 'align', 'valign', 'width', 'scope'],
        'tr'    => ['align', 'valign'],
    ];

    /**
     * Sanitize an HTML fragment for storage and rendering.
     *
     * Returns a string that is safe to render with <?= ?> inside the body of
     * an HTML document. Does NOT escape the result — sanitization preserves
     * the allowlisted tags as real HTML.
     */
    public static function purify(string $html): string
    {
        if ($html === '') return '';

        // libxml is bundled with every PHP build — no extra extension required.
        // LIBXML_HTML_NOIMPLIED + NODEFDTD keep DOMDocument from wrapping the
        // fragment in <html><body>…</body></html>, which would otherwise show
        // up in the serialized output.
        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        // Prefix the input with a UTF-8 meta tag so DOMDocument doesn't mis-detect
        // the encoding and mangle multi-byte characters.
        $wrapped = '<?xml encoding="UTF-8"?><div id="__wk_root__">' . $html . '</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $dom->getElementById('__wk_root__');
        if (!$root) return ''; // parse failure → conservative

        self::walk($root);

        // Serialize the cleaned children of the root wrapper, not the wrapper itself.
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }
        return $out;
    }

    /**
     * HTML5 void elements never have real children. When DOMDocument
     * encounters a non-self-closed void like `<embed src="e">` followed
     * by other markup, it incorrectly parents the following siblings as
     * children of the void. Stripping the whole subtree would then drop
     * legitimate content. For voids we strip the element itself but
     * hoist any "children" (actually misparented siblings) back up.
     */
    private const HTML5_VOIDS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    /** Recursively clean a node and its descendants. */
    private static function walk(\DOMNode $node): void
    {
        // Snapshot children before mutating — DOMNodeList is live.
        $children = [];
        foreach ($node->childNodes as $c) $children[] = $c;

        foreach ($children as $child) {
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->nodeName);

                if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                    // Tag not allowed. Two strip modes:
                    //
                    // 1) Container-with-payload (script/style/iframe/etc.) —
                    //    nuke the entire subtree. Hoisting the children up
                    //    would leak the JS body as bare text or the iframe
                    //    target as a stray text node.
                    //
                    // 2) Void elements (embed/link/meta/svg) — strip the
                    //    element itself but hoist any "children" back into
                    //    the parent. For void elements those "children" are
                    //    DOMDocument parsing artifacts (real HTML5 voids
                    //    have no content); for non-void containers like svg
                    //    we treat them the same way out of conservatism so
                    //    benign trailing markup doesn't disappear.
                    $stripSubtree = in_array($tag, ['script', 'style', 'iframe', 'object', 'form', 'textarea', 'button', 'select'], true);
                    if ($stripSubtree) {
                        $child->parentNode->removeChild($child);
                    } else {
                        // Recurse into descendants first so we clean them too.
                        self::walk($child);
                        while ($child->firstChild) {
                            $child->parentNode->insertBefore($child->firstChild, $child);
                        }
                        $child->parentNode->removeChild($child);
                    }
                    continue;
                }

                // Tag is allowed — clean its attributes, then recurse.
                self::cleanAttributes($child, $tag);
                self::walk($child);
            }
            // Text and comment nodes are left alone (comments aren't in our
            // input path normally; if they are, they're harmless).
        }
    }

    /** Strip disallowed attributes; validate URL attributes. */
    private static function cleanAttributes(\DOMElement $el, string $tag): void
    {
        $globalAllow = self::ALLOWED_ATTRIBUTES['*'];
        $tagAllow    = self::ALLOWED_ATTRIBUTES[$tag] ?? [];
        $allow       = array_merge($globalAllow, $tagAllow);

        // Snapshot — attributes is live too.
        $attrs = [];
        foreach ($el->attributes as $a) $attrs[] = $a->nodeName;

        foreach ($attrs as $name) {
            $lname = strtolower($name);
            // Unconditional: strip any event handler attribute.
            if (str_starts_with($lname, 'on')) {
                $el->removeAttribute($name);
                continue;
            }
            if (!in_array($lname, $allow, true)) {
                $el->removeAttribute($name);
                continue;
            }
            // URL attribute validation
            if ($lname === 'href' || $lname === 'src') {
                $value = $el->getAttribute($name);
                if (!self::isSafeUrl($value, $lname === 'src' && $tag === 'img')) {
                    $el->removeAttribute($name);
                    continue;
                }
            }
            // target attribute on <a>: if set to _blank, force rel="noopener noreferrer"
            if ($tag === 'a' && $lname === 'target') {
                $target = strtolower($el->getAttribute('target'));
                if ($target === '_blank') {
                    $rel = $el->getAttribute('rel');
                    if (!str_contains($rel, 'noopener')) {
                        $el->setAttribute('rel', trim($rel . ' noopener noreferrer'));
                    }
                }
            }
        }
    }

    /**
     * Returns true if the URL is safe to render in href/src.
     * Delegates to View::isSafeUrl so there's one canonical implementation
     * shared across template rendering and the sanitizer.
     */
    public static function isSafeUrl(string $url, bool $allowDataImage = false): bool
    {
        return \Core\View::isSafeUrl($url, $allowDataImage);
    }
}
