# Public API: nvl/csv

Generated from the package public contract. Use this reference to confirm fully qualified names, parameter order, defaults, native array shapes and return types. Read [usage](usage.md) for policy and operation semantics; declarations alone never grant authority.

Search for the relevant fully qualified service or contract and read only its matching symbol sections; this is a lookup catalog, not an instruction to load every declaration. `resolved` annotations expand imported aliases and domain type shapes. Framework inheritance and host-authored extensions remain outside this package contract.

## Symbols

- `Nvl\Csv\Contracts\CSVRowHandler`
- `Nvl\Csv\Enums\CSVDataQualityEnum`
- `Nvl\Csv\Enums\CSVDelimiterEnum`
- `Nvl\Csv\Enums\CSVDuplicateStrategyEnum`
- `Nvl\Csv\Enums\CSVEncodingEnum`
- `Nvl\Csv\Enums\CSVErrorLevelEnum`
- `Nvl\Csv\Enums\CSVExportFormatEnum`
- `Nvl\Csv\Enums\CSVNotificationChannelEnum`
- `Nvl\Csv\Enums\CSVOperationStatusEnum`
- `Nvl\Csv\Enums\CSVProcessingModeEnum`
- `Nvl\Csv\Enums\CSVResponseCode`
- `Nvl\Csv\Enums\CSVTypeEnum`
- `Nvl\Csv\Exceptions\CSVConfigurationException`
- `Nvl\Csv\Exceptions\CSVException`
- `Nvl\Csv\Exceptions\CSVFileNotFoundException`
- `Nvl\Csv\Exceptions\CSVMemoryException`
- `Nvl\Csv\Exceptions\CSVParseException`
- `Nvl\Csv\Exceptions\CSVValidationException`
- `Nvl\Csv\Filters\AndFilter`
- `Nvl\Csv\Filters\CSVFilter`
- `Nvl\Csv\Filters\CustomFilter`
- `Nvl\Csv\Filters\FieldFilter`
- `Nvl\Csv\Filters\NotFilter`
- `Nvl\Csv\Filters\OrFilter`
- `Nvl\Csv\Services\CSVAsyncProcessor`
- `Nvl\Csv\Services\CSVHandlerRegistry`
- `Nvl\Csv\Services\CsvAnalyzer`
- `Nvl\Csv\Services\CsvExporter`
- `Nvl\Csv\Services\CsvImporter`
- `Nvl\Csv\Transformers\CSVTransformer`
- `Nvl\Csv\Transformers\ChainedTransformer`
- `Nvl\Csv\Transformers\ConditionalTransformer`
- `Nvl\Csv\Transformers\DateTransformer`
- `Nvl\Csv\Transformers\NumericTransformer`
- `Nvl\Csv\Transformers\StringTransformer`
- `Nvl\Csv\Validators\CSVFieldValidator`
- `Nvl\Csv\Validators\CSVRowValidator`
- `Nvl\Csv\ValueObjects\CSVConfiguration`
- `Nvl\Csv\ValueObjects\CSVFieldMapping`

## Nvl\Csv\Contracts\CSVRowHandler

```text
interface Nvl\Csv\Contracts\CSVRowHandler
public abstract function process(array $row, int $rowNumber): void
@param array<string, mixed> $row
```

## Nvl\Csv\Enums\CSVDataQualityEnum

```text
enum Nvl\Csv\Enums\CSVDataQualityEnum implements BackedEnum, UnitEnum: string
public const Nvl\Csv\Enums\CSVDataQualityEnum CRITICAL=Nvl\Csv\Enums\CSVDataQualityEnum::CRITICAL="critical"
public const Nvl\Csv\Enums\CSVDataQualityEnum EXCELLENT=Nvl\Csv\Enums\CSVDataQualityEnum::EXCELLENT="excellent"
public const Nvl\Csv\Enums\CSVDataQualityEnum FAIR=Nvl\Csv\Enums\CSVDataQualityEnum::FAIR="fair"
public const Nvl\Csv\Enums\CSVDataQualityEnum GOOD=Nvl\Csv\Enums\CSVDataQualityEnum::GOOD="good"
public const Nvl\Csv\Enums\CSVDataQualityEnum POOR=Nvl\Csv\Enums\CSVDataQualityEnum::POOR="poor"
public function canAutoProcess(): bool
@return bool
public static function fromColumnScores(array $columnScores): self
@param array<float> $columnScores
@return self
resolved @return Nvl\Csv\Enums\CSVDataQualityEnum
public static function fromScore(float $validityPercentage): self
@param float $validityPercentage
@return self
resolved @return Nvl\Csv\Enums\CSVDataQualityEnum
public function getColor(): string
@return string
public function getConfidenceLevel(): string
@return string
public function getDescription(): string
@return string
public function getIcon(): string
@return string
public function getImportStrategy(): string
@return string
public function getRecommendedActions(): array
@return array<string>
public function getScoreMultiplier(): float
@return float
public function getValidityRange(): array
@return array{min: float, max: float}
public function getValidityThreshold(): float
@return float
public function label(): string
@return string
public static function productionReady(): array
@return array<self>
resolved @return array<Nvl\Csv\Enums\CSVDataQualityEnum>
public static function requiresIntervention(): array
@return array<self>
resolved @return array<Nvl\Csv\Enums\CSVDataQualityEnum>
public function requiresManualReview(): bool
@return bool
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Csv\Enums\CSVDelimiterEnum

```text
enum Nvl\Csv\Enums\CSVDelimiterEnum implements BackedEnum, UnitEnum: string
public const Nvl\Csv\Enums\CSVDelimiterEnum COLON=Nvl\Csv\Enums\CSVDelimiterEnum::COLON="colon"
public const Nvl\Csv\Enums\CSVDelimiterEnum COMMA=Nvl\Csv\Enums\CSVDelimiterEnum::COMMA="comma"
public const Nvl\Csv\Enums\CSVDelimiterEnum PIPE=Nvl\Csv\Enums\CSVDelimiterEnum::PIPE="pipe"
public const Nvl\Csv\Enums\CSVDelimiterEnum SEMICOLON=Nvl\Csv\Enums\CSVDelimiterEnum::SEMICOLON="semicolon"
public const Nvl\Csv\Enums\CSVDelimiterEnum TAB=Nvl\Csv\Enums\CSVDelimiterEnum::TAB="tab"
public static function fromCharacter(string $character): ?self
@param string $character
@return (self | null)
resolved @return (Nvl\Csv\Enums\CSVDelimiterEnum | null)
public function getCharacter(): string
@return string
public function getFileExtension(): string
@return string
public function label(): string
@return string
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Csv\Enums\CSVDuplicateStrategyEnum

```text
enum Nvl\Csv\Enums\CSVDuplicateStrategyEnum implements BackedEnum, UnitEnum: string
public const Nvl\Csv\Enums\CSVDuplicateStrategyEnum ARCHIVE=Nvl\Csv\Enums\CSVDuplicateStrategyEnum::ARCHIVE="archive"
public const Nvl\Csv\Enums\CSVDuplicateStrategyEnum CREATE=Nvl\Csv\Enums\CSVDuplicateStrategyEnum::CREATE="create"
public const Nvl\Csv\Enums\CSVDuplicateStrategyEnum ERROR=Nvl\Csv\Enums\CSVDuplicateStrategyEnum::ERROR="error"
public const Nvl\Csv\Enums\CSVDuplicateStrategyEnum INCREMENT=Nvl\Csv\Enums\CSVDuplicateStrategyEnum::INCREMENT="increment"
public const Nvl\Csv\Enums\CSVDuplicateStrategyEnum MERGE=Nvl\Csv\Enums\CSVDuplicateStrategyEnum::MERGE="merge"
public const Nvl\Csv\Enums\CSVDuplicateStrategyEnum REPLACE=Nvl\Csv\Enums\CSVDuplicateStrategyEnum::REPLACE="replace"
public const Nvl\Csv\Enums\CSVDuplicateStrategyEnum SKIP=Nvl\Csv\Enums\CSVDuplicateStrategyEnum::SKIP="skip"
public const Nvl\Csv\Enums\CSVDuplicateStrategyEnum UPDATE=Nvl\Csv\Enums\CSVDuplicateStrategyEnum::UPDATE="update"
public function createsNew(): bool
@return bool
public function getAuditMessage(): string
@return string
public function getDescription(): string
@return string
public function getIcon(): string
@return string
public function getMergeStrategy(): string
@return string
public function getPerformanceImpact(): string
@return string
public function getPriority(): int
@return int
public function getSqlOperation(): string
@return string
public static function historyPreserving(): array
@return array<self>
resolved @return array<Nvl\Csv\Enums\CSVDuplicateStrategyEnum>
public function label(): string
@return string
public function modifiesExisting(): bool
@return bool
public function preservesExisting(): bool
@return bool
public static function productionSafe(): array
@return array<self>
resolved @return array<Nvl\Csv\Enums\CSVDuplicateStrategyEnum>
public function requiresUniqueIdentifier(): bool
@return bool
public function shouldCheckExisting(): bool
@return bool
public function stopsProcessing(): bool
@return bool
public static function upsertStrategies(): array
@return array<self>
resolved @return array<Nvl\Csv\Enums\CSVDuplicateStrategyEnum>
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Csv\Enums\CSVEncodingEnum

```text
enum Nvl\Csv\Enums\CSVEncodingEnum implements BackedEnum, UnitEnum: string
public const Nvl\Csv\Enums\CSVEncodingEnum ASCII=Nvl\Csv\Enums\CSVEncodingEnum::ASCII="ASCII"
public const Nvl\Csv\Enums\CSVEncodingEnum BIG5=Nvl\Csv\Enums\CSVEncodingEnum::BIG5="Big5"
public const Nvl\Csv\Enums\CSVEncodingEnum EUC_JP=Nvl\Csv\Enums\CSVEncodingEnum::EUC_JP="EUC-JP"
public const Nvl\Csv\Enums\CSVEncodingEnum EUC_KR=Nvl\Csv\Enums\CSVEncodingEnum::EUC_KR="EUC-KR"
public const Nvl\Csv\Enums\CSVEncodingEnum GB2312=Nvl\Csv\Enums\CSVEncodingEnum::GB2312="GB2312"
public const Nvl\Csv\Enums\CSVEncodingEnum GBK=Nvl\Csv\Enums\CSVEncodingEnum::GBK="GBK"
public const Nvl\Csv\Enums\CSVEncodingEnum ISO_2022_JP=Nvl\Csv\Enums\CSVEncodingEnum::ISO_2022_JP="ISO-2022-JP"
public const Nvl\Csv\Enums\CSVEncodingEnum ISO_8859_1=Nvl\Csv\Enums\CSVEncodingEnum::ISO_8859_1="ISO-8859-1"
public const Nvl\Csv\Enums\CSVEncodingEnum ISO_8859_15=Nvl\Csv\Enums\CSVEncodingEnum::ISO_8859_15="ISO-8859-15"
public const Nvl\Csv\Enums\CSVEncodingEnum ISO_8859_2=Nvl\Csv\Enums\CSVEncodingEnum::ISO_8859_2="ISO-8859-2"
public const Nvl\Csv\Enums\CSVEncodingEnum SHIFT_JIS=Nvl\Csv\Enums\CSVEncodingEnum::SHIFT_JIS="Shift_JIS"
public const Nvl\Csv\Enums\CSVEncodingEnum UTF16=Nvl\Csv\Enums\CSVEncodingEnum::UTF16="UTF-16"
public const Nvl\Csv\Enums\CSVEncodingEnum UTF16_BE=Nvl\Csv\Enums\CSVEncodingEnum::UTF16_BE="UTF-16BE"
public const Nvl\Csv\Enums\CSVEncodingEnum UTF16_LE=Nvl\Csv\Enums\CSVEncodingEnum::UTF16_LE="UTF-16LE"
public const Nvl\Csv\Enums\CSVEncodingEnum UTF32=Nvl\Csv\Enums\CSVEncodingEnum::UTF32="UTF-32"
public const Nvl\Csv\Enums\CSVEncodingEnum UTF32_BE=Nvl\Csv\Enums\CSVEncodingEnum::UTF32_BE="UTF-32BE"
public const Nvl\Csv\Enums\CSVEncodingEnum UTF32_LE=Nvl\Csv\Enums\CSVEncodingEnum::UTF32_LE="UTF-32LE"
public const Nvl\Csv\Enums\CSVEncodingEnum UTF8=Nvl\Csv\Enums\CSVEncodingEnum::UTF8="UTF-8"
public const Nvl\Csv\Enums\CSVEncodingEnum UTF8_BOM=Nvl\Csv\Enums\CSVEncodingEnum::UTF8_BOM="UTF-8-BOM"
public const Nvl\Csv\Enums\CSVEncodingEnum WINDOWS_1250=Nvl\Csv\Enums\CSVEncodingEnum::WINDOWS_1250="Windows-1250"
public const Nvl\Csv\Enums\CSVEncodingEnum WINDOWS_1251=Nvl\Csv\Enums\CSVEncodingEnum::WINDOWS_1251="Windows-1251"
public const Nvl\Csv\Enums\CSVEncodingEnum WINDOWS_1252=Nvl\Csv\Enums\CSVEncodingEnum::WINDOWS_1252="Windows-1252"
public const Nvl\Csv\Enums\CSVEncodingEnum WINDOWS_1254=Nvl\Csv\Enums\CSVEncodingEnum::WINDOWS_1254="Windows-1254"
public const Nvl\Csv\Enums\CSVEncodingEnum WINDOWS_1257=Nvl\Csv\Enums\CSVEncodingEnum::WINDOWS_1257="Windows-1257"
public function canConvert(string $targetEncoding): bool
@param string $targetEncoding
@return bool
public static function detectFromBOM(string $bytes): ?self
@param string $bytes
@return (self | null)
resolved @return (Nvl\Csv\Enums\CSVEncodingEnum | null)
public static function excelRecommended(bool $windows=true): self
@param bool $windows
@return self
resolved @return Nvl\Csv\Enums\CSVEncodingEnum
public function getBOM(): string
@return string
public function getEncodingFamily(): string
@return string
public function getPhpEncoding(): string
@return string
public function hasBOM(): bool
@return bool
public function label(): string
@return string
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Csv\Enums\CSVErrorLevelEnum

