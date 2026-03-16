<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository extends BaseRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function getByUuid(string $uuid): ?User
    {
        return User::query()->where('uuid', $uuid)->first();
    }
}
