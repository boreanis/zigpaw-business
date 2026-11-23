@props(['status'])

@php
    [$label, $tone] = match ($status) {
        'pending' => ['Pending', 'warn'],
        'approved' => ['Approved', 'good'],
        'partially_approved' => ['Partly approved', 'blue'],
        'rejected' => ['Not added', 'danger'],
        default => ['Status unavailable', 'neutral'],
    };
@endphp

<x-clinical.status :tone="$tone">{{ $label }}</x-clinical.status>
