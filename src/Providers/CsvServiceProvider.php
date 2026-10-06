<?php

declare(strict_types=1);

namespace Nvl\Csv\Providers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Nvl\Csv\Contracts\CSVAnalyzerContract;
use Nvl\Csv\Contracts\CSVAsyncProcessorContract;
use Nvl\Csv\Contracts\CSVExportContract;
use Nvl\Csv\Contracts\CSVImportContract;
use Nvl\Csv\Services\CSVAnalyzerService;
use Nvl\Csv\Services\CSVAsyncProcessor;
use Nvl\Csv\Services\CSVExport;
use Nvl\Csv\Services\CSVHandlerRegistry;
use Nvl\Csv\Services\CSVImport;
use Nvl\Csv\Services\CSVWorkStore;
use Nvl\Csv\Tenancy\CsvAdoptionAdapter;
use Nvl\Data\Services\TypeScriptSourceRegistry;
use Nvl\Support\Globals\GlobalNames;
use Nvl\Support\Providers\TenantServiceProvider;
use Nvl\Support\Tenancy\Contracts\TenantContext;
use Nvl\Support\Tenancy\Contracts\TenantQueueContext;
use Nvl\Support\Traits\RegistersNamespacedResources;
use Nvl\Tenancy\Services\TenantAdoptionRegistry;

/**
 * Registers generated TypeScript discovery and publishable agent guidance.
 */
final class CsvServiceProvider extends ServiceProvider
{
    use RegistersNamespacedResources;

    public function register(): void
    {
        $this->app->register(TenantServiceProvider::class);
        $this->app->booted(function (): void {
            if ($this->app->bound(TenantAdoptionRegistry::class)) {
                $this->app->make(TenantAdoptionRegistry::class)->register('csv', CsvAdoptionAdapter::class);
            }
        });
        $this->app->bindIf(CSVAnalyzerService::class);
        $this->app->bindIf(CSVAnalyzerContract::class, static fn (Container $app): CSVAnalyzerContract => $app->make(CSVAnalyzerService::class));
        $this->app->bindIf(CSVImport::class);
        $this->app->bindIf(CSVImportContract::class, static fn (Container $app): CSVImportContract => $app->make(CSVImport::class));
        $this->app->bindIf(CSVExport::class);
        $this->app->bindIf(CSVExportContract::class, static fn (Container $app): CSVExportContract => $app->make(CSVExport::class));
        $this->app->bindIf(CSVAsyncProcessorContract::class, static fn (Container $app): CSVAsyncProcessorContract => $app->make(CSVAsyncProcessor::class));

        $this->app->singleton(CSVHandlerRegistry::class);
        $this->app->bindIf(CSVAsyncProcessor::class, fn (Container $app): CSVAsyncProcessor => new CSVAsyncProcessor(
            $app->make(TenantContext::class),
            $app->make(CSVWorkStore::class),
            $app->make(CSVHandlerRegistry::class),
            $app->make(TenantQueueContext::class),
        ));
    }

    /**
     * Register package TypeScript sources and publishing.
     */
    public function boot(TypeScriptSourceRegistry $typeScriptSources): void
    {
        $this->app->make(GlobalNames::class)->translations('csv', __DIR__.'/../../lang', $this->app->make('translation.loader'));
        $this->publishes([
            __DIR__.'/../../lang' => lang_path('vendor/nvl-csv'),
        ], 'nvl-csv-translations');
        $typeScriptSources->register(__DIR__.'/..', 'nvl/csv');
        $this->publishes([
            __DIR__.'/../../resources/boost/skills' => base_path('.agents/skills'),
        ], 'csv-skills');
    }
}
