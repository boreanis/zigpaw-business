@props(['href', 'label' => 'Partners', 'homeLabel' => 'Zigpaw Partners home'])

<a href="{{ $href }}" aria-label="{{ $homeLabel }}" {{ $attributes->class(['brand-link']) }}>
    <span class="brand-art" aria-hidden="true">
        <img class="brand-light" src="{{ asset('brand/zigpaw-wordmark-light.svg') }}" alt="">
        <img class="brand-dark" src="{{ asset('brand/zigpaw-wordmark-dark.svg') }}" alt="">
    </span>
    <span>{{ $label }}</span>
</a>
