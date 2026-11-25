<details class="membership-image-preview">
    <summary>Preview {{ $filename }}</summary>
    <img src="{{ route('staff.requests.image-preview', ['serviceRequest' => $item, 'key' => $key]) }}"
         alt="{{ $filename }}" loading="lazy">
</details>
