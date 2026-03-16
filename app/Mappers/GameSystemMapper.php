<?php

namespace App\Mappers;

use App\DTOs\GameSystemCreateRequest;
use App\DTOs\GameSystemDataResponse;
use App\Models\GameSystem;

class GameSystemMapper
{
    public static function requestToAttributes(GameSystemCreateRequest $request): array
    {
        return [
            'name' => $request->name,
            'description' => $request->description ?: null,
        ];
    }

    public static function modelToResponse(GameSystem $model): GameSystemDataResponse
    {
        return new GameSystemDataResponse(
            id: $model->id,
            name: $model->name,
            description: $model->description,
        );
    }
}
