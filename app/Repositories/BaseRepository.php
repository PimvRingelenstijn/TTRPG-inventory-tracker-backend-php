<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

abstract class BaseRepository
{
    public function __construct(
        protected Model $model
    ) {}

    /**
     * @return Collection<int, Model>
     */
    public function getAll(int $skip = 0, int $limit = 100): Collection
    {
        return $this->model::query()
            ->offset($skip)
            ->limit($limit)
            ->get();
    }

    public function create(array $attributes): Model
    {
        return $this->model::query()->create($attributes);
    }
}
