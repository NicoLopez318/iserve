<dialog id="{{ $dialogId }}" aria-labelledby="{{ $dialogId }}-title">
    <button type="button" class="close-dialog" data-close aria-label="Close">×</button>
    <form class="panel" method="post" action="/workspace/organization/{{ $organization->id }}/member">
        @csrf
        <h2 id="{{ $dialogId }}-title">{{ $title }}</h2>
        <label class="field">Student
            <select name="user_id" required>
                <option value="">{{ $candidates->isEmpty() ? 'No eligible students' : 'Select student' }}</option>
                @foreach($candidates as $student)
                    <option value="{{ $student->id }}">{{ $student->name }}{{ $student->student_id ? ' · '.$student->student_id : '' }}{{ !$adding && $student->position ? ' · '.$student->position : '' }}</option>
                @endforeach
            </select>
        </label>
        @error('user_id')<p class="sfu-review-error" role="alert">{{ $message }}</p>@enderror
        <label class="field">Role
            <select name="position">
                @foreach(['Member','President','Vice President','Secretary','Treasurer','Auditor','Moderator'] as $position)
                    <option>{{ $position }}</option>
                @endforeach
            </select>
        </label>
        @error('position')<p class="sfu-review-error" role="alert">{{ $message }}</p>@enderror
        <div class="actions">
            <button @disabled($candidates->isEmpty())>{{ $adding ? 'Add Member' : 'Save Role' }}</button>
            @if($adding)<a class="button outline" href="/workspace/organizations?tab=membership">Review Membership Applications</a>@endif
        </div>
    </form>
</dialog>
