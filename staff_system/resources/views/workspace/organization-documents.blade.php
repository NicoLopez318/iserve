@extends('workspace.layout')

@section('content')
    <section class="panel" aria-label="Submitted organization documents">
        <form method="get" action="{{ route('staff.organization-documents') }}" class="toolbar">
            <div class="filters">
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120" aria-label="Search documents"
                    placeholder="Search by reference, title, or filename">
                <select name="organization" aria-label="Filter by organization">
                    <option value="">All organizations</option>
                    @foreach($organizations as $organization)
                        <option value="{{ $organization->id }}" @selected((string)($filters['organization'] ?? '') === (string)$organization->id)>{{ $organization->name }}</option>
                    @endforeach
                </select>
                <button type="submit">Search</button>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">Organization</th><th scope="col">Submission</th><th scope="col">Status</th><th scope="col">Documents</th></tr></thead>
                <tbody>
                    @forelse($submissions as $submission)
                        @php
                            $details = $submission->details ?? [];
                            $organization = isset($details['organization_id'])
                                ? $organizations->get((int)$details['organization_id'])
                                : $submission->user?->organization;
                            $category = !empty($details['completion_report']) ? 'Completion Report' : App\Models\ServiceRequest::kinds()[$submission->kind];
                        @endphp
                        <tr>
                            <td>{{ $organization?->name ?? 'Organization not recorded' }}<small>{{ $submission->user?->name }}</small></td>
                            <td>
                                <a href="/workspace/request/{{ $submission->id }}"><strong>{{ $details['title'] ?? $submission->ticket }}</strong></a>
                                <small>{{ $category }} · {{ $submission->ticket }} · {{ $submission->created_at->format('M j, Y') }}</small>
                            </td>
                            <td><span class="status {{ $submission->status }}">{{ str($submission->status)->headline() }}</span></td>
                            <td>
                                @foreach($submission->files ?? [] as $key => $attachment)
                                    @if($path = $submission->attachmentPath($key))
                                        @php($filename = basename(is_array($attachment) ? ($attachment['name'] ?? $path) : $path))
                                        <a class="file" href="{{ route('staff.requests.attachment-view', ['serviceRequest'=>$submission, 'key'=>$key]) }}" target="_blank" rel="noopener">
                                            <span>{{ $filename }}</span><small>View / Download</small>
                                        </a>
                                    @endif
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">No organization documents found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination"><span>{{ $submissions->total() }} submissions</span>{{ $submissions->links() }}</div>
    </section>
@endsection
