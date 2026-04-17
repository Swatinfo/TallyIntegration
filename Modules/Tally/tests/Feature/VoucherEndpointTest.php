<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Tests\Concerns\MocksTallyClient;

uses(RefreshDatabase::class, MocksTallyClient::class);

function voucherUser(array $perms = ['view_vouchers', 'manage_vouchers']): User
{
    return User::factory()->create(['tally_permissions' => $perms]);
}

function voucherConnection(): TallyConnection
{
    return TallyConnection::factory()->create(['code' => 'VCH']);
}

it('requires authentication for vouchers', function () {
    $conn = voucherConnection();
    $this->getJson("/api/tally/{$conn->code}/vouchers?type=Sales")->assertStatus(401);
});

it('requires view_vouchers permission to list', function () {
    $conn = voucherConnection();
    $user = User::factory()->create(['tally_permissions' => []]);

    $this->actingAs($user)
        ->getJson("/api/tally/{$conn->code}/vouchers?type=Sales")
        ->assertStatus(403);
});

it('lists vouchers by type', function () {
    $conn = voucherConnection();
    $xml = '<ENVELOPE><HEADER><VERSION>1</VERSION><STATUS>1</STATUS></HEADER><BODY><DESC></DESC><DATA><COLLECTION></COLLECTION></DATA></BODY></ENVELOPE>';
    $this->mockTallyClient($xml);
    $user = voucherUser();

    $this->actingAs($user)
        ->getJson("/api/tally/{$conn->code}/vouchers?type=Sales")
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('requires type parameter for listing', function () {
    $conn = voucherConnection();
    $user = voucherUser();

    $this->actingAs($user)
        ->getJson("/api/tally/{$conn->code}/vouchers")
        ->assertStatus(422);
});

it('creates a voucher', function () {
    $conn = voucherConnection();
    $this->mockTallyClient($this->fixture('import-success.xml'));
    $user = voucherUser();

    $this->actingAs($user)
        ->postJson("/api/tally/{$conn->code}/vouchers", [
            'type' => 'Sales',
            'data' => [
                'DATE' => '20260416',
                'PARTYLEDGERNAME' => 'Customer A',
                'ALLLEDGERENTRIES.LIST' => [
                    ['LEDGERNAME' => 'Customer A', 'ISDEEMEDPOSITIVE' => 'Yes', 'AMOUNT' => '-50000'],
                    ['LEDGERNAME' => 'Sales', 'ISDEEMEDPOSITIVE' => 'No', 'AMOUNT' => '50000'],
                ],
            ],
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.created', 1);
});

it('rejects invalid voucher type', function () {
    $conn = voucherConnection();
    $user = voucherUser();

    $this->actingAs($user)
        ->postJson("/api/tally/{$conn->code}/vouchers", [
            'type' => 'InvalidType',
            'data' => [],
        ])
        ->assertStatus(422);
});

it('requires manage_vouchers to create', function () {
    $conn = voucherConnection();
    $user = User::factory()->create(['tally_permissions' => ['view_vouchers']]);

    $this->actingAs($user)
        ->postJson("/api/tally/{$conn->code}/vouchers", [
            'type' => 'Sales',
            'data' => ['DATE' => '20260416'],
        ])
        ->assertStatus(403);
});

it('cancels a voucher', function () {
    $conn = voucherConnection();
    $this->mockTallyClient($this->fixture('cancel-success.xml'));
    $user = voucherUser();

    $this->actingAs($user)
        ->deleteJson("/api/tally/{$conn->code}/vouchers/12345", [
            'type' => 'Sales',
            'date' => '16-Apr-2026',
            'voucher_number' => 'SI-001',
            'action' => 'cancel',
            'narration' => 'Cancelled',
        ])
        ->assertOk()
        ->assertJsonPath('data.combined', 1);
});
