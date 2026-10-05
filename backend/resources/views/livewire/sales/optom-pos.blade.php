<div class="trade-workspace trade-sale">
    <livewire:modals.inline-product-modal />
    <livewire:modals.inline-customer-modal />
    <div class="sale-modes" aria-label="Sotuv turini tanlang"><button type="button" wire:click="setSalesMode('quick')" aria-pressed="{{ $salesMode === 'quick' ? 'true' : 'false' }}" class="{{ $salesMode === 'quick' ? 'is-active' : '' }}"><span>⚡</span><strong>Tezkor sotuv</strong><small>Mijozsiz, to‘liq to‘lov</small></button><button type="button" wire:click="setSalesMode('customer')" aria-pressed="{{ $salesMode === 'customer' ? 'true' : 'false' }}" class="{{ $salesMode === 'customer' ? 'is-active' : '' }}"><span>👤</span><strong>Mijozga sotuv</strong><small>Mijoz, to‘lov yoki nasiya</small></button></div>
    <div class="trade-hero"><div><span class="trade-eyebrow">{{ $salesMode === 'quick' ? 'TEZKOR SOTUV' : 'MIJOZGA MAHSULOT SOTISH' }}</span><h1>Sotuvni oson rasmiylashtiring</h1><p>Mahsulotni tanlang, nechta sotilishini yozing va to‘lovni belgilang.</p></div><div class="trade-path" aria-label="Sotuv tartibi"><span><b>1</b> Mahsulot</span>@if ($salesMode === 'customer')<span><b>2</b> Mijoz</span>@endif<span><b>3</b> To‘lov</span></div></div>
    @if ($errorMessage)<div class="trade-alert trade-alert-error" role="alert">{{ $errorMessage }}</div>@endif
    @if ($posMessage && ! $completedSale)<div class="trade-alert" role="status">{{ $posMessage }}</div>@endif
    @if ($completedSale)
        <div class="trade-success" role="status"><span class="trade-success-icon">✓</span><div><h2>Sotuv saqlandi!</h2><p>{{ $completedSale['customer_name'] ?? 'Tezkor xaridor' }} · Chek № {{ $completedSale['invoice_number'] ?? '' }}</p><p>To‘langan: {{ number_format($completedSale['paid_amount'] ?? 0, 0, '', ' ') }} so‘m · Qarz: {{ number_format($completedSale['debt_amount'] ?? 0, 0, '', ' ') }} so‘m</p></div><strong>{{ number_format($completedSale['total_amount'] ?? 0, 0, '', ' ') }} so‘m</strong><button type="button" wire:click="$set('completedSale', null)" class="trade-button trade-button-secondary">Yana sotuv qilish</button></div>
        <div class="operation-outcome" role="status"><h3>Amaldan keyingi holat</h3><div>@foreach($completedSale['remaining_stock'] ?? [] as $stock)<p><span>{{ $stock['name'] }} — omborda</span><strong>{{ number_format($stock['quantity'], 0, '', ' ') }} dona</strong></p>@endforeach
        @if(isset($completedSale['remaining_cash']))<p><span>Tanlangan kassada qolgan pul</span><strong>{{ number_format($completedSale['remaining_cash'], 0, '', ' ') }} so‘m</strong></p>@endif
        @if(($completedSale['remaining_party_balance'] ?? 0) !== 0)<p><span>{{ $completedSale['remaining_party_balance'] < 0 ? 'Oldindan to‘langan pul' : 'Mijozning jami qarzi' }}</span><strong>{{ number_format(abs($completedSale['remaining_party_balance']), 0, '', ' ') }} so‘m</strong></p>@endif</div><a href="{{ route('inventory.index') }}">Omborda ko‘rish →</a><a href="{{ route('debts.index') }}">Qarzlar va to‘lovlar →</a></div>
    @endif
    <div class="trade-layout">
        <div class="trade-main">
            <section class="trade-card">
                <div class="trade-card-heading"><div class="trade-heading"><span class="trade-step">1</span><div><h2>Nima sotiladi?</h2><p>Mahsulot va hajmni tanlang. Keyin donasini yozing.</p></div></div><button type="button" wire:click="$dispatch('open-inline-product-modal')" class="trade-button trade-button-secondary">+ Yangi Mahsulot / hajm</button></div>
                <x-trade-product-picker :products="$products" :volumes="$availableVolumes" :selected-product="$selectedProductId" :selected-volume="$selectedVolumeId" />
                <div class="trade-items">
                    @forelse ($items as $index => $item)
                        <article class="trade-item" wire:key="sale-item-{{ $item['variant_id'] }}">
                            <div class="trade-item-heading"><span class="trade-product-icon">🥤</span><div><h3>{{ $item['display_name'] }}</h3><p>Nechta sotiladi va bir donasi qancha?</p></div><button type="button" wire:click="removeItem({{ $index }})" class="trade-remove" aria-label="{{ $item['display_name'] }} ni olib tashlash">×</button></div>
                            <div class="trade-item-fields">
                                <div class="trade-field"><label for="sale-quantity-{{ $index }}">Nechta dona?</label><div class="trade-input-unit"><input id="sale-quantity-{{ $index }}" type="number" min="1" step="1" inputmode="numeric" value="{{ $item['quantity'] }}" wire:input.debounce.300ms="updateQuantity({{ $index }}, $event.target.value)"><span>dona</span></div>@if(isset($invalidFields['quantity-'.$item['variant_id']]))<p class="trade-input-error">{{ $invalidFields['quantity-'.$item['variant_id']] }}</p>@endif</div>
                                <div class="trade-field"><label for="sale-price-{{ $index }}">1 donasining sotuv narxi</label><div class="trade-input-unit"><input id="sale-price-{{ $index }}" type="number" min="0" step="1" inputmode="numeric" placeholder="Narxni yozing" value="{{ ($item['price_entered'] ?? false) ? $item['price'] : '' }}" wire:input.debounce.300ms="updatePrice({{ $index }}, $event.target.value)" @readonly($item['is_system_price'])><span>so‘m</span></div>@if(isset($invalidFields['price-'.$item['variant_id']]))<p class="trade-input-error">{{ $invalidFields['price-'.$item['variant_id']] }}</p>@endif</div>
                                <div class="trade-line-total"><span>Shu mahsulot uchun</span><strong>{{ number_format($item['total'], 0, '', ' ') }}</strong><small>so‘m</small></div>
                            </div>
                            @if ($item['default_system_price'] > 0)
                                <label class="trade-check"><input type="checkbox" wire:click="toggleSystemPrice({{ $index }})" @checked($item['is_system_price'])><span>Belgilangan narxdan foydalanish <strong>{{ number_format($item['default_system_price'], 0, '', ' ') }} so‘m</strong></span></label>
                                @if ($item['is_system_price'])<p class="trade-help">Boshqa narxda sotish uchun yuqoridagi belgini o‘chiring.</p>@endif
                            @endif
                        </article>
                    @empty
                        <div class="trade-empty"><span>🛒</span><h3>Savatingiz hozircha bo‘sh</h3><p>Yuqorida mahsulot va hajmni tanlab «Qo‘shish»ni bosing.</p></div>
                    @endforelse
                </div>
            </section>
            @if ($salesMode === 'customer')
            <section class="trade-card">
                <div class="trade-card-heading"><div class="trade-heading"><span class="trade-step">2</span><div><h2>Kimga sotiladi?</h2><p>Naqd savdoda mijoz tanlash shart emas. Nasiya uchun tanlang.</p></div></div><button type="button" wire:click="$dispatch('open-inline-customer-modal')" class="trade-button trade-button-secondary">+ Yangi Mijoz</button></div>
                @if ($selectedCustomerId)
                    <div class="trade-selected-customer"><span class="trade-avatar">✓</span><div><h3>{{ $selectedCustomerName }}</h3><p>{{ $customerCurrentDebt > 0 ? 'Avvalgi qarz: '.number_format($customerCurrentDebt, 0, '', ' ').' so‘m' : ($customerCurrentDebt < 0 ? 'Avvalgi avans: '.number_format(abs($customerCurrentDebt), 0, '', ' ').' so‘m' : 'Bu mijozning qarzi yo‘q') }}</p></div><button type="button" wire:click="selectExistingCustomer(null)" class="trade-clear">Bekor qilish</button></div>
                @else <p class="trade-guest">⚡ Tezkor savdo — mijozsiz, to‘liq to‘lov bilan</p> @endif
                <div class="trade-field"><label for="pos-customer-search">Mavjud mijozni qidirish</label><input id="pos-customer-search" type="search" wire:model.live.debounce.300ms="customerSearch" placeholder="Ism, telefon, do‘kon nomi yoki manzil"></div>
                @if (trim($customerSearch) !== '')
                    <div class="trade-search-results">
                        @forelse ($customerResults as $customer)<button type="button" wire:key="customer-result-{{ $customer->id }}" wire:click="selectExistingCustomer({{ $customer->id }})"><span>{{ $customer->display_name }}</span><span>Tanlash →</span></button>@empty<p>Mijoz topilmadi. «+ Yangi Mijoz» orqali qo‘shing.</p>@endforelse
                    </div>
                @elseif (! $selectedCustomerId && $recentCustomers->isNotEmpty())
                    <div class="trade-chips">@foreach ($recentCustomers as $customer)<button type="button" wire:click="selectExistingCustomer({{ $customer->id }})">{{ $customer->display_name }}</button>@endforeach</div>
                @endif
                <details class="trade-details"><summary>Sotuvga izoh <span>ixtiyoriy</span></summary><div class="trade-field"><label for="sale-notes">Izoh</label><input id="sale-notes" wire:model="notes" placeholder="Qo‘shimcha ma’lumot"></div></details>
            </section>
            @else
                <div class="workspace-hint">⚡ Tezkor sotuv: donani klaviaturada yozing (masalan, 200). Mijoz tanlash shart emas. Nasiya uchun yuqoridagi «Mijozga sotuv»ni tanlang.</div>
            @endif
        </div>
        <aside class="trade-receipt">
            <div class="trade-heading"><span class="trade-step">3</span><div><h2>To‘lov va yakunlash</h2><p>Qancha to‘lanishini belgilang.</p></div></div>
            <div class="trade-total"><span>Jami sotuv summasi</span><strong>{{ number_format($totalAmount, 0, '', ' ') }} <small>so‘m</small></strong><p>{{ count($items) }} xil mahsulot · {{ number_format(array_sum(array_column($items, 'quantity')), 0, '', ' ') }} dona</p></div>
            <fieldset class="trade-payment"><legend>Mijoz qanday to‘laydi?</legend>
                @foreach (['FULL' => ['To‘liq to‘lov', 'Barcha summa hozir to‘lanadi'], 'PARTIAL' => ['Qisman to‘lov', 'Bir qismi hozir, qolgani qarz'], 'DEBT' => ['To‘liq nasiya', 'Barcha summa mijoz qarziga yoziladi']] as $value => $option)
                    <label class="trade-payment-option {{ $paymentType === $value ? 'is-selected' : '' }} {{ $value !== 'FULL' && ! $selectedCustomerId ? 'is-disabled' : '' }}"><input type="radio" name="sale-payment" wire:model.live="paymentType" value="{{ $value }}" @disabled($value !== 'FULL' && ! $selectedCustomerId)><span><strong>{{ $option[0] }}</strong><small>{{ $option[1] }}</small></span><span class="trade-radio-mark"></span></label>
                @endforeach
            </fieldset>
            @if (! $selectedCustomerId)<p class="trade-help">Nasiya yoki qisman to‘lov uchun mijozni tanlang.</p>@endif
            @if ($paymentType === 'PARTIAL')<div class="trade-field"><label for="sale-paid">Hozir qancha to‘lanadi?</label><div class="trade-input-unit"><input id="sale-paid" type="number" min="0" max="{{ $totalAmount }}" step="1" inputmode="numeric" wire:model.live.debounce.300ms="paidAmount" placeholder="Summani yozing"><span>so‘m</span></div></div>@endif
            @if ($paymentType !== 'DEBT')
            @endif
            <div class="trade-summary"><div><span>Hozir to‘lanadi</span><strong>{{ number_format($paidAmount, 0, '', ' ') }} so‘m</strong></div><div class="trade-debt"><span>Shu sotuvdan qolgan qarz</span><strong>{{ number_format($debtAmount, 0, '', ' ') }} so‘m</strong></div>@if ($selectedCustomerId)<div><span>{{ $finalCustomerDebt < 0 ? 'Mijozda qoladigan avans' : 'Mijozning jami qarzi' }}</span><strong>{{ number_format(abs($finalCustomerDebt), 0, '', ' ') }} so‘m</strong></div>@endif</div>
            <button type="button" wire:click="checkout" wire:loading.attr="disabled" wire:target="checkout" @disabled(empty($items)) class="trade-button trade-button-primary trade-submit"><span wire:loading.remove wire:target="checkout">✓ Sotuvni yakunlash</span><span wire:loading wire:target="checkout">Saqlanyapti…</span></button>
            <p class="trade-help trade-center">{{ empty($items) ? 'Avval savatga mahsulot qo‘shing.' : 'Tasdiqlangandan so‘ng sotuv saqlanadi va chek yaratiladi.' }}</p>
            @if (! empty($items))<button type="button" wire:click="clearDraft" class="trade-clear">Savatni tozalash</button>@endif
        </aside>
    </div>
</div>
