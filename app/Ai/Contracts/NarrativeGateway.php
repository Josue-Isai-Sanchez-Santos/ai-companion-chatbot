<?php

namespace App\Ai\Contracts;

use App\Ai\DTOs\NarrativeRequest;
use App\Ai\DTOs\NarrativeResponse;
use App\Ai\DTOs\NarrativeSegment;

interface NarrativeGateway
{
    public function generate(
        NarrativeRequest $request
    ): NarrativeResponse;

    /**
     * @param  callable(NarrativeSegment): void  $onSegment
     */
    public function stream(
        NarrativeRequest $request,
        callable $onSegment
    ): NarrativeResponse;
}
