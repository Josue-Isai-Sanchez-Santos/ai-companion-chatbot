<?php

namespace App\Ai\Contracts;

use App\Ai\DTOs\SceneDirectorRequest;
use App\Ai\DTOs\SceneDirectorResponse;

interface SceneDirectorGateway
{
    public function decide(
        SceneDirectorRequest $request
    ): SceneDirectorResponse;
}
