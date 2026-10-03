<?php

namespace App\Livewire\Sales;

use App\Models\Customer;
use App\Models\ProductVariant;
use Livewire\Attributes\On;
use Livewire\Component;

class OptomPos extends Component
{
    public ?int $selectedCustomerId = null;

    public ?string $selectedCustomerName = null;

    public array $items = [];

    public string $notes = '';

    public ?string $posMessage = null;

    #[On('product-created')]
    public function onProductCreated(array $payload): void
    {
        $variantId = $payload['variant_id'];

        // Tekshiramiz: agar savatda allaqachon bo'lsa, sonini oshiramiz
        foreach ($this->items as $index => $item) {
            if ($item['variant_id'] === $variantId) {
                $this->items[$index]['quantity'] += 1;
                $this->items[$index]['total'] = $this->items[$index]['quantity'] * $this->items[$index]['price'];
                $this->posMessage = "Savatdagi mahsulot soni oshirildi: {$payload['display_name']}";

                return;
            }
        }

        // Yangi qator sifatida savat qoralamasiga qo'shamiz (mavjud qoralama saqlanadi!)
        $price = $payload['sale_price'] ?? 0;
        $this->items[] = [
            'variant_id' => $variantId,
            'display_name' => $payload['display_name'],
            'sku' => $payload['sku'],
            'quantity' => 1,
            'price' => $price,
            'is_system_price' => $price > 0,
            'total' => $price * 1,
        ];

        $this->posMessage = "Yangi mahsulot saqlandi va savatga qo'shildi: {$payload['display_name']}";
    }

    #[On('customer-created')]
    public function onCustomerCreated(array $payload): void
    {
        $this->selectedCustomerId = $payload['customer_id'];
        $this->selectedCustomerName = $payload['display_name'];
        $this->posMessage = "Yangi mijoz saqlandi va tanlandi: {$payload['name']}";
    }

    public function selectExistingCustomer(int $customerId): void
    {
        $customer = Customer::find($customerId);
        if ($customer) {
            $this->selectedCustomerId = $customer->id;
            $this->selectedCustomerName = $customer->display_name;
        }
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function updateQuantity(int $index, int $qty): void
    {
        if ($qty <= 0) {
            $this->removeItem($index);

            return;
        }
        $this->items[$index]['quantity'] = $qty;
        $this->items[$index]['total'] = $qty * $this->items[$index]['price'];
    }

    public function updatePrice(int $index, int $price): void
    {
        $this->items[$index]['price'] = max(0, $price);
        $this->items[$index]['total'] = $this->items[$index]['quantity'] * $this->items[$index]['price'];
    }

    public function clearDraft(): void
    {
        $this->items = [];
        $this->selectedCustomerId = null;
        $this->selectedCustomerName = null;
        $this->notes = '';
        $this->posMessage = null;
    }

    public function getTotalAmount(): int
    {
        return array_sum(array_column($this->items, 'total'));
    }

    public function render()
    {
        $recentCustomers = Customer::where('status', 'active')->latest()->take(5)->get();
        $recentVariants = ProductVariant::with(['product', 'volume'])
            ->where('status', 'active')
            ->latest()
            ->take(8)
            ->get();

        return view('livewire.sales.optom-pos', [
            'recentCustomers' => $recentCustomers,
            'recentVariants' => $recentVariants,
            'totalAmount' => $this->getTotalAmount(),
        ]);
    }
}
