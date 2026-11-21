@if(auth()->user()->role==='sfu_head'&&in_array($page??'',['dashboard','applications']))
<div class="queue-with-review"><div>@include('workspace.request-table')</div><aside>
@if($selectedRequest)@include('workspace.review-panel',['item'=>$selectedRequest,'history'=>$selectedHistory])
@else<div class="panel compact-review"><h2>Application Review</h2><p class="empty">Select an application to review.</p></div>@endif
</aside></div>
@else
@include('workspace.request-table')
@endif
