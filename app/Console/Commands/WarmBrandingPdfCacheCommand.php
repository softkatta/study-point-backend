<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\BrandingPdfCacheService;
use App\Support\AppearanceDefaults;
use Illuminate\Console\Command;

class WarmBrandingPdfCacheCommand extends Command
{
    protected $signature = 'branding:warm-pdf-cache';

    protected $description = 'Pre-build JPEG logo cache for invoice PDFs (needed for PNG logos when GD is unavailable on the web server)';

    public function handle(BrandingPdfCacheService $brandingPdf): int
    {
        $appearance = AppearanceDefaults::merge(Setting::getSection('appearance'));
        $url = $appearance['logo_url'] ?? null;

        if (! $url) {
            $this->warn('No logo configured in appearance settings.');

            return self::FAILURE;
        }

        if ($brandingPdf->warmLogoCache($url)) {
            $this->info('Invoice PDF logo cache is ready.');

            return self::SUCCESS;
        }

        $this->error('Could not build PDF logo cache. Ensure the logo file exists and PHP GD is enabled.');

        return self::FAILURE;
    }
}
