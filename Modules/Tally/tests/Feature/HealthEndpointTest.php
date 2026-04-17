<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tally\Tests\Concerns\MocksTallyClient;

uses(RefreshDatabase::class, MocksTallyClient::class);

function authenticatedUser(array $permissions = []): User
{
    return User::factory()->create([
        'tally_permissions' => $permissions,
    ]);
}

it('requires authentication', function () {
    $this->getJson('/api/tally/health')
        ->assertStatus(401);
});

it('returns healthy when tally is connected', function () {
    $this->mockTallyClient($this->fixture('company-list.xml'));

    $user = authenticatedUser();

    $this->actingAs($user)
        ->getJson('/api/tally/health')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.connected', true);
});
