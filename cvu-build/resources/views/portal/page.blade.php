@extends('layouts.portal')
@section('subtitle')
@if($portal==='leadership')Your central hub for leadership tracking, point totals, and progress.
@elseif($portal==='scholar')Track your scholarship status and tuition savings.
@else Track assigned hours, verified attendance, schedules, and completion status.
@endif
@endsection
@section('content')
@if($portal==='leadership'&&in_array($page,['dashboard','events']))
@php($portalNotices=\Illuminate\Support\Facades\DB::table('announcements')->where('unit','leadership')->whereNotNull('published_at')->where('published_at','<=',now())->orderByDesc('published_at')->get()->filter(function($a)use($u,$page){$m=\App\Support\PortalContent::metadata($a->id);return isset($m['portal_location'])&&$m['portal_location']===($page==='dashboard'?'Student Dashboard':'Event & Activities')&&\App\Support\PortalContent::active($a,$u);}))
@foreach($portalNotices as $notice)
@php($meta=\App\Support\PortalContent::metadata($notice->id))
<section class="panel"><h2>{{$notice->title}}</h2>@if(isset($meta['image']))<img src="/content-image/{{$notice->id}}" alt="{{$notice->title}}" style="max-width:100%;max-height:240px;object-fit:cover">@endif
<p class="pre-wrap">{{$notice->body}}</p>@if($destination=\App\Support\PortalContent::destination($meta['cta_destination']??null))<a class="btn orange" href="{{$destination}}">{{$meta['cta_label']??'View'}}</a>@endif
</section>
@endforeach
@endif

@if($page==='settings')
<section class="panel settings-panel"><h2>Preferences & Security</h2><form method="post" action="/settings">@csrf<h3>Notifications</h3><label class="toggle-row">In-app notifications<input type="checkbox" name="in_app_notifications" value="1" @checked($u->preferences['in_app']??true)></label><label class="toggle-row">Email notifications<input type="checkbox" name="email_notifications" value="1" @checked($u->preferences['email']??true)></label><h3>Account security</h3><label class="toggle-row">Email sign-in code (two-step verification)<input type="checkbox" name="two_factor" value="1" @checked($u->two_factor)></label><p class="muted">Verify your email first. Sign-in codes are delivered through the configured mail service.</p><div class="form-grid"><label>Current Password<input type="password" name="current_password" autocomplete="current-password"></label><label>New Password<input type="password" name="password" autocomplete="new-password" minlength="10"></label><label>Confirm New Password<input type="password" name="password_confirmation" autocomplete="new-password"></label></div><button class="btn {{ $portal==='leadership'?'orange':'primary' }}">Save Changes</button></form></section>
@elseif($page==='profile')
@include('account.profile-content')
@elseif($page==='archives')
@if($portal==='leadership')
<section class="panel" id="event-archive" aria-labelledby="event-archive-title">
    <div class="section-heading">
        <h2 id="event-archive-title">Event Archive</h2>
        <a href="/portal/leadership/events">Back to Events &amp; Activities</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th scope="col">Event</th><th scope="col">Venue</th><th scope="col">Ended</th></tr></thead>
            <tbody>
                @forelse($archivedEvents as $event)
                    <tr id="event-{{ $event->id }}">
                        <td>{{ $event->title }}</td>
                        <td>{{ $event->venue }}</td>
                        <td><time datetime="{{ $event->ends_at->toIso8601String() }}">{{ $event->ends_at->format('M j, Y, g:i A') }}</time></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">No completed events yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endif
