@push('dialogs')
    @foreach($orgDirectory['rows'] as $entry)
        @php
            $organization = $entry['organization'];
            $officers = $organization->members->filter(fn($member) => filled($member->position) && $member->position !== 'Member');
            $president = $officers->firstWhere('position', 'President');
            $achievements = $orgDirectory['achievements']->get($organization->id, collect());
            $memberDialog = 'organization-member-'.$organization->id;
            $rolesDialog = 'organization-roles-'.$organization->id;
        @endphp

        <dialog id="organization-{{ $organization->id }}" class="slu-org-manage-dialog" aria-labelledby="organization-title-{{ $organization->id }}"
            @if(in_array(old('_dialog'), [$memberDialog, $rolesDialog], true)) open data-auto-open @endif>
            <button type="button" class="close-dialog" data-close aria-label="Close organization details">×</button>
            <header class="slu-org-manage-banner">
                <span class="slu-org-manage-monogram" aria-hidden="true">{{ str($entry['acronym'] ?: $organization->name)->substr(0, 1)->upper() }}</span>
                <div>
                    <h2 id="organization-title-{{ $organization->id }}">{{ $organization->name }}</h2>
                    <p>{{ $organization->members_count }} {{ Str::plural('Member', $organization->members_count) }} · {{ $entry['adviser'] ? '1 Adviser' : '0 Advisers' }} · {{ number_format($entry['points']) }} points</p>
                </div>
            </header>

            <div class="slu-org-manage-body">
                <dl class="slu-org-manage-facts">
                    <div><dt><img src="/staff-assets/21-5331.svg" alt="">President:</dt><dd>{{ $president?->name ?? '—' }}</dd></div>
                    <div><dt><img src="/staff-assets/21-5331.svg" alt="">Adviser:</dt><dd>{{ $entry['adviser'] ?: '—' }}</dd></div>
                    <div><dt><img src="/staff-assets/21-5559.svg" alt="">Portal Visibility:</dt><dd class="slu-org-verified">Published</dd></div>
                </dl>

                <div class="slu-org-manage-card">
                    <div class="slu-org-manage-tabs" role="group" aria-label="Organization details">
                        <label><input type="radio" name="organization-pane-{{ $organization->id }}" value="officers" checked><span>Officers</span></label>
                        <label><input type="radio" name="organization-pane-{{ $organization->id }}" value="achievements"><span>Achievements</span></label>
                    </div>

                    <div class="slu-org-manage-table slu-org-officers">
                        <table aria-label="Organization officers">
                            <thead><tr><th scope="col">Name</th><th scope="col">Student ID</th><th scope="col">Role</th><th scope="col">Status</th></tr></thead>
                            <tbody>
                                @forelse($officers as $officer)
                                    <tr><td>{{ $officer->name }}</td><td>{{ $officer->student_id ?: '—' }}</td><td>{{ $officer->position }}</td><td @class(['slu-org-verified'=>$officer->hasVerifiedEmail()])>{{ $officer->hasVerifiedEmail() ? 'Verified' : 'Unverified' }}</td></tr>
                                @empty
                                    <tr><td colspan="4" class="slu-org-manage-empty">No officers assigned.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="slu-org-manage-table slu-org-achievements">
                        <table aria-label="Approved organization achievements">
                            <thead><tr><th scope="col">Achievement</th><th scope="col">Submitted by</th><th scope="col">Status</th></tr></thead>
                            <tbody>
                                @forelse($achievements as $achievement)
                                    <tr><td><a href="/workspace/achievements?review={{ $achievement->id }}">{{ $achievement->details['title'] ?? $achievement->ticket }}</a></td><td>{{ $achievement->user->name }}</td><td class="slu-org-verified">Approved</td></tr>
                                @empty
                                    <tr><td colspan="3" class="slu-org-manage-empty">No approved achievements yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="slu-org-manage-actions">
                        <button class="sfu-button" type="button" data-dialog="{{ $memberDialog }}">Add / Accept Member</button>
                        <button class="sfu-button slu-org-roles-button" type="button" data-dialog="{{ $rolesDialog }}">Manage Officer Roles</button>
                    </div>
                </div>
            </div>
        </dialog>

        @include('workspace.organization-member-dialog', ['dialogId'=>$memberDialog, 'title'=>'Add / Accept Member', 'candidates'=>$students->whereNull('organization_id'), 'adding'=>true])
        @include('workspace.organization-member-dialog', ['dialogId'=>$rolesDialog, 'title'=>'Manage Officer Roles', 'candidates'=>$organization->members, 'adding'=>false])
    @endforeach
@endpush
