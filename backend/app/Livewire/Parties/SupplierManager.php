<?php

namespace App\Livewire\Parties;

use App\Exceptions\CannotDeleteReferencedRecordException;
use App\Models\Supplier;
use App\Services\Parties\SupplierService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'active';

    // Create Supplier Modal state
    public bool $showCreateModal = false;

    public string $name = '';

    public string $companyName = '';

    public string $phone = '';

    public string $address = '';

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

    public function openCreateModal(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->name = '';
        $this->companyName = '';
        $this->phone = '';
        $this->address = '';
        $this->showCreateModal = true;
    }

    public function saveSupplier(SupplierService $supplierService): void
    {
        $this->validate([
            'name' => 'required|min:2|max:100',
            'companyName' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:25',
            'address' => 'nullable|string|max:200',
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

            $this->showCreateModal = false;
            $this->successMessage = "Ta'minotchi muvaffaqiyatli saqlandi: {$supplier->name}";
        } catch (\Exception $e) {
            $this->errorMessage = 'Xatolik: '.$e->getMessage();
        }
    }

    public function toggleArchive(int $supplierId, SupplierService $supplierService): void
    {
        $supplier = Supplier::findOrFail($supplierId);
        if ($supplier->status === 'active') {
            $supplierService->archiveSupplier($supplier);
            $this->successMessage = "Ta'minotchi arxivlandi: {$supplier->name}";
        } else {
            $supplier->update(['status' => 'active']);
            $this->successMessage = "Ta'minotchi qayta faollashtirildi: {$supplier->name}";
        }
    }

    public function deleteSupplier(int $supplierId, SupplierService $supplierService): void
    {
        $supplier = Supplier::findOrFail($supplierId);

        try {
            $supplierService->deleteSupplier($supplier);
            $this->successMessage = 'Ta\'minotchi muvaffaqiyatli o\'chirildi.';
        } catch (CannotDeleteReferencedRecordException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $this->errorMessage = 'Xatolik: '.$e->getMessage();
        }
    }

    public function render()
    {
        $query = Supplier::query();

        if ($this->search !== '') {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                    ->orWhere('company_name', 'ilike', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('address', 'ilike', "%{$term}%");
            });
        }

        if ($this->statusFilter === 'active') {
            $query->where('status', 'active');
        } elseif ($this->statusFilter === 'archived') {
            $query->where('status', 'archived');
        }

        $suppliers = $query->orderBy('name')->paginate(15);

        return view('livewire.parties.supplier-manager', [
            'suppliers' => $suppliers,
        ]);
    }
}