```text
enum Nvl\Csv\Enums\CSVErrorLevelEnum implements BackedEnum, UnitEnum: string
public const Nvl\Csv\Enums\CSVErrorLevelEnum CRITICAL=Nvl\Csv\Enums\CSVErrorLevelEnum::CRITICAL="critical"
public const Nvl\Csv\Enums\CSVErrorLevelEnum DEBUG=Nvl\Csv\Enums\CSVErrorLevelEnum::DEBUG="debug"
public const Nvl\Csv\Enums\CSVErrorLevelEnum ERROR=Nvl\Csv\Enums\CSVErrorLevelEnum::ERROR="error"
public const Nvl\Csv\Enums\CSVErrorLevelEnum INFO=Nvl\Csv\Enums\CSVErrorLevelEnum::INFO="info"
public const Nvl\Csv\Enums\CSVErrorLevelEnum WARNING=Nvl\Csv\Enums\CSVErrorLevelEnum::WARNING="warning"
public static function fromNumeric(int $level): ?self
@param int $level
@return (self | null)
resolved @return (Nvl\Csv\Enums\CSVErrorLevelEnum | null)
public function getConsoleColor(): string
@return string
public function getIcon(): string
@return string
public function getImpactDescription(): string
@return string
public function getLogLevel(): string
@return string
public function getNumericLevel(): int
@return int
public function isMoreSevereThan(self $other): bool
@param self $other
resolved @param Nvl\Csv\Enums\CSVErrorLevelEnum $other
@return bool
public function label(): string
@return string
public function meetsThreshold(self $threshold): bool
@param self $threshold
resolved @param Nvl\Csv\Enums\CSVErrorLevelEnum $threshold
@return bool
public function shouldLog(bool $debugMode=false): bool
@param bool $debugMode
@return bool
public function shouldNotify(): bool
@return bool
public function shouldSkipRow(): bool
@return bool
public function shouldStopProcessing(): bool
@return bool
public static function skippingLevels(): array
@return array<self>
resolved @return array<Nvl\Csv\Enums\CSVErrorLevelEnum>
public static function sortedBySeverity(): array
@return array<self>
resolved @return array<Nvl\Csv\Enums\CSVErrorLevelEnum>
public static function stoppingLevels(): array
@return array<self>
resolved @return array<Nvl\Csv\Enums\CSVErrorLevelEnum>
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Csv\Enums\CSVExportFormatEnum

```text
enum Nvl\Csv\Enums\CSVExportFormatEnum implements BackedEnum, UnitEnum: string
public const Nvl\Csv\Enums\CSVExportFormatEnum EXCEL=Nvl\Csv\Enums\CSVExportFormatEnum::EXCEL="excel"
public const Nvl\Csv\Enums\CSVExportFormatEnum EXCEL_MAC=Nvl\Csv\Enums\CSVExportFormatEnum::EXCEL_MAC="excel_mac"
public const Nvl\Csv\Enums\CSVExportFormatEnum RFC4180=Nvl\Csv\Enums\CSVExportFormatEnum::RFC4180="rfc4180"
public const Nvl\Csv\Enums\CSVExportFormatEnum STANDARD=Nvl\Csv\Enums\CSVExportFormatEnum::STANDARD="standard"
public const Nvl\Csv\Enums\CSVExportFormatEnum TSV=Nvl\Csv\Enums\CSVExportFormatEnum::TSV="tsv"
public const Nvl\Csv\Enums\CSVExportFormatEnum UNIX=Nvl\Csv\Enums\CSVExportFormatEnum::UNIX="unix"
public function description(): string
@return string
public function getDelimiter(): string
@return string
public function getEnclosure(): string
@return string
public function getEscape(): string
@return string
public function getFileExtension(): string
@return string
public function getLineEnding(): string
@return string
public function getSettings(): array
@return array{delimiter: string, enclosure: string, escape: string, line_ending: string, include_bom: bool}
public function includeBOM(): bool
@return bool
public function label(): string
@return string
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Csv\Enums\CSVNotificationChannelEnum

```text
enum Nvl\Csv\Enums\CSVNotificationChannelEnum implements BackedEnum, UnitEnum: string
public const Nvl\Csv\Enums\CSVNotificationChannelEnum DATABASE=Nvl\Csv\Enums\CSVNotificationChannelEnum::DATABASE="database"
public const Nvl\Csv\Enums\CSVNotificationChannelEnum DISCORD=Nvl\Csv\Enums\CSVNotificationChannelEnum::DISCORD="discord"
public const Nvl\Csv\Enums\CSVNotificationChannelEnum EMAIL=Nvl\Csv\Enums\CSVNotificationChannelEnum::EMAIL="email"
public const Nvl\Csv\Enums\CSVNotificationChannelEnum LOG=Nvl\Csv\Enums\CSVNotificationChannelEnum::LOG="log"
public const Nvl\Csv\Enums\CSVNotificationChannelEnum PUSHER=Nvl\Csv\Enums\CSVNotificationChannelEnum::PUSHER="pusher"
public const Nvl\Csv\Enums\CSVNotificationChannelEnum SLACK=Nvl\Csv\Enums\CSVNotificationChannelEnum::SLACK="slack"
public const Nvl\Csv\Enums\CSVNotificationChannelEnum SMS=Nvl\Csv\Enums\CSVNotificationChannelEnum::SMS="sms"
public const Nvl\Csv\Enums\CSVNotificationChannelEnum TEAMS=Nvl\Csv\Enums\CSVNotificationChannelEnum::TEAMS="teams"
public const Nvl\Csv\Enums\CSVNotificationChannelEnum WEBHOOK=Nvl\Csv\Enums\CSVNotificationChannelEnum::WEBHOOK="webhook"
public static function errorChannels(): array
@return array<self>
resolved @return array<Nvl\Csv\Enums\CSVNotificationChannelEnum>
public function getCostTier(): string
@return string
public function getDeliverySpeed(): string
@return string
public function getDescription(): string
@return string
public function getIcon(): string
@return string
public function getPriority(): int
@return int
public function getReliability(): string
@return string
public function getRequiredConfig(): array
@return array<string>
public function getSuitableNotificationTypes(): array
@return array<string>
public function isRealTime(): bool
@return bool
public function label(): string
@return string
public static function progressChannels(): array
@return array<self>
resolved @return array<Nvl\Csv\Enums\CSVNotificationChannelEnum>
public static function reportChannels(): array
@return array<self>
resolved @return array<Nvl\Csv\Enums\CSVNotificationChannelEnum>
public function requiresConfiguration(): bool
@return bool
public function supportsAttachments(): bool
@return bool
public function supportsRichContent(): bool
@return bool
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Csv\Enums\CSVOperationStatusEnum

```text
enum Nvl\Csv\Enums\CSVOperationStatusEnum implements BackedEnum, UnitEnum: string
public const Nvl\Csv\Enums\CSVOperationStatusEnum CANCELLED=Nvl\Csv\Enums\CSVOperationStatusEnum::CANCELLED="cancelled"
public const Nvl\Csv\Enums\CSVOperationStatusEnum COMPLETED=Nvl\Csv\Enums\CSVOperationStatusEnum::COMPLETED="completed"
public const Nvl\Csv\Enums\CSVOperationStatusEnum FAILED=Nvl\Csv\Enums\CSVOperationStatusEnum::FAILED="failed"
public const Nvl\Csv\Enums\CSVOperationStatusEnum PAUSED=Nvl\Csv\Enums\CSVOperationStatusEnum::PAUSED="paused"
public const Nvl\Csv\Enums\CSVOperationStatusEnum PENDING=Nvl\Csv\Enums\CSVOperationStatusEnum::PENDING="pending"
public const Nvl\Csv\Enums\CSVOperationStatusEnum PREPARING=Nvl\Csv\Enums\CSVOperationStatusEnum::PREPARING="preparing"
public const Nvl\Csv\Enums\CSVOperationStatusEnum RESUMING=Nvl\Csv\Enums\CSVOperationStatusEnum::RESUMING="resuming"
public const Nvl\Csv\Enums\CSVOperationStatusEnum RETRYING=Nvl\Csv\Enums\CSVOperationStatusEnum::RETRYING="retrying"
public const Nvl\Csv\Enums\CSVOperationStatusEnum RUNNING=Nvl\Csv\Enums\CSVOperationStatusEnum::RUNNING="running"
public function canCancel(): bool
@return bool
public function canPause(): bool
@return bool
public function canResume(): bool
@return bool
public function canRetry(): bool
@return bool
public function canTransitionTo(self $newStatus): bool
@param self $newStatus
resolved @param Nvl\Csv\Enums\CSVOperationStatusEnum $newStatus
@return bool
public function getColor(): string
@return string
public function getDescription(): string
@return string
public function getIcon(): string
@return string
public function getPriority(): int
@return int
public function getProgressRange(): array
@return array{min: int, max: int}
public function getValidTransitions(): array
@return array<self>
resolved @return array<Nvl\Csv\Enums\CSVOperationStatusEnum>
public static function inProgress(): array
@return array<self>
resolved @return array<Nvl\Csv\Enums\CSVOperationStatusEnum>
public function isActive(): bool
@return bool
public function isFailure(): bool
@return bool
public function isSuccess(): bool
@return bool
public function isTerminal(): bool
@return bool
public function label(): string
@return string
public static function terminal(): array
@return array<self>
resolved @return array<Nvl\Csv\Enums\CSVOperationStatusEnum>
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Csv\Enums\CSVProcessingModeEnum

