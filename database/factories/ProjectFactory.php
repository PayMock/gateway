<?php

namespace Database\Factories;

use App\Models\Project;
use App\Services\Security\TokenGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $tokenGenerator = app(TokenGenerator::class);

        return [
            'name' => $this->faker->company(),
            'public_id' => $tokenGenerator->generateProjectId(),
            'api_key' => $tokenGenerator->generateApiKey(),
            'is_active' => true,
        ];
    }
}
