<?php

namespace App\Services;

use App\DTOs\GameSystemCreateRequest;
use App\DTOs\GameSystemDataResponse;
use App\Mappers\GameSystemMapper;
use App\Repositories\GameSystemRepository;
use Illuminate\Support\Collection;

class GameSystemService
{
    public function __construct(
        private readonly GameSystemRepository $repository,
    ) {}

    public function addGameSystem(GameSystemCreateRequest $request): GameSystemDataResponse
    {
        $attrs = GameSystemMapper::requestToAttributes($request);
        $model = $this->repository->create($attrs);

        return GameSystemMapper::modelToResponse($model);
    }

    /**
     * @return Collection<int, GameSystemDataResponse>
     */
    public function getAllGameSystems(): Collection
    {
        return $this->repository->getAll()->map(
            fn ($model) => GameSystemMapper::modelToResponse($model)
        );
    }

    public function getGameSystemById(int $id): GameSystemDataResponse
    {
        $model = $this->repository->getById($id);
        if (!$model) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json(['detail' => 'Game system not found'], 404)
            );
        }

        return GameSystemMapper::modelToResponse($model);
    }
}
