@props(['products', 'volumes', 'selectedProduct', 'selectedVolume'])
<div class="trade-picker">
    <div class="trade-field">
        <label for="trade-product">Mahsulot</label>
        <select id="trade-product" wire:model.live="selectedProductId">
            <option value="">Mahsulotni tanlang</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}">{{ $product->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="trade-field">
        <label for="trade-volume">Hajmi / litri</label>
        <select id="trade-volume" wire:model.live="selectedVolumeId" @disabled(! $selectedProduct)>
            <option value="">{{ $selectedProduct ? 'Hajmni tanlang' : 'Avval mahsulotni tanlang' }}</option>
            @foreach ($volumes as $volume)
                <option value="{{ $volume->id }}">{{ $volume->name }}</option>
            @endforeach
        </select>
    </div>
    <button type="button" wire:click="addSelectedVariant" wire:loading.attr="disabled" wire:target="addSelectedVariant" class="trade-button trade-button-primary" @disabled(! $selectedProduct || ! $selectedVolume)>
        <span wire:loading.remove wire:target="addSelectedVariant">+ Qo‘shish</span>
        <span wire:loading wire:target="addSelectedVariant">Qo‘shilyapti…</span>
    </button>
</div>
