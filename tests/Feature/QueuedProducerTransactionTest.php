<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Contracts\CSVRowHandler;
use Nvl\Csv\Services\CSVAsyncProcessor;
use Nvl\Csv\Services\CSVHandlerRegistry;

/** Observe handler effects independently of database rollback. */
final class CsvCommittedProducerProbe implements CSVRowHandler
{
    public static ?string $source = null;

    /** @var list<int> */
    public static array $levels = [];

    public function process(array $row, int $rowNumber): void
    {
        self::$levels[] = DB::connection(self::$source)->transactionLevel();
    }
}

function csvProducerBatchSchema(?string $connection = null): void
{
    Schema::connection($connection)->create('job_batches', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('name');
        $table->integer('total_jobs');
        $table->integer('pending_jobs');
        $table->integer('failed_jobs');
        $table->text('failed_job_ids');
        $table->mediumText('options')->nullable();
        $table->integer('cancelled_at')->nullable();
        $table->integer('created_at');
        $table->integer('finished_at')->nullable();
    });
}

beforeEach(function (): void {
    Schema::dropIfExists('job_batches');
    Storage::fake('local');
    config(['queue.default' => 'sync', 'queue.connections.sync.after_commit' => false, 'queue.batching.database' => DB::getDefaultConnection()]);
    app(CSVHandlerRegistry::class)->register('committed-probe', CsvCommittedProducerProbe::class);
    CsvCommittedProducerProbe::$source = null;
    CsvCommittedProducerProbe::$levels = [];
});

afterEach(function (): void {
    Schema::dropIfExists('job_batches');
});

it('refuses source producer transactions before staging a batch', function (int $depth): void {
    csvProducerBatchSchema();
    $path = $this->temporaryCsv("name\nAda\n");
    for ($index = 0; $index < $depth; $index++) {
        DB::beginTransaction();
    }
    try {
        expect(fn () => app(CSVAsyncProcessor::class)->fromFile($path)->usingHandler('committed-probe')->processAsync())
            ->toThrow(DomainException::class, 'outside existing source and batch database transactions');
    } finally {
        DB::rollBack(0);
    }
    expect(CsvCommittedProducerProbe::$levels)->toBe([])
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and(DB::table('job_batches')->count())->toBe(0);
})->with([1, 2]);

it('uses the actual nondefault source and batch connections for producer admission', function (string $transactionSource): void {
    $name = 'csv-producer-source';
    config()->set('database.connections.'.$name, ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    if ($transactionSource === 'batch') {
        config()->set('queue.batching.database', $name);
    }
    $path = $this->temporaryCsv("name\nAda\n");
    $connection = DB::connection($name);
    $connection->beginTransaction();
    try {
        $processor = app(CSVAsyncProcessor::class)->fromFile($path)->usingHandler('committed-probe');
        if ($transactionSource === 'source') {
            $processor->withOptions(['connection' => $name]);
        }
        expect(fn () => $processor->processAsync())->toThrow(DomainException::class);
    } finally {
        $connection->rollBack();
    }
    expect(DB::transactionLevel())->toBe(0)
        ->and(CsvCommittedProducerProbe::$levels)->toBe([])
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['source', 'batch']);

it('executes sync queue handlers only after the batch metadata root commit', function (): void {
    csvProducerBatchSchema();
    $path = $this->temporaryCsv("name\nAda\n");
    $batch = app(CSVAsyncProcessor::class)->fromFile($path)->usingHandler('committed-probe')->processAsync();
    expect(CsvCommittedProducerProbe::$levels)->toBe([0])
        ->and($batch->fresh()->finished())->toBeTrue()
        ->and(DB::transactionLevel())->toBe(0);
});

it('does not mistake an unrelated default transaction for the selected source root', function (): void {
    $name = 'csv-selected-source';
    config()->set('database.connections.'.$name, ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    config()->set('queue.batching.database', $name);
    csvProducerBatchSchema($name);
    CsvCommittedProducerProbe::$source = $name;
    $path = $this->temporaryCsv("name\nAda\n");
    DB::beginTransaction();
    try {
        app(CSVAsyncProcessor::class)->fromFile($path)->withOptions(['connection' => $name])->usingHandler('committed-probe')->processAsync();
        expect(CsvCommittedProducerProbe::$levels)->toBe([0])->and(DB::transactionLevel())->toBe(1);
    } finally {
        DB::rollBack();
    }
});
