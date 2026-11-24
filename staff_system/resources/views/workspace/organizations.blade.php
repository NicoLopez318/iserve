<section class="sfu-panel slu-org-directory" aria-labelledby="slu-org-title">
    <div class="slu-queue-heading slu-org-heading">
        <h2 id="slu-org-title"><span class="slu-org-heading-icon" aria-hidden="true"></span>{{ $membershipTab ? 'Membership Applications' : 'Organization Directory' }}</h2>
        <button class="sfu-button" type="button" data-dialog="create-organization">Add Organization</button>
    </div>

    <nav class="slu-org-tabs" aria-label="Organization views">
        <a href="/workspace/organizations" @unless($membershipTab) aria-current="page" @endunless>Organization Directory</a>
        <span aria-hidden="true">/</span>
        <a href="/workspace/organizations?tab=membership" @if($membershipTab) aria-current="page" @endif>Membership Applications</a>
    </nav>

    @if($membershipTab)
        <div class="slu-org-membership">
            @include('workspace.request-table', ['showHeading'=>false, 'queryTab'=>'membership'])
        </div>
    @else
        <form class="sfu-filters slu-org-filters" method="get" action="/workspace/organizations" data-sfu-filters>
            <div class="sfu-search">
                <button type="submit" aria-label="Apply organization filters"><img src="/staff-assets/21-4727.svg" alt=""></button>
                <input name="organization_search" aria-label="Search organizations" placeholder="Search organization..." value="{{ request('organization_search') }}">
            </div>
            <select name="department" aria-label="Department">
                <option value="">All Departments</option>
                @foreach($orgDirectory['departments'] as $department)
                    <option value="{{ $department }}" @selected(request('department') === $department)>{{ $department }}</option>
                @endforeach
            </select>
            <select name="organization_status" aria-label="Organization status">
                <option value="">All Statuses</option>
                @foreach($orgDirectory['statuses'] as $status => $label)
                    <option value="{{ $status }}" @selected(request('organization_status') === $status)>{{ $label }}</option>
                @endforeach
            </select>
        </form>

        <div class="sfu-table-wrap slu-queue-table slu-org-table">
            <table>
                <thead><tr>
                    <th scope="col">Organization</th><th scope="col">College</th><th scope="col">Adviser</th>
                    <th scope="col">Members</th><th scope="col">Points</th><th scope="col">Status</th><th scope="col">Action</th>
                </tr></thead>
                <tbody>
                    @forelse($orgDirectory['rows'] as $entry)
                        @php
                            $organization = $entry['organization'];
                            $initials = str($entry['acronym'] ?: $organization->name)->substr(0, 2)->upper();
                        @endphp
                        <tr>
                            <td><span class="slu-org-name"><span class="slu-org-initials is-color-{{ $organization->id % 4 }}" aria-hidden="true">{{ $initials }}</span>{{ $organization->name }}</span></td>
                            <td>{{ $entry['college'] ?: '—' }}</td><td>{{ $entry['adviser'] ?: '—' }}</td>
                            <td>{{ $organization->members_count }}</td><td>{{ number_format($entry['points']) }}</td>
                            <td><span class="slu-org-status is-{{ $entry['status'] }}">{{ $orgDirectory['statuses'][$entry['status']] }}</span></td>
                            <td><button class="sfu-button" type="button" data-dialog="organization-{{ $organization->id }}" aria-label="Manage {{ $organization->name }}">Manage</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="sfu-empty">No organizations found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('workspace.organization-dialogs')
    @endif
</section>

@include('workspace.drafts',['draftKind'=>'organization'])
