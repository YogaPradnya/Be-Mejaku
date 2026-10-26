<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidtransNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder']);
    }

    private function makePendingPayment(): Payment
    {
        $menuItem = MenuItem::first();
        $orderResponse = $this->postJson('/api/orders', [
            'table_code' => 'A12',
            'items' => [['menu_item_id' => $menuItem->id, 'quantity' => 1]],
        ]);
        $orderResponse->assertStatus(201);

        $order = Order::where('order_number', $orderResponse->json('data.order_number'))->firstOrFail();

        return Payment::create([
            'order_id' => $order->id,
            'method' => 'midtrans',
            'payment_code' => 'TEST' . $order->id,
            'amount' => $order->total,
            'status' => 'pending',
            'midtrans_order_id' => $order->order_number . '-' . $order->id,
        ]);
    }

    private function signedPayload(Payment $payment, string $transactionStatus): array
    {
        $payload = [
            'order_id' => $payment->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => (string) $payment->amount,
            'transaction_status' => $transactionStatus,
            'transaction_id' => 'trx-' . $payment->id,
            'payment_type' => 'qris',
            'fraud_status' => 'accept',
        ];
        $payload['signature_key'] = hash('sha512', implode('', [
            $payload['order_id'],
            $payload['status_code'],
            $payload['gross_amount'],
            (string) config('midtrans.server_key'),
        ]));

        return $payload;
    }

    public function test_notification_settlement_marks_paid(): void
    {
        $payment = $this->makePendingPayment();

        $this->postJson('/api/midtrans/notification', $this->signedPayload($payment, 'settlement'))
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('paid', $payment->fresh()->status);
        $this->assertEquals('paid', $payment->fresh()->order->payment_status);
        $this->assertEquals('preparing', $payment->fresh()->order->preparation_status);
    }

    public function test_notification_expire_marks_expired(): void
    {
        $payment = $this->makePendingPayment();

        $this->postJson('/api/midtrans/notification', $this->signedPayload($payment, 'expire'))
            ->assertStatus(200);

        $this->assertEquals('expired', $payment->fresh()->status);
        $this->assertEquals('expired', $payment->fresh()->order->payment_status);
    }

    public function test_notification_rejects_bad_signature(): void
    {
        $payment = $this->makePendingPayment();
        $payload = $this->signedPayload($payment, 'settlement');
        $payload['signature_key'] = 'invalid';

        $this->postJson('/api/midtrans/notification', $payload)->assertStatus(400);
        $this->assertEquals('pending', $payment->fresh()->status);
    }

    public function test_verify_signature_logic(): void
    {
        $service = app(MidtransService::class);
        $payment = $this->makePendingPayment();
        $payload = $this->signedPayload($payment, 'settlement');

        $this->assertTrue($service->verifySignature($payload));
        $payload['gross_amount'] = '999999';
        $this->assertFalse($service->verifySignature($payload));
    }
}
