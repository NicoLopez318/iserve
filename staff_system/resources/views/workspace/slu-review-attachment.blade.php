@php
    $path = $item->attachmentPath($key);
    $file = $item->files[$key] ?? null;
    $size = is_array($file) ? ($file['size'] ?? null) : null;
@endphp

<div class="slu-review-requirement">
    <span class="slu-review-document"><img src="/staff-assets/21-5564.svg" alt="">{{ $label }}</span>
    <img class="slu-review-check" src="/staff-assets/{{ $path ? '21-4770.svg' : '21-4776.svg' }}"
         alt="{{ $path ? 'Attached' : ($unattachedLabel ?? 'Missing') }}">
    @if($path)
        <a class="sfu-attachment" href="{{ route('staff.requests.attachment-view', ['serviceRequest' => $item, 'key' => $key]) }}"
           target="_blank" rel="noopener" aria-label="Open {{ $label }}"
           title="{{ is_array($file) ? ($file['name'] ?? basename($path)) : basename($path) }}">
            <img src="/staff-assets/21-4782.svg" alt="">{{ is_numeric($size) && $size > 0 ? number_format($size / 1048576, 1).' MB' : 'Open' }}
        </a>
    @else
        <span class="slu-review-file-missing" aria-hidden="true">—</span>
    @endif
</div>
