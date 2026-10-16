@if (isset($errors) && $errors->any())
    <section class="inline-alert" role="alert" aria-label="Check these fields" tabindex="-1">
        <p>Check the highlighted fields and try again.</p>
        <ul>
            @foreach ($errors->messages() as $field => $messages)
                @foreach ($messages as $errorMessage)
                    <li>
                        @if ($field === 'workspace')
                            {{ $errorMessage }}
                        @else
                            <a href="#business-field-{{ str($field)->replace(['.', '[', ']'], '-') }}">{{ $errorMessage }}</a>
                        @endif
                    </li>
                @endforeach
            @endforeach
        </ul>
    </section>
@endif
