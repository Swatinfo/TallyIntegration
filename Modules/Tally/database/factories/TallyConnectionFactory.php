<?php

namespace Modules\Tally\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tally\Models\TallyConnection;

/**
 * @extends Factory<TallyConnection>
 */
class TallyConnectionFactory extends Factory
{
    protected $model = TallyConnection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'host' => 'localhost',
            'port' => 9000,
            'company_name' => fake()->company(),
            'timeout' => 30,
            'is_active' => true,
        ];
    }
}
