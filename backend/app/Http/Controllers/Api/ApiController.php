<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\Catalog\CreateProductService;
use App\Services\Catalog\CreateVariantService;
use App\Services\Inventory\InventoryCalculatorService;
use App\Services\Purchase\ReceivePurchaseService;
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
        protected InventoryCalculatorService $calculatorService
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
            'payment_type' => 'required|string|in:cash,card,debt,bank,CASH,CARD,DEBT,BANK',
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:0.1',
            'items.*.package_name' => 'nullable|string|in:dona,blok,yashik',
            'items.*.unit_price' => 'nullable|numeric',
            'items.*.sale_price' => 'nullable|numeric',
            'items.*.is_system_price' => 'nullable|boolean',
        ]);

        try {
            // Customer
            $customerId = $validated['customer_id'] ?? null;
            if (! $customerId && ! empty($validated['customer_name'])) {
                $customer = Customer::firstOrCreate(['name' => trim($validated['customer_name'])]);
                $customerId = $customer->id;
            }

            $paymentType = strtoupper($validated['payment_type']);

            $preparedItems = [];
            foreach ($validated['items'] as $item) {
                $variant = ProductVariant::with(['packages', 'volume', 'product'])->findOrFail($item['variant_id']);
                $pkgName = $item['package_name'] ?? 'dona';
                $package = $variant->packages->firstWhere('name', $pkgName);
                $unitsPerPkg = $package ? $package->units_per_package : 1;

                $inputQty = (float) $item['quantity'];
                $totalUnits = (float) round($inputQty * $unitsPerPkg, 3);

                $salePrice = (int) ($item['sale_price'] ?? $item['unit_price'] ?? $variant->default_sale_price ?? 0);
                $isSystemPrice = $item['is_system_price'] ?? ($salePrice === (int) $variant->default_sale_price);

                $preparedItems[] = [
                    'variant_id' => $variant->id,
                    'package_id' => $package ? $package->id : null,
                    'package_quantity' => $inputQty,
                    'quantity' => $totalUnits,
                    'sale_price' => $salePrice,
                    'is_system_price' => $isSystemPrice,
                ];
            }

            $sale = $this->saleService->execute(
                customerId: $customerId,
                items: $preparedItems,
                paymentType: $paymentType,
                warehouseId: null,
                source: 'Flutter Mobile / API'
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Savdo yakunlandi va Telegram kanalga xabar yuborildi!',
                'data' => [
                    'sale_id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'total_amount' => $sale->total_amount,
                    'gross_profit' => (auth()->check() && ! auth()->user()->can('view_cost_price')) ? null : $sale->gross_profit,
                    'payment_type' => $sale->payment_type,
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
}
