<?php

namespace App\Ai\DTOs;

final readonly class AIModelDescriptor
{
    public const SIMULATED = 'simulated';

    public const UNCONFIGURED = 'unconfigured';

    public const CONFIGURED = 'configured';

    public function __construct(
        public string $role,
        public string $driver,
        public ?string $provider,
        public ?string $model,
        public int $timeout,
        public ?int $contextWindow,
        public bool $supportsStreaming,
        public bool $supportsStructuredOutput,
        public string $outputFormat,
        public bool $supportsCancellation,
        public string $status,
    ) {}
}
