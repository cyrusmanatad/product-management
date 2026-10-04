<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    useVendor();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate('view analytics', 'api');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('view analytics');
    joinVendor($this->user);
    $this->actingAs($this->user, 'api');
});

test('revenue performance includes recent orders for the current week', function () {
    $monday = now()->startOfWeek();

    createOrder($this->user->id, 'ORD-WEEK-MON', 250, 'pending', $monday->copy()->setTime(10, 0));
    createOrder($this->user->id, 'ORD-WEEK-TUE', 80, 'cancelled', $monday->copy()->addDay()->setTime(11, 0));
    createOrder($this->user->id, 'ORD-LAST-WEEK', 400, 'delivered', $monday->copy()->subWeek()->setTime(9, 0));

    $response = $this->getJson('/api/v1/vendors/benta-door/analytics/revenue');

    $response->assertOk()
        ->assertJsonPath('data.categories', ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])
        ->assertJsonPath('data.current.0', 250)
        ->assertJsonPath('data.current.1', 0)
        ->assertJsonPath('data.previous.0', 400)
        ->assertJsonPath('data.current_total', '250.00');
});

function createOrder(int $userId, string $number, float $total, string $status, $createdAt): void
{
    $order = Order::query()->create([
        'order_number' => $number,
        'user_id' => $userId,
        'total' => $total,
        'payment_status' => 'paid',
        'status' => $status,
    ]);

    $order->forceFill([
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ])->save();
}
