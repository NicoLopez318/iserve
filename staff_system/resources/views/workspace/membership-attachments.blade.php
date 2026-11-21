@forelse($item->files ?? [] as $key => $file)
    @php
        $path = is_array($file) ? ($file['path'] ?? '') : ($file ?? '');
        $filename = is_array($file) ? ($file['name'] ?? basename($path)) : basename($path);
        $isImage = in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg'], true);
    @endphp
    @if($isImage)
        <details class="membership-image-preview">
            <summary>Preview {{ $filename }}</summary>
            <img src="{{ route('staff.requests.image-preview', ['serviceRequest' => $item, 'key' => $key]) }}"
                 alt="{{ $filename }}" loading="lazy">
        </details>
    @endif
    <a class="file" href="{{ url('/requests/'.$item->id.'/files/'.rawurlencode($key)) }}">
        <span>{{ $filename }}</span><small>Download</small>
    </a>
@empty
    <p class="muted">No supporting files attached.</p>
@endforelse