<section class="panel"><h2>Achievement Archives</h2><a class="btn orange" href="/apply/achievement">Submit Achievement</a><div class="table-wrap"><table><thead><tr><th>Title</th><th>Submitted</th><th>Status</th><th></th></tr></thead><tbody>@forelse($requests->where('kind','achievement') as $q)<tr><td>{{ $q->details['title'] }}</td><td>{{ $q->created_at->format('M j, Y') }}</td><td><span class="badge {{ $q->status }}">{{ $q->status }}</span></td><td><a href="/requests/{{ $q->id }}">View</a></td></tr>@empty<tr><td colspan="4" class="empty">No achievements submitted.</td></tr>@endforelse</tbody></table></div></section>
@elseif($page==='propose')
@include('portal.activity-document-reminders')
<section class="panel"><h2>Organization Activity Proposal</h2><p>Provide the proposed activity, schedule, venue, and expected participants. The Leadership Unit reviews the proposal before publishing an event.</p><a class="btn orange" href="/apply/activity">Propose Activity →</a></section>
<section class="panel">
    <h2>My Proposals</h2>
    @forelse($requests->where('kind','activity') as $q)
        <div class="record-row">
            <a href="/requests/{{ $q->id }}"><strong>{{ $q->details['title'] }}</strong></a>
            <span class="badge {{ $q->status }}">{{ $q->status }}</span>
            @if($q->status === 'approved' && empty($q->details['completion_report']))
                <a class="btn secondary" href="{{ route('activity.completion', $q) }}">After-event report</a>
            @endif
        </div>
    @empty
        <p class="empty">No activity proposals yet.</p>
    @endforelse
</section>
@elseif($page==='events')
<div class="section-heading"><h2>Events & Activities</h2>@if($portal==='leadership')<a class="btn secondary" href="/portal/leadership/archives#event-archive">Event Archive</a>@endif</div>
@forelse($events as $event)@php($a=$attendances->firstWhere('event_id',$event->id))<section class="panel event-card" id="event-{{ $event->id }}"><div class="event-date"><b>{{ $event->starts_at->format('d') }}</b><span>{{ $event->starts_at->format('M') }}</span></div><div class="event-content"><span class="eyebrow">{{ $event->type }} · {{ $event->points }} EXP</span><h2>{{ $event->title }}</h2><p>{{ $event->description }}</p><p>{{ $event->venue }} · {{ $event->starts_at->format('M j, Y · g:i A') }}–{{ $event->ends_at->format('g:i A') }}</p>
@if(!$a)<form method="post" action="/events/{{ $event->id }}" data-confirm="Are you sure you want to attend this event?">@csrf<input type="hidden" name="action" value="join"><button class="btn orange" @disabled($event->starts_at->isPast()||$event->attendances()->count()>=$event->capacity)>Join Event</button></form>
@else
@include('portal.event-clock')
@if($a->time_out&&in_array($a->status,['awaiting_reflection','rejected']))<form method="post" action="/events/{{ $event->id }}">@csrf<input type="hidden" name="action" value="reflection"><label>Event Reflection<textarea name="reflection" minlength="30" rows="3" required>{{ old('reflection',$a->reflection) }}</textarea></label><button class="btn orange">Submit Reflection</button></form>@elseif($a->reflection)<p>{{ $a->reflection }}</p>@endif
@if($a->review_note)<p class="muted">Staff note: {{ $a->review_note }}</p>@endif
@endif</div></section>@empty<section class="panel"><p class="empty">No upcoming events. Check back later.</p></section>@endforelse
@elseif(in_array($page,['organization','members']))
<section class="panel organization-hero">
    <span class="org-monogram">{{ substr($organization?->name??'O',0,1) }}</span>
    <div>
        <h2>{{ $organization?->name ?? 'Your Organization' }}</h2>
        @if($organization?->description)<p>{{ $organization->description }}</p>@elseif(!$organization)<p>Apply for leadership membership to join an organization.</p>@endif
    </div>
    @if($organization && $u->canManageOrganization())
        <a class="btn orange" href="{{ route('organization.manage') }}">Manage Organization</a>
    @endif
