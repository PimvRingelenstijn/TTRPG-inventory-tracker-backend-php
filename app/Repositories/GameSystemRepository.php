<?php

namespace App\Repositories;

use App\Models\GameSystem;

class GameSystemRepository extends BaseRepository
{
    public function __construct(GameSystem $model)
    {
        parent::__construct($model);
    }

    public function getById(int $id): ?GameSystem
    {
        return GameSystem::query()->find($id);
    }
}
