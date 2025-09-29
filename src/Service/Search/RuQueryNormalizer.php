<?php
declare(strict_types=1);

namespace App\Service\Search;

use Wamania\Snowball\Stemmer\Russian;

final class RuQueryNormalizer
{
    /**
     * @param string[] $stopWords
     */
    public function __construct(
        private readonly array $stopWords = []
    ) {}

    public function normalize(string $text, bool $forPrefix = false): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('~[^a-zа-я0-9\s]+~u', ' ', $text) ?? '';
        $parts = preg_split('~\s+~u', trim($text)) ?: [];
        $stemmer = new Russian();

        $result = [];
        foreach ($parts as $p) {
            if ($p === '' || \in_array($p, $this->stopWords, true)) { continue; }
            $stem = $stemmer->stem($p);
            if ($stem === '') { continue; }
            $result[] = $forPrefix ? $stem.'*' : $stem;
        }
        return implode(' ', $result);
    }
}


