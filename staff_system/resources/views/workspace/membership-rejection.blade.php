<details @if(old('status') === 'rejected' && $errors->any()) open @endif>
    <summary>Reject application</summary>
    <form method="post" action="{{ url('/staff/requests/'.$item->id) }}">
        @csrf
        <input type="hidden" name="status" value="rejected">
        <label class="field">
            Reason for rejection
            <textarea name="review_note" required minlength="5" maxlength="3000"
                      placeholder="Explain why the application was rejected">{{ old('status') === 'rejected' ? old('review_note') : '' }}</textarea>
        </label>
        <p class="muted">The applicant can see this reason in their request details.</p>
        <div class="actions"><button type="submit" class="outline">Confirm rejection</button></div>
    </form>
</details>
