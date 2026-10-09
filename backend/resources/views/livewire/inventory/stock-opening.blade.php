<div class="trade-workspace" aria-label="Boshlang‘ich ombor qoldig‘i">
    @if($savedOpening)
        <div class="trade-success" role="status">
            <span class="trade-success-icon">✓</span>
            <div><h2>Mahsulot omborga qo‘shildi</h2><p>{{ $savedOpening['name'] }} · {{ $savedOpening['document_number'] }}</p></div>
            <button type="button" wire:click="startNextProduct" wire:loading.attr="disabled" class="trade-button trade-button-primary">+ Keyingi mahsulotni qo‘shish</button>
        </div>
        <div class="operation-outcome">
            <h3>Saqlashdan keyingi holat</h3>
            <p><span>Boshlang‘ich qoldiq sifatida qo‘shildi</span><strong>{{ number_format($savedOpening['added'], 0, '', ' ') }} dona</strong></p>
            <p><span>Shu mahsulotdan hozir omborda</span><strong>{{ number_format($savedOpening['quantity'], 0, '', ' ') }} dona</strong></p>
            <p><span>Qo‘shilgan mahsulotlarning tannarxi</span><strong>{{ number_format($savedOpening['total_cost'], 0, '', ' ') }} so‘m</strong></p>
            <p class="trade-help">Bu boshlang‘ich qoldiq. Kassadan pul chiqmadi, yetkazuvchiga qarz yozilmadi.</p>
            <a href="{{ route('inventory.index') }}">Ombor qoldiqlarini ko‘rish →</a>
        </div>
    @else
        @php
            $currentQuantity = (int) ($selectedVariant?->balance?->quantity ?? 0);
            $enteredQuantity = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]) ?: 0;
            $enteredCost = filter_var($unitCost, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000000]]) ?: 0;
        @endphp
        @if($errorMessage)<div class="trade-alert trade-alert-error" role="alert">{{ $errorMessage }}</div>@endif
        <form wire:submit="save" class="trade-layout">
            <section class="trade-card trade-main">
                <div class="trade-heading"><span class="trade-step">1</span><div><h2>Do‘konda avvaldan bor mahsulot</h2><p>Mahsulotlarni bittalab kiriting. Saqlagach, keyingisiga o‘tasiz.</p></div></div>
                <div class="trade-two-fields">
                    <div class="trade-field">
                        <label for="opening-product">Mahsulot nomi</label>
                        <select id="opening-product" wire:model.live="productSelection">
                            <option value="">Mahsulotni tanlang</option>
                            <option value="new">+ Yangi mahsulot nomini yozish</option>
                            @foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach
                        </select>
                        @error('productSelection')<p class="trade-input-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="trade-field">
                        <label for="opening-volume">Litri</label>
                        <select id="opening-volume" wire:model.live="volumeSelection">
                            <option value="">Litrni tanlang</option>
                            @foreach($volumes as $volume)<option value="{{ $volume->id }}">{{ $volume->name }}</option>@endforeach
                            <option value="new">+ Boshqa litrni yozish</option>
                        </select>
                        @error('volumeSelection')<p class="trade-input-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                @if($productSelection === 'new')
                    <div class="trade-field" wire:key="opening-new-product">
                        <label for="opening-new-name">Yangi mahsulot nomi</label>
                        <input id="opening-new-name" type="text" wire:model.live.debounce.300ms="newProductName" maxlength="100" placeholder="Masalan: Fanta" required>
                        @error('newProductName')<p class="trade-input-error">{{ $message }}</p>@enderror
                    </div>
                @endif
                @if($volumeSelection === 'new')
                    <div class="trade-field" wire:key="opening-new-volume">
                        <label for="opening-new-litres">Yangi hajm (litr)</label>
                        <div class="trade-input-unit"><input id="opening-new-litres" type="text" inputmode="decimal" wire:model.live.debounce.300ms="customLitres" placeholder="Masalan: 0.75" maxlength="20" required><span>litr</span></div>
                        @error('customLitres')<p class="trade-input-error">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div class="trade-heading"><span class="trade-step">2</span><div><h2>Qancha mahsulot bor?</h2><p>Hozir do‘konda bor, tizimga hali kiritilmagan donalarni yozing.</p></div></div>
                <div class="trade-two-fields">
                    <div class="trade-field">
                        <label for="opening-quantity">Nechta dona qo‘shiladi?</label>
                        <div class="trade-input-unit"><input id="opening-quantity" type="number" min="1" max="1000000" step="1" inputmode="numeric" wire:model.live.debounce.300ms="quantity" placeholder="Masalan: 150" required><span>dona</span></div>
                        @error('quantity')<p class="trade-input-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="trade-field">
                        <label for="opening-cost">1 dona tannarxi</label>
                        <div class="trade-input-unit"><input id="opening-cost" type="number" min="0" max="1000000000" step="1" inputmode="numeric" wire:model.live.debounce.300ms="unitCost" placeholder="Masalan: 5000" required><span>so‘m</span></div>
                        <p class="trade-help">Sizga 1 donasi necha so‘mga tushgan?</p>
                        @error('unitCost')<p class="trade-input-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                @if($selectedVariant?->default_sale_price !== null)
                    <p class="trade-help">Belgilangan sotuv narxi: <strong>{{ number_format($selectedVariant->default_sale_price, 0, '', ' ') }} so‘m</strong>. Bu narx saqlanadi.</p>
                @else
                    <details class="trade-details"><summary>Sotuv narxini ham belgilash <span>ixtiyoriy</span></summary>
                        <div class="trade-field"><label for="opening-price">1 dona sotuv narxi</label><input id="opening-price" type="number" min="0" max="1000000000" step="1" inputmode="numeric" wire:model="salePrice" placeholder="Keyinroq ham belgilash mumkin">@error('salePrice')<p class="trade-input-error">{{ $message }}</p>@enderror</div>
                    </details>
                @endif
            </section>
            <aside class="trade-receipt" aria-label="Saqlashdan oldingi tekshiruv">
                <div class="trade-heading"><span class="trade-step">3</span><div><h2>Tekshiring va saqlang</h2><p>Oddiy kirim hujjati talab qilinmaydi.</p></div></div>
                <div class="trade-summary" aria-live="polite">
                    <div><span>Tizimda hozir bor</span><strong>{{ number_format($currentQuantity, 0, '', ' ') }} dona</strong></div>
                    <div><span>Qo‘shilayotgan qoldiq</span><strong>+{{ number_format($enteredQuantity, 0, '', ' ') }} dona</strong></div>
                    <div><span>Saqlangandan keyin</span><strong>{{ number_format($currentQuantity + $enteredQuantity, 0, '', ' ') }} dona</strong></div>
                    <div><span>Qo‘shilayotgan mahsulot qiymati</span><strong>{{ number_format($enteredQuantity * $enteredCost, 0, '', ' ') }} so‘m</strong></div>
                </div>
                @if($currentQuantity > 0)<div class="trade-alert">Bu mahsulot tizimda allaqachon bor. Faqat hali kiritilmagan donalarni qo‘shing. Avvalgi qoldiq o‘chirilmaydi.</div>@endif
                <p class="trade-help">Kassadagi pul o‘zgarmaydi. Yetkazuvchi tanlanmaydi va yangi qarz yozilmaydi.</p>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="trade-button trade-button-primary trade-submit"><span wire:loading.remove wire:target="save">✓ Boshlang‘ich qoldiqni saqlash</span><span wire:loading wire:target="save">Saqlanyapti…</span></button>
                <p class="trade-help">Tugmani takror bosish bir yozuvni ikki marta qo‘shmaydi.</p>
            </aside>
        </form>
    @endif
</div>