```text
enum Nvl\Csv\Enums\CSVProcessingModeEnum implements BackedEnum, UnitEnum: string
public const Nvl\Csv\Enums\CSVProcessingModeEnum BATCH=Nvl\Csv\Enums\CSVProcessingModeEnum::BATCH="batch"
public const Nvl\Csv\Enums\CSVProcessingModeEnum CHUNKED=Nvl\Csv\Enums\CSVProcessingModeEnum::CHUNKED="chunked"
public const Nvl\Csv\Enums\CSVProcessingModeEnum LAZY=Nvl\Csv\Enums\CSVProcessingModeEnum::LAZY="lazy"
public const Nvl\Csv\Enums\CSVProcessingModeEnum MEMORY=Nvl\Csv\Enums\CSVProcessingModeEnum::MEMORY="memory"
public const Nvl\Csv\Enums\CSVProcessingModeEnum QUEUE=Nvl\Csv\Enums\CSVProcessingModeEnum::QUEUE="queue"
public const Nvl\Csv\Enums\CSVProcessingModeEnum STREAM=Nvl\Csv\Enums\CSVProcessingModeEnum::STREAM="stream"
public function description(): string
@return string
public function getDefaultChunkSize(): int
@return int
public function getPerformanceCharacteristics(): array
@return array{speed: string, memory: string, scalability: string}
public function getRecommendedMemoryLimit(): int
@return int
public function isSuitableForLargeFiles(): bool
@return bool
public function label(): string
@return string
public function requiresQueue(): bool
@return bool
public function supportsFiltering(): bool
@return bool
public function supportsRandomAccess(): bool
@return bool
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Csv\Enums\CSVResponseCode

```text
enum Nvl\Csv\Enums\CSVResponseCode implements BackedEnum, Nvl\Support\Contracts\ResponseCode, UnitEnum: string
public const Nvl\Csv\Enums\CSVResponseCode CsvParseFailed=Nvl\Csv\Enums\CSVResponseCode::CsvParseFailed="csv_parse_failed"
public const Nvl\Csv\Enums\CSVResponseCode FileNotFound=Nvl\Csv\Enums\CSVResponseCode::FileNotFound="file_not_found"
public const Nvl\Csv\Enums\CSVResponseCode InvalidConfiguration=Nvl\Csv\Enums\CSVResponseCode::InvalidConfiguration="invalid_configuration"
public const Nvl\Csv\Enums\CSVResponseCode InvalidCsvInput=Nvl\Csv\Enums\CSVResponseCode::InvalidCsvInput="invalid_csv_input"
public const Nvl\Csv\Enums\CSVResponseCode MemoryLimitExceeded=Nvl\Csv\Enums\CSVResponseCode::MemoryLimitExceeded="memory_limit_exceeded"
public const Nvl\Csv\Enums\CSVResponseCode OperationFailed=Nvl\Csv\Enums\CSVResponseCode::OperationFailed="operation_failed"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Csv\Enums\CSVTypeEnum

