<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Tests\Concerns\MocksTallyClient;

uses(RefreshDatabase::class, MocksTallyClient::class);

function connectionUser(array $permissions = ['manage_connections']): User
{
    return User::factory()->create([
        'tally_permissions' => $permissions,
    ]);
}

it('requires authentication for connections', function () {
    $this->getJson('/api/tally/connections')->assertStatus(401);
});

it('requires manage_connections permission', function () {
    $user = User::factory()->create(['tally_permissions' => []]);

    $this->actingAs($user)
        ->getJson('/api/tally/connections')
        ->assertStatus(403);
});

it('lists connections', function () {
    TallyConnection::factory()->count(3)->create();
    $user = connectionUser();

    $this->actingAs($user)
        ->getJson('/api/tally/connections')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data');
});

it('creates a connection', function () {
    $user = connectionUser();

    $this->actingAs($user)
        ->postJson('/api/tally/connections', [
            'name' => 'Test Office',
            'code' => 'TST',
            'host' => '192.168.1.100',
            'port' => 9000,
            'company_name' => 'Test Company',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.code', 'TST');

    $this->assertDatabaseHas('tally_connections', ['code' => 'TST']);
});

it('rejects duplicate connection code', function () {
    TallyConnection::factory()->create(['code' => 'DUP']);
    $user = connectionUser();

    $this->actingAs($user)
        ->postJson('/api/tally/connections', [
            'name' => 'Duplicate',
            'code' => 'DUP',
            'host' => 'localhost',
            'port' => 9000,
        ])
        ->assertStatus(422);
});

it('shows a connection', function () {
    $connection = TallyConnection::factory()->create(['code' => 'SHW']);
    $user = connectionUser();

    $this->actingAs($user)
        ->getJson("/api/tally/connections/{$connection->id}")
        ->assertOk()
        ->assertJsonPath('data.code', 'SHW');
});

it('updates a connection', function () {
    $connection = TallyConnection::factory()->create();
    $user = connectionUser();

    $this->actingAs($user)
        ->putJson("/api/tally/connections/{$connection->id}", [
            'host' => '10.0.0.1',
        ])
        ->assertOk()
        ->assertJsonPath('data.host', '10.0.0.1');
});

it('deletes a connection', function () {
    $connection = TallyConnection::factory()->create();
    $user = connectionUser();

    $this->actingAs($user)
        ->deleteJson("/api/tally/connections/{$connection->id}")
        ->assertOk();

    $this->assertDatabaseMissing('tally_connections', ['id' => $connection->id]);
});
