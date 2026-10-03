<?php

namespace App\Livewire\Parties;

use App\Exceptions\CannotDeleteReferencedRecordException;
use App\Models\Customer;
use App\Services\Parties\CustomerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class CustomerManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'active';

    // Create Customer Modal state
    public bool $showCreateModal = false;

    public string $name = '';

    public string $phone = '';

    public string $storeName = '';

    public string $address = '';

    public ?int $debtLimit = null;

    public string $notes = '';

    public ?string $phoneWarning = null;

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'active'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPhone(CustomerService $customerService): void
    {
        $this->phoneWarning = $customerService->checkPhoneDuplicate($this->phone);
    }

    public function openCreateModal(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->phoneWarning = null;
        $this->name = '';
        $this->phone = '';
        $this->storeName = '';
        $this->address = '';
        $this->debtLimit = null;
        $this->notes = '';
        $this->showCreateModal = true;
    }

    public function saveCustomer(CustomerService $customerService): void
    {
        $this->validate([
            'name' => 'required|min:2|max:100',
            'phone' => 'nullable|string|max:25',
            'storeName' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:200',
            'debtLimit' => 'nullable|integer|min:0',
        ], [
            'name.required' => 'Mijoz ismi kiritilishi shart.',
            'name.min' => 'Mijoz ismi kamida 2 ta harfdan iborat bo\'lsin.',
        ]);

        try {
            $customer = $customerService->createCustomer([
                'name' => $this->name,
                'phone' => $this->phone,
                'store_name' => $this->storeName,
                'address' => $this->address,
                'debt_limit' => $this->debtLimit ?? 0,
                'notes' => $this->notes,
            ], createdBy: Auth::id());

            $this->showCreateModal = false;
            $this->successMessage = "Mijoz muvaffaqiyatli saqlandi: {$customer->name}";
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

    public function toggleArchive(int $customerId, CustomerService $customerService): void
    {
        $customer = Customer::findOrFail($customerId);
        if ($customer->status === 'active') {
            $customerService->archiveCustomer($customer);
            $this->successMessage = "Mijoz arxivlandi: {$customer->name}";
        } else {
            $customer->update(['status' => 'active']);
            $this->successMessage = "Mijoz qayta faollashtirildi: {$customer->name}";
        }
    }

    public function deleteCustomer(int $customerId, CustomerService $customerService): void
    {
        $customer = Customer::findOrFail($customerId);

        try {
            $customerService->deleteCustomer($customer);
            $this->successMessage = 'Mijoz muvaffaqiyatli o\'chirildi.';
        } catch (CannotDeleteReferencedRecordException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $this->errorMessage = 'Xatolik: '.$e->getMessage();
        }
    }

    public function render()
    {
        $query = Customer::query();

        if ($this->search !== '') {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                    ->orWhere('store_name', 'ilike', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('address', 'ilike', "%{$term}%");
            });
        }

        if ($this->statusFilter === 'active') {
            $query->where('status', 'active');
        } elseif ($this->statusFilter === 'archived') {
            $query->where('status', 'archived');
        }

        $customers = $query->orderBy('name')->paginate(15);

        return view('livewire.parties.customer-manager', [
            'customers' => $customers,
        ]);
    }
}
