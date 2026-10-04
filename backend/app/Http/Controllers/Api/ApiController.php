<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\Catalog\CreateProductService;
use App\Services\Catalog\CreateVariantService;
use App\Services\Dashboard\DashboardQueryService;
use App\Services\Inventory\InventoryCalculatorService;
use App\Services\Payments\CustomerPaymentService;
use App\Services\Payments\SupplierPaymentService;
use App\Services\Purchase\ReceivePurchaseService;
use App\Services\Reports\ReportQueryService;
use App\Services\Sales\CreateSaleService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    public function __construct(
        protected CreateProductService $productService,
        protected CreateVariantService $variantService,
        protected ReceivePurchaseService $purchaseService,
        protected CreateSaleService $saleService,
        protected InventoryCalculatorService $calculatorService,
        protected DashboardQueryService $dashboardService,
        protected ReportQueryService $reportService,
        protected CustomerPaymentService $customerPaymentService,
        protected SupplierPaymentService $supplierPaymentService
    ) {}

    /**
     * Barcha mahsulotlar va ularning litr variantlari ro'yxati (Flutter & Web API)
     */
    public function getProducts(): JsonResponse
    {
        $products = Product::with(['variants.volume', 'variants.packages', 'variants.balance'])->get();

        $canViewCost = auth()->user()?->can('view_cost_price') ?? false;

        $formatted = $products->map(function ($p) use ($canViewCost) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'variants' => $p->variants->map(function ($v) use ($canViewCost) {
                    $stock = $v->balance ? (float) $v->balance->quantity : 0.0;
                    $wac = $v->balance ? (int) $v->balance->average_cost : 0;
                    $litres = $v->volume ? round($v->volume->value_ml / 1000, 2) : 0.5;

                    return [
                        'id' => $v->id,
                        'product_id' => $v->product_id,
                        'sku' => $v->sku,
                        'litres' => $litres, // Flutter model compatibility
                        'volume_ml' => $v->volume ? $v->volume->value_ml : 500,
                        'display_volume' => $v->volume ? $v->volume->name : "{$litres} L",
                        'stock_qty' => (int) $stock, // Flutter model compatibility
                        'current_stock' => $stock,
                        'cost_price' => $canViewCost ? (float) $wac : null, // Masked if unauthorized
                        'average_cost' => $canViewCost ? $wac : null,       // Masked if unauthorized
                        'retail_price' => (float) ($v->default_sale_price ?? 0), // Flutter model compatibility
                        'default_sale_price' => (int) ($v->default_sale_price ?? 0),
                        'packages' => $v->packages->map(fn ($pkg) => [
                            'id' => $pkg->id,
                            'name' => $pkg->name,
                            'units_per_package' => $pkg->units_per_package,
                        ]),
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
        ]);
    }

    /**
     * Mobildan yoki API orqali tezkor kirim qilish
     */
    public function storeInward(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|min:2',
            'product_name' => 'nullable|string|min:2',
            'litres' => 'nullable|numeric|min:0.1',
            'volume_ml' => 'nullable|integer|min:50',
            'variant_id' => 'nullable|exists:product_variants,id',
            'package_name' => 'nullable|string|in:dona,blok,yashik',
            'package_quantity' => 'nullable|numeric|min:0.1',
            'quantity' => 'required|numeric|min:0.1',
            'cost_price' => 'nullable|numeric|min:1',
            'purchase_price' => 'nullable|numeric|min:1',
            'supplier_name' => 'nullable|string',
            'invoice_number' => 'nullable|string',
        ]);

        try {
            $productName = trim($validated['product_name'] ?? $validated['name'] ?? '');
            $costPrice = (int) ($validated['purchase_price'] ?? $validated['cost_price'] ?? 0);
            $inputQty = (float) $validated['quantity'];
            $packageName = $validated['package_name'] ?? 'dona';

            // Supplier
            $supplierId = null;
            if (! empty($validated['supplier_name'])) {
                $supplier = Supplier::firstOrCreate(['name' => trim($validated['supplier_name'])]);
                $supplierId = $supplier->id;
            }

            // Variantni aniqlash yoki yaratish
            if (! empty($validated['variant_id'])) {
                $variant = ProductVariant::with(['packages', 'volume', 'product'])->findOrFail($validated['variant_id']);
            } else {
                if (empty($productName)) {
                    return response()->json(['status' => 'error', 'message' => 'Mahsulot nomi kiritilishi shart!'], 422);
                }

                $volumeMl = 500;
                if (! empty($validated['volume_ml'])) {
                    $volumeMl = (int) $validated['volume_ml'];
                } elseif (! empty($validated['litres'])) {
                    $volumeMl = (int) round(((float) $validated['litres']) * 1000);
                }

                $displayVolume = ($volumeMl >= 1000 && $volumeMl % 1000 === 0)
                    ? ($volumeMl / 1000).' L'
                    : (round($volumeMl / 1000, 2)).' L';

                $product = $this->productService->execute($productName, null, null, 'API_INWARD');
                $volume = $this->variantService->findOrCreateVolume($displayVolume, $volumeMl);
                $variant = $this->variantService->execute($product, $volume, null, null, 'API_INWARD');
                $variant->load(['packages', 'volume', 'product']);
            }

            // Qadoq konversiyasi
            $package = $variant->packages->firstWhere('name', $packageName);
            $unitsPerPackage = $package ? $package->units_per_package : 1;
            $totalUnits = (float) round($inputQty * $unitsPerPackage, 3);

            // Purchase items tayyorlash
            $items = [
                [
                    'variant_id' => $variant->id,
                    'package_id' => $package ? $package->id : null,
                    'package_quantity' => $inputQty,
                    'quantity' => $totalUnits,
                    'unit_cost' => $costPrice,
                ],
            ];

            $purchase = $this->purchaseService->execute(
                supplierId: $supplierId,
                items: $items,
                invoiceNumber: $validated['invoice_number'] ?? null,
                warehouseId: null,
                source: 'Flutter Mobile / API'
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Yuk muvaffaqiyatli qabul qilindi va Telegram kanalga xabarnoma yuborildi!',
                'data' => [
                    'purchase_id' => $purchase->id,
                    'invoice_number' => $purchase->invoice_number,
                    'total_amount' => $purchase->total_amount,
                    'total_units' => $totalUnits,
                ],
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Mobildan yoki API orqali optom savdo (chiqim) qilish
     */
    public function storeSale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
            'payment_type' => 'nullable|string|in:cash,card,debt,bank,mixed,full,partial,CASH,CARD,DEBT,BANK,MIXED,FULL,PARTIAL',
            'payment_method' => 'nullable|string|in:cash,card,bank,CASH,CARD,BANK',
            'paid_amount' => 'nullable|integer|min:0',
            'cash_account_id' => 'nullable|exists:cash_accounts,id',
            'operation_id' => 'nullable|uuid',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1|max:2147483647',
            'items.*.package_name' => 'nullable|string|in:dona',
            'items.*.unit_price' => 'nullable|integer|min:1',
            'items.*.sale_price' => 'nullable|integer|min:1',
            'items.*.price_version' => 'nullable|integer|min:1',
            'items.*.is_system_price' => 'nullable|boolean',
        ]);

        try {
            // Customer
            $customerId = $validated['customer_id'] ?? null;
            if (! $customerId && ! empty($validated['customer_name'])) {
                $customer = Customer::firstOrCreate(['name' => trim($validated['customer_name'])]);
                $customerId = $customer->id;
            }

            $paymentType = strtoupper($validated['payment_type'] ?? 'CASH');
            $paymentMethod = strtoupper($validated['payment_method'] ?? 'CASH');
            // null => CreateSaleService o'zi CASH/CARD/BANK uchun to'liq, DEBT uchun 0 deb hal qiladi
            $paidAmount = isset($validated['paid_amount']) ? (int) $validated['paid_amount'] : 0;
            // null => servis to'lov usuli (CASH/CARD/BANK) bo'yicha default kassani tanlaydi
            $cashAccountId = $validated['cash_account_id'] ?? null;
            $operationId = $validated['operation_id'] ?? null;
            $notes = $validated['notes'] ?? null;

            $preparedItems = [];
            foreach ($validated['items'] as $item) {
                $variant = ProductVariant::with(['packages', 'volume', 'product'])->findOrFail($item['variant_id']);
                $pkgName = $item['package_name'] ?? 'dona';
                $package = $variant->packages->firstWhere('name', $pkgName);
                $unitsPerPkg = $package ? $package->units_per_package : 1;

                $inputQty = (int) $item['quantity'];
                $totalUnits = $inputQty;

                $salePrice = (int) ($item['sale_price'] ?? $item['unit_price'] ?? $variant->default_sale_price ?? 0);
                $isSystemPrice = $item['is_system_price'] ?? ($salePrice === (int) $variant->default_sale_price);

                $preparedItems[] = [
                    'variant_id' => $variant->id,
                    'package_id' => $package ? $package->id : null,
                    'package_quantity' => $inputQty,
                    'quantity' => $totalUnits,
                    'sale_price' => $salePrice,
                    'is_system_price' => $isSystemPrice,
                    'price_version' => $item['price_version'] ?? null,
                ];
            }

            $sale = $this->saleService->execute(
                customerId: $customerId,
                items: $preparedItems,
                operationId: $operationId,
                paidAmount: $paidAmount,
                cashAccountId: $cashAccountId,
                paymentType: $paymentType,
                paymentMethod: $paymentMethod,
                notes: $notes,
                warehouseId: null,
                userId: auth()->id(),
                source: 'Flutter Mobile / API',
                rawPayload: $validated
            );

            $sale->load(['customer', 'items.variant.product', 'items.variant.volume']);
            $canViewCost = auth()->check() && auth()->user()->can('view_cost_price');

            return response()->json([
                'status' => 'success',
                'message' => 'Savdo yakunlandi va Telegram kanalga xabar yuborildi!',
                'data' => [
                    'sale_id' => $sale->id,
                    'id' => $sale->id,
                    'operation_id' => $sale->operation_id,
                    'invoice_number' => $sale->invoice_number,
                    'customer_id' => $sale->customer_id,
                    'customer_name' => $sale->customer?->name,
                    'total_amount' => $sale->total_amount,
                    'paid_amount' => $sale->paid_amount,
                    'debt_amount' => $sale->debt_amount,
                    'gross_profit' => $canViewCost ? $sale->gross_profit : null,
                    'payment_type' => $sale->payment_type,
                    'payment_method' => $sale->payment_method,
                    'created_at' => $sale->created_at?->toIso8601String(),
                    'items' => $sale->items->map(fn ($it) => [
                        'id' => $it->id,
                        'variant_id' => $it->variant_id,
                        'product_name' => $it->variant?->product?->name ?? 'Mahsulot',
                        'volume_name' => $it->variant?->volume?->name ?? '',
                        'quantity' => $it->quantity,
                        'sale_price' => $it->sale_price,
                        'total_price' => $it->total_price,
                        'is_system_price' => $it->is_system_price,
                    ]),
                ],
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Interaktiv Ombor Kalkulyatori API
     */
    public function calculate(Request $request): JsonResponse
    {
        if (! auth()->user()?->can('view_cost_price')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sizda tannarx va ombor rentabellik kalkulyatsiyasini ko\'rish huquqi yo\'q.',
            ], 403);
        }

        $variantIds = $request->input('variant_ids', []);
        if (empty($variantIds)) {
            $variantIds = ProductVariant::pluck('id')->toArray();
        }

        $result = $this->calculatorService->calculate($variantIds);

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    /**
     * Dashboard statistikasi (rolga moslashtirilgan real SQL ma'lumotlar)
     */
    public function dashboard(Request $request): JsonResponse
    {
        $period = $request->input('period', 'today');
        $customStart = $request->input('start_date');
        $customEnd = $request->input('end_date');

        $data = $this->dashboardService->getDashboardData(
            user: $request->user(),
            period: $period,
            customStart: $customStart,
            customEnd: $customEnd
        );

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Mijozlar ro'yxati (Search va qarz balansi bilan)
     */
    public function getCustomers(Request $request): JsonResponse
    {
        $search = $request->input('search');
        $query = Customer::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('store_name', 'ilike', "%{$search}%");
            });
        }

        $customers = $query->orderBy('name')->limit(100)->get();

        return response()->json([
            'status' => 'success',
            'data' => $customers->map(fn ($c) => [
                'id' => $c->id,
                'uuid' => $c->uuid,
                'name' => $c->name,
                'phone' => $c->phone,
                'store_name' => $c->store_name,
                'address' => $c->address,
                'debt_limit' => (int) $c->debt_limit,
                'current_debt' => (int) $c->current_debt,
                'display_name' => $c->display_name,
            ]),
        ]);
    }

    /**
     * Yangi mijoz yaratish (inline POS yoki alohida forma)
     */
    public function storeCustomer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|min:2',
            'phone' => 'nullable|string',
            'store_name' => 'nullable|string',
            'address' => 'nullable|string',
            'debt_limit' => 'nullable|integer|min:0',
        ]);

        $customer = Customer::create([
            'name' => trim($validated['name']),
            'phone' => $validated['phone'] ?? null,
            'store_name' => $validated['store_name'] ?? null,
            'address' => $validated['address'] ?? null,
            'debt_limit' => $validated['debt_limit'] ?? 0,
            'current_debt' => 0,
            'status' => 'ACTIVE',
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Mijoz muvaffaqiyatli saqlandi',
            'data' => [
                'id' => $customer->id,
                'uuid' => $customer->uuid,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'store_name' => $customer->store_name,
                'address' => $customer->address,
                'debt_limit' => (int) $customer->debt_limit,
                'current_debt' => 0,
                'display_name' => $customer->display_name,
            ],
        ], 201);
    }

    /**
     * Ta'minotchilar ro'yxati (Search va qarz balansi bilan)
     */
    public function getSuppliers(Request $request): JsonResponse
    {
        $search = $request->input('search');
        $query = Supplier::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('company_name', 'ilike', "%{$search}%");
            });
        }

        $suppliers = $query->orderBy('name')->limit(100)->get();

        return response()->json([
            'status' => 'success',
            'data' => $suppliers->map(fn ($s) => [
                'id' => $s->id,
                'uuid' => $s->uuid,
                'name' => $s->name,
                'company_name' => $s->company_name,
                'phone' => $s->phone,
                'address' => $s->address,
                'balance' => (int) $s->balance,
                'credit_limit' => (int) $s->credit_limit,
                'display_name' => $s->display_name,
            ]),
        ]);
    }

    /**
     * Yangi ta'minotchi yaratish (inline Kirim yoki alohida forma)
     */
    public function storeSupplier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|min:2',
            'company_name' => 'nullable|string',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $supplier = Supplier::create([
            'name' => trim($validated['name']),
            'company_name' => $validated['company_name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'balance' => 0,
            'status' => 'ACTIVE',
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Ta'minotchi muvaffaqiyatli saqlandi",
            'data' => [
                'id' => $supplier->id,
                'uuid' => $supplier->uuid,
                'name' => $supplier->name,
                'company_name' => $supplier->company_name,
                'phone' => $supplier->phone,
                'address' => $supplier->address,
                'balance' => 0,
                'display_name' => $supplier->display_name,
            ],
        ], 201);
    }

    /**
     * Kassa hisob raqamlari
     */
    public function getCashAccounts(): JsonResponse
    {
        $accounts = CashAccount::all();

        return response()->json([
            'status' => 'success',
            'data' => $accounts->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type,
                'balance' => (int) $a->balance,
                'is_default' => (bool) $a->is_default,
            ]),
        ]);
    }

    /**
     * Mijozdan qarz yig'ish yoki ta'minotchiga to'lov qilish
     */
    public function storePayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:customer,supplier,CUSTOMER,SUPPLIER',
            'party_id' => 'required|integer',
            'amount' => 'required|integer|min:1',
            'cash_account_id' => 'nullable|exists:cash_accounts,id',
            'payment_method' => 'nullable|string|in:cash,card,bank,CASH,CARD,BANK',
            'operation_id' => 'nullable|uuid',
            'notes' => 'nullable|string',
            'confirm_excess_as_advance' => 'nullable|boolean',
        ]);

        try {
            $type = strtolower($validated['type']);
            $cashAccountId = $validated['cash_account_id'] ?? null;
            if (! $cashAccountId) {
                $defaultAccount = CashAccount::where('is_default', true)->first() ?? CashAccount::first();
                $cashAccountId = $defaultAccount?->id;
            }

            $method = strtoupper($validated['payment_method'] ?? 'CASH');
            $opId = $validated['operation_id'] ?? null;
            $notes = $validated['notes'] ?? null;
            $confirmAdvance = (bool) ($validated['confirm_excess_as_advance'] ?? true);

            if ($type === 'customer') {
                $result = $this->customerPaymentService->execute(
                    customerId: (int) $validated['party_id'],
                    amount: (int) $validated['amount'],
                    cashAccountId: (int) $cashAccountId,
                    paymentMethod: $method,
                    operationId: $opId,
                    userId: auth()->id(),
                    notes: $notes,
                    confirmExcessAsAdvance: $confirmAdvance
                );
            } else {
                $result = $this->supplierPaymentService->execute(
                    supplierId: (int) $validated['party_id'],
                    amount: (int) $validated['amount'],
                    cashAccountId: (int) $cashAccountId,
                    paymentMethod: $method,
                    operationId: $opId,
                    userId: auth()->id(),
                    notes: $notes,
                    confirmExcessAsAdvance: $confirmAdvance
                );
            }

            return response()->json([
                'status' => 'success',
                'message' => "To'lov muvaffaqiyatli qabul qilindi",
                'data' => $result,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Savdo tarixi (So'nggi savdolar ro'yxati)
     */
    public function getSalesHistory(Request $request): JsonResponse
    {
        $canViewCost = auth()->check() && auth()->user()->can('view_cost_price');

        $sales = Sale::with(['customer', 'items.variant.product', 'items.variant.volume'])
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $sales->map(fn ($sale) => [
                'id' => $sale->id,
                'operation_id' => $sale->operation_id,
                'invoice_number' => $sale->invoice_number,
                'customer_name' => $sale->customer?->name ?? 'Tezkor xaridor',
                'total_amount' => (int) $sale->total_amount,
                'paid_amount' => (int) $sale->paid_amount,
                'debt_amount' => (int) $sale->debt_amount,
                'gross_profit' => $canViewCost ? (int) $sale->gross_profit : null,
                'payment_type' => $sale->payment_type,
                'status' => $sale->status,
                'created_at' => $sale->created_at?->toIso8601String(),
                'items_count' => $sale->items->count(),
                'items' => $sale->items->map(fn ($it) => [
                    'id' => $it->id,
                    'product_name' => $it->variant?->product?->name ?? 'Noma\'lum',
                    'volume_name' => $it->variant?->volume?->name ?? '',
                    'quantity' => $it->quantity,
                    'sale_price' => (int) $it->sale_price,
                    'total_price' => (int) $it->total_price,
                ]),
            ]),
        ]);
    }

    /**
     * Hisobotlar (Davriy savdo va kassa xulosalari)
     */
    public function getReports(Request $request): JsonResponse
    {
        $filters = [
            'period' => $request->input('period', 'today'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ];

        $salesSummary = $this->reportService->getSalesSummary($filters);
        $cashSummary = $this->reportService->getCashSummary($filters);

        return response()->json([
            'status' => 'success',
            'data' => [
                'sales' => $salesSummary,
                'cash' => $cashSummary,
            ],
        ]);
    }
}
