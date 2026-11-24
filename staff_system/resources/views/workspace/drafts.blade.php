@php($drafts=App\Support\StaffDrafts::listing(auth()->user(),$draftKind))
@if(count($drafts))
<section class="panel"><h2>Drafts</h2><div class="table-wrap"><table><thead><tr><th>Draft</th><th>Last Saved</th><th>Action</th></tr></thead><tbody>
@foreach($drafts as $draft)
@php($draftDialog = ['organization'=>'create-organization','assignment'=>'assign-student','scholar'=>'add-scholar'][$draftKind])
@php($draftData = $draftKind === 'scholar' ? [...$draft,'files'=>array_map(fn($file)=>$file['name'],$draft['files']??[])] : $draft)
<tr><td>{{$draft['fields']['name']??$draft['fields']['offense']??$draft['fields']['full_name']??$draft['fields']['student_id']??'Untitled Draft'}}</td><td>{{\Carbon\Carbon::parse($draft['saved_at'])->format('M j, Y g:i A')}}</td><td><button type="button" class="table-action outline" data-dialog="{{$draftDialog}}" data-draft="{{json_encode($draftData)}}">Continue Draft</button></td></tr>
@endforeach
</tbody></table></div></section>
@endif
