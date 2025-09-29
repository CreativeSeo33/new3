<?php
declare(strict_types=1);

namespace App\Service\Search;

use TeamTNT\TNTSearch\TNTSearch;

final class TNTSearchFactory
{
    public function __construct(
        private readonly string $storagePath,
        private readonly bool $asYouType,
        private readonly bool $fuzzy,
        private readonly int $fuzzyDistance
    ) {}

    public function create(): TNTSearch
    {
        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0775, true);
        }

        $tnt = new TNTSearch();
        $tnt->loadConfig([
            'driver' => 'filesystem',
            'storage' => $this->storagePath,
            'asYouType' => $this->asYouType,
            'fuzziness' => $this->fuzzy,
            'fuzzy_distance' => $this->fuzzyDistance,
        ]);
        return $tnt;
    }
}


