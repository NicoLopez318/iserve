<details class="review-record-actions">
    <summary>Record Actions</summary>
    @if($item->canMarkIdReprinted())
        <form method="post" action="/workspace/request/{{ $item->id }}/disposition">
            @csrf
            <button name="action" value="reprint">Mark ID as Reprinted</button>
        </form>
    @endif
    <form method="post" action="/workspace/request/{{ $item->id }}/disposition">
        @csrf
        <label class="field">Remarks<textarea name="review_note" minlength="5" maxlength="3000" required></textarea></label>
        <div class="actions">
            @if($item->status === 'approved' && in_array($item->kind, ['lost', 'found', 'id-replacement']) && !($item->details['released'] ?? false))
                <button name="action" value="release">{{ $item->kind==='id-replacement' ? 'Release ID' : 'Release Item / Slip' }}</button>
            @endif
            <button class="outline" name="action" value="{{ ($item->details['archived'] ?? false) ? 'restore' : 'archive' }}">
                {{ ($item->details['archived'] ?? false) ? 'Restore Record' : 'Archive Record' }}
            </button>
        </div>
    </form>
</details>
