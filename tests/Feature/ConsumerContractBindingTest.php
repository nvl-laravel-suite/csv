<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Nvl\Csv\Contracts\CSVRowHandler;
use Nvl\Csv\Providers\CsvServiceProvider;
use Nvl\Csv\Services\CsvAnalyzer;
use Nvl\Csv\Services\CSVAsyncProcessor;
use Nvl\Csv\Services\CsvExporter;
use Nvl\Csv\Services\CSVHandlerRegistry;
use Nvl\Csv\Services\CsvImporter;
use Nvl\Support\Tenancy\Contracts\TenantContext;

it('publishes the native CSV service signatures', function (): void {
    foreach ([
        [CsvAnalyzer::class, 'analyze', ['string', 'array'], 'array'],
        [CsvImporter::class, 'import', ['string', 'array', CSVRowHandler::class], 'array'],
        [CsvExporter::class, 'export', ['iterable', 'array'], 'string'],
    ] as [$service, $method, $parameters, $result]) {
        $reflection = new ReflectionMethod($service, $method);
        expect(array_map(static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(), $reflection->getParameters()))->toBe($parameters)
            ->and((string) $reflection->getReturnType())->toBe($result);
    }
});

it('resolves each native workflow without Data or Tenancy bindings', function (): void {
    foreach ([CsvAnalyzer::class, CsvImporter::class, CsvExporter::class, CSVAsyncProcessor::class, CSVHandlerRegistry::class] as $service) {
        expect($this->app->make($service))->toBeInstanceOf($service);
    }
    expect($this->app->bound(TenantContext::class))->toBeFalse();
});

it('preserves early host class bindings in a second native application', function (): void {
    $consumer = new Application($this->app->basePath());
    $consumer->instance('config', new Repository($this->app->make('config')->all()));
    $consumer->instance('env', 'testing');
    $consumer->register(FilesystemServiceProvider::class);
    $host = new stdClass;
    $consumer->instance(CsvAnalyzer::class, $host);
    try {
        $consumer->register(CsvServiceProvider::class);
        expect($consumer->make(CsvAnalyzer::class))->toBe($host);
    } finally {
        Container::setInstance($this->app);
        $consumer->flush();
    }
});
