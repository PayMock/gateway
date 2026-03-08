<?php

namespace Tests\Feature\Api;

use App\Models\Balance;
use App\Models\Project;
use App\Services\Security\TokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function canCreateAProject(): void
    {
        $response = $this->postJson('/api/v1/projects', [
            'name' => 'My Test App',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'object', 'name', 'api_key', 'webhook_url', 'created',
            ])
            ->assertJsonFragment(['object' => 'project'])
            ->assertJsonFragment(['name' => 'My Test App']);

        $data = $response->json();

        $this->assertStringStartsWith('proj_', $data['id']);
        $this->assertStringStartsWith('sk_test_', $data['api_key']);
    }

    #[Test]
    public function creatingProjectAlsoCreatesBalance(): void
    {
        $response = $this->postJson('/api/v1/projects', ['name' => 'Balance Test']);
        $response->assertStatus(201);

        $projectId = Project::where('public_id', $response->json('id'))->first()->id;

        $this->assertDatabaseHas('balances', [
            'project_id' => $projectId,
            'available' => 0,
            'pending' => 0,
        ]);
    }

    #[Test]
    public function canGetCurrentProject(): void
    {
        $project = $this->createProject();

        $response = $this->withHeader('Authorization', 'Bearer ' . $project->api_key)
            ->getJson('/api/v1/projects/me');

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $project->public_id])
            ->assertJsonFragment(['name' => $project->name]);
    }

    #[Test]
    public function unauthenticatedRequestReturns401(): void
    {
        $this->getJson('/api/v1/projects/me')
            ->assertStatus(401)
            ->assertJsonStructure(['error' => ['type', 'code', 'message']]);
    }

    #[Test]
    public function invalidApiKeyReturns401(): void
    {
        $this->withHeader('Authorization', 'Bearer sk_test_invalid')
            ->getJson('/api/v1/projects/me')
            ->assertStatus(401);
    }

    private function createProject(string $name = 'Test Project'): Project
    {
        $tokenGen = app(TokenGenerator::class);

        $project = Project::create([
            'name' => $name,
            'public_id' => $tokenGen->generateProjectId(),
            'api_key' => $tokenGen->generateApiKey(),
            'is_active' => true,
        ]);

        Balance::create(['project_id' => $project->id, 'available' => 0, 'pending' => 0]);

        return $project;
    }
}