```text
enum Nvl\Csv\Enums\CSVTypeEnum implements BackedEnum, UnitEnum: string
public const Nvl\Csv\Enums\CSVTypeEnum ARRAY=Nvl\Csv\Enums\CSVTypeEnum::ARRAY="array"
public const Nvl\Csv\Enums\CSVTypeEnum BOOLEAN=Nvl\Csv\Enums\CSVTypeEnum::BOOLEAN="boolean"
public const Nvl\Csv\Enums\CSVTypeEnum DATE=Nvl\Csv\Enums\CSVTypeEnum::DATE="date"
public const Nvl\Csv\Enums\CSVTypeEnum DATETIME=Nvl\Csv\Enums\CSVTypeEnum::DATETIME="datetime"
public const Nvl\Csv\Enums\CSVTypeEnum EMAIL=Nvl\Csv\Enums\CSVTypeEnum::EMAIL="email"
public const Nvl\Csv\Enums\CSVTypeEnum FLOAT=Nvl\Csv\Enums\CSVTypeEnum::FLOAT="float"
public const Nvl\Csv\Enums\CSVTypeEnum INTEGER=Nvl\Csv\Enums\CSVTypeEnum::INTEGER="integer"
public const Nvl\Csv\Enums\CSVTypeEnum JSON=Nvl\Csv\Enums\CSVTypeEnum::JSON="json"
public const Nvl\Csv\Enums\CSVTypeEnum NULLABLE_FLOAT=Nvl\Csv\Enums\CSVTypeEnum::NULLABLE_FLOAT="nullable_float"
public const Nvl\Csv\Enums\CSVTypeEnum NULLABLE_INTEGER=Nvl\Csv\Enums\CSVTypeEnum::NULLABLE_INTEGER="nullable_integer"
public const Nvl\Csv\Enums\CSVTypeEnum NULLABLE_STRING=Nvl\Csv\Enums\CSVTypeEnum::NULLABLE_STRING="nullable_string"
public const Nvl\Csv\Enums\CSVTypeEnum STRING=Nvl\Csv\Enums\CSVTypeEnum::STRING="string"
public function cast(mixed $value): mixed
@param mixed $value
@return mixed
@throws InvalidArgumentException
public function getDefaultValue(): mixed
@return mixed
public function getPhpType(): string
@return string
public function isNullable(): bool
@return bool
public function validate(mixed $value): bool
@param mixed $value
@return bool
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Csv\Exceptions\CSVConfigurationException

```text
final class Nvl\Csv\Exceptions\CSVConfigurationException extends Nvl\Csv\Exceptions\CSVException
public function getContext(): array
@return array<string, mixed>
public function getContextValue(string $key, mixed $default=null): mixed
@param string $key
@param mixed $default
@return mixed
public static function invalidChunkSize(int $size): self
public static function invalidDelimiter(string $delimiter): self
public static function invalidDisk(string $disk): self
public static function invalidEnclosure(string $enclosure): self
public static function invalidMemoryLimit(int $limit): self
public static function invalidProcessingMode(string $mode): self
public static function missingConfiguration(string $key): self
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function toArray(): array
@return array<string, mixed>
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
public function withContext(array $context): static
@param array<string, mixed> $context
@return static
resolved @return Nvl\Csv\Exceptions\CSVConfigurationException
```

## Nvl\Csv\Exceptions\CSVException

```text
abstract class Nvl\Csv\Exceptions\CSVException extends Exception implements Nvl\Support\Contracts\RespondableException
public function getContext(): array
@return array<string, mixed>
public function getContextValue(string $key, mixed $default=null): mixed
@param string $key
@param mixed $default
@return mixed
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function toArray(): array
@return array<string, mixed>
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
public function withContext(array $context): static
@param array<string, mixed> $context
@return static
resolved @return Nvl\Csv\Exceptions\CSVException
```

## Nvl\Csv\Exceptions\CSVFileNotFoundException

```text
final class Nvl\Csv\Exceptions\CSVFileNotFoundException extends Nvl\Csv\Exceptions\CSVException
public static function directoryNotFound(string $path): self
public static function fileEmpty(string $path): self
public static function fileNotFound(string $path): self
public static function fileNotFoundOnDisk(string $disk, string $path): self
public static function fileNotReadable(string $path): self
public function getContext(): array
@return array<string, mixed>
public function getContextValue(string $key, mixed $default=null): mixed
@param string $key
@param mixed $default
@return mixed
public function package(): string
public static function pathNotWritable(string $path): self
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function toArray(): array
@return array<string, mixed>
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
public function withContext(array $context): static
@param array<string, mixed> $context
@return static
resolved @return Nvl\Csv\Exceptions\CSVFileNotFoundException
```

## Nvl\Csv\Exceptions\CSVMemoryException

```text
final class Nvl\Csv\Exceptions\CSVMemoryException extends Nvl\Csv\Exceptions\CSVException
public static function allocationFailed(string $operation): self
public static function chunkSizeTooLarge(int $chunkSize, int $recommendedSize): self
public static function fileTooLarge(string $path, int $size, int $maxSize): self
public function getContext(): array
@return array<string, mixed>
public function getContextValue(string $key, mixed $default=null): mixed
@param string $key
@param mixed $default
@return mixed
public static function insufficientMemory(int $required, int $available): self
public static function memoryLimitExceeded(int $current, int $limit): self
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function toArray(): array
@return array<string, mixed>
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
public function withContext(array $context): static
@param array<string, mixed> $context
@return static
resolved @return Nvl\Csv\Exceptions\CSVMemoryException
```

## Nvl\Csv\Exceptions\CSVParseException

```text
final class Nvl\Csv\Exceptions\CSVParseException extends Nvl\Csv\Exceptions\CSVException
public static function cannotDetectDelimiter(): self
public static function columnCountMismatch(int $expected, int $actual, int $line): self
public function getContext(): array
@return array<string, mixed>
public function getContextValue(string $key, mixed $default=null): mixed
@param string $key
@param mixed $default
@return mixed
public static function headerMismatch(array $expected, array $actual): self
@param list<string> $expected
@param list<string> $actual
public static function invalidEncoding(string $encoding, ?int $line=null): self
public static function invalidHeaders(): self
public static function invalidStructure(string $reason, ?int $line=null): self
public static function missingRequiredColumn(string $column): self
public function package(): string
public static function parsingFailed(string $reason, ?int $line=null): self
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function toArray(): array
@return array<string, mixed>
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
public function withContext(array $context): static
@param array<string, mixed> $context
@return static
resolved @return Nvl\Csv\Exceptions\CSVParseException
```

## Nvl\Csv\Exceptions\CSVValidationException

```text
final class Nvl\Csv\Exceptions\CSVValidationException extends Nvl\Csv\Exceptions\CSVException
public static function duplicateValue(string $field, mixed $value, int $row): self
@param string $field
@param mixed $value
@param int $row
@return self
resolved @return Nvl\Csv\Exceptions\CSVValidationException
public static function fieldValidationFailed(string $field, mixed $value, string $rule): self
@param string $field
@param mixed $value
@param string $rule
@return self
resolved @return Nvl\Csv\Exceptions\CSVValidationException
public function getAffectedRows(): array
@return array<int>
public function getContext(): array
@return array<string, mixed>
public function getContextValue(string $key, mixed $default=null): mixed
@param string $key
@param mixed $default
@return mixed
public function getErrorCount(): int
@return int
public function getRowErrors(int $row): ?array
@param int $row
@return (array<string, array<string>> | null)
public function getValidationErrors(): array
@return array<int, array<string, array<string>>>
public static function invalidType(string $field, mixed $value, string $expectedType): self
@param string $field
@param mixed $value
@param string $expectedType
@return self
resolved @return Nvl\Csv\Exceptions\CSVValidationException
public static function missingRequiredFields(array $fields): self
@param array<int, string> $fields
@return self
resolved @return Nvl\Csv\Exceptions\CSVValidationException
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public static function requiredFieldMissing(string $field, int $row): self
@param string $field
@param int $row
@return self
resolved @return Nvl\Csv\Exceptions\CSVValidationException
public function responseCode(): ?string
public function responseHeaders(): array
@return array<string, string>
public static function rowValidationFailed(int $row, array $errors): self
@param int $row
@param array<string, array<string>> $errors
@return self
resolved @return Nvl\Csv\Exceptions\CSVValidationException
public function suggestedStatus(): int
public function toArray(): array
@return array<string, mixed>
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
public static function validationFailed(array $errors): self
@param array<int, array<string, array<string>>> $errors
@return self
resolved @return Nvl\Csv\Exceptions\CSVValidationException
public function withContext(array $context): static
@param array<string, mixed> $context
@return static
resolved @return Nvl\Csv\Exceptions\CSVValidationException
```

## Nvl\Csv\Filters\AndFilter

```text
final class Nvl\Csv\Filters\AndFilter extends Nvl\Csv\Filters\CSVFilter
public function __construct(array $filters): mixed
@param array<int, CSVFilter> $filters
resolved @param array<int, Nvl\Csv\Filters\CSVFilter> $filters
@return void
public function add(Nvl\Csv\Filters\CSVFilter $filter): self
@param CSVFilter $filter
resolved @param Nvl\Csv\Filters\CSVFilter $filter
@return self
resolved @return Nvl\Csv\Filters\AndFilter
public static function all(array $filters): Nvl\Csv\Filters\AndFilter
@param array<int, CSVFilter> $filters
resolved @param array<int, Nvl\Csv\Filters\CSVFilter> $filters
@return AndFilter
resolved @return Nvl\Csv\Filters\AndFilter
public function and(Nvl\Csv\Filters\CSVFilter $filter): Nvl\Csv\Filters\AndFilter
@param CSVFilter $filter
resolved @param Nvl\Csv\Filters\CSVFilter $filter
@return AndFilter
resolved @return Nvl\Csv\Filters\AndFilter
public static function any(array $filters): Nvl\Csv\Filters\OrFilter
@param array<int, CSVFilter> $filters
resolved @param array<int, Nvl\Csv\Filters\CSVFilter> $filters
@return OrFilter
resolved @return Nvl\Csv\Filters\OrFilter
public static function custom(callable $callback): Nvl\Csv\Filters\CustomFilter
@param callable $callback
@return CustomFilter
resolved @return Nvl\Csv\Filters\CustomFilter
public static function field(string $field): Nvl\Csv\Filters\FieldFilter
@param string $field
@return FieldFilter
resolved @return Nvl\Csv\Filters\FieldFilter
public function not(): Nvl\Csv\Filters\NotFilter
@return NotFilter
resolved @return Nvl\Csv\Filters\NotFilter
public function or(Nvl\Csv\Filters\CSVFilter $filter): Nvl\Csv\Filters\OrFilter
@param CSVFilter $filter
resolved @param Nvl\Csv\Filters\CSVFilter $filter
@return OrFilter
resolved @return Nvl\Csv\Filters\OrFilter
public function passes(array $row): bool
@param array<string, mixed> $row
@return bool
```

## Nvl\Csv\Filters\CSVFilter

```text
abstract class Nvl\Csv\Filters\CSVFilter
public static function all(array $filters): Nvl\Csv\Filters\AndFilter
@param array<int, CSVFilter> $filters
resolved @param array<int, Nvl\Csv\Filters\CSVFilter> $filters
@return AndFilter
resolved @return Nvl\Csv\Filters\AndFilter
public function and(Nvl\Csv\Filters\CSVFilter $filter): Nvl\Csv\Filters\AndFilter
@param CSVFilter $filter
resolved @param Nvl\Csv\Filters\CSVFilter $filter
@return AndFilter
resolved @return Nvl\Csv\Filters\AndFilter
public static function any(array $filters): Nvl\Csv\Filters\OrFilter
@param array<int, CSVFilter> $filters
resolved @param array<int, Nvl\Csv\Filters\CSVFilter> $filters
@return OrFilter
resolved @return Nvl\Csv\Filters\OrFilter
public static function custom(callable $callback): Nvl\Csv\Filters\CustomFilter
@param callable $callback
@return CustomFilter
resolved @return Nvl\Csv\Filters\CustomFilter
public static function field(string $field): Nvl\Csv\Filters\FieldFilter
@param string $field
@return FieldFilter
resolved @return Nvl\Csv\Filters\FieldFilter
public function not(): Nvl\Csv\Filters\NotFilter
@return NotFilter
resolved @return Nvl\Csv\Filters\NotFilter
public function or(Nvl\Csv\Filters\CSVFilter $filter): Nvl\Csv\Filters\OrFilter
@param CSVFilter $filter
resolved @param Nvl\Csv\Filters\CSVFilter $filter
@return OrFilter
resolved @return Nvl\Csv\Filters\OrFilter
public abstract function passes(array $row): bool
@param array<string, mixed> $row
@return bool
```

## Nvl\Csv\Filters\CustomFilter

```text
final class Nvl\Csv\Filters\CustomFilter extends Nvl\Csv\Filters\CSVFilter
public function __construct(callable $callback): mixed
@param callable $callback
@return void
public static function all(array $filters): Nvl\Csv\Filters\AndFilter
@param array<int, CSVFilter> $filters
resolved @param array<int, Nvl\Csv\Filters\CSVFilter> $filters
@return AndFilter
resolved @return Nvl\Csv\Filters\AndFilter
public function and(Nvl\Csv\Filters\CSVFilter $filter): Nvl\Csv\Filters\AndFilter
@param CSVFilter $filter
resolved @param Nvl\Csv\Filters\CSVFilter $filter
@return AndFilter
resolved @return Nvl\Csv\Filters\AndFilter
public static function any(array $filters): Nvl\Csv\Filters\OrFilter
@param array<int, CSVFilter> $filters
resolved @param array<int, Nvl\Csv\Filters\CSVFilter> $filters
@return OrFilter
resolved @return Nvl\Csv\Filters\OrFilter
public static function custom(callable $callback): Nvl\Csv\Filters\CustomFilter
@param callable $callback
@return CustomFilter
resolved @return Nvl\Csv\Filters\CustomFilter
public static function field(string $field): Nvl\Csv\Filters\FieldFilter
@param string $field
@return FieldFilter
resolved @return Nvl\Csv\Filters\FieldFilter
public function not(): Nvl\Csv\Filters\NotFilter
@return NotFilter
resolved @return Nvl\Csv\Filters\NotFilter
public function or(Nvl\Csv\Filters\CSVFilter $filter): Nvl\Csv\Filters\OrFilter
@param CSVFilter $filter
resolved @param Nvl\Csv\Filters\CSVFilter $filter
@return OrFilter
resolved @return Nvl\Csv\Filters\OrFilter
public function passes(array $row): bool
@param array<string, mixed> $row
@return bool
```

## Nvl\Csv\Filters\FieldFilter

```text
final class Nvl\Csv\Filters\FieldFilter extends Nvl\Csv\Filters\CSVFilter
public function __construct(string $field): mixed
@param string $field
@return void
public static function all(array $filters): Nvl\Csv\Filters\AndFilter
@param array<int, CSVFilter> $filters
resolved @param array<int, Nvl\Csv\Filters\CSVFilter> $filters
@return AndFilter
resolved @return Nvl\Csv\Filters\AndFilter
public function and(Nvl\Csv\Filters\CSVFilter $filter): Nvl\Csv\Filters\AndFilter
@param CSVFilter $filter
resolved @param Nvl\Csv\Filters\CSVFilter $filter
@return AndFilter
resolved @return Nvl\Csv\Filters\AndFilter
public static function any(array $filters): Nvl\Csv\Filters\OrFilter
@param array<int, CSVFilter> $filters
resolved @param array<int, Nvl\Csv\Filters\CSVFilter> $filters
@return OrFilter
resolved @return Nvl\Csv\Filters\OrFilter
public function between(mixed $min, mixed $max): self
@param mixed $min
@param mixed $max
@return self
resolved @return Nvl\Csv\Filters\FieldFilter
public function contains(string $value, bool $caseInsensitive=false): self
@param string $value
@param bool $caseInsensitive
@return self
resolved @return Nvl\Csv\Filters\FieldFilter
public static function custom(callable $callback): Nvl\Csv\Filters\CustomFilter
@param callable $callback
@return CustomFilter
resolved @return Nvl\Csv\Filters\CustomFilter
public function endsWith(string $value, bool $caseInsensitive=false): self
@param string $value
@param bool $caseInsensitive
@return self
resolved @return Nvl\Csv\Filters\FieldFilter
public function equals(mixed $value): self
@param mixed $value
@return self
resolved @return Nvl\Csv\Filters\FieldFilter
public static function field(string $field): Nvl\Csv\Filters\FieldFilter
@param string $field
@return FieldFilter
resolved @return Nvl\Csv\Filters\FieldFilter
public function greaterThan(mixed $value): self
@param mixed $value
@return self
resolved @return Nvl\Csv\Filters\FieldFilter
public function greaterThanOrEqual(mixed $value): self
@param mixed $value
@return self
resolved @return Nvl\Csv\Filters\FieldFilter
public function in(array $values): self
@param array<int, mixed> $values
@return self
resolved @return Nvl\Csv\Filters\FieldFilter
public function isEmpty(): self
@return self
resolved @return Nvl\Csv\Filters\FieldFilter
public function isNotEmpty(): self
@return self
resolved @return Nvl\Csv\Filters\FieldFilter
public function isNotNull(): self
@return self
resolved @return Nvl\Csv\Filters\FieldFilter
public function isNull(): self
@return self
resolved @return Nvl\Csv\Filters\FieldFilter
public function lessThan(mixed $value): self
@param mixed $value
@return self
resolved @return Nvl\Csv\Filters\FieldFilter
public function lessThanOrEqual(mixed $value): self
@param mixed $value
@return self
resolved @return Nvl\Csv\Filters\FieldFilter
public function matches(string $pattern): self
@param string $pattern
@return self
resolved @return Nvl\Csv\Filters\FieldFilter
public function not(): Nvl\Csv\Filters\NotFilter
@return NotFilter
resolved @return Nvl\Csv\Filters\NotFilter
public function notEquals(mixed $value): self
@param mixed $value
@return self
resolved @return Nvl\Csv\Filters\FieldFilter
public function notIn(array $values): self
@param array<int, mixed> $values
@return self
resolved @return Nvl\Csv\Filters\FieldFilter
public function or(Nvl\Csv\Filters\CSVFilter $filter): Nvl\Csv\Filters\OrFilter
@param CSVFilter $filter
resolved @param Nvl\Csv\Filters\CSVFilter $filter
@return OrFilter
resolved @return Nvl\Csv\Filters\OrFilter
public function passes(array $row): bool
@param array<string, mixed> $row
@return bool
public function startsWith(string $value, bool $caseInsensitive=false): self
@param string $value
@param bool $caseInsensitive
@return self
resolved @return Nvl\Csv\Filters\FieldFilter
```

## Nvl\Csv\Filters\NotFilter

```text
final class Nvl\Csv\Filters\NotFilter extends Nvl\Csv\Filters\CSVFilter
public function __construct(Nvl\Csv\Filters\CSVFilter $filter): mixed
@param CSVFilter $filter
resolved @param Nvl\Csv\Filters\CSVFilter $filter
@return void
public static function all(array $filters): Nvl\Csv\Filters\AndFilter
@param array<int, CSVFilter> $filters
resolved @param array<int, Nvl\Csv\Filters\CSVFilter> $filters
@return AndFilter
resolved @return Nvl\Csv\Filters\AndFilter
public function and(Nvl\Csv\Filters\CSVFilter $filter): Nvl\Csv\Filters\AndFilter
@param CSVFilter $filter
resolved @param Nvl\Csv\Filters\CSVFilter $filter
@return AndFilter
resolved @return Nvl\Csv\Filters\AndFilter
public static function any(array $filters): Nvl\Csv\Filters\OrFilter
@param array<int, CSVFilter> $filters
resolved @param array<int, Nvl\Csv\Filters\CSVFilter> $filters
@return OrFilter
resolved @return Nvl\Csv\Filters\OrFilter
public static function custom(callable $callback): Nvl\Csv\Filters\CustomFilter
@param callable $callback
@return CustomFilter
resolved @return Nvl\Csv\Filters\CustomFilter
public static function field(string $field): Nvl\Csv\Filters\FieldFilter
@param string $field
@return FieldFilter
resolved @return Nvl\Csv\Filters\FieldFilter
public function not(): Nvl\Csv\Filters\NotFilter
@return NotFilter
resolved @return Nvl\Csv\Filters\NotFilter
public function or(Nvl\Csv\Filters\CSVFilter $filter): Nvl\Csv\Filters\OrFilter
@param CSVFilter $filter
resolved @param Nvl\Csv\Filters\CSVFilter $filter
@return OrFilter
resolved @return Nvl\Csv\Filters\OrFilter
public function passes(array $row): bool
@param array<string, mixed> $row
@return bool
```

## Nvl\Csv\Filters\OrFilter

```text
final class Nvl\Csv\Filters\OrFilter extends Nvl\Csv\Filters\CSVFilter
public function __construct(array $filters): mixed
@param array<int, CSVFilter> $filters
resolved @param array<int, Nvl\Csv\Filters\CSVFilter> $filters
@return void
public function add(Nvl\Csv\Filters\CSVFilter $filter): self
@param CSVFilter $filter
resolved @param Nvl\Csv\Filters\CSVFilter $filter
@return self
resolved @return Nvl\Csv\Filters\OrFilter
public static function all(array $filters): Nvl\Csv\Filters\AndFilter
@param array<int, CSVFilter> $filters
resolved @param array<int, Nvl\Csv\Filters\CSVFilter> $filters
@return AndFilter
resolved @return Nvl\Csv\Filters\AndFilter
public function and(Nvl\Csv\Filters\CSVFilter $filter): Nvl\Csv\Filters\AndFilter
@param CSVFilter $filter
resolved @param Nvl\Csv\Filters\CSVFilter $filter
@return AndFilter
resolved @return Nvl\Csv\Filters\AndFilter
public static function any(array $filters): Nvl\Csv\Filters\OrFilter
@param array<int, CSVFilter> $filters
resolved @param array<int, Nvl\Csv\Filters\CSVFilter> $filters
@return OrFilter
resolved @return Nvl\Csv\Filters\OrFilter
public static function custom(callable $callback): Nvl\Csv\Filters\CustomFilter
@param callable $callback
@return CustomFilter
resolved @return Nvl\Csv\Filters\CustomFilter
public static function field(string $field): Nvl\Csv\Filters\FieldFilter
@param string $field
@return FieldFilter
resolved @return Nvl\Csv\Filters\FieldFilter
public function not(): Nvl\Csv\Filters\NotFilter
@return NotFilter
resolved @return Nvl\Csv\Filters\NotFilter
public function or(Nvl\Csv\Filters\CSVFilter $filter): Nvl\Csv\Filters\OrFilter
@param CSVFilter $filter
resolved @param Nvl\Csv\Filters\CSVFilter $filter
@return OrFilter
resolved @return Nvl\Csv\Filters\OrFilter
public function passes(array $row): bool
@param array<string, mixed> $row
@return bool
```

## Nvl\Csv\Services\CSVAsyncProcessor

```text
final class Nvl\Csv\Services\CSVAsyncProcessor
public function __construct(Nvl\Csv\Services\CSVWorkStore $workStore, Illuminate\Bus\BatchRepository $batches, Nvl\Csv\Services\CSVHandlerRegistry $handlers, Nvl\Support\Ownership\Services\OwnershipContextResolver $ownership, Nvl\Support\Ownership\Contracts\OwnershipQueueContext $ownershipQueue): mixed
public function cancelBatch(string $batchId): bool
public function fromFile(string $path): self
public function getBatchStatus(string $batchId): array
@return array<string, mixed>
public static function make(): self
public function mapField(string $source, string $target, ?Nvl\Csv\ValueObjects\CSVFieldMapping $mapping=null): self
public function onBatchComplete(Closure $callback): never
public function onComplete(Closure $callback): never
public function onProgress(Closure $callback): never
public function processAsync(): Illuminate\Bus\Batch
public function processRow(Closure $processor): never
public function usingHandler(string $alias, int $version=1): self
public function withChunkSize(int $size): self
public function withOptions(array $options): self
@param array<string, mixed> $options
```

## Nvl\Csv\Services\CSVHandlerRegistry

```text
final class Nvl\Csv\Services\CSVHandlerRegistry
public function __construct(Illuminate\Contracts\Container\Container $container): mixed
public function has(string $alias, int $version=1): bool
public function register(string $alias, string $handlerClass, int $version=1): void
public function resolve(string $alias, int $version=1): Nvl\Csv\Contracts\CSVRowHandler
```

## Nvl\Csv\Services\CsvAnalyzer

```text
final class Nvl\Csv\Services\CsvAnalyzer
public function analyze(string $filePath, array $options=[]): array
@param array<string, mixed> $options
@return array<string, mixed>
public function analyzeDisk(string $disk, string $path): array
@param string $disk
@param string $path
@return array<string, mixed>
@throws CSVFileNotFoundException
resolved @throws Nvl\Csv\Exceptions\CSVFileNotFoundException
@throws RuntimeException
@throws Exception
@throws DivisionByZeroError
public function quickAnalyze(string $filePath): array
@param string $filePath
@return array<string, mixed>
@throws CSVFileNotFoundException
resolved @throws Nvl\Csv\Exceptions\CSVFileNotFoundException
@throws RuntimeException
```

## Nvl\Csv\Services\CsvExporter

```text
final class Nvl\Csv\Services\CsvExporter
@throws CSVConfigurationException
resolved @throws Nvl\Csv\Exceptions\CSVConfigurationException
@throws CSVMemoryException
resolved @throws Nvl\Csv\Exceptions\CSVMemoryException
@throws RuntimeException
public function __construct(?Nvl\Csv\ValueObjects\CSVConfiguration $configuration=null): mixed
@param (CSVConfiguration | null) $configuration
resolved @param (Nvl\Csv\ValueObjects\CSVConfiguration | null) $configuration
@return void
public function chunked(int $chunkSize=1000): self
@param int $chunkSize
@return self
resolved @return Nvl\Csv\Services\CsvExporter
public function configure(Nvl\Csv\ValueObjects\CSVConfiguration $configuration): self
@param CSVConfiguration $configuration
resolved @param Nvl\Csv\ValueObjects\CSVConfiguration $configuration
@return self
resolved @return Nvl\Csv\Services\CsvExporter
public function disk(string $disk): self
@param string $disk
@return self
resolved @return Nvl\Csv\Services\CsvExporter
@throws Exception
public function export(iterable $rows, array $options): string
@param iterable<array-key, (array<string, mixed> | Model)> $rows
resolved @param iterable<array-key, (array<string, mixed> | Illuminate\Database\Eloquent\Model)> $rows
@param array<string, mixed> $options
public function fields(array $fields): self
@param array<(string | Closure)> $fields
@return self
resolved @return Nvl\Csv\Services\CsvExporter
@throws Exception
public function filename(string $filename): self
@param string $filename
@return self
resolved @return Nvl\Csv\Services\CsvExporter
@throws Exception
public function fromArray(array $data): array
@param list<array<string, mixed>> $data
@return array<string, mixed>
@throws RuntimeException
@throws CSVConfigurationException
resolved @throws Nvl\Csv\Exceptions\CSVConfigurationException
public function fromCollection(Illuminate\Support\Collection $collection): array
@param Collection<array-key, mixed> $collection
resolved @param Illuminate\Support\Collection<array-key, mixed> $collection
@return array<string, mixed>
@throws RuntimeException
@throws CSVConfigurationException
resolved @throws Nvl\Csv\Exceptions\CSVConfigurationException
public function fromQuery(Illuminate\Database\Eloquent\Builder $query): array
@template TModel of Model
resolved @template TModel of Illuminate\Database\Eloquent\Model
@param Builder<TModel> $query
resolved @param Illuminate\Database\Eloquent\Builder<TModel> $query
@return array<string, mixed>
@throws RuntimeException
@throws CSVConfigurationException
resolved @throws Nvl\Csv\Exceptions\CSVConfigurationException
@throws CSVMemoryException
resolved @throws Nvl\Csv\Exceptions\CSVMemoryException
template Nvl\Csv\Services\CsvExporter::TModel of Illuminate\Database\Eloquent\Model
public function headings(array $headings): self
@param array<string> $headings
@return self
resolved @return Nvl\Csv\Services\CsvExporter
@throws Exception
public static function make(): self
@return self
resolved @return Nvl\Csv\Services\CsvExporter
public function path(string $path): self
@param string $path
@return self
resolved @return Nvl\Csv\Services\CsvExporter
@throws Exception
public function stream(Closure $dataProvider): array
@return array<string, mixed>
public function withOptions(array $options): self
@param array<string, mixed> $options
```

## Nvl\Csv\Services\CsvImporter

```text
final class Nvl\Csv\Services\CsvImporter
public function __construct(?Nvl\Csv\ValueObjects\CSVConfiguration $configuration=null): mixed
@param (CSVConfiguration | null) $configuration
resolved @param (Nvl\Csv\ValueObjects\CSVConfiguration | null) $configuration
@return void
public function batch(int $batchSize, Closure $batchProcessor): array
@return array<string, mixed>
public function configure(Nvl\Csv\ValueObjects\CSVConfiguration $configuration): self
@param CSVConfiguration $configuration
resolved @param Nvl\Csv\ValueObjects\CSVConfiguration $configuration
@return self
resolved @return Nvl\Csv\Services\CsvImporter
public function detectDuplicates(string $field): self
@param string $field
@return self
resolved @return Nvl\Csv\Services\CsvImporter
public function fromDisk(string $disk, string $path): self
@param string $disk
@param string $path
@return self
resolved @return Nvl\Csv\Services\CsvImporter
@throws CSVFileNotFoundException
resolved @throws Nvl\Csv\Exceptions\CSVFileNotFoundException
@throws Exception
public function fromFile(string $path): self
@param string $path
@return self
resolved @return Nvl\Csv\Services\CsvImporter
@throws CSVFileNotFoundException
resolved @throws Nvl\Csv\Exceptions\CSVFileNotFoundException
@throws Exception
public function getDuplicateStrategy(): Nvl\Csv\Enums\CSVDuplicateStrategyEnum
@return CSVDuplicateStrategyEnum
resolved @return Nvl\Csv\Enums\CSVDuplicateStrategyEnum
public function getEncoding(): Nvl\Csv\Enums\CSVEncodingEnum
@return CSVEncodingEnum
resolved @return Nvl\Csv\Enums\CSVEncodingEnum
public function getProgress(): ?array
@return (array<string, mixed> | null)
public function import(string $path, array $options, Nvl\Csv\Contracts\CSVRowHandler $handler): array
@param array<string, mixed> $options
@return array<string, mixed>
public static function make(): self
@return self
resolved @return Nvl\Csv\Services\CsvImporter
public function mapField(string $csvField, string $targetField, ?Nvl\Csv\ValueObjects\CSVFieldMapping $mapping=null): self
@param string $csvField
@param string $targetField
@param (CSVFieldMapping | null) $mapping
resolved @param (Nvl\Csv\ValueObjects\CSVFieldMapping | null) $mapping
@return self
resolved @return Nvl\Csv\Services\CsvImporter
public function mapFields(array $mappings): self
@param array<string, (CSVFieldMapping | string)> $mappings
resolved @param array<string, (Nvl\Csv\ValueObjects\CSVFieldMapping | string)> $mappings
@return self
resolved @return Nvl\Csv\Services\CsvImporter
public function onConnection(?string $connection): self
@param (string | null) $connection
@return self
resolved @return Nvl\Csv\Services\CsvImporter
public function onError(Closure $handler): self
@param Closure $handler
@return self
resolved @return Nvl\Csv\Services\CsvImporter
public function onProgress(Closure $callback): self
@param Closure $callback
@return self
resolved @return Nvl\Csv\Services\CsvImporter
public function processRow(Closure $processor): self
@param Closure $processor
@return self
resolved @return Nvl\Csv\Services\CsvImporter
public function stopOnError(bool $stop=true): self
@param bool $stop
@return self
resolved @return Nvl\Csv\Services\CsvImporter
public function stream(): Generator
@return Generator<int, array<string, mixed>>
@throws CSVConfigurationException
resolved @throws Nvl\Csv\Exceptions\CSVConfigurationException
@throws CSVFileNotFoundException
resolved @throws Nvl\Csv\Exceptions\CSVFileNotFoundException
@throws RuntimeException
@throws CSVParseException
resolved @throws Nvl\Csv\Exceptions\CSVParseException
@throws CSVMemoryException
resolved @throws Nvl\Csv\Exceptions\CSVMemoryException
@throws CSVValidationException
resolved @throws Nvl\Csv\Exceptions\CSVValidationException
@throws InvalidArgumentException
@throws DivisionByZeroError
@throws Exception
public function withDuplicateStrategy(Nvl\Csv\Enums\CSVDuplicateStrategyEnum $strategy): self
@param CSVDuplicateStrategyEnum $strategy
resolved @param Nvl\Csv\Enums\CSVDuplicateStrategyEnum $strategy
@return self
resolved @return Nvl\Csv\Services\CsvImporter
public function withEncoding(Nvl\Csv\Enums\CSVEncodingEnum $encoding): self
@param CSVEncodingEnum $encoding
resolved @param Nvl\Csv\Enums\CSVEncodingEnum $encoding
@return self
resolved @return Nvl\Csv\Services\CsvImporter
public function withErrorThreshold(Nvl\Csv\Enums\CSVErrorLevelEnum $threshold): self
@param CSVErrorLevelEnum $threshold
resolved @param Nvl\Csv\Enums\CSVErrorLevelEnum $threshold
@return self
resolved @return Nvl\Csv\Services\CsvImporter
public function withOptions(array $options): self
@param array<string, mixed> $options
public function withTransaction(bool $use=true): self
@param bool $use
@return self
resolved @return Nvl\Csv\Services\CsvImporter
```

## Nvl\Csv\Transformers\CSVTransformer

```text
abstract class Nvl\Csv\Transformers\CSVTransformer
public static function chain(array $transformers): Nvl\Csv\Transformers\ChainedTransformer
@param array<int, CSVTransformer> $transformers
resolved @param array<int, Nvl\Csv\Transformers\CSVTransformer> $transformers
@return ChainedTransformer
resolved @return Nvl\Csv\Transformers\ChainedTransformer
public function reverseTransform(mixed $value, array $context=[]): mixed
@param mixed $value
@param array<string, mixed> $context
@return mixed
public function supportsReverseTransform(): bool
@return bool
public abstract function transform(mixed $value, array $context=[]): mixed
@param mixed $value
@param array<string, mixed> $context
@return mixed
public static function when(callable $condition, Nvl\Csv\Transformers\CSVTransformer $transformer): Nvl\Csv\Transformers\ConditionalTransformer
@param callable $condition
@param CSVTransformer $transformer
resolved @param Nvl\Csv\Transformers\CSVTransformer $transformer
@return ConditionalTransformer
resolved @return Nvl\Csv\Transformers\ConditionalTransformer
```

## Nvl\Csv\Transformers\ChainedTransformer

```text
final class Nvl\Csv\Transformers\ChainedTransformer extends Nvl\Csv\Transformers\CSVTransformer
public function __construct(array $transformers): mixed
@param array<int, CSVTransformer> $transformers
resolved @param array<int, Nvl\Csv\Transformers\CSVTransformer> $transformers
@return void
public function add(Nvl\Csv\Transformers\CSVTransformer $transformer): self
@param CSVTransformer $transformer
resolved @param Nvl\Csv\Transformers\CSVTransformer $transformer
@return self
resolved @return Nvl\Csv\Transformers\ChainedTransformer
public static function chain(array $transformers): Nvl\Csv\Transformers\ChainedTransformer
@param array<int, CSVTransformer> $transformers
resolved @param array<int, Nvl\Csv\Transformers\CSVTransformer> $transformers
@return ChainedTransformer
resolved @return Nvl\Csv\Transformers\ChainedTransformer
public function reverseTransform(mixed $value, array $context=[]): mixed
@param mixed $value
@param array<string, mixed> $context
@return mixed
public function supportsReverseTransform(): bool
@return bool
public function transform(mixed $value, array $context=[]): mixed
@param mixed $value
@param array<string, mixed> $context
@return mixed
public static function when(callable $condition, Nvl\Csv\Transformers\CSVTransformer $transformer): Nvl\Csv\Transformers\ConditionalTransformer
@param callable $condition
@param CSVTransformer $transformer
resolved @param Nvl\Csv\Transformers\CSVTransformer $transformer
@return ConditionalTransformer
resolved @return Nvl\Csv\Transformers\ConditionalTransformer
```

## Nvl\Csv\Transformers\ConditionalTransformer

```text
final class Nvl\Csv\Transformers\ConditionalTransformer extends Nvl\Csv\Transformers\CSVTransformer
public function __construct(callable $condition, Nvl\Csv\Transformers\CSVTransformer $transformer): mixed
@param callable $condition
@param CSVTransformer $transformer
resolved @param Nvl\Csv\Transformers\CSVTransformer $transformer
@return void
public static function chain(array $transformers): Nvl\Csv\Transformers\ChainedTransformer
@param array<int, CSVTransformer> $transformers
resolved @param array<int, Nvl\Csv\Transformers\CSVTransformer> $transformers
@return ChainedTransformer
resolved @return Nvl\Csv\Transformers\ChainedTransformer
public function else(Nvl\Csv\Transformers\CSVTransformer $transformer): self
@param CSVTransformer $transformer
resolved @param Nvl\Csv\Transformers\CSVTransformer $transformer
@return self
resolved @return Nvl\Csv\Transformers\ConditionalTransformer
public function reverseTransform(mixed $value, array $context=[]): mixed
@param mixed $value
@param array<string, mixed> $context
@return mixed
public function supportsReverseTransform(): bool
@return bool
public function transform(mixed $value, array $context=[]): mixed
@param mixed $value
@param array<string, mixed> $context
@return mixed
public static function when(callable $condition, Nvl\Csv\Transformers\CSVTransformer $transformer): Nvl\Csv\Transformers\ConditionalTransformer
@param callable $condition
@param CSVTransformer $transformer
resolved @param Nvl\Csv\Transformers\CSVTransformer $transformer
@return ConditionalTransformer
resolved @return Nvl\Csv\Transformers\ConditionalTransformer
```

## Nvl\Csv\Transformers\DateTransformer

```text
final class Nvl\Csv\Transformers\DateTransformer extends Nvl\Csv\Transformers\CSVTransformer
public function addDays(int $days): self
@param int $days
@return self
resolved @return Nvl\Csv\Transformers\DateTransformer
public function addMonths(int $months): self
@param int $months
@return self
resolved @return Nvl\Csv\Transformers\DateTransformer
public function addYears(int $years): self
@param int $years
@return self
resolved @return Nvl\Csv\Transformers\DateTransformer
public static function age(): self
@return self
resolved @return Nvl\Csv\Transformers\DateTransformer
public static function chain(array $transformers): Nvl\Csv\Transformers\ChainedTransformer
@param array<int, CSVTransformer> $transformers
resolved @param array<int, Nvl\Csv\Transformers\CSVTransformer> $transformers
@return ChainedTransformer
resolved @return Nvl\Csv\Transformers\ChainedTransformer
public function default(?string $value): self
@param (string | null) $value
@return self
resolved @return Nvl\Csv\Transformers\DateTransformer
public function endOfDay(bool $end=true): self
@param bool $end
@return self
resolved @return Nvl\Csv\Transformers\DateTransformer
public static function format(string $inputFormat, string $outputFormat="Y-m-d"): self
@param string $inputFormat
@param string $outputFormat
@return self
resolved @return Nvl\Csv\Transformers\DateTransformer
public function inputFormat(string $format): self
@param string $format
@return self
resolved @return Nvl\Csv\Transformers\DateTransformer
public static function iso(): self
@return self
resolved @return Nvl\Csv\Transformers\DateTransformer
public function outputFormat(string $format): self
@param string $format
@return self
resolved @return Nvl\Csv\Transformers\DateTransformer
public function reverseTransform(mixed $value, array $context=[]): mixed
@param mixed $value
@param array<string, mixed> $context
@return mixed
public function startOfDay(bool $start=true): self
@param bool $start
@return self
resolved @return Nvl\Csv\Transformers\DateTransformer
public function supportsReverseTransform(): bool
@return bool
public static function timestamp(): self
@return self
resolved @return Nvl\Csv\Transformers\DateTransformer
public function timezone(string $timezone): self
@param string $timezone
@return self
resolved @return Nvl\Csv\Transformers\DateTransformer
public function transform(mixed $value, array $context=[]): mixed
@param mixed $value
@param array<string, mixed> $context
@return mixed
public static function when(callable $condition, Nvl\Csv\Transformers\CSVTransformer $transformer): Nvl\Csv\Transformers\ConditionalTransformer
@param callable $condition
@param CSVTransformer $transformer
resolved @param Nvl\Csv\Transformers\CSVTransformer $transformer
@return ConditionalTransformer
resolved @return Nvl\Csv\Transformers\ConditionalTransformer
```

## Nvl\Csv\Transformers\NumericTransformer

```text
final class Nvl\Csv\Transformers\NumericTransformer extends Nvl\Csv\Transformers\CSVTransformer
public function absolute(bool $absolute=true): self
@param bool $absolute
@return self
resolved @return Nvl\Csv\Transformers\NumericTransformer
public function add(float $value): self
@param float $value
@return self
resolved @return Nvl\Csv\Transformers\NumericTransformer
public function ceil(bool $ceil=true): self
@param bool $ceil
@return self
resolved @return Nvl\Csv\Transformers\NumericTransformer
public static function chain(array $transformers): Nvl\Csv\Transformers\ChainedTransformer
@param array<int, CSVTransformer> $transformers
resolved @param array<int, Nvl\Csv\Transformers\CSVTransformer> $transformers
@return ChainedTransformer
resolved @return Nvl\Csv\Transformers\ChainedTransformer
public static function currency(int $precision=2): self
@param int $precision
@return self
resolved @return Nvl\Csv\Transformers\NumericTransformer
public function default(float $value): self
@param float $value
@return self
resolved @return Nvl\Csv\Transformers\NumericTransformer
public function divide(float $value): self
@param float $value
@return self
resolved @return Nvl\Csv\Transformers\NumericTransformer
@throws InvalidArgumentException
public function floor(bool $floor=true): self
@param bool $floor
@return self
resolved @return Nvl\Csv\Transformers\NumericTransformer
public static function integer(): self
@return self
resolved @return Nvl\Csv\Transformers\NumericTransformer
public function max(float $max): self
@param float $max
@return self
resolved @return Nvl\Csv\Transformers\NumericTransformer
public function min(float $min): self
@param float $min
@return self
resolved @return Nvl\Csv\Transformers\NumericTransformer
public function multiply(float $value): self
@param float $value
@return self
resolved @return Nvl\Csv\Transformers\NumericTransformer
public static function percentage(int $precision=2): self
@param int $precision
@return self
resolved @return Nvl\Csv\Transformers\NumericTransformer
public static function positive(): self
@return self
resolved @return Nvl\Csv\Transformers\NumericTransformer
public function precision(int $precision): self
@param int $precision
@return self
resolved @return Nvl\Csv\Transformers\NumericTransformer
public function reverseTransform(mixed $value, array $context=[]): mixed
@param mixed $value
@param array<string, mixed> $context
@return mixed
public function round(bool $round=true): self
@param bool $round
@return self
resolved @return Nvl\Csv\Transformers\NumericTransformer
public function subtract(float $value): self
@param float $value
@return self
resolved @return Nvl\Csv\Transformers\NumericTransformer
public function supportsReverseTransform(): bool
@return bool
public function transform(mixed $value, array $context=[]): mixed
@param mixed $value
@param array<string, mixed> $context
@return mixed
public static function when(callable $condition, Nvl\Csv\Transformers\CSVTransformer $transformer): Nvl\Csv\Transformers\ConditionalTransformer
@param callable $condition
@param CSVTransformer $transformer
resolved @param Nvl\Csv\Transformers\CSVTransformer $transformer
@return ConditionalTransformer
resolved @return Nvl\Csv\Transformers\ConditionalTransformer
```

## Nvl\Csv\Transformers\StringTransformer

```text
final class Nvl\Csv\Transformers\StringTransformer extends Nvl\Csv\Transformers\CSVTransformer
public const mixed CASE_CAMEL="camel"
public const mixed CASE_KEBAB="kebab"
public const mixed CASE_LOWER="lower"
public const mixed CASE_SNAKE="snake"
public const mixed CASE_TITLE="title"
public const mixed CASE_UPPER="upper"
public static function chain(array $transformers): Nvl\Csv\Transformers\ChainedTransformer
@param array<int, CSVTransformer> $transformers
resolved @param array<int, Nvl\Csv\Transformers\CSVTransformer> $transformers
@return ChainedTransformer
resolved @return Nvl\Csv\Transformers\ChainedTransformer
public function default(string $value): self
@param string $value
@return self
resolved @return Nvl\Csv\Transformers\StringTransformer
public static function lowercase(): self
@return self
resolved @return Nvl\Csv\Transformers\StringTransformer
public function maxLength(int $length, string $suffix="..."): self
@param int $length
@param string $suffix
@return self
resolved @return Nvl\Csv\Transformers\StringTransformer
public function prefix(string $prefix): self
@param string $prefix
@return self
resolved @return Nvl\Csv\Transformers\StringTransformer
public function replace(string $search, string $replace): self
@param string $search
@param string $replace
@return self
resolved @return Nvl\Csv\Transformers\StringTransformer
public function reverseTransform(mixed $value, array $context=[]): mixed
@param mixed $value
@param array<string, mixed> $context
@return mixed
public static function sanitize(): self
@return self
resolved @return Nvl\Csv\Transformers\StringTransformer
public static function slug(): self
@return self
resolved @return Nvl\Csv\Transformers\StringTransformer
public function suffix(string $suffix): self
@param string $suffix
@return self
resolved @return Nvl\Csv\Transformers\StringTransformer
public function supportsReverseTransform(): bool
@return bool
public function toCase(string $case): self
@param string $case
@return self
resolved @return Nvl\Csv\Transformers\StringTransformer
public function transform(mixed $value, array $context=[]): mixed
@param mixed $value
@param array<string, mixed> $context
@return mixed
public function trim(bool $trim=true): self
@param bool $trim
@return self
resolved @return Nvl\Csv\Transformers\StringTransformer
public static function uppercase(): self
@return self
resolved @return Nvl\Csv\Transformers\StringTransformer
public static function when(callable $condition, Nvl\Csv\Transformers\CSVTransformer $transformer): Nvl\Csv\Transformers\ConditionalTransformer
@param callable $condition
@param CSVTransformer $transformer
resolved @param Nvl\Csv\Transformers\CSVTransformer $transformer
@return ConditionalTransformer
resolved @return Nvl\Csv\Transformers\ConditionalTransformer
```

## Nvl\Csv\Validators\CSVFieldValidator

```text
final class Nvl\Csv\Validators\CSVFieldValidator extends Nvl\Csv\Validators\CSVValidator
public static function boolean(): self
@return self
resolved @return Nvl\Csv\Validators\CSVFieldValidator
public function clearMessages(): void
public function custom(callable $validator): self
@param callable $validator
@return self
resolved @return Nvl\Csv\Validators\CSVFieldValidator
public static function date(string $format="Y-m-d"): self
@param string $format
@return self
resolved @return Nvl\Csv\Validators\CSVFieldValidator
public static function email(): self
@return self
resolved @return Nvl\Csv\Validators\CSVFieldValidator
public function getErrors(): array
@return array<string>
public function getWarnings(): array
@return array<string>
public function in(array $values): self
@param array<int, mixed> $values
@return self
resolved @return Nvl\Csv\Validators\CSVFieldValidator
public static function integer(?int $min=null, ?int $max=null): self
@param (int | null) $min
@param (int | null) $max
@return self
resolved @return Nvl\Csv\Validators\CSVFieldValidator
public function length(?int $min=null, ?int $max=null): self
@param (int | null) $min
@param (int | null) $max
@return self
resolved @return Nvl\Csv\Validators\CSVFieldValidator
public function nullable(bool $nullable=true): self
@param bool $nullable
@return self
resolved @return Nvl\Csv\Validators\CSVFieldValidator
public static function numeric(?float $min=null, ?float $max=null): self
@param (float | null) $min
@param (float | null) $max
@return self
resolved @return Nvl\Csv\Validators\CSVFieldValidator
public function pattern(string $pattern, ?string $message=null): self
@param string $pattern
@param (string | null) $message
@return self
resolved @return Nvl\Csv\Validators\CSVFieldValidator
public static function phone(): self
@return self
resolved @return Nvl\Csv\Validators\CSVFieldValidator
public function range(?float $min=null, ?float $max=null): self
@param (float | null) $min
@param (float | null) $max
@return self
resolved @return Nvl\Csv\Validators\CSVFieldValidator
public function required(bool $required=true): self
@param bool $required
@return self
resolved @return Nvl\Csv\Validators\CSVFieldValidator
public function type(Nvl\Csv\Enums\CSVTypeEnum $type): self
@param CSVTypeEnum $type
resolved @param Nvl\Csv\Enums\CSVTypeEnum $type
@return self
resolved @return Nvl\Csv\Validators\CSVFieldValidator
public static function url(): self
@return self
resolved @return Nvl\Csv\Validators\CSVFieldValidator
public function validate(mixed $value, array $context=[]): bool
@param mixed $value
@param array<string, mixed> $context
@return bool
```

## Nvl\Csv\Validators\CSVRowValidator

```text
final class Nvl\Csv\Validators\CSVRowValidator extends Nvl\Csv\Validators\CSVValidator
public function addFieldMapping(string $field, Nvl\Csv\ValueObjects\CSVFieldMapping $mapping): self
@param string $field
@param CSVFieldMapping $mapping
resolved @param Nvl\Csv\ValueObjects\CSVFieldMapping $mapping
@return self
resolved @return Nvl\Csv\Validators\CSVRowValidator
public function addFieldValidator(string $field, Nvl\Csv\Validators\CSVFieldValidator $validator): self
@param string $field
@param CSVFieldValidator $validator
resolved @param Nvl\Csv\Validators\CSVFieldValidator $validator
@return self
resolved @return Nvl\Csv\Validators\CSVRowValidator
public function addRowValidator(Closure $validator): self
@param Closure $validator
@return self
resolved @return Nvl\Csv\Validators\CSVRowValidator
public function clearMessages(): void
public function getErrors(): array
@return array<string>
public function getWarnings(): array
@return array<string>
public function validate(mixed $value, array $context=[]): bool
@param mixed $value
@param array<string, mixed> $context
@return bool
public function validateCompleteness(array $row, array $requiredFields): bool
@param array<string, mixed> $row
@param array<int, string> $requiredFields
@return bool
public function validateDependencies(array $row): bool
@param array<string, mixed> $row
@return bool
public function validateUniqueness(array $row, array $existingRows, array $uniqueFields): bool
@param array<string, mixed> $row
@param array<int, array<string, mixed>> $existingRows
@param array<int, string> $uniqueFields
@return bool
```

## Nvl\Csv\ValueObjects\CSVConfiguration

```text
final readonly class Nvl\Csv\ValueObjects\CSVConfiguration
public function __construct(string $delimiter=",", string $enclosure="\"", string $escape="", string $lineEnding="\n", bool $includeBOM=false, bool $includeHeaders=true, bool $includeIndex=false, ?int $chunkSize=null, Nvl\Csv\Enums\CSVProcessingModeEnum $processingMode=\Nvl\Csv\Enums\CSVProcessingModeEnum::MEMORY, ?Nvl\Csv\Enums\CSVExportFormatEnum $exportFormat=null, ?string $encoding="UTF-8", ?int $memoryLimit=null, bool $strictMode=false, bool $skipEmptyRows=true, bool $trimValues=true): mixed
@param string $delimiter
@param string $enclosure
@param string $escape
@param string $lineEnding
@param bool $includeBOM
@param bool $includeHeaders
@param bool $includeIndex
@param (int | null) $chunkSize
@param CSVProcessingModeEnum $processingMode
resolved @param Nvl\Csv\Enums\CSVProcessingModeEnum $processingMode
@param (CSVExportFormatEnum | null) $exportFormat
resolved @param (Nvl\Csv\Enums\CSVExportFormatEnum | null) $exportFormat
@param (string | null) $encoding
@param (int | null) $memoryLimit
@param bool $strictMode
@param bool $skipEmptyRows
@param bool $trimValues
public static function default(): self
@return self
resolved @return Nvl\Csv\ValueObjects\CSVConfiguration
public static function excel(): self
@return self
resolved @return Nvl\Csv\ValueObjects\CSVConfiguration
public static function fromDelimiter(Nvl\Csv\Enums\CSVDelimiterEnum $delimiter): self
@param CSVDelimiterEnum $delimiter
resolved @param Nvl\Csv\Enums\CSVDelimiterEnum $delimiter
@return self
resolved @return Nvl\Csv\ValueObjects\CSVConfiguration
public static function fromFormat(Nvl\Csv\Enums\CSVExportFormatEnum $format): self
@param CSVExportFormatEnum $format
resolved @param Nvl\Csv\Enums\CSVExportFormatEnum $format
@return self
resolved @return Nvl\Csv\ValueObjects\CSVConfiguration
public function getEffectiveChunkSize(): int
@return int
public function isChunked(): bool
@return bool
public function isStreaming(): bool
@return bool
public static function largeFile(): self
@return self
resolved @return Nvl\Csv\ValueObjects\CSVConfiguration
public function toArray(): array
@return array<string, mixed>
public function withChunkSize(int $chunkSize): self
@param int $chunkSize
@return self
resolved @return Nvl\Csv\ValueObjects\CSVConfiguration
public function withDelimiter(string $delimiter): self
@param string $delimiter
@return self
resolved @return Nvl\Csv\ValueObjects\CSVConfiguration
public function withIncludeIndex(bool $includeIndex=true): self
@param bool $includeIndex
@return self
resolved @return Nvl\Csv\ValueObjects\CSVConfiguration
public function withProcessingMode(Nvl\Csv\Enums\CSVProcessingModeEnum $mode): self
@param CSVProcessingModeEnum $mode
resolved @param Nvl\Csv\Enums\CSVProcessingModeEnum $mode
@return self
resolved @return Nvl\Csv\ValueObjects\CSVConfiguration
public readonly ?int $chunkSize
@param (int | null) $chunkSize
public readonly string $delimiter
@param string $delimiter
public readonly string $enclosure
@param string $enclosure
public readonly ?string $encoding
@param (string | null) $encoding
public readonly string $escape
@param string $escape
public readonly ?Nvl\Csv\Enums\CSVExportFormatEnum $exportFormat
@param (CSVExportFormatEnum | null) $exportFormat
resolved @param (Nvl\Csv\Enums\CSVExportFormatEnum | null) $exportFormat
public readonly bool $includeBOM
@param bool $includeBOM
public readonly bool $includeHeaders
@param bool $includeHeaders
public readonly bool $includeIndex
@param bool $includeIndex
public readonly string $lineEnding
@param string $lineEnding
public readonly ?int $memoryLimit
@param (int | null) $memoryLimit
public readonly Nvl\Csv\Enums\CSVProcessingModeEnum $processingMode
@param CSVProcessingModeEnum $processingMode
resolved @param Nvl\Csv\Enums\CSVProcessingModeEnum $processingMode
public readonly bool $skipEmptyRows
@param bool $skipEmptyRows
public readonly bool $strictMode
@param bool $strictMode
public readonly bool $trimValues
@param bool $trimValues
```

## Nvl\Csv\ValueObjects\CSVFieldMapping

```text
final readonly class Nvl\Csv\ValueObjects\CSVFieldMapping
public function __construct(string $sourceField, string $targetField, ?Nvl\Csv\Enums\CSVTypeEnum $type=null, bool $required=false, mixed $defaultValue=null, ?Closure $transformer=null, array $validators=[], bool $unique=false, bool $nullable=true, ?string $format=null, array $metadata=[]): mixed
@param string $sourceField
@param string $targetField
@param (CSVTypeEnum | null) $type
resolved @param (Nvl\Csv\Enums\CSVTypeEnum | null) $type
@param bool $required
@param mixed $defaultValue
@param (Closure | null) $transformer
@param array<int, callable> $validators
@param bool $unique
@param bool $nullable
@param (string | null) $format
@param array<string, mixed> $metadata
public function __serialize(): array
@return array<string, mixed>
public function __unserialize(array $data): void
@param array<string, mixed> $data
public function apply(mixed $value): mixed
@param mixed $value
@return mixed
@throws InvalidArgumentException
public function getValidationErrors(mixed $value): array
@param mixed $value
@return array<int, string>
public function shouldIndex(): bool
@return bool
public static function simple(string $sourceField, string $targetField): self
@param string $sourceField
@param string $targetField
@return self
resolved @return Nvl\Csv\ValueObjects\CSVFieldMapping
public function toArray(): array
@return array<string, mixed>
public static function typed(string $sourceField, string $targetField, Nvl\Csv\Enums\CSVTypeEnum $type, bool $required=false): self
@param string $sourceField
@param string $targetField
@param CSVTypeEnum $type
resolved @param Nvl\Csv\Enums\CSVTypeEnum $type
@param bool $required
@return self
resolved @return Nvl\Csv\ValueObjects\CSVFieldMapping
public function validate(mixed $value): bool
@param mixed $value
@return bool
public static function withTransformer(string $sourceField, string $targetField, Closure $transformer): self
@param string $sourceField
@param string $targetField
@param Closure $transformer
@return self
resolved @return Nvl\Csv\ValueObjects\CSVFieldMapping
public readonly mixed $defaultValue
@param mixed $defaultValue
public readonly ?string $format
@param (string | null) $format
public readonly array $metadata
@param array<string, mixed> $metadata
public readonly bool $nullable
@param bool $nullable
public readonly bool $required
@param bool $required
public readonly string $sourceField
@param string $sourceField
public readonly string $targetField
@param string $targetField
public readonly ?Closure $transformer
@param (Closure | null) $transformer
public readonly ?Nvl\Csv\Enums\CSVTypeEnum $type
@param (CSVTypeEnum | null) $type
resolved @param (Nvl\Csv\Enums\CSVTypeEnum | null) $type
public readonly bool $unique
@param bool $unique
public readonly array $validators
@param array<int, callable> $validators
```
