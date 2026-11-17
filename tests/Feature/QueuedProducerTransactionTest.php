<?php

declare(strict_types=1);

use Illuminate\Bus\Events\BatchDispatched;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Contracts\CSVRowHandler;
use Nvl\Csv\Enums\CSVTypeEnum;
use Nvl\Csv\Exceptions\CSVDispatchException;
use Nvl\Csv\Services\CSVAsyncProcessor;
use Nvl\Csv\Services\CSVHandlerRegistry;
use Nvl\Csv\Services\CSVWorkStore;
use Nvl\Csv\ValueObjects\CSVFieldMapping;

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

/** Apply the first row, then fail inline on the second. */
final class CsvFailingAfterEffectProbe implements CSVRowHandler
{
    /** @var list<string> */
    public static array $applied = [];

    public function process(array $row, int $rowNumber): void
    {
        if ($rowNumber === 2) {
            throw new RuntimeException('inline handler failed');
        }
        self::$applied[] = $row['name'];
    }
}

/** Capture the rows a worker delivers. */
final class CsvCapturingProbe implements CSVRowHandler
{
    /** @var list<array<string,mixed>> */
    public static array $rows = [];

    public function process(array $row, int $rowNumber): void
    {
        self::$rows[] = $row;
    }
}

/** Define an alternate connection with the selected test driver's settings. */
function csvProducerConnection(string $name): void
{
    config()->set('database.connections.'.$name, config('database.connections.'.DB::getDefaultConnection()));
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
    app(CSVHandlerRegistry::class)->register('failing-after-effect', CsvFailingAfterEffectProbe::class);
    CsvCommittedProducerProbe::$source = null;
    CsvCommittedProducerProbe::$levels = [];
    CsvFailingAfterEffectProbe::$applied = [];
    app(CSVHandlerRegistry::class)->register('capturing', CsvCapturingProbe::class);
    CsvCapturingProbe::$rows = [];
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
    csvProducerConnection($name);
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
    csvProducerConnection($name);
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

it('refuses an open database-queue connection transaction before staging chunk files', function (): void {
    $source = 'csv-clean-source';
    $queueDb = 'csv-queue-db';
    csvProducerConnection($source);
    csvProducerConnection($queueDb);
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database', ['driver' => 'database', 'connection' => $queueDb, 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90]);
    config()->set('queue.batching.database', $source);
    csvProducerBatchSchema($source);

    $path = $this->temporaryCsv("name\nAda\n");
    DB::connection($queueDb)->beginTransaction();
    try {
        expect(fn () => app(CSVAsyncProcessor::class)->fromFile($path)->withOptions(['connection' => $source])->usingHandler('committed-probe')->processAsync())
            ->toThrow(DomainException::class, 'outside existing source and batch database transactions');
    } finally {
        DB::connection($queueDb)->rollBack();
    }

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('retains work when an inline handler fails after an earlier effect', function (): void {
    csvProducerBatchSchema();
    $path = $this->temporaryCsv("name\nAda\nBob\n");

    try {
        app(CSVAsyncProcessor::class)->fromFile($path)->usingHandler('failing-after-effect')->processAsync();
        $this->fail('Expected the inline handler failure to surface.');
    } catch (CSVDispatchException $exception) {
        $manifest = $exception->work->manifestPath;
        expect($exception->getPrevious()?->getMessage())->toBe('inline handler failed')
            ->and($exception->getContext()['work_id'])->toBe($exception->work->workId)
            ->and(Storage::disk('local')->exists($manifest))->toBeTrue()
            ->and(Storage::disk('local')->allFiles(dirname($manifest).'/chunks'))->not->toBe([]);
    }

    expect(CsvFailingAfterEffectProbe::$applied)->toBe(['Ada'])
        ->and(DB::transactionLevel())->toBe(0);
});

it('runs its alternate connections on the selected database driver', function (): void {
    csvProducerConnection('csv-driver-probe');

    expect(DB::connection('csv-driver-probe')->getDriverName())->toBe(DB::connection()->getDriverName());
});

it('delivers deeply nested staged array cells to the worker', function (): void {
    csvProducerBatchSchema();
    $nested = str_repeat('[', 62).'1'.str_repeat(']', 62);
    $path = $this->temporaryCsv("tags\n{$nested}\n");

    $batch = app(CSVAsyncProcessor::class)->fromFile($path)->usingHandler('capturing')
        ->mapField('tags', 'tags', CSVFieldMapping::typed('tags', 'tags', CSVTypeEnum::ARRAY))->processAsync();

    expect($batch->fresh()->failedJobs)->toBe(0)
        ->and(CsvCapturingProbe::$rows)->toBe([['tags' => json_decode($nested, true)]]);
});

it('delivers staged whole floats to the worker as floats', function (CSVTypeEnum $type): void {
    csvProducerBatchSchema();
    $path = $this->temporaryCsv("amount\n1.0\n");

    $batch = app(CSVAsyncProcessor::class)->fromFile($path)->usingHandler('capturing')
        ->mapField('amount', 'amount', CSVFieldMapping::typed('amount', 'amount', $type))->processAsync();

    expect($batch->fresh()->failedJobs)->toBe(0)
        ->and(CsvCapturingProbe::$rows[0]['amount'])->toBe(1.0);
})->with([CSVTypeEnum::FLOAT, CSVTypeEnum::NULLABLE_FLOAT]);

it('retains completed inline work when a batch dispatched listener fails', function (): void {
    csvProducerBatchSchema();
    Event::listen(BatchDispatched::class, static function (): void {
        throw new RuntimeException('dispatched listener failed');
    });
    $path = $this->temporaryCsv("name\nAda\n");

    try {
        app(CSVAsyncProcessor::class)->fromFile($path)->usingHandler('capturing')->processAsync();
        $this->fail('Expected the dispatched listener failure to surface.');
    } catch (CSVDispatchException $exception) {
        expect($exception->getPrevious()?->getMessage())->toBe('dispatched listener failed')
            ->and(app(CSVWorkStore::class)->read($exception->work))->toHaveKey('chunks');
    }

    expect(CsvCapturingProbe::$rows)->toBe([['name' => 'Ada']]);
});

it('removes completed inline work once dispatch returns', function (): void {
    csvProducerBatchSchema();
    $path = $this->temporaryCsv("name\nAda\n");

    app(CSVAsyncProcessor::class)->fromFile($path)->usingHandler('capturing')->processAsync();

    expect(CsvCapturingProbe::$rows)->toBe([['name' => 'Ada']])
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});
