<?php

namespace App\Http\Controllers;

use App\DTOs\GameSystemCreateRequest;
use App\Services\GameSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GameSystemController extends Controller
{
    public function __construct(
        private readonly GameSystemService $gameSystemService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $dto = new GameSystemCreateRequest(
            name: $validated['name'],
            description: $validated['description'] ?? '',
        );

        $result = $this->gameSystemService->addGameSystem($dto);

        return response()->json($result->toArray(), 201);
    }

    public function index(): JsonResponse
    {
        $systems = $this->gameSystemService->getAllGameSystems();

        return response()->json($systems->map(fn ($dto) => $dto->toArray())->values()->all());
    }

    public function show(Request $request, int $system_id): JsonResponse
    {
        $result = $this->gameSystemService->getGameSystemById($system_id);

        return response()->json($result->toArray());
    }
}
