@php
    $contactFields = [
        'parent_guardian_name' => ['Full name', 120],
        'parent_guardian_relationship' => ['Relationship to student', 60],
        'parent_guardian_phone' => ['Contact number', 40],
    ];
    $hasContact = collect($item->details)->only(array_keys($contactFields))->contains(fn($value) => filled($value));
@endphp
<section class="cvu-case-evidence" aria-labelledby="complaint-parent-guardian-title">
    <h3 id="complaint-parent-guardian-title">Parent or guardian contact</h3>
    @if(session('parent_guardian_saved'))
        <p role="status">{{ session('parent_guardian_saved') }}</p>
    @endif
    @if($hasContact)
        <dl class="cvu-case-facts">
            @foreach($contactFields as $key => [$label])
                @if(filled($item->details[$key] ?? null))
                    <div><dt>{{ $label }}</dt><dd>{{ $item->details[$key] }}</dd></div>
                @endif
            @endforeach
        </dl>
    @else
        <p class="cvu-evidence-empty">No contact recorded.</p>
    @endif
    @if(auth()->user()->role === 'cvu_head' && $item->canEditComplaintContact())
        <details class="cvu-complaint-schedule" @if($errors->hasAny(array_keys($contactFields))) open @endif>
            <summary class="cvu-review-action">{{ $hasContact ? 'Edit contact' : 'Add contact' }}</summary>
            <form method="post" action="{{ route('staff.complaints.parent-guardian', $item) }}" class="cvu-case-decision">
                @csrf
                @foreach($contactFields as $key => [$label, $max])
                    @php($value = old($key, $item->details[$key] ?? ''))
                    <label>{{ $label }}
                        <input type="{{ $key === 'parent_guardian_phone' ? 'tel' : 'text' }}" name="{{ $key }}"
                            value="{{ is_string($value) ? $value : '' }}" maxlength="{{ $max }}">
                    </label>
                    @error($key)<p class="cvu-case-error" role="alert">{{ $message }}</p>@enderror
                @endforeach
                <button type="submit" class="cvu-review-action">Save contact</button>
            </form>
        </details>
    @endif
</section>
