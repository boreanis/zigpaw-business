@props(['home', 'homeLabel' => 'Zigpaw Partners home', 'label' => 'Partners'])

<main id="main-content" class="access-shell" tabindex="-1">
    <header class="access-header">
        <x-business.brand :href="$home" :label="$label" :home-label="$homeLabel" />
        <x-business.appearance />
    </header>
    <section {{ $attributes->class(['access-card']) }}>
        {{ $slot }}
    </section>
</main>
