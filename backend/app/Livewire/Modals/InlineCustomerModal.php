<?php

namespace App\Livewire\Modals;

use App\Services\Parties\CustomerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class InlineCustomerModal extends Component
{
    #[Locked]
    public string $customerUuid = '';

    public bool $isOpen = false;

    public string $name = '';

    public string $phone = '';

    public string $storeName = '';

    public string $address = '';

    public ?string $phoneWarning = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->customerUuid = (string) Str::uuid();
    }

    #[On('open-inline-customer-modal')]
    public function open(): void
    {
        $this->customerUuid = (string) Str::uuid();
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->phoneWarning = null;
        $this->name = '';
        $this->phone = '';
        $this->storeName = '';
        $this->address = '';
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function updatedPhone(CustomerService $customerService): void
    {
        $this->phoneWarning = $customerService->checkPhoneDuplicate($this->phone);
    }

    public function save(CustomerService $customerService): void
    {
        $this->validate([
            'name' => 'required|min:2|max:100',
            'phone' => 'nullable|string|max:25',
            'storeName' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:200',
        ], [
            'name.required' => 'Mijoz ismi kiritilishi shart.',
            'name.min' => 'Mijoz ismi kamida 2 ta harfdan iborat bo\'lsin.',
        ]);

        try {
            $customer = $customerService->createCustomer([
                'uuid' => $this->customerUuid,
                'name' => $this->name,
                'phone' => $this->phone,
                'store_name' => $this->storeName,
                'address' => $this->address,
            ], createdBy: Auth::id());

            $this->isOpen = false;

            // Ota komponentga hodisa yuboramiz — savdo qoralamasi buzilmasdan mijoz tanlanadi!
            $this->dispatch('customer-created', [
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'display_name' => $customer->display_name,
                'phone' => $customer->phone,
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $msg) {
                    $this->addError($field === 'store_name' ? 'storeName' : $field, $msg);
                }
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Xatolik: '.$e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.modals.inline-customer-modal');
    }
}
