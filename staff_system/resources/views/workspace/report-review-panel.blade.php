@php($snapshot=isset($item->snapshot['records'])?$item->snapshot:['records'=>$item->snapshot,'version'=>1,'files'=>[]])
<section class="panel compact-review"><h2>{{auth()->user()->role==='admin'?'Final Report Review':'Report Review'}}</h2>
<strong>{{$item->title}}</strong><p>{{$item->semester}} · {{$item->academic_year}}</p><p class="muted">{{$item->user->name}} · Version {{$snapshot['version']??1}}</p><span class="status {{$item->status}}">{{ucwords(str_replace('_',' ',$item->status))}}</span>
<h3>Summary</h3><p class="text-body">{{$item->summary}}</p>
@foreach($snapshot['activity']??[] as $metric)<div class="summary-line"><span>{{$metric['service']}}</span><strong>{{$metric['completed']}} / {{$metric['pending']}} pending</strong></div>@endforeach
<h3>Attachments</h3>
@forelse($snapshot['files']??[] as $i=>$file)<a class="file" href="/workspace/report/{{$item->id}}/file/{{$i}}">{{$file['name']}}</a>@empty<p class="muted">No supporting files.</p>@endforelse
@if($item->user_id===auth()->id()&&in_array($item->status,['draft','needs_revision']))
<form method="post" action="/workspace/report/{{$item->id}}" enctype="multipart/form-data">@csrf
<label class="field">Unit Head Note / Summary<textarea name="summary" required minlength="30" maxlength="5000">{{old('summary',$item->summary)}}</textarea></label><label class="field">Replace Supporting Files<input type="file" name="attachments[]" multiple accept=".pdf,.png,.jpg,.jpeg,.xlsx,.csv,.docx"></label>
<div class="actions"><button name="status" value="pending">Submit Report</button><button name="status" value="draft" class="outline">Save Draft</button></div></form>
@elseif((auth()->user()->role==='assistant'&&in_array($item->status,['pending','assistant_revision']))||(auth()->user()->role==='admin'&&$item->status==='awaiting_admin'))
<form method="post" action="/workspace/report/{{$item->id}}">@csrf<h3>Verification Checklist</h3>
@foreach(['check_content'=>'Report content checked','check_files'=>'Attachments checked','check_source'=>'Source records checked'] as $key=>$label)<label class="check"><input type="checkbox" name="{{$key}}" value="1" @checked(old($key))>{{$label}}</label>@endforeach
<label class="field">{{auth()->user()->role==='admin'?'Admin Feedback':'Assistant Feedback'}}<textarea name="review_note" required minlength="5" maxlength="3000">{{old('review_note')}}</textarea></label><div class="actions">
@if(auth()->user()->role==='assistant')<button name="status" value="awaiting_admin">Forward to Admin</button><button name="status" value="needs_revision" class="outline">Return for Revision</button>
@else<button name="status" value="approved">Acknowledge Report</button><button name="status" value="assistant_revision" class="outline">Return to Assistant</button><button name="status" value="rejected" class="outline">Decline</button>@endif
</div></form>
@endif
<h3>Reviewer Feedback</h3><p class="text-body">{{$item->review_note??'No review yet.'}}</p>
<details><summary>Submission Timeline</summary><div class="timeline">
@foreach($history as $entry)<article><strong>{{ucwords(str_replace(['report.','_'],['',' '],$entry->action))}}</strong><p>{{$entry->details}}</p><small>{{$entry->name}} · {{$entry->created_at}}</small></article>@endforeach
</div></details>
<a class="button outline" href="/workspace/report/{{$item->id}}/receipt" target="_blank" rel="noopener">{{$item->status==='approved'?'Download Receipt':'Preview / Print Report'}}</a>
</section>
