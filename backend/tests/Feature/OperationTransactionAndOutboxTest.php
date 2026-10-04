<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\OperationResult;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Services\Operations\DocumentNumberGenerator;
use App\Services\Operations\Exceptions\OperationConflictException;
use App\Services\Operations\Exceptions\OperationException;
use App\Services\Operations\OutboxProcessor;
use App\Services\Operations\PayloadFingerprint;
use App\Services\Operations\TransactionalOperationService;
use Database\Seeders\RoleAndPermissionSeeder;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationTransactionAndOutboxTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected TransactionalOperationService $operationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->user = User::factory()->create([
            'role' => 'SALES_MANAGER',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $this->operationService = app(TransactionalOperationService::class);
    }

    public function test_audit_replay_cannot_return_another_actors_result(): void
    {
        $operationId = (string) Str::uuid();
        $other = User::factory()->create();
        $payload = ['amount' => 1000];
        $this->operationService->execute($operationId, 'CUSTOMER_PAYMENT', $payload, fn () => ['payment_id' => 1], $this->user->id);

        $this->expectException(OperationConflictException::class);
        $this->operationService->execute($operationId, 'CUSTOMER_PAYMENT', $payload, fn () => [], $other->id);
    }

    public function test_audit_operation_type_is_part_of_replay_identity(): void
    {
        $operationId = (string) Str::uuid();
        $payload = ['amount' => 1000];
        $this->operationService->execute($operationId, 'CUSTOMER_PAYMENT', $payload, fn () => ['payment_id' => 1], $this->user->id);

        $this->expectException(OperationConflictException::class);
        $this->operationService->execute($operationId, 'SUPPLIER_PAYMENT', $payload, fn () => [], $this->user->id);
    }

    public function test_audit_generic_operation_endpoint_is_unavailable_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        try {
            $this->actingAs($this->user, 'sanctum')->postJson('/api/operations/execute', [
                'operation_id' => (string) Str::uuid(),
                'operation_type' => 'CREATE_SALE',
                'payload' => ['amount' => 1000],
            ])->assertNotFound();
            $this->assertDatabaseCount('operation_results', 0);
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_audit_internal_operation_error_does_not_disclose_secrets(): void
    {
        $operationId = (string) Str::uuid();
        try {
            $this->operationService->execute($operationId, 'AUDIT_FAILURE', [], function () {
                throw new \RuntimeException('private-database-password');
            }, $this->user->id);
            $this->fail('An internal failure must not be reported as success.');
        } catch (OperationException $exception) {
            $this->assertStringNotContainsString('private-database-password', json_encode($exception->toResponseArray()));
            $this->assertSame('INTERNAL_OPERATION_ERROR', $exception->errorCode);
            $this->assertDatabaseMissing('operation_results', ['operation_id' => $operationId]);
        }
    }

    /**
     * 1. 20 ta so'rov bitta operation_id bilan kelganda: faqat 1 ta operatsiya bajariladi,
     * barcha 20 ta chaqiruv bir xil natijani oladi, DBda faqat 1 ta yozuv bo'ladi.
     */
    public function test_twenty_identical_requests_with_same_operation_id_yield_single_execution(): void
    {
        $operationId = (string) Str::uuid();
        $payload = ['customer_id' => 1, 'amount' => 50000, 'items' => ['fanta_05' => 10]];

        $executionCounter = 0;
        $results = [];

        // 20 marta bir xil operation_id va bir xil payload bilan yuboramiz
        for ($i = 0; $i < 20; $i++) {
            $results[] = $this->operationService->execute(
                operationId: $operationId,
                operationType: 'TEST_OPERATION',
                payload: $payload,
                businessCallback: function ($context) use (&$executionCounter) {
                    $executionCounter++;
                    $context->logAudit('TEST_ACTION', null, null, null, ['counter' => $executionCounter]);
                    $context->enqueueEvent('TestEventOccurred', 'Test', 1, ['counter' => $executionCounter]);

                    return [
                        'doc_number' => 'DOC-001',
                        'amount' => 50000,
                        'execution_run' => $executionCounter,
                    ];
                },
                actorId: $this->user->id
            );
        }

        // MAJBURIY QABUL MEZONI:
        // Biznes callback faqat 1 marta bajarildi!
        $this->assertEquals(1, $executionCounter);

        // Barcha 20 ta natija aynan 1-ijro natijasiga teng
        foreach ($results as $res) {
            $this->assertEquals('DOC-001', $res['doc_number']);
            $this->assertEquals(50000, $res['amount']);
            $this->assertEquals(1, $res['execution_run']);
            $this->assertEquals($operationId, $res['operation_id']);
        }

        // DBda faqat 1 ta OperationResult, 1 ta AuditLog va 1 ta OutboxEvent mavjud!
        $this->assertEquals(1, OperationResult::where('operation_id', $operationId)->count());
        $this->assertEquals(1, AuditLog::where('operation_id', $operationId)->count());
        $this->assertEquals(1, OutboxEvent::where('operation_id', $operationId)->count());
    }

    /**
     * 2. Shu operation_id bilan BOSHQA payload kelsa: konflikt beradi (409 Conflict),
     * eski natija o'zgarmaydi va yangi operatsiya bajarilmaydi.
     */
    public function test_same_operation_id_with_different_payload_causes_conflict(): void
    {
        $operationId = (string) Str::uuid();

        // 1. Asl operatsiya
        $this->operationService->execute(
            operationId: $operationId,
            operationType: 'SALE_CREATE',
            payload: ['amount' => 50000],
            businessCallback: fn () => ['invoice' => 'INV-001', 'amount' => 50000]
        );

        // 2. Shu operation_id, lekin o'zgargan summa (masalan 75 000)
        $this->expectException(OperationConflictException::class);

        $this->operationService->execute(
            operationId: $operationId,
            operationType: 'SALE_CREATE',
            payload: ['amount' => 75000], // Boshqa payload!
            businessCallback: fn () => ['invoice' => 'INV-002', 'amount' => 75000]
        );
    }

    /**
     * 3. Tarmoq uzilishi va javob yo'qolganda: qayta yuborilgan so'rov eski natijani qaytaradi
     */
    public function test_client_timeout_retry_returns_original_cached_result(): void
    {
        $operationId = (string) Str::uuid();
        $payload = ['item_id' => 99, 'qty' => 5];

        // 1-urinish
        $res1 = $this->operationService->execute(
            operationId: $operationId,
            operationType: 'INWARD_RECEIVE',
            payload: $payload,
            businessCallback: fn () => ['status' => 'RECEIVED', 'invoice_id' => 456]
        );

        // 2-urinish (mijoz timeout deb o'ylab xuddi shu ID bilan qayta so'radi)
        $res2 = $this->operationService->execute(
            operationId: $operationId,
            operationType: 'INWARD_RECEIVE',
            payload: $payload,
            businessCallback: function () {
                $this->fail('Biznes callback qayta chaqirilmasligi kerak edi!');
            }
        );

        $this->assertEquals($res1, $res2);
        $this->assertEquals(456, $res2['invoice_id']);
    }

    /**
     * 4. Yarim operation result, yarim audit yoki yarim outbox yo'q:
     * Operatsiya ichida xato bo'lsa tranzaksiya to'liq rollback bo'ladi.
     */
    public function test_atomic_rollback_leaves_zero_partial_records(): void
    {
        $operationId = (string) Str::uuid();

        try {
            $this->operationService->execute(
                operationId: $operationId,
                operationType: 'FAILING_OPERATION',
                payload: ['test' => 123],
                businessCallback: function ($context) {
                    $context->logAudit('HALF_AUDIT');
                    $context->enqueueEvent('HALF_EVENT', 'Test', 1, []);

                    // Kutilmagan biznes xatolik
                    throw new Exception('Ombor yetarli emas yoki hisob xatosi!');
                }
            );
            $this->fail('Xatolik otilishi kerak edi.');
        } catch (OperationException $e) {
            $this->assertEquals('needs_review', $e->errorCategory);
        }

        // MAJBURIY QABUL MEZONI:
        // Tranzaksiya rollback bo'ldi — hech qanday chala yozuv qolmagan!
        $this->assertEquals(0, OperationResult::where('operation_id', $operationId)->count());
        $this->assertEquals(0, AuditLog::where('operation_id', $operationId)->count());
        $this->assertEquals(0, OutboxEvent::where('operation_id', $operationId)->count());
    }

    /**
     * 5. Outbox Worker: hodisani muvaffaqiyatli tarqatadi, PUBLISHED qiladi va
     * qayta chaqirilganda takroriy hodisa yoki biznes dublikat yaratmaydi.
     */
    public function test_outbox_worker_publishes_events_idempotently(): void
    {
        Queue::fake();
        Event::fake(['DomainEntityCreated']);

        $operationId = (string) Str::uuid();

        // Operatsiya bajarilib OutboxEvent yaratiladi
        $this->operationService->execute(
            operationId: $operationId,
            operationType: 'CREATE_ENTITY',
            payload: ['name' => 'Tovar'],
            businessCallback: function ($context) {
                $context->enqueueEvent('DomainEntityCreated', 'Product', 10, ['name' => 'Tovar']);

                return ['id' => 10];
            }
        );

        $event = OutboxEvent::where('operation_id', $operationId)->first();
        $this->assertNotNull($event);
        $this->assertEquals('PENDING', $event->status);

        // 1-worker ishga tushadi
        $processor = new OutboxProcessor;
        $processed = $processor->processPending();

        $this->assertEquals(1, $processed);
        $this->assertEquals('PUBLISHED', $event->fresh()->status);
        $this->assertNotNull($event->fresh()->published_at);

        Event::assertDispatched('DomainEntityCreated');

        // 2-marta worker ishga tushganda qayta ishlanadigan PENDING hodisa yo'q (Idempotency)
        $secondRun = $processor->processPending();
        $this->assertEquals(0, $secondRun);
    }

    /**
     * 6. Hujjat raqamlari max(id)+1 bilan emas, concurrency-safe PostgreSQL sequence orqali yaratiladi
     */
    public function test_document_number_generator_uses_safe_sequences(): void
    {
        $num1 = DocumentNumberGenerator::nextSalesInvoiceNumber();
        $num2 = DocumentNumberGenerator::nextSalesInvoiceNumber();
        $num3 = DocumentNumberGenerator::nextPurchaseInvoiceNumber();
        $num4 = DocumentNumberGenerator::nextPaymentNumber();

        $year = date('Y');
        $this->assertStringStartsWith("INV-{$year}-", $num1);
        $this->assertStringStartsWith("INV-{$year}-", $num2);
        $this->assertStringStartsWith("PUR-{$year}-", $num3);
        $this->assertStringStartsWith("PAY-{$year}-", $num4);

        // Ketma-ket unikal raqamlar
        $this->assertNotEquals($num1, $num2);
    }

    /**
     * 7. PayloadFingerprint kalitlar tartibidan qat'i nazar bir xil SHA-256 beradi
     */
    public function test_payload_fingerprint_canonical_sorting(): void
    {
        $payload1 = [
            'zebra' => 100,
            'apple' => 'suv',
            'details' => ['b' => 2, 'a' => 1],
        ];

        $payload2 = [
            'apple' => ' suv ', // bo'shliqlar tozalanishi kerak
            'details' => ['a' => 1, 'b' => 2], // ichki tartib
            'zebra' => 100,
            '_token' => 'transient-csrf-token', // transport kaliti chiqariladi
        ];

        $hash1 = PayloadFingerprint::compute($payload1);
        $hash2 = PayloadFingerprint::compute($payload2);

        $this->assertEquals($hash1, $hash2);
    }

    /**
     * 8. API darajasida operatsiya bajarish va xatoliklar taksonomiyasi
     */
    public function test_api_operations_endpoint(): void
    {
        $operationId = (string) Str::uuid();

        // 1. Muvaffaqiyatli so'rov
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/operations/execute', [
            'operation_id' => $operationId,
            'operation_type' => 'SALES_CHECKOUT',
            'payload' => ['total' => 150000, 'items_count' => 3],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'operation_id' => $operationId,
                    'status' => 'PROCESSED',
                ],
            ]);

        // 2. Shu operation_id bilan boshqa ma'lumot jo'natilganda 409 Conflict
        $conflictResponse = $this->actingAs($this->user, 'sanctum')->postJson('/api/operations/execute', [
            'operation_id' => $operationId,
            'operation_type' => 'SALES_CHECKOUT',
            'payload' => ['total' => 250000], // Boshqa summa!
        ]);

        $conflictResponse->assertStatus(409)
            ->assertJson([
                'success' => false,
                'operation_id' => $operationId,
                'error' => [
                    'category' => 'conflict',
                    'code' => 'OPERATION_PAYLOAD_CONFLICT',
                ],
            ]);
    }
}
