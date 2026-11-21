@php($drafts=App\Support\StaffDrafts::listing(auth()->user(),$draftKind))
@if(count($drafts))
<section class="panel"><h2>Drafts</h2><div class="table-wrap"><table><thead><tr><th>Draft</th><th>Last Saved</th><th>Action</th></tr></thead><tbody>
@foreach($drafts as $draft)
<tr><td>{{$draft['fields']['name']??$draft['fields']['offense']??'Untitled Draft'}}</td><td>{{\Carbon\Carbon::parse($draft['saved_at'])->format('M j, Y g:i A')}}</td><td><button type="button" class="table-action outline" data-dialog="{{$draftKind==='organization'?'create-organization':'assign-student'}}" data-draft="{{json_encode($draft)}}">Continue Draft</button></td></tr>
@endforeach
</tbody></table></div></section>
@endif
