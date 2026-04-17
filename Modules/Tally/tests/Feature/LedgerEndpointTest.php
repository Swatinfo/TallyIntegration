<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Tests\Concerns\MocksTallyClient;

uses(RefreshDatabase::class, MocksTallyClient::class);

function ledgerUser(array $permissions = ['view_masters', 'manage_masters']): User
{
    return User::factory()->create(['tally_permissions' => $permissions]);
}

function createConnection(): TallyConnection
{
    return TallyConnection::factory()->create(['code' => 'TST']);
}

it('requires authentication for ledgers', function () {
    $conn = createConnection();
    $this->getJson("/api/tally/{$conn->code}/ledgers")->assertStatus(401);
});

it('requires view_masters permission to list', function () {
    $conn = createConnection();
    $user = User::factory()->create(['tally_permissions' => []]);

    $this->actingAs($user)
        ->getJson("/api/tally/{$conn->code}/ledgers")
        ->assertStatus(403);
});

it('lists ledgers', function () {
    $conn = createConnection();
    $this->mockTallyClient($this->fixture('collection-ledgers.xml'));
    $user = ledgerUser();

    $this->actingAs($user)
        ->getJson("/api/tally/{$conn->code}/ledgers")
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('shows a single ledger', function () {
    $conn = createConnection();
    $this->mockTallyClient($this->fixture('object-ledger.xml'));
    $user = ledgerUser();

    $this->actingAs($user)
        ->getJson("/api/tally/{$conn->code}/ledgers/Cash")
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('creates a ledger', function () {
    $conn = createConnection();
    $this->mockTallyClient($this->fixture('import-success.xml'));
    $user = ledgerUser();

    $this->actingAs($user)
        ->postJson("/api/tally/{$conn->code}/ledgers", [
            'NAME' => 'Test Customer',
            'PARENT' => 'Sundry Debtors',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.created', 1);
});

it('rejects ledger creation without NAME', function () {
    $conn = createConnection();
    $user = ledgerUser();

    $this->actingAs($user)
        ->postJson("/api/tally/{$conn->code}/ledgers", [
            'PARENT' => 'Sundry Debtors',
        ])
        ->assertStatus(422);
});

it('rejects XML injection in ledger name', function () {
    $conn = createConnection();
    $user = ledgerUser();

    $this->actingAs($user)
        ->postJson("/api/tally/{$conn->code}/ledgers", [
            'NAME' => '<!DOCTYPE foo>Hack',
            'PARENT' => 'Sundry Debtors',
        ])
        ->assertStatus(422);
});

it('requires manage_masters permission to create', function () {
    $conn = createConnection();
    $user = User::factory()->create(['tally_permissions' => ['view_masters']]);

    $this->actingAs($user)
        ->postJson("/api/tally/{$conn->code}/ledgers", [
            'NAME' => 'Test',
            'PARENT' => 'Sundry Debtors',
        ])
        ->assertStatus(403);
});

it('deletes a ledger', function () {
    $conn = createConnection();
    $this->mockTallyClient($this->fixture('import-success.xml'));
    $user = ledgerUser();

    $this->actingAs($user)
        ->deleteJson("/api/tally/{$conn->code}/ledgers/Old%20Ledger")
        ->assertOk();
});

it('returns 404 for unknown connection code', function () {
    $user = ledgerUser();

    $this->actingAs($user)
        ->getJson('/api/tally/INVALID/ledgers')
        ->assertStatus(404);
});
