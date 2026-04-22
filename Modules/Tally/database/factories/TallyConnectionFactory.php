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
            // Phase 9Z — hierarchy FKs default to null (backwards-compatible).
            'tally_organization_id' => null,
            'tally_company_id' => null,
            'tally_branch_id' => null,
        ];
    }

    /** State: attach to a specific organization/company/branch hierarchy. */
    public function inHierarchy(int $organizationId, ?int $companyId = null, ?int $branchId = null): static
    {
        return $this->state(fn () => [
            'tally_organization_id' => $organizationId,
            'tally_company_id' => $companyId,
            'tally_branch_id' => $branchId,
        ]);
    }
}
