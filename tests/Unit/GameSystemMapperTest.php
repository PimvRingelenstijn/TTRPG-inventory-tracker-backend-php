<?php

namespace Tests\Unit;

use App\DTOs\GameSystemCreateRequest;
use App\DTOs\GameSystemDataResponse;
use App\Mappers\GameSystemMapper;
use App\Models\GameSystem;
use PHPUnit\Framework\TestCase;

class GameSystemMapperTest extends TestCase
{
    public function test_request_to_attributes(): void
    {
        $request = new GameSystemCreateRequest(name: 'D&D', description: '5e');
        $attrs = GameSystemMapper::requestToAttributes($request);

        $this->assertEquals('D&D', $attrs['name']);
        $this->assertEquals('5e', $attrs['description']);
    }

    public function test_model_to_response(): void
    {
        $model = new GameSystem();
        $model->id = 1;
        $model->name = 'Pathfinder';
        $model->description = '2e';

        $response = GameSystemMapper::modelToResponse($model);

        $this->assertInstanceOf(GameSystemDataResponse::class, $response);
        $this->assertEquals(1, $response->id);
        $this->assertEquals('Pathfinder', $response->name);
        $this->assertEquals('2e', $response->description);
    }
}
