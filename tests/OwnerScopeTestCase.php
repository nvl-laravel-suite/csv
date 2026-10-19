<?php

declare(strict_types=1);

namespace Nvl\Csv\Tests;

use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Providers\CsvServiceProvider;
use Nvl\Csv\Services\CSVHandlerRegistry;
use Nvl\Csv\Tests\Fixtures\OwnershipRowHandler;
use Nvl\Data\Providers\DataServiceProvider;
use Nvl\Ownership\Tests\Fixtures\Queue\QueueScenario;
use Nvl\Ownership\Tests\Fixtures\Resources\ResourceScenario;
use Nvl\Ownership\Tests\OwnershipTestCase;

/** Tests captured private CSV work alongside unrelated owned resources without Tenancy. */
abstract class OwnerScopeTestCase extends OwnershipTestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), DataServiceProvider::class, CsvServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set(['queue.default' => 'database', 'nvl-csv.queue.connection' => 'database']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->allowOwners();
        ResourceScenario::install($this->app);
        QueueScenario::install();
        Storage::fake('local');
        $this->app->make(CSVHandlerRegistry::class)->register('ownership-rows', OwnershipRowHandler::class);
    }
}
