<?php

namespace App\Livewire\Modals;

use App\Services\Parties\SupplierService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class InlineSupplierModal extends Component
{
    public bool $isOpen = false;

    public string $name = '';

    public string $companyName = '';

    public string $phone = '';

    public string $address = '';

    public ?string $errorMessage = null;

    #[On('open-inline-supplier-modal')]
    public function open(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->name = '';
        $this->companyName = '';
        $this->phone = '';
        $this->address = '';
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function save(SupplierService $supplierService): void
    {
        $this->validate([
            'name' => 'required|min:2|max:100',
            'phone' => 'nullable|string|max:25',
        ], [
            'name.required' => 'Ta\'minotchi nomi kiritilishi shart.',
            'name.min' => 'Ta\'minotchi nomi kamida 2 ta harfdan iborat bo\'lsin.',
        ]);

        try {
            $supplier = $supplierService->createSupplier([
                'name' => $this->name,
                'company_name' => $this->companyName,
                'phone' => $this->phone,
                'address' => $this->address,
            ], createdBy: Auth::id());

            $this->isOpen = false;

            // Ota komponentga hodisa yuboramiz — kirim qoralamasi saqlanib ta'minotchi tanlanadi!
            $this->dispatch('supplier-created', [
                'supplier_id' => $supplier->id,
                'name' => $supplier->name,
                'display_name' => $supplier->display_name,
                'company_name' => $supplier->company_name,
            ]);
        } catch (\Exception $e) {
            $this->errorMessage = 'Xatolik: '.$e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.modals.inline-supplier-modal');
    }
}
