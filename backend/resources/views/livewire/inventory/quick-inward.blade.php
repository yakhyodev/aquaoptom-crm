<div class="trade-workspace trade-receiving">
    <livewire:modals.inline-product-modal />
    <livewire:modals.inline-supplier-modal />

    <div class="trade-hero">
        <div>
            <span class="trade-eyebrow">OMBORGA MAHSULOT QABUL QILISH</span>
            <h1>Yangi mahsulotlar keldimi?</h1>
            <p>Mahsulotni tanlang, dona va narxini yozing. Qolganini tizim hisoblaydi.</p>
        </div>
        <div class="trade-path" aria-label="Kirim tartibi"><span><b>1</b> Mahsulot</span><span><b>2</b> Yetkazuvchi</span><span><b>3</b> Saqlash</span></div>
    </div>

    @if ($activeTab !== 'receiving')
        <button type="button" wire:click="$set('activeTab', 'receiving')" class="trade-button trade-button-secondary">← Kirimga qaytish</button>
        @if ($activeTab === 'catalog') <livewire:catalog.product-manager /> @else <livewire:parties.supplier-manager /> @endif
    @else
        @if ($successPurchase)
            <div class="trade-success" role="status">
                <span class="trade-success-icon">✓</span>
                <div><h2>Mahsulotlar omborga qo‘shildi!</h2><p>{{ $successPurchase['supplier_name'] }} · Hujjat № {{ $successPurchase['invoice_number'] }}</p></div>
                <strong>{{ number_format($successPurchase['total_amount'], 0, '', ' ') }} so‘m</strong>
                <button type="button" wire:click="$set('successPurchase', null)" class="trade-button trade-button-secondary">Yana kirim qilish</button>
            </div>
            <div class="operation-outcome" role="status"><h3>Amaldan keyingi holat</h3><div>@foreach($successPurchase['remaining_stock'] ?? [] as $stock)<p><span>{{ $stock['name'] }} — omborda</span><strong>{{ number_format($stock['quantity'], 0, '', ' ') }} dona</strong></p>@endforeach
        @if(isset($successPurchase['remaining_cash']))<p><span>Tanlangan kassada qolgan pul</span><strong>{{ number_format($successPurchase['remaining_cash'], 0, '', ' ') }} so‘m</strong></p>@endif
        @if(($successPurchase['remaining_party_balance'] ?? 0) !== 0)<p><span>{{ $successPurchase['remaining_party_balance'] < 0 ? 'Oldindan to‘langan pul' : 'Yetkazuvchiga qolgan qarzimiz' }}</span><strong>{{ number_format(abs($successPurchase['remaining_party_balance']), 0, '', ' ') }} so‘m</strong></p>@endif</div><a href="{{ route('inventory.index') }}">Omborda ko‘rish →</a><a href="{{ route('debts.index') }}">Qarzlar va to‘lovlar →</a></div>
    @endif
        @if ($errorMessage) <div class="trade-alert trade-alert-error" role="alert">{{ $errorMessage }}</div> @endif
        @if ($inwardMessage && ! $successPurchase) <div class="trade-alert" role="status">{{ $inwardMessage }}</div> @endif

        <div class="trade-layout">
            <div class="trade-main">
                <section class="trade-card">
                    <div class="trade-card-heading">
                        <div class="trade-heading"><span class="trade-step">1</span><div><h2>Qaysi mahsulot keldi?</h2><p>Mahsulot va hajmni tanlab, ro‘yxatga qo‘shing.</p></div></div>
                        <button type="button" wire:click="$dispatch('open-inline-product-modal')" class="trade-button trade-button-secondary">+ Yangi Mahsulot / hajm</button>
                    </div>
                    <x-trade-product-picker :products="$products" :volumes="$availableVolumes" :selected-product="$selectedProductId" :selected-volume="$selectedVolumeId" />
                    @if ($products->isEmpty()) <p class="trade-help">Birinchi mahsulotingizni «Yangi Mahsulot / hajm» orqali qo‘shing.</p> @endif

                    <div class="trade-items">
                        @forelse ($items as $index => $item)
                            <article class="trade-item" wire:key="inward-item-{{ $item['variant_id'] }}">
                                <div class="trade-item-heading"><span class="trade-product-icon">📦</span><div><h3>{{ $item['display_name'] }}</h3><p>Dona soni va bir donasining kirim narxini yozing.</p></div><button type="button" wire:click="removeItem({{ $index }})" class="trade-remove" aria-label="{{ $item['display_name'] }} ni olib tashlash">×</button></div>
                                <div class="trade-item-fields">
                                    <div class="trade-field"><label for="inward-quantity-{{ $index }}">Nechta dona?</label><div class="trade-input-unit"><input id="inward-quantity-{{ $index }}" type="number" min="1" step="1" inputmode="numeric" value="{{ $item['quantity'] }}" wire:input.debounce.300ms="updateQuantity({{ $index }}, $event.target.value)"><span>dona</span></div>@if(isset($invalidFields['quantity-'.$item['variant_id']]))<p class="trade-input-error">{{ $invalidFields['quantity-'.$item['variant_id']] }}</p>@endif</div>
                                    <div class="trade-field"><label for="inward-cost-{{ $index }}">1 donasining kirim narxi</label><div class="trade-input-unit"><input id="inward-cost-{{ $index }}" type="number" min="1" step="1" inputmode="numeric" placeholder="Masalan: 5000" value="{{ ($item['cost_entered'] ?? false) ? $item['unit_cost'] : '' }}" wire:input.debounce.300ms="updateUnitCost({{ $index }}, $event.target.value)"><span>so‘m</span></div>@if(isset($invalidFields['price-'.$item['variant_id']]))<p class="trade-input-error">{{ $invalidFields['price-'.$item['variant_id']] }}</p>@endif</div>
                                    <div class="trade-line-total"><span>Shu mahsulot uchun</span><strong>{{ number_format((int) $item['quantity'] * (int) $item['unit_cost'], 0, '', ' ') }}</strong><small>so‘m</small></div>
                                </div>
                                <details class="trade-details"><summary>Sotuv narxini ham belgilash <span>ixtiyoriy</span></summary><div class="trade-field"><label for="inward-sale-price-{{ $index }}">1 donasining sotuv narxi</label><input id="inward-sale-price-{{ $index }}" type="number" min="0" step="1" inputmode="numeric" wire:model="items.{{ $index }}.new_sale_price" placeholder="Keyinroq ham belgilash mumkin"></div></details>
                            </article>
                        @empty
                            <div class="trade-empty"><span>📦</span><h3>Hozircha mahsulot qo‘shilmagan</h3><p>Yuqorida mahsulot va hajmni tanlab «Qo‘shish»ni bosing.</p></div>
                        @endforelse
                    </div>
                </section>

                <section class="trade-card">
                    <div class="trade-card-heading"><div class="trade-heading"><span class="trade-step">2</span><div><h2>Kimdan olindi?</h2><p>Mahsulotni olib kelgan yetkazuvchini tanlang.</p></div></div><button type="button" wire:click="$dispatch('open-inline-supplier-modal')" class="trade-button trade-button-secondary">+ Yangi yetkazuvchi</button></div>
                    <div class="trade-field"><label for="inward-supplier">Ta’minotchi / yetkazuvchi</label><select id="inward-supplier" wire:model.live="selectedSupplierId"><option value="">Yetkazuvchini tanlang</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->display_name }}</option>@endforeach</select></div>
                    <details class="trade-details"><summary>Izoh <span>ixtiyoriy</span></summary><div class="trade-field"><label for="inward-notes">Izoh</label><input id="inward-notes" type="text" wire:model="notes" placeholder="Qo‘shimcha ma’lumot"></div></details>
                </section>
            </div>

            <aside class="trade-receipt">
                <div class="trade-heading"><span class="trade-step">3</span><div><h2>To‘lov va saqlash</h2><p>Summalarni tekshirib, kirimni yakunlang.</p></div></div>
                <div class="trade-total"><span>Jami kirim summasi</span><strong>{{ number_format($totalAmount, 0, '', ' ') }} <small>so‘m</small></strong><p>{{ count($items) }} xil mahsulot · {{ number_format(array_sum(array_column($items, 'quantity')), 0, '', ' ') }} dona</p></div>
                @if ($canManageCash)
                    <fieldset class="trade-payment"><legend>Yetkazuvchiga pul to‘landimi?</legend>
                        @foreach (['UNPAID' => ['Keyin to‘lanadi', 'Hozir pul berilmaydi'], 'FULL' => ['To‘liq to‘langan', 'Jami summa kassadan chiqadi'], 'PARTIAL' => ['Qisman to‘langan', 'To‘langan summani yozing']] as $value => $option)
                            <label class="trade-payment-option {{ $paymentType === $value ? 'is-selected' : '' }}"><input type="radio" wire:model.live="paymentType" value="{{ $value }}" name="inward-payment"><span><strong>{{ $option[0] }}</strong><small>{{ $option[1] }}</small></span><span class="trade-radio-mark"></span></label>
                        @endforeach
                    </fieldset>
                    @if ($paymentType === 'PARTIAL')<div class="trade-field"><label for="inward-paid">Hozir qancha to‘landi?</label><div class="trade-input-unit"><input id="inward-paid" type="number" min="0" max="{{ $totalAmount }}" step="1" wire:model.live.debounce.300ms="paidAmount" inputmode="numeric" placeholder="Summani yozing"><span>so‘m</span></div></div>@endif
                @else <p class="trade-help">Bu kirim to‘lovsiz qabul qilinadi. To‘lovni kassa xodimi kiritadi.</p> @endif
                <div class="trade-summary"><div><span>Hozir to‘langan</span><strong>{{ number_format($paidAmount, 0, '', ' ') }} so‘m</strong></div><div class="trade-debt"><span>Yetkazuvchiga qolgan qarz</span><strong>{{ number_format(max(0, $totalAmount - $paidAmount), 0, '', ' ') }} so‘m</strong></div></div>
                <button type="button" wire:click="postPurchase" wire:loading.attr="disabled" wire:target="postPurchase" @disabled(empty($items) || ! $selectedSupplierId) class="trade-button trade-button-primary trade-submit"><span wire:loading.remove wire:target="postPurchase">✓ Omborga kirim qilish</span><span wire:loading wire:target="postPurchase">Saqlanyapti…</span></button>
                <p class="trade-help trade-center">{{ empty($items) ? 'Avval mahsulot qo‘shing.' : (! $selectedSupplierId ? 'Yetkazuvchini tanlang.' : 'Tasdiqlangandan so‘ng ombor qoldig‘i oshadi.') }}</p>
                @if (! empty($items))<button type="button" wire:click="clearDraft" class="trade-clear">Ro‘yxatni tozalash</button>@endif
            </aside>
        </div>
    @endif
</div>