</section>
<div class="tab-links"><a class="{{ $page==='organization'?'active':'' }}" href="/portal/leadership/organization">Achievements & Updates</a><a class="{{ $page==='members'?'active':'' }}" href="/portal/leadership/members">Members</a></div>
@if(!$organization)<section class="panel empty">Your organization will appear once your leadership application is approved.</section>
@elseif($page==='members')<div class="members-grid">@foreach($organization->members as $member)<article class="panel"><div class="member-avatar">{{ substr($member->name,0,1) }}</div><h3>{{ $member->name }}</h3><p>{{ $member->position??'Member' }}</p><small>{{ $member->course }} · {{ $member->year_level }}</small></article>@endforeach</div>
@else<section class="panel"><h3>Share an update</h3><form method="post" action="/posts">@csrf<label class="sr-only" for="post-body">Your update</label><textarea id="post-body" name="body" maxlength="3000" rows="3" required placeholder="Celebrate achievements with your organization…"></textarea><button class="btn orange">Publish Post</button></form></section>@forelse($posts as $post)<article class="panel"><div class="section-heading"><strong>{{ $post->user->name }}</strong><small>{{ $post->created_at->diffForHumans() }}</small></div><p class="pre-wrap">{{ $post->body }}</p><div class="comments">@foreach($post->comments as $comment)<p><strong>{{ $comment->user->name }}</strong> {{ $comment->body }}</p>@endforeach</div><form method="post" action="/posts/{{ $post->id }}/comments" class="inline-form">@csrf<label class="sr-only" for="comment-{{ $post->id }}">Comment</label><input id="comment-{{ $post->id }}" name="body" maxlength="1000" required placeholder="Write a comment…"><button class="btn orange">Comment</button></form></article>@empty<section class="panel empty">Be the first to post an organization update.</section>@endforelse
@endif
@elseif($page==='leaderboards')
<section class="panel" aria-labelledby="top-leaders-title"><div class="section-heading"><h2 id="top-leaders-title">Top Leaders</h2><a href="#top-organizations">View Top Organizations ↓</a></div><div class="podium">@foreach($leaders->take(3) as $leader)<div><img src="{{ asset('design-assets/'.['53533.png','3b110.png','f1b03.png'][$loop->index]) }}" alt="" class="leader-avatar"><b>#{{ $loop->iteration }}</b><h3>{{ $leader->name }}</h3><p>{{ number_format($leader->xp) }} EXP</p></div>@endforeach</div><div class="table-wrap"><table><thead><tr><th>Rank</th><th>Student</th><th>Organization</th><th>EXP</th><th>Level</th></tr></thead><tbody>@foreach($leaders as $leader)<tr><td>{{ $loop->iteration }}</td><td>{{ $leader->name }}</td><td>{{ $leader->organization?->name??'—' }}</td><td>{{ number_format($leader->xp) }}</td><td>{{ 1+(int)floor($leader->xp/250) }}</td></tr>@endforeach</tbody></table></div></section>
<section class="panel" id="top-organizations" aria-labelledby="top-organizations-title">
    <div class="section-heading"><h2 id="top-organizations-title">Top Organizations</h2><small>Combined EXP of current members</small></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th scope="col">Rank</th><th scope="col">Organization</th><th scope="col">Total EXP</th></tr></thead>
            <tbody>
                @forelse($organizationLeaders as $rankedOrganization)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $rankedOrganization->name }}</td><td>{{ number_format($rankedOrganization->xp) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="empty">No organizations to rank yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@elseif($page==='tuition')
<section class="panel tuition-panel"><h2>Tuition Discount</h2><p>Your tuition fees and savings based on your approved scholarship.</p><div class="table-wrap"><table><thead><tr><th>Fee Breakdown</th><th>Original Amount</th><th>Discount Rate</th><th>Discount Amount</th><th>Amount After Discount</th></tr></thead><tbody>@foreach($fees as $label=>$cents)@php($saving=(int)round($cents*$rate/100))<tr><td>{{ $label }}</td><td>₱{{ number_format($cents/100,2) }}</td><td>{{ $rate }}%</td><td>₱{{ number_format($saving/100,2) }}</td><td>₱{{ number_format(($cents-$saving)/100,2) }}</td></tr>@endforeach<tr class="total-row"><td>Total</td><td>₱{{ number_format($total/100,2) }}</td><td></td><td>₱{{ number_format($discount/100,2) }}</td><td>₱{{ number_format(($total-$discount)/100,2) }}</td></tr></tbody></table></div><div class="tuition-summary"><strong>Total Savings<br><b>₱{{ number_format($discount/100,2) }}</b></strong><strong>Net Tuition<br><b>₱{{ number_format(($total-$discount)/100,2) }}</b></strong></div><p class="muted">Demonstration fee schedule. Your official billing statement remains with the Finance Office.</p>@if(!$scholarship)<a class="btn primary" href="/apply/scholarship">Apply for a Scholarship</a>@endif</section>
@elseif($page==='violations')
@include('portal.service-violations')
@elseif($page==='journal')
@include('portal.service-assignment-picker')
@foreach(config('portal.journals') as $module=>$title)@php($threshold=(int)ceil(($assignment?->required_minutes??0)*($module-1)/3))@php($journal=$assignment?->journals->firstWhere('module',$module))<section class="panel journal-card"><div class="section-heading"><div><span class="eyebrow">MODULE {{ $module }}</span><h2>{{ $title }}</h2></div><span class="badge">{{ $journal?->status??($assignment&&$minutes>=$threshold?'Available':'Locked') }}</span></div>
@if(!$assignment||$minutes<$threshold)<p class="muted">Unlock after {{ number_format($threshold/60,1) }} verified hours.</p>@elseif($journal?->status==='approved')<p class="pre-wrap">{{ $journal->answer }}</p>@else<form method="post" action="/community/journal">@csrf<input type="hidden" name="assignment_id" value="{{ $assignment->id }}"><input type="hidden" name="module" value="{{ $module }}"><label>Your reflection<textarea name="answer" minlength="30" maxlength="10000" rows="5" required>{{ $journal?->answer }}</textarea></label><button class="btn primary" @disabled($assignment->status!=='active')>{{ $journal?'Update & Resubmit':'Submit Journal' }}</button></form>@endif</section>@endforeach
@elseif($page==='community-service')
@include('portal.service-assignment-picker')
@if(!$assignment)<section class="panel empty"><h2>No Record Found</h2><p>Contact the Community Values Unit for assistance.</p><a class="btn primary" href="/home">Return to iServe</a></section>@else
<section class="panel deployment"><div><small>Deployment</small><h2>{{ strtoupper($assignment->department) }}</h2></div><div><small>Monitored by:</small><h2>{{ $assignment->supervisor }}</h2></div></section>
@if(filled($assignment->note))
    <section class="panel"><h2>Assignment Note</h2><p class="pre-wrap">{{ $assignment->note }}</p></section>
@endif
@include('portal.community-clock')
@include('portal.service-feedback')
<section class="panel"><h2>Service Progress</h2><p class="muted">Verified hours completed</p><div class="progress-heading"><span>{{ number_format($minutes/60,1) }} / {{ $assignment->required_minutes/60 }} HOURS</span><b>{{ min(100,(int)round($minutes/max(1,$assignment->required_minutes)*100)) }}% COMPLETE</b></div><div class="progress-blocks">@for($i=0;$i<10;$i++)<span class="{{ $i<$minutes/max(1,$assignment->required_minutes)*10?'filled':'' }}"></span>@endfor</div><p class="center muted">{{ number_format(max(0,$assignment->required_minutes-$minutes)/60,1) }} hours remaining</p><a href="{{ url('/portal/values/journal').'?assignment='.$assignment->id }}">Continue Duty Journal →</a></section>
<section class="panel"><h2>Documentation & Attendance History</h2><div class="table-wrap"><table><thead><tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Hours</th><th>Status</th><th>Staff Note</th></tr></thead><tbody>@forelse($attendances->where('assignment_id',$assignment->id) as $a)<tr><td>{{ $a->time_in?->format('M j, Y') }}</td><td>{{ $a->time_in?->format('g:i A') }}</td><td>{{ $a->time_out?->format('g:i A')??'In progress' }}</td><td>{{ number_format($a->minutes/60,1) }}</td><td><span class="badge {{ $a->status }}">{{ $a->status }}</span></td><td>{{ $a->review_note??'—' }}@if($a->status==='rejected'&&$assignment->status==='active')<details><summary>Resubmit Evidence</summary><form method="post" action="/attendance/{{ $a->id }}/revise" enctype="multipart/form-data">@csrf<label>Replacement evidence<input name="photo" type="file" accept="image/*" required></label><label>Explanation<textarea name="explanation" minlength="10" required rows="2"></textarea></label><button class="btn primary">Resubmit</button></form></details>@endif</td></tr>@empty<tr><td colspan="6" class="empty">No duty sessions recorded yet.</td></tr>@endforelse</tbody></table></div></section>@endif
@elseif($portal==='scholar')
<h2>Good {{ now()->hour<12?'morning':'afternoon' }}, {{ explode(' ',$u->name)[0] }}!</h2><p class="muted">Here's your scholarship overview.</p><section class="panel profile-hero"><div><small>Current Tuition</small><h2 class="large-number">₱{{ number_format(($total-$discount)/100,2) }}</h2><p>{{ $scholarship?'Your scholarship is active.':($scholarEnrollment?'Your application is approved by the SFU Head and awaiting final review.':'Start your scholarship application today.') }}</p><a class="btn primary" href="/portal/scholar/tuition">View Tuition & Discount</a></div><div class="scholar-orb">{{ $rate }}<small>% discount</small></div></section><div class="stats-grid"><article class="panel"><small>Scholarship Status</small><h2>{{ $scholarship?'Active':($scholarEnrollment?'Unit approved':'Not enrolled') }}</h2></article><article class="panel"><small>Discount Rate</small><h2>{{ $rate }}%</h2></article><article class="panel"><small>Total Savings</small><h2>₱{{ number_format($discount/100,2) }}</h2></article><article class="panel"><small>Applications</small><h2>{{ $requests->where('kind','scholarship')->count() }}</h2></article></div><section class="panel"><div class="section-heading"><h2>Scholarship Applications</h2><a class="btn primary" href="/apply/scholarship">Apply Now</a></div>@forelse($requests->where('kind','scholarship') as $q)<a class="record-row" href="/requests/{{ $q->id }}"><strong>{{ $q->ticket }} · {{ $q->details['academic_year']??'' }}</strong><span class="badge {{ $q->status }}">{{ $q->status }}</span></a>@empty<p class="empty">No scholarship applications yet.</p>@endforelse</section>
@elseif($portal==='leadership')
<div class="section-heading"><div><small>Welcome</small><h2>{{ $u->name }}!</h2></div><div class="organization-name"><strong>{{ $organization?->name??'Join an Organization' }}</strong><small>{{ $u->position??'Student Leader' }}</small></div><span class="org-monogram">{{ substr($organization?->name??'i',0,1) }}</span></div><section class="panel xp-strip"><div><x-icon file="bb3ad.svg" :size="48"/><strong>{{ number_format($xp) }}<small>Total EXP</small></strong></div><div><span class="medal-crop"><img src="{{ asset('design-assets/d1cb7.png') }}" alt=""></span><strong>Level {{ 1+(int)floor($xp/250) }}<small>Inspiring Leader</small></strong></div><div><span class="achievement-medal"><img src="{{ asset('design-assets/ffb57.png') }}" alt=""></span><strong>{{ $requests->where('kind','achievement')->where('status','approved')->count() }}<small>Achievements earned</small></strong></div></section>
<div class="two-columns"><div><section class="panel"><div class="section-heading"><h3>Org Participation</h3><small>This month</small></div><div class="mini-stats"><div><b>{{ $attendances->whereNotNull('event_id')->count() }}</b><small>Events Joined</small></div><div><b>{{ $attendances->where('status','approved')->whereNotNull('event_id')->count() }}</b><small>Verified Events</small></div><div><b>{{ $xp }} EXP</b><small>EXP Earned</small></div></div></section><section class="panel"><div class="section-heading"><h2>Upcoming Events</h2><a href="/portal/leadership/events">View All</a></div><div class="calendar-heading">{{ now()->format('F Y') }}</div><div class="calendar"><div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>@for($i=0;$i<now()->startOfMonth()->dayOfWeek;$i++)<span></span>@endfor @for($d=1;$d<=now()->daysInMonth;$d++)<div class="{{ $d===now()->day?'today':'' }}"><b>{{ $d }}</b>@foreach($events->filter(fn($e)=>$e->starts_at->format('Y-m-d')===now()->day($d)->format('Y-m-d')) as $e)<a href="/portal/leadership/events">{{ $e->title }}</a>@endforeach</div>@endfor</div></section></div><div><section class="panel"><div class="section-heading"><h3>Top Leaders</h3><a href="/portal/leadership/leaderboards">View All</a></div><div class="podium">@foreach($leaders->take(3) as $leader)<div><img src="{{ asset('design-assets/'.['53533.png','3b110.png','f1b03.png'][$loop->index]) }}" alt="" class="leader-avatar"><b>#{{ $loop->iteration }}</b><h4>{{ $leader->name }}</h4><small>{{ $leader->xp }} EXP</small></div>@endforeach</div></section><section class="panel"><h3>Top Organizations</h3>@foreach($organizationLeaders as $org)<div class="org-progress"><strong>{{ $org->name }}</strong><small>{{ number_format($org->xp) }} pts</small><progress max="{{ max(1000,$org->xp) }}" value="{{ max(0,$org->xp) }}"></progress></div>@endforeach<a href="/portal/leadership/leaderboards">View All Leaderboards</a></section></div></div><section class="panel growth-banner"><div><h2>Keep Growing, keep leading!</h2><p>Your Actions today shape the leaders of tomorrow</p></div><img src="{{ asset('design-assets/6efc2.png') }}" alt="Leadership trophies"></section>
@else
<h2>Good {{ now()->hour<12?'morning':'afternoon' }}, {{ explode(' ',$u->name)[0] }}!</h2><p class="muted">Here's your values and conduct overview.</p>
<section class="android-banner"><img src="{{ asset('design-assets/ff1a9.png') }}" alt=""><div><h2>iServe: ComServ App</h2><p>Track your assigned service hours,<br>journal, and next steps in one place.</p><a class="btn primary" href="/portal/values/community-service">Open Community Service</a></div></section>
<section class="panel profile-hero"><div><h2>{{ $u->name }}</h2><p>{{ $u->course }} – {{ $u->year_level }}</p><p>Student ID: {{ $u->student_id }}</p><div class="button-row"><a class="btn primary" href="/portal/values/profile">VIEW PROFILE</a><small>AY {{ Illuminate\Support\Facades\DB::table('settings')->where('key','academic_year')->value('value')??now()->year.'–'.(now()->year+1) }}</small><small>{{ Illuminate\Support\Facades\DB::table('settings')->where('key','semester')->value('value')??'1st Semester' }}</small></div></div><div class="avatar-large"><img src="{{ $u->avatar?url('/avatar'):asset('design-assets/7992e.png') }}" alt="Student profile"></div></section>
@php($activeCount = $assignments->where('status','active')->count())
@php($serviceRecords = $assignments->whereIn('status',['active','completed']))
@php($requiredMinutes = $serviceRecords->sum('required_minutes'))
@php($creditedMinutes = $serviceRecords->sum(fn($record) => min($record->required_minutes, $record->verified_minutes ?? 0)))
@php($completion = $requiredMinutes ? (int)round($creditedMinutes / $requiredMinutes * 100) : 100)
<div class="stats-grid"><article class="panel stat-icon"><x-icon file="a067a.svg" :size="50"/><div><h3 class="amber">{{ $activeCount ? 'Action Required' : 'Good Standing' }}</h3><small>Current standing</small></div></article><article class="panel"><h2 class="amber">{{ $activeCount }} ACTIVE</h2><p>Violations</p><a href="/portal/values/violations">View Records</a></article><article class="panel"><h2 class="blue-text">{{ $completion }}%</h2><p>Community Service</p><small>{{ number_format($creditedMinutes/60,1) }} credited hours</small></article><article class="panel"><h2 class="green-text">{{ $attendances->where('status','approved')->whereNotNull('assignment_id')->count() }}</h2><p>Verified Sessions</p></article></div>
<div class="two-columns values-bottom"><section class="panel"><h2>Overall</h2><p class="muted">Hours completed</p><div class="progress-heading"><span>{{ number_format($creditedMinutes/60,1) }} / {{ $requiredMinutes/60 }} HOURS</span><b>{{ $completion }}% COMPLETE</b></div><div class="progress-blocks">@for($i=0;$i<10;$i++)<span class="{{ $i<$completion/10?'filled':'' }}"></span>@endfor</div><a class="btn primary" href="/portal/values/community-service">Continue Service</a></section><section class="panel"><h3>Record & Next Step</h3><a class="record-row" href="/portal/values/violations">Recorded Offense →</a><a class="record-row" href="/portal/values/community-service">Community Service →</a><a class="record-row" href="/portal/values/journal">Duty Journal →</a><a class="record-row" href="/apply/clearance">Final Verification & Clearance →</a><small class="muted">Resolution requires authorized verification.</small></section></div>
@endif
@endsection





