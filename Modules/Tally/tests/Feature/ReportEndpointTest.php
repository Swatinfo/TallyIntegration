<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Tests\Concerns\MocksTallyClient;

uses(RefreshDatabase::class, MocksTallyClient::class);

function reportUser(array $perms = ['view_reports']): User
{
    return User::factory()->create(['tally_permissions' => $perms]);
}

function reportConnection(): TallyConnection
{
    return TallyConnection::factory()->create(['code' => 'RPT']);
}

it('requires authentication for reports', function () {
    $conn = reportConnection();
    $this->getJson("/api/tally/{$conn->code}/reports/balance-sheet")->assertStatus(401);
});

it('requires view_reports permission', function () {
    $conn = reportConnection();
    $user = User::factory()->create(['tally_permissions' => []]);

    $this->actingAs($user)
        ->getJson("/api/tally/{$conn->code}/reports/balance-sheet")
        ->assertStatus(403);
});

it('fetches balance sheet report', function () {
    $conn = reportConnection();
    $xml = '<ENVELOPE><HEADER><VERSION>1</VERSION><STATUS>1</STATUS></HEADER><BODY><DESC></DESC><DATA><BSNAME>Capital</BSNAME></DATA></BODY></ENVELOPE>';
    $this->mockTallyClient($xml);
    $user = reportUser();

    $this->actingAs($user)
        ->getJson("/api/tally/{$conn->code}/reports/balance-sheet?date=20260331")
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('fetches profit and loss report', function () {
    $conn = reportConnection();
    $xml = '<ENVELOPE><HEADER><VERSION>1</VERSION></HEADER><BODY><DATA></DATA></BODY></ENVELOPE>';
    $this->mockTallyClient($xml);
    $user = reportUser();

    $this->actingAs($user)
        ->getJson("/api/tally/{$conn->code}/reports/profit-and-loss?from=20260401&to=20260430")
        ->assertOk();
});

it('returns 404 for unknown report type', function () {
    $conn = reportConnection();
    $user = reportUser();

    $this->actingAs($user)
        ->getJson("/api/tally/{$conn->code}/reports/invalid-report")
        ->assertStatus(404);
});

it('fetches stock summary', function () {
    $conn = reportConnection();
    $xml = '<ENVELOPE><HEADER><VERSION>1</VERSION></HEADER><BODY><DATA></DATA></BODY></ENVELOPE>';
    $this->mockTallyClient($xml);
    $user = reportUser();

    $this->actingAs($user)
        ->getJson("/api/tally/{$conn->code}/reports/stock-summary")
        ->assertOk();
});
