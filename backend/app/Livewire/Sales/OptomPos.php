<?php

namespace App\Livewire\Sales;

use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Volume;
use App\Services\Operations\Exceptions\OperationValidationException;
use App\Services\Parties\CustomerService;
use App\Services\Sales\CreateSaleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class OptomPos extends Component
{
    public string $operationId = '';

    public string $salesMode = 'quick';

    public string $customerSearch = '';

    public ?int $selectedCustomerId = null;

    public ?string $selectedCustomerName = null;

    public int $customerCurrentDebt = 0;

    public ?int $selectedProductId = null;

    public ?int $selectedVolumeId = null;

    public array $items = [];

    #[Locked]
    public array $invalidFields = [];

    // To'lov parametrlari
    public string $paymentType = 'FULL'; // FULL, PARTIAL, DEBT

    public int $paidAmount = 0;

    public string $paymentMethod = 'CASH'; // CASH, CARD, BANK

    public ?int $cashAccountId = null;

    public string $notes = '';

    public ?string $posMessage = null;

    public ?string $errorMessage = null;

    public ?array $completedSale = null;

    public function updatedPaymentMethod(): void
    {
        if (in_array($this->paymentMethod, ['CASH', 'CARD', 'BANK'], true)) {
            $this->cashAccountId = CashAccount::where('type', $this->paymentMethod)
                ->orderByDesc('is_default')->orderBy('id')->value('id');
        }
    }

    public function updatedCashAccountId(): void
    {
        $account = CashAccount::find($this->cashAccountId);
        if ($account && in_array($account->type, ['CASH', 'CARD', 'BANK'], true)) {
            $this->paymentMethod = $account->type;
        }
    }

    public function updatedSelectedProductId(): void
    {
        $this->selectedVolumeId = null;
    }

    public function setSalesMode(string $mode): void
    {
        if (! in_array($mode, ['quick', 'customer'], true)) {
            return;
        }

        $this->salesMode = $mode;
        if ($mode === 'quick') {
            $this->selectedCustomerId = null;
            $this->selectedCustomerName = null;
            $this->customerCurrentDebt = 0;
            $this->customerSearch = '';
            $this->paymentType = 'FULL';
            $this->syncPaymentAmount();
        }
    }

    public function mount(): void
    {
        $this->operationId = Str::uuid()->toString();
        $this->salesMode = request()->query('mode') === 'customer' ? 'customer' : $this->salesMode;

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
            'price_entered' => (int) $price > 0,
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

        $customer = Customer::where('status', 'active')->find($customerId);
        if ($customer) {
            $this->selectedCustomerId = $customer->id;
            $this->selectedCustomerName = $customer->display_name;
            $this->customerCurrentDebt = (int) $customer->current_debt;
        }
    }

    public function addSelectedVariant(): void
    {
        $variant = ProductVariant::with(['product', 'volume'])->where('status', 'active')
            ->where('product_id', $this->selectedProductId)
            ->where('volume_id', $this->selectedVolumeId)->first();
        if (! $variant) {
            $this->errorMessage = 'Avval mahsulotni, keyin uning hajmini tanlang.';

            return;
        }
        $this->onProductCreated([
            'variant_id' => $variant->id, 'sku' => $variant->sku,
            'display_name' => $variant->product->name.' — '.$variant->volume->name,
            'sale_price' => $variant->default_sale_price,
        ]);
    }

    public function removeItem(int $index): void
    {
        if (isset($this->items[$index])) {
            unset($this->invalidFields['quantity-'.$this->items[$index]['variant_id']], $this->invalidFields['price-'.$this->items[$index]['variant_id']]);
            $this->resetErrorBag('quantity-'.$this->items[$index]['variant_id']);
            $this->resetErrorBag('price-'.$this->items[$index]['variant_id']);
        }
        $this->reset(['errorMessage', 'posMessage']);
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->syncPaymentAmount();
    }

    public function updateQuantity(int $index, mixed $qty): void
    {
        if (! isset($this->items[$index])) {
            return;
        }
        $errorKey = 'quantity-'.$this->items[$index]['variant_id'];
        $value = filter_var($qty, FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            $this->errorMessage = 'Dona sonini 1 yoki undan katta butun raqam bilan yozing.';

            $this->invalidFields[$errorKey] = $this->errorMessage;
            $this->addError($errorKey, $this->errorMessage);

            return;
        }
        unset($this->invalidFields[$errorKey]);
        $this->resetErrorBag($errorKey);
        $this->reset(['errorMessage', 'posMessage']);
        $this->items[$index]['quantity'] = $value;
        $this->items[$index]['total'] = $value * $this->items[$index]['price'];
        $this->syncPaymentAmount();
    }

    public function updatePrice(int $index, mixed $price): void
    {
        if (! isset($this->items[$index])) {
            return;
        }
        $errorKey = 'price-'.$this->items[$index]['variant_id'];
        $value = filter_var($price, FILTER_VALIDATE_INT);
        if ($value === false || $value < 0) {
            $this->errorMessage = 'Narxni 0 yoki undan katta butun raqam bilan yozing.';

            $this->invalidFields[$errorKey] = $this->errorMessage;
            $this->addError($errorKey, $this->errorMessage);

            return;
        }
        unset($this->invalidFields[$errorKey]);
        $this->resetErrorBag($errorKey);
        $this->reset(['errorMessage', 'posMessage']);
        $this->items[$index]['price'] = $value;
        $this->items[$index]['price_entered'] = true;
        $this->items[$index]['is_system_price'] = false;
        $this->items[$index]['total'] = $this->items[$index]['quantity'] * $value;
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
                $this->items[$index]['price_entered'] = true;
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
        $this->updatedPaymentType();
    }

    public function updatedPaymentType(): void
    {
        if ($this->paymentType === 'PARTIAL') {
            $this->paidAmount = 0;
        }
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
        $this->invalidFields = [];
        $this->resetErrorBag();
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
        if ($this->invalidFields !== []) {
            foreach ($this->invalidFields as $key => $message) {
                $this->addError($key, $message);
            }
            $this->errorMessage = 'Dona va narx maydonlaridagi xatolarni tuzating.';

            return;
        }

        $this->reset(['errorMessage', 'posMessage', 'completedSale']);

        if (empty($this->items)) {
            $this->errorMessage = "Savdo savati bo'sh! Kamida bitta tovar tanlang.";

            return;
        }

        foreach ($this->items as $item) {
            if (! ($item['price_entered'] ?? false)) {
                $this->errorMessage = 'Har bir mahsulotning sotuv narxini yozing.';

                return;
            }
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

            $this->completedSale['remaining_party_balance'] = (int) Customer::find($this->selectedCustomerId)?->current_debt;
            $this->completedSale['remaining_stock'] = array_map(fn ($item) => [
                'name' => $item['display_name'],
                'quantity' => (int) InventoryBalance::where('product_variant_id', $item['variant_id'])->sum('quantity'),
            ], $this->items);
            if (Auth::user()?->hasPermission('view_cash') || Auth::user()?->isOwner()) {
                $this->completedSale['remaining_cash'] = (int) CashAccount::find($this->cashAccountId)?->balance;
            }
            $this->dispatch('refresh-dashboard');

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
        $products = Product::where('status', 'active')
            ->whereHas('variants', fn ($query) => $query->where('status', 'active'))
            ->orderBy('name')->get();
        $availableVolumes = Volume::where('status', 'active')
            ->whereHas('variants', fn ($query) => $query->where('product_id', $this->selectedProductId)
                ->where('status', 'active'))->orderBy('value_ml')->get();

        return view('livewire.sales.optom-pos', [
            'products' => $products,
            'availableVolumes' => $availableVolumes,
            'recentCustomers' => Customer::where('status', 'active')->latest()->take(6)->get(),
            'customerResults' => trim($this->customerSearch) !== ''
                ? app(CustomerService::class)->search($this->customerSearch) : collect(),
            'cashAccounts' => CashAccount::all(),
            'totalAmount' => $this->getTotalAmount(),
            'debtAmount' => $this->getDebtAmount(),
            'finalCustomerDebt' => $this->getFinalCustomerDebt(),
        ]);
    }
}
