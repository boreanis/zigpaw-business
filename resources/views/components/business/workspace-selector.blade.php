@props([
    'label', 'resource', 'items' => [], 'selectedId' => null, 'selectedLabel' => null,
    'searchProperty', 'searchAction', 'selectAction', 'pagination' => [], 'list' => false,
    'fieldName' => null,
])

<div class="workspace-selector">
    <form wire:submit="{{ $searchAction }}" class="inline-form">
        <x-business.field :label="'Search '.str($label)->lower()" :name="$searchProperty">
            <x-business.control type="search" wire:model="{{ $searchProperty }}" maxlength="80" />
        </x-business.field>
        <x-business.button type="submit">Search</x-business.button>
    </form>
    @if ($list)
        <div class="organization-list">
            @foreach ($items as $item)
                <x-business.button variant="organization" wire:click="{{ $selectAction }}({{ Illuminate\Support\Js::from($item['id']) }})"><span>{{ $item['name'] }}</span><small>{{ str($item['role'])->headline() }}</small></x-business.button>
            @endforeach
        </div>
    @else
        <x-business.field :label="$label" :name="$fieldName">
            <x-business.control as="select" wire:change="{{ $selectAction }}($event.target.value)" aria-label="{{ $label }}">
                @if (! $selectedId)<option value="">Choose {{ str($label)->lower() }}</option>@endif
                @if ($selectedId && ! collect($items)->contains('id', $selectedId))
                    <option value="{{ $selectedId }}" selected>{{ $selectedLabel }}</option>
                @endif
                @foreach ($items as $item)
                    <option value="{{ $item['id'] }}" @selected($item['id'] === $selectedId)>{{ $item['name'] ?? (data_get($item, 'place.name') ?: data_get($item, 'provider.name')) }}</option>
                @endforeach
            </x-business.control>
        </x-business.field>
    @endif
    @if ($items === [])<p role="status">No matching {{ str($label)->lower() }}. Try another search.</p>@endif
    <x-business-pagination :resource="$resource" :label="str($label)->lower()" :pagination="$pagination" />
</div>
