<section class="panel">
    <h2>Moderators</h2>
    @foreach($organization->members->whereIn('id', $organization->moderators ?? []) as $moderator)
        <div class="record-row"><strong>{{ $moderator->name }}</strong><span>{{ $moderator->position ?? 'Member' }}</span></div>
    @endforeach
    @if($u->canManageOrganization())
        <details @if($showModeratorEditor ?? false) open @endif>
            <summary class="btn secondary">Edit Moderators</summary>
            <form method="post" action="/organization/moderators">
                @csrf
                @foreach($organization->members as $member)
                    <label class="check"><input type="checkbox" name="moderators[]" value="{{ $member->id }}" @checked(in_array($member->id, $organization->moderators ?? []))>{{ $member->name }} · {{ $member->position ?? 'Member' }}</label>
                @endforeach
                <button class="btn orange">Save</button>
            </form>
        </details>
    @endif
</section>
