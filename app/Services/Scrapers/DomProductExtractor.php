<?php

declare(strict_types=1);

namespace App\Services\Scrapers;

use App\Exceptions\ProductNotOnPageException;
use Symfony\Component\DomCrawler\Crawler;

final class DomProductExtractor
{
    /**
     * @throws ProductNotOnPageException when the node is missing or empty
     */
    public function extractRequiredText(Crawler $crawler, string $selector): string
    {
        $node = $crawler->filter($selector);

        if ($node->count() === 0) {
            throw ProductNotOnPageException::forSelector($selector);
        }

        $text = trim($node->text());

        if ($text === '') {
            throw ProductNotOnPageException::forSelector($selector);
        }

        return $text;
    }

    /**
     * Amazon renders an empty price span first and fills it in with JavaScript, so the
     * first matching node is often blank. Take the first node that holds a real number.
     */
    public function extractPrice(Crawler $crawler, string $selector): float
    {
        foreach ($crawler->filter($selector) as $node) {
            $price = (float) (preg_replace('/[^0-9.]/', '', $node->textContent) ?? '');

            if ($price > 0) {
                return $price;
            }
        }

        return 0.00;
    }

    public function extractAttribute(Crawler $crawler, string $selector, string $attribute): string
    {
        $node = $crawler->filter($selector);

        if ($node->count() === 0) {
            return '';
        }

        $value = $node->attr($attribute);

        return is_string($value) ? trim($value) : '';
    }
}
