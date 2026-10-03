<?php

namespace App\Livewire\Sales;

use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Sales\CreateSaleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class OptomPos extends Component
{
    public string $operationId = '';

    public ?int $selectedCustomerId = null;

    public ?string $selectedCustomerName = null;

    public int $customerCurrentDebt = 0;

    public array $items = [];

    // To'lov parametrlari
    public string $paymentType = 'FULL'; // FULL, PARTIAL, DEBT

    public int $paidAmount = 0;

    public string $paymentMethod = 'CASH'; // CASH, CARD, BANK

    public ?int $cashAccountId = null;

    public string $notes = '';

    public ?string $posMessage = null;

    public ?string $errorMessage = null;

    public ?array $completedSale = null;

    public function mount(): void
    {
        $this->operationId = Str::uuid()->toString();

        // Standart kassa hisobini olish
        $defaultCash = CashAccount::where('is_default', true)->first();
        if ($defaultCash) {
            $this->cashAccountId = $defaultCash->id;
        }
    }

    #[On('product-created')]
    public function onProductCreated(array $payload): void
    {
        $this->reset(['errorMessage', 'posMessage']);
        $variantId = (int) $payload['variant_id'];

        $variant = ProductVariant::with(['product', 'volume'])->find($variantId);
        $price = $payload['sale_price'] ?? ($variant?->default_sale_price ?? 0);

        // Savatda allaqachon bo'lsa, sonini 1 taga oshiramiz
        foreach ($this->items as $index => $item) {
            if ($item['variant_id'] === $variantId) {
                $this->items[$index]['quantity'] += 1;
                $this->items[$index]['total'] = $this->items[$index]['quantity'] * $this->items[$index]['price'];
                $this->posMessage = "Savatdagi tovar soni oshirildi: {$item['display_name']}";
                $this->syncPaymentAmount();

                return;
            }
        }

        // Yangi qator sifatida savat qoralamasiga qo'shamiz (mavjud qoralama saqlanadi!)
        $this->items[] = [
            'variant_id' => $variantId,
            'display_name' => $payload['display_name'] ?? ($variant ? "{$variant->product->name} {$variant->volume->name}" : "#{$variantId}"),
            'sku' => $payload['sku'] ?? ($variant?->sku ?? ''),
            'quantity' => 1,
            'price' => (int) $price,
            'is_system_price' => (int) $price > 0,
            'default_system_price' => (int) ($variant?->default_sale_price ?? 0),
            'price_version' => $variant?->version ?? 1,
            'total' => (int) $price * 1,
        ];

        $this->posMessage = "Mahsulot savatga qo'shildi: {$payload['display_name']}";
        $this->syncPaymentAmount();
    }

    #[On('customer-created')]
    public function onCustomerCreated(array $payload): void
    {
        $this->reset(['errorMessage', 'posMessage']);
        $this->selectedCustomerId = (int) $payload['customer_id'];
        $this->selectedCustomerName = $payload['name'];

        $customer = Customer::find($this->selectedCustomerId);
        $this->customerCurrentDebt = (int) ($customer?->current_debt ?? 0);

        $this->posMessage = "Yangi mijoz saqlandi va tanlandi: {$payload['name']}";
    }

    public function selectExistingCustomer(?int $customerId): void
    {
        $this->reset(['errorMessage', 'posMessage']);

        if (! $customerId) {
            $this->selectedCustomerId = null;
            $this->selectedCustomerName = null;
            $this->customerCurrentDebt = 0;
            $this->paymentType = 'FULL';
            $this->syncPaymentAmount();

            return;
        }

        $customer = Customer::find($customerId);
        if ($customer) {
            $this->selectedCustomerId = $customer->id;
            $this->selectedCustomerName = $customer->display_name;
            $this->customerCurrentDebt = (int) $customer->current_debt;
        }
    }

    public function removeItem(int $index): void
    {
        $this->reset(['errorMessage', 'posMessage']);
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->syncPaymentAmount();
    }

    public function updateQuantity(int $index, int $qty): void
    {
        $this->reset(['errorMessage', 'posMessage']);
        if ($qty <= 0) {
            $this->removeItem($index);

            return;
        }
        $this->items[$index]['quantity'] = $qty;
        $this->items[$index]['total'] = $qty * $this->items[$index]['price'];
        $this->syncPaymentAmount();
    }

    public function updatePrice(int $index, int $price): void
    {
        $this->reset(['errorMessage', 'posMessage']);
        $this->items[$index]['price'] = max(0, $price);
        $this->items[$index]['total'] = $this->items[$index]['quantity'] * $this->items[$index]['price'];
        $this->syncPaymentAmount();
    }

    public function toggleSystemPrice(int $index): void
    {
        $this->reset(['errorMessage', 'posMessage']);
        $isSys = ! ($this->items[$index]['is_system_price'] ?? false);
        $this->items[$index]['is_system_price'] = $isSys;

        if ($isSys) {
            $sysPrice = (int) ($this->items[$index]['default_system_price'] ?? 0);
            if ($sysPrice > 0) {
                $this->items[$index]['price'] = $sysPrice;
                $this->items[$index]['total'] = $this->items[$index]['quantity'] * $sysPrice;
            } else {
                $this->errorMessage = "'{$this->items[$index]['display_name']}' uchun tizim narxi belgilanmagan!";
            }
        }
        $this->syncPaymentAmount();
    }

    public function setPaymentType(string $type): void
    {
        $this->reset(['errorMessage', 'posMessage']);
        $this->paymentType = $type;
        $this->syncPaymentAmount();
    }

    public function updatedPaymentType(): void
    {
        $this->syncPaymentAmount();
    }

    public function syncPaymentAmount(): void
    {
        $total = $this->getTotalAmount();
        if ($this->paymentType === 'FULL') {
            $this->paidAmount = $total;
        } elseif ($this->paymentType === 'DEBT') {
            $this->paidAmount = 0;
        }
        // PARTIAL bo'lsa foydalanuvchi kiritgan summa saqlanadi
    }

    public function clearDraft(): void
    {
        $this->items = [];
        $this->selectedCustomerId = null;
        $this->selectedCustomerName = null;
        $this->customerCurrentDebt = 0;
        $this->paidAmount = 0;
        $this->paymentType = 'FULL';
        $this->notes = '';
        $this->posMessage = 'Savdo qoralamasi tozalandi.';
        $this->errorMessage = null;
        $this->operationId = Str::uuid()->toString();
    }

    public function getTotalAmount(): int
    {
        return array_sum(array_column($this->items, 'total'));
    }

    public function getDebtAmount(): int
    {
        return max(0, $this->getTotalAmount() - $this->paidAmount);
    }

    public function getFinalCustomerDebt(): int
    {
        return $this->customerCurrentDebt + $this->getDebtAmount();
    }

    /**
     * Savdoni tasdiqlash va chek chiqarish (Atomic checkout).
     */
    public function checkout(CreateSaleService $saleService): void
    {
        $this->reset(['errorMessage', 'posMessage', 'completedSale']);

        if (empty($this->items)) {
            $this->errorMessage = "Savdo savati bo'sh! Kamida bitta tovar tanlang.";

            return;
        }

        $totalAmount = $this->getTotalAmount();

        // 1. Mijozsiz nasiya taqiqlanadi
        if (! $this->selectedCustomerId && $this->paidAmount < $totalAmount) {
            $this->errorMessage = "Mijozsiz (noma'lum xaridorga) nasiya savdo qilish taqiqlanadi! To'liq to'lov yoki mijozni tanlang.";

            return;
        }

        try {
            $sale = $saleService->execute(
                customerId: $this->selectedCustomerId,
                items: $this->items,
                operationId: $this->operationId,
                paidAmount: $this->paidAmount,
                cashAccountId: $this->cashAccountId,
                paymentType: $this->paymentType,
                paymentMethod: $this->paymentMethod,
                notes: $this->notes ?: null,
                userId: Auth::id(),
                source: 'web'
            );

            // Muvaffaqiyatli chek ma'lumotlari
            $this->completedSale = $sale->receipt_data ?? [
                'invoice_number' => $sale->invoice_number,
                'total_amount' => $sale->total_amount,
                'paid_amount' => $sale->paid_amount,
                'debt_amount' => $sale->debt_amount,
                'customer_name' => $sale->customer_name,
                'items' => $this->items,
            ];

            $this->posMessage = "Savdo muvaffaqiyatli yakunlandi! Chek: #{$sale->invoice_number}";

            // Yangi operatsiya uchun yangi qoralama
            $this->items = [];
            $this->selectedCustomerId = null;
            $this->selectedCustomerName = null;
            $this->customerCurrentDebt = 0;
            $this->paidAmount = 0;
            $this->paymentType = 'FULL';
            $this->notes = '';
            $this->operationId = Str::uuid()->toString();

        } catch (OperationValidationException $e) {
            // Xatoda savat qoladi!
            $this->errorMessage = $e->getMessage();
        } catch (\Throwable $e) {
            $this->errorMessage = 'Kutilmagan xatolik: '.$e->getMessage();
        }
    }

    public function render()
    {
        $recentCustomers = Customer::where('status', 'active')->latest()->take(6)->get();
        $recentVariants = ProductVariant::with(['product', 'volume'])
            ->where('status', 'active')
            ->latest()
            ->take(8)
            ->get();
        $cashAccounts = CashAccount::all();

        return view('livewire.sales.optom-pos', [
            'recentCustomers' => $recentCustomers,
            'recentVariants' => $recentVariants,
            'cashAccounts' => $cashAccounts,
            'totalAmount' => $this->getTotalAmount(),
            'debtAmount' => $this->getDebtAmount(),
            'finalCustomerDebt' => $this->getFinalCustomerDebt(),
        ]);
    }
}
