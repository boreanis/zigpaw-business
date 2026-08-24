@props([
    'title' => 'Something went wrong',
    'message' => 'We could not load this workspace right now. Try again shortly.',
])

<div {{ $attributes->class(['error-state']) }} role="alert">
    <span class="error-state-icon" aria-hidden="true">!</span>
    <div>
        <h3>{{ $title }}</h3>
        <p>{{ $message }}</p>
    </div>
    @if (isset($actions))
        <div class="button-row">{{ $actions }}</div>
    @endif
</div>
