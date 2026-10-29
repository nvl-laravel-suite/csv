<?php

declare(strict_types=1);

namespace Nvl\Csv\Providers;

use Illuminate\Support\ServiceProvider;
use Nvl\Csv\Ownership\CsvAdoptionAdapter;
use Nvl\Csv\Services\CsvAnalyzer;
use Nvl\Csv\Services\CSVAsyncProcessor;
use Nvl\Csv\Services\CsvExporter;
use Nvl\Csv\Services\CSVHandlerRegistry;
use Nvl\Csv\Services\CsvImporter;
use Nvl\Csv\Services\CSVWorkStore;
use Nvl\Ownership\Services\OwnershipAdoptionRegistry;
use Nvl\Support\Globals\GlobalNames;
use Nvl\Support\Ownership\Contracts\OwnershipIntegratedProvider;
use Nvl\Support\Providers\SupportServiceProvider;
use Nvl\Support\Traits\RegistersNamespacedResources;

/** Registers native CSV services and optional ownership adoption. */
final class CsvServiceProvider extends ServiceProvider implements OwnershipIntegratedProvider
{
    use RegistersNamespacedResources;

    public function register(): void
    {
        $this->app->register(SupportServiceProvider::class);
        $this->app->bindIf(CsvAnalyzer::class);
        $this->app->bindIf(CsvImporter::class);
        $this->app->bindIf(CsvExporter::class);
        $this->app->bindIf(CSVAsyncProcessor::class);
        $this->app->bindIf(CSVWorkStore::class);
        $this->app->singleton(CSVHandlerRegistry::class);
        $this->app->booted(function (): void {
            if ($this->app->bound(OwnershipAdoptionRegistry::class)) {
                $this->app->make(OwnershipAdoptionRegistry::class)->register('csv', CsvAdoptionAdapter::class);
            }
        });
    }

    public function boot(): void
    {
        $this->app->make(GlobalNames::class)->translations('csv', __DIR__.'/../../lang', $this->app->make('translation.loader'));
        $this->publishes([__DIR__.'/../../lang' => lang_path('vendor/nvl-csv')], 'nvl-csv-translations');
        $this->publishes([__DIR__.'/../../resources/boost/skills' => base_path('.agents/skills')], 'nvl-csv-skills');
    }
}
