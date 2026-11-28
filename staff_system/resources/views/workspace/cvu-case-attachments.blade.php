<section class="cvu-case-evidence" aria-labelledby="cvu-case-evidence-title">
    <h3 id="cvu-case-evidence-title">{{ $evidenceTitle }}</h3>
    <ul>
        @forelse($item->files ?? [] as $key => $file)
            @php
                $path = $item->attachmentPath($key);
                $filename = basename(is_array($file) ? ($file['name'] ?? $path ?? $key) : ($path ?? $key));
                $bytes = is_array($file) && is_numeric($file['size'] ?? null) ? (int) $file['size'] : null;
                $size = match (true) {
                    $bytes === null => null,
                    $bytes >= 1000000 => number_format($bytes / 1000000, 1).' MB',
                    $bytes >= 1000 => number_format($bytes / 1000, 1).' KB',
                    default => $bytes.' B',
                };
            @endphp
            <li>
                <a href="{{ route('staff.requests.attachment-view', ['serviceRequest' => $item, 'key' => $key]) }}" target="_blank" rel="noopener">
                    <span aria-hidden="true">📎</span>
                    {{ $filename ?: $key }}
                    @if($size)<small>{{ $size }}</small>@endif
                </a>
            </li>
        @empty
            <li class="cvu-evidence-empty">{{ $evidenceEmpty }}</li>
        @endforelse
    </ul>
</section>
