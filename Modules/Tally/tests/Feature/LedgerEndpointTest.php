<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Services\TallyHttpClient;
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

it('filters ledgers by parent group', function () {
    // Fixture has Cash (PARENT: Cash-in-hand) and HDFC Bank (PARENT: Bank Accounts).
    // ?parent=Cash-in-hand should return only Cash; total/meta count must reflect filter.
    $conn = createConnection();
    $this->mockTallyClient($this->fixture('collection-ledgers.xml'));

    $this->actingAs(ledgerUser())
        ->getJson("/api/tally/{$conn->code}/ledgers?parent=Cash-in-hand")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.@attributes.NAME', 'Cash');
});

it('returns empty when filtering ledgers by an unknown parent', function () {
    $conn = createConnection();
    $this->mockTallyClient($this->fixture('collection-ledgers.xml'));

    $this->actingAs(ledgerUser())
        ->getJson("/api/tally/{$conn->code}/ledgers?parent=Nonexistent%20Group")
        ->assertOk()
        ->assertJsonPath('meta.total', 0)
        ->assertJsonCount(0, 'data');
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

it('filters from the cached list (not the connection code) when showing a single ledger', function () {
    // Regression: the show() signature previously accepted only (string $name) so
    // Laravel bound the first route param ({connection}) into $name, causing the
    // service to query Tally for "TST" instead of the real ledger name.
    //
    // 2026-04-19: LedgerService::get() now filters from the cached list to avoid
    // Object-export hangs that have been reproduced across master types in
    // TallyPrime. The request must therefore be a Collection export of
    // "List of Ledgers" — NOT an Object export — and the matched name must come
    // from PHP-side filtering, not the request URL.
    $conn = createConnection();
    $this->mockTallyClient($this->fixture('collection-ledgers.xml'));

    $this->actingAs(ledgerUser())
        ->getJson("/api/tally/{$conn->code}/ledgers/Cash")
        ->assertOk()
        ->assertJsonPath('data.@attributes.NAME', 'Cash');

    $xml = $this->lastTallyRequestXml();
    expect($xml)->toContain('<TYPE>Collection</TYPE>');
    expect($xml)->toContain('<ID>List of Ledgers</ID>');
    expect($xml)->not->toContain('<TYPE>Object</TYPE>');
    expect($xml)->not->toContain("<ID TYPE=\"Name\">{$conn->code}</ID>");
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

it('pins SVCURRENTCOMPANY to the connection company on master requests', function () {
    // Regression: services called TallyXmlBuilder::buildObjectExportRequest($type, $name)
    // without threading the company, so the builder fell back to an empty config value.
    // With no <SVCURRENTCOMPANY> pin, Tally can hang for 30s on multi-company installs.
    $conn = TallyConnection::factory()->create([
        'code' => 'PIN',
        'company_name' => 'Acme Books Pvt Ltd',
    ]);
    $this->mockTallyClient($this->fixture('object-ledger.xml'));

    // Our mock doesn't carry a real company, so we also rebind getCompany().
    // The builder calls app(TallyHttpClient::class)->getCompany() — making sure
    // the connection's company reaches the outbound XML.
    $mock = Mockery::mock(TallyHttpClient::class);
    $mock->shouldReceive('sendXml')->andReturnUsing(function (string $xml) {
        $this->capturedTallyRequests[] = $xml;

        return $this->fixture('object-ledger.xml');
    });
    $mock->shouldReceive('isConnected')->andReturn(true);
    $mock->shouldReceive('getCompanies')->andReturn(['Acme Books Pvt Ltd']);
    $mock->shouldReceive('getUrl')->andReturn('http://localhost:9000');
    $mock->shouldReceive('getCompany')->andReturn('Acme Books Pvt Ltd');
    $mock->shouldReceive('getConnectionCode')->andReturn('PIN');
    $this->app->instance(TallyHttpClient::class, $mock);

    $this->actingAs(ledgerUser())
        ->getJson("/api/tally/{$conn->code}/ledgers/Cash")
        ->assertOk();

    expect($this->lastTallyRequestXml())
        ->toContain('<SVCURRENTCOMPANY>Acme Books Pvt Ltd</SVCURRENTCOMPANY>');
});

it('rejects dangerous XML tokens in the {name} path parameter', function () {
    $conn = createConnection();

    $this->actingAs(ledgerUser())
        ->getJson('/api/tally/'.$conn->code.'/ledgers/'.rawurlencode('<!DOCTYPE foo>'))
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});
