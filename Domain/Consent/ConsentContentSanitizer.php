<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\Consent;

use DOMDocument;
use DOMElement;
use DOMNode;

class ConsentContentSanitizer
{
    private const ALLOWED_TAGS = ['a', 'br', 'em', 'strong', 'span', 'p', 'ul', 'ol', 'li'];
    private const ALLOWED_LINK_SCHEMES = ['http', 'https', 'mailto'];

    public function sanitize(string $content): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="gdpr-root">' . $content . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('gdpr-root');
        if (!$root instanceof DOMElement) {
            return '';
        }

        $this->cleanChildren($root);
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child) ?: '';
        }

        return trim($result);
    }

    private function cleanChildren(DOMNode $parent): void
    {
        for ($node = $parent->firstChild; $node !== null;) {
            $next = $node->nextSibling;
            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                    $this->unwrap($node);
                } else {
                    $this->sanitizeAttributes($node, $tag);
                    $this->cleanChildren($node);
                }
            }
            $node = $next;
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $href = $tag === 'a' ? trim($element->getAttribute('href')) : '';
        $title = $tag === 'a' ? trim($element->getAttribute('title')) : '';
        $attributeNames = [];
        foreach ($element->attributes as $attribute) {
            $attributeNames[] = $attribute->name;
        }
        foreach ($attributeNames as $attributeName) {
            $element->removeAttribute($attributeName);
        }

        if ($tag !== 'a') {
            return;
        }
        if ($href !== '' && $this->isSafeHref($href)) {
            $element->setAttribute('href', $href);
            $element->setAttribute('rel', 'noopener noreferrer');
        }
        if ($title !== '') {
            $element->setAttribute('title', mb_substr($title, 0, 255));
        }
    }

    private function isSafeHref(string $href): bool
    {
        if (str_starts_with($href, '/') || str_starts_with($href, '#')) {
            return !str_starts_with($href, '//');
        }
        $scheme = parse_url($href, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), self::ALLOWED_LINK_SCHEMES, true);
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }
        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }
}
