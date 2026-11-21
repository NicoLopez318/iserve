<div class="panel"><div class="toolbar"><h2>Content Library</h2><button data-dialog="portal-content">Add Announcement Banner</button></div><div class="table-wrap"><table><thead><tr><th>Content</th><th>Portal Location</th><th>Schedule</th><th>Status</th><th>Action</th></tr></thead><tbody>@forelse($announcements as $a)<tr><td><strong>{{$a->title}}</strong></td><td>Student Leadership</td><td>{{$a->published_at??'—'}}</td><td>{{$a->published_at?'Published':'Draft'}}</td><td><button class="table-action outline" data-dialog="content-{{$a->id}}">Edit</button></td></tr>@empty<tr><td colspan="5" class="empty">No portal content.</td></tr>@endforelse</tbody></table></div></div>
<div class="panel">
    <h2>Student Portal Preview</h2>
    <div class="actions">
        <a class="button outline" href="{{ rtrim(env('USER_PORTAL_URL', 'http://127.0.0.1:8001'), '/') }}/announcements?unit=leadership" target="_blank" rel="noopener">Preview</a>
        <a class="button outline" href="/workspace/events">View Events</a>
    </div>
</div>
@push('dialogs')@foreach($announcements as $a)<dialog id="content-{{$a->id}}"><button class="close-dialog" data-close aria-label="Close">×</button><form class="panel" action="/workspace/content/{{$a->id}}" method="post" enctype="multipart/form-data">@csrf<h2>Edit Portal Content</h2><label class="field">Title<input name="title" value="{{$a->title}}" required maxlength="180"></label><label class="field">Description<textarea name="body" required>{{$a->body}}</textarea></label><label class="field">Start Date & Time<input type="datetime-local" name="published_at" value="{{$a->published_at?\Carbon\Carbon::parse($a->published_at)->format('Y-m-d\TH:i'):''}}"></label>@include('workspace.content-fields')
<label class="check"><input type="checkbox" name="publish" value="1" @checked($a->published_at)>Publish / Schedule</label><div class="actions"><button>Save Changes</button></div></form></dialog>@endforeach
@endpush
