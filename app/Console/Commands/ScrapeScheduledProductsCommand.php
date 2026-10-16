<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ScrapeProductData;
use App\Support\ComponentLog;
use Illuminate\Console\Command;

final class ScrapeScheduledProductsCommand extends Command
{
    protected $signature = 'scrape:scheduled';

    protected $description = 'Dispatch scrape jobs for each configured product URL';

    public function handle(): int
    {
        $urls = config('scraping.urls', []);

        if (! is_array($urls)) {
            $this->error('config scraping.urls must be an array.');

            return self::FAILURE;
        }

        $dispatched = 0;

        foreach ($urls as $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }

            ScrapeProductData::dispatch($url);
            $dispatched++;
        }

        ComponentLog::info('ScrapeScheduledProductsCommand', 'dispatched scrape jobs', [
            'count' => $dispatched,
        ]);

        return self::SUCCESS;
    }
}
