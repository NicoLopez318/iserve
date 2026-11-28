<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\{Storage,Mail,Hash,Notification};
use Symfony\Component\Mailer\Exception\TransportException;
use App\Models\{User,ServiceRequest,Assignment,Attendance,Journal,Event,Organization,Point};
class WorkflowTest extends TestCase {
 use RefreshDatabase;
 private function image():UploadedFile{return UploadedFile::fake()->createWithContent('photo.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lGQAAAAASUVORK5CYII='));}
 private function user(string $role='student',array $extra=[]):User{return User::create([...['name'=>'Test User','username'=>'user'.random_int(1000,999999),'email'=>uniqid().'@example.test','password'=>'TestPassword!123','role'=>$role,'account_type'=>'student','student_id'=>uniqid(),'course'=>'BSIT','year_level'=>'3rd Year','profile_complete'=>true,'email_verified_at'=>now()],...$extra]);}
 private function request(User $u,string $kind='complaint',array $details=[]):ServiceRequest{return ServiceRequest::create(['user_id'=>$u->id,'kind'=>$kind,'ticket'=>uniqid('T-'),'details'=>$details]);}
 public function test_leadership_portal_reminds_organizers_about_pending_activity_documents():void
 {
  $organization=Organization::create(['name'=>'Reminders Organization']);
  $owner=$this->user(extra:['organization_id'=>$organization->id]);
  $member=$this->user(extra:['organization_id'=>$organization->id]);
  $pre=$this->request($owner,'activity',['title'=>'Pending off-campus proposal','off_campus'=>true]);
  $pre->update(['status'=>'needs_revision','files'=>['pre_intent'=>['path'=>'requests/intent.pdf']]]);
  $this->request($owner,'activity',['title'=>'On-campus proposal','off_campus'=>false]);
  $post=$this->request($owner,'activity',['title'=>'Finished off-campus event','off_campus'=>true]);
  $post->update(['status'=>'approved']);
  $event=Event::create(['title'=>'Finished off-campus event','description'=>'Event details','venue'=>'Community hall','starts_at'=>now()->subDays(2),'ends_at'=>now()->subDay()]);
  \Illuminate\Support\Facades\DB::table('settings')->insert([
   'key'=>'staff.event-source.'.$event->id,'value'=>json_encode(['proposal_id'=>$post->id,'user_id'=>$owner->id]),
  ]);
  $page='/portal/leadership/notifications';
  $this->actingAs($owner)->get($page)->assertOk()
   ->assertSee('Pre-activity documents')->assertSee('Program Matrix')->assertDontSee('Letter of Intent')
   ->assertSee('Post-activity report due')->assertSee('Narrative Report')
   ->assertSee('/requests/'.$post->id.'/completion')->assertDontSee('On-campus proposal');
  $this->get('/portal/leadership/propose')->assertOk()->assertSee('Activity Document Reminders');
  $this->actingAs($member)->get($page)->assertOk()->assertDontSee('Activity Document Reminders');
  $report=$this->request($owner,'activity',['completion_report'=>true,'event_id'=>$event->id]);
  $this->actingAs($owner)->get($page)->assertOk()->assertDontSee('Post-activity report due');
  $report->update(['status'=>'needs_revision']);
  $this->get($page)->assertOk()->assertSee('Post-activity report needs revision')->assertSee('/requests/'.$report->id);
 }
 public function test_completed_events_automatically_appear_in_the_event_archive():void
 {
  $organization=Organization::create(['name'=>'Event Archive Organization']);
  $student=$this->user(extra:['organization_id'=>$organization->id]);
  $details=['description'=>'Event details','venue'=>'Campus','starts_at'=>now()->subHours(2),'ends_at'=>now()->subHour()];
  $event=Event::create($details+['title'=>'Completed Campus Event','status'=>'published']);
  Event::create($details+['title'=>'Cancelled Campus Event','status'=>'cancelled']);
  Event::create($details+['title'=>'Awaiting Completion Event','status'=>'published']);
  $archive='/portal/leadership/archives';
  $events='/portal/leadership/events';

  $this->actingAs($student)->get($events)->assertOk()->assertSee('Completed Campus Event')
   ->assertSee('href="'.$archive.'#event-archive"',false);
  $this->get($archive)->assertOk()->assertSee('No completed events yet.');
  $event->update(['status'=>'completed']);
  $this->get($events)->assertOk()->assertDontSee('Completed Campus Event')->assertSee('Awaiting Completion Event');
  $this->get($archive)->assertOk()->assertSee('Completed Campus Event')
   ->assertDontSee('Cancelled Campus Event')->assertDontSee('Awaiting Completion Event')->assertDontSee('Join Event');
 }
 public function test_leadership_bell_shows_portal_announcements_and_keeps_the_general_inbox_separate():void
 {
  $organization=Organization::create(['name'=>'Leadership Organization']);
  $student=$this->user(extra:['organization_id'=>$organization->id]);
  $head=$this->user('slu_head');
  $announce=function(string $title,string $unit='leadership',?array $metadata=[],?string $published=null)use($head){
   $id=\Illuminate\Support\Facades\DB::table('announcements')->insertGetId([
    'title'=>$title,'body'=>'Announcement details','unit'=>$unit,'user_id'=>$head->id,'published_at'=>$published??now()->subMinute(),
   ]);
   if($metadata!==null)\Illuminate\Support\Facades\DB::table('settings')->insert([
    'key'=>'staff.content.'.$id,
    'value'=>json_encode($metadata+['portal_location'=>'Student Dashboard','content_type'=>'Announcement Banner']),
   ]);
  };
  $announce('Leadership portal announcement');
  $announce('Leadership portal reminder',metadata:['content_type'=>'Notification']);
  $announce('Community announcement','values');
  $announce('Public-only announcement',metadata:null);
  $announce('Future announcement',published:now()->addHour()->format('Y-m-d\TH:i'));
  $announce('Expired announcement',metadata:['ends_at'=>now()->subSecond()->toDateTimeString()]);
  $student->notifications()->create([
   'id'=>(string)\Illuminate\Support\Str::uuid(),'type'=>'request',
   'data'=>['title'=>'General request update','url'=>'/home'],
  ]);
  $this->actingAs($student)->get('/portal/leadership/leaderboards')->assertOk()
   ->assertSee('href="/portal/leadership/notifications"',false);
  $this->assertSame(2,\App\Support\PortalContent::unreadLeadershipNotifications($student));
  $this->get('/portal/leadership/notifications')->assertOk()
   ->assertSee('Leadership portal announcement')->assertSee('Leadership portal reminder')
   ->assertDontSee('Community announcement')->assertDontSee('Public-only announcement')
   ->assertDontSee('Future announcement')->assertDontSee('Expired announcement')->assertDontSee('General request update');
  $this->assertSame(0,\App\Support\PortalContent::unreadLeadershipNotifications($student));
  $this->assertSame(1,$student->unreadNotifications()->count());
  $this->get('/notifications')->assertOk()->assertSee('General request update');
 }
 public function test_student_notifications_open_the_linked_update_on_the_current_portal():void
 {
  $student=$this->user();
  $application=$this->request($student,'leadership');
  $application->update(['status'=>'needs_revision']);
  $path='/requests/'.$application->id;
  foreach ([
   ['title'=>'Application returned', 'url'=>'http://127.0.0.1:8001'.$path],
   json_encode(['title'=>'Event updated','url'=>'https://student.example.test/portal/leadership/events?month=11#event-7']),
  ] as $data) {
   $student->notifications()->create(['id'=>(string)\Illuminate\Support\Str::uuid(),'type'=>'request','data'=>$data]);
  }
  $this->actingAs($student)->get('/notifications')->assertOk()
   ->assertSee('href="'.$path.'"',false)
   ->assertSee('href="/portal/leadership/events?month=11#event-7"',false);
  $this->get($path)->assertOk()->assertSee('Resubmit for Review');
 }
 public function test_leaderboards_rank_students_and_organizations_separately():void
 {
  $team=Organization::create(['name'=>'Combined EXP Society']);
  $solo=Organization::create(['name'=>'Individual EXP Society']);
  $member=$this->user(extra:['name'=>'Team First','organization_id'=>$team->id]);
  $teammate=$this->user(extra:['name'=>'Team Second','organization_id'=>$team->id]);
  $leader=$this->user(extra:['name'=>'Individual Leader','organization_id'=>$solo->id]);
  foreach([[$member,70],[$teammate,60],[$leader,100]] as [$student,$points]){
   Point::create(['user_id'=>$student->id,'amount'=>$points,'source'=>'rank:'.$student->id,'reason'=>'Verified participation']);
  }
  $response=$this->actingAs($member)->get('/portal/leadership/leaderboards')
   ->assertOk()->assertSee('Top Leaders')->assertSee('Top Organizations')->assertSee('Total EXP');
  $this->assertSame($leader->id,$response->viewData('leaders')->first()->id);
  $ranked=$response->viewData('organizationLeaders');
  $this->assertSame([$team->id,$solo->id],$ranked->pluck('id')->all());
  $this->assertSame(130,(int)$ranked->first()->xp);
 }
 public function test_student_profile_course_dropdown_preserves_and_saves_courses():void
 {
  $student=$this->user(extra:['course'=>'bsit']);
  $this->user(extra:['course'=>'BSCS']);
  $this->actingAs($student)->get('/profile/setup')->assertOk()
   ->assertSee('<select name="course"',false)->assertSee('value="bsit" selected',false)
   ->assertSee('value="BSCS"',false);
  $profile=['name'=>$student->name,'account_type'=>'student','student_id'=>$student->student_id,'year_level'=>'3rd Year'];
  $this->from('/profile/setup')->post('/profile',$profile+['course'=>'__other__'])
   ->assertRedirect('/profile/setup')->assertSessionHasErrors('other_course');
  $this->post('/profile',$profile+['course'=>'__other__','other_course'=>'BS Mathematics'])
   ->assertRedirect('/home')->assertSessionHasNoErrors();
  $this->assertSame('BS Mathematics',$student->fresh()->course);
 }
 private function assignment(User $u):Assignment{return Assignment::create(['user_id'=>$u->id,'record_number'=>uniqid('CV-'),'offense'=>'Conduct','severity'=>'Minor','department'=>'IT','supervisor'=>'Supervisor','required_minutes'=>120]);}
 public function test_activity_drafts_are_private_and_submit_retained_documents():void{
  Storage::fake('local');$org=Organization::create(['name'=>'Draft Organization']);$u=$this->user('student',['organization_id'=>$org->id]);$this->actingAs($u);
  $following=$this->user('slu_head',['preferences'=>['alerts'=>['proposals'=>true,'submissions'=>false]]]);
  $muted=$this->user('slu_head',['preferences'=>['alerts'=>['proposals'=>false,'submissions'=>true]]]);
  $this->post('/proposal-draft',['details'=>['capacity'=>1001]])->assertSessionHasErrors('details.capacity');
  $this->post('/proposal-draft',['details'=>['title'=>'Saved activity'],'documents'=>['pre_intent'=>$this->image()]])->assertRedirect('/apply/activity');
  $this->assertDatabaseCount('service_requests',0);$this->assertDatabaseHas('proposal_drafts',['user_id'=>$u->id]);
  $this->get('/apply/activity')->assertOk()->assertSee('Saved activity')->assertSee('photo.png');
  $other=$this->user('student',['organization_id'=>$org->id]);$this->actingAs($other)->get('/apply/activity')->assertDontSee('Saved activity')->assertDontSee('photo.png');
  $this->actingAs($u)->post('/apply/activity',['declaration'=>1,'details'=>['title'=>'Incomplete']])->assertSessionHasErrors();
  $this->assertDatabaseCount('proposal_drafts',1);
  $details=['title'=>'Submitted activity','description'=>'A complete activity description','venue'=>'Campus','starts_at'=>now()->addDays(2)->format('Y-m-d H:i'),'ends_at'=>now()->addDays(2)->addHours(2)->format('Y-m-d H:i'),'points'=>100];
  $this->post('/apply/activity',['declaration'=>1,'details'=>$details+['capacity'=>1001]])->assertSessionHasErrors('details.capacity');
  $this->post('/apply/activity',['declaration'=>1,'details'=>$details+['capacity'=>1000]])->assertRedirect()->assertSessionHasNoErrors();
  $q=ServiceRequest::first();$this->assertSame('pending',$q->status);$this->assertSame($org->id,$q->details['organization_id']);$this->assertSame('photo.png',$q->files['pre_intent']['name']);Storage::assertExists($q->files['pre_intent']['path']);$this->assertDatabaseCount('proposal_drafts',0);
  $this->assertSame('New transaction: '.$q->ticket,$following->notifications()->sole()->data['title']);
  $this->assertSame(0,$muted->notifications()->count());
 }
 public function test_moderator_editing_requires_president_and_same_organization():void{
  $org=Organization::create(['name'=>'Test organization']);$other=Organization::create(['name'=>'Other']);
  $president=$this->user('student',['organization_id'=>$org->id,'position'=>'President']);$member=$this->user('student',['organization_id'=>$org->id]);$outsider=$this->user('student',['organization_id'=>$other->id]);
  $this->actingAs($member)->get('/portal/leadership/organization')->assertOk()->assertDontSee('Manage Organization');
  $this->get('/portal/leadership/organization/manage')->assertForbidden();
  $this->actingAs($member)->post('/organization/moderators',['moderators'=>[$member->id]])->assertForbidden();
  $this->actingAs($president)->get('/portal/leadership/organization')->assertOk()->assertSee('Manage Organization');
  $this->get('/portal/leadership/organization/manage')->assertOk()->assertSee('Edit Moderators');
  $this->actingAs($president)->postJson('/organization/moderators',['moderators'=>[$outsider->id]])->assertStatus(422);
  $this->post('/organization/moderators',['moderators'=>[$member->id]])->assertRedirect();$this->assertSame([$member->id],$org->fresh()->moderators);
  $this->get('/portal/leadership/propose')->assertOk()->assertDontSee('Edit Moderators');
 }

 public function test_student_submits_an_after_event_report_from_the_approved_proposal():void
 {
  Storage::fake('local');
  $org=Organization::create(['name'=>'Report Organization']);
  $student=$this->user(extra:['organization_id'=>$org->id]);
  $other=$this->user(extra:['organization_id'=>$org->id]);
  $proposal=$this->request($student,'activity',['title'=>'Campus Activity','organization_id'=>$org->id]);
  $proposal->update(['status'=>'approved']);
  $event=Event::create([
   'title'=>'Campus Activity','description'=>'An activity report test','venue'=>'Campus',
   'starts_at'=>now()->subHours(2),'ends_at'=>now()->addHour(),
  ]);
  \Illuminate\Support\Facades\DB::table('settings')->insert([
   'key'=>'staff.event-source.'.$event->id,
   'value'=>json_encode(['proposal_id'=>$proposal->id,'user_id'=>$student->id]),
  ]);
  $path='/requests/'.$proposal->id.'/completion';
  $data=['summary'=>str_repeat('Activity work completed. ',3),'declaration'=>1];
  $this->actingAs($other)->get($path)->assertForbidden();
  $this->actingAs($student)->get('/portal/leadership/propose')->assertOk()->assertSee($path);
  $this->get($path)->assertOk()->assertSee('You can submit your report after the event ends');
  $this->post($path,$data+['report'=>$this->image()])->assertUnprocessable();
  $this->assertSame([],Storage::allFiles('completion-reports'));
  $event->update(['ends_at'=>now()->subHour()]);
  $student->update(['organization_id'=>null]);
  $this->get($path)->assertOk()->assertSee('Submit after-event report');
  $this->post($path,$data+['report'=>$this->image()])->assertRedirect();
  $report=ServiceRequest::whereKeyNot($proposal->id)->sole();
  $this->assertSame($event->id,$report->details['event_id']);
  $this->assertSame($org->id,$report->details['organization_id']);
  $this->assertTrue($report->details['completion_report']);
  $this->assertSame('approved',$proposal->fresh()->status);
  Storage::assertExists($report->files['completion_report']['path']);
  $this->post($path,$data+['report'=>$this->image()])->assertUnprocessable();
  $this->assertCount(1,Storage::allFiles('completion-reports'));
  $report->update(['status'=>'needs_revision']);
  $this->get($path)->assertOk()->assertSee('Revise report');
  $this->post('/requests/'.$report->id.'/revise',[
   'revision'=>'Added the attendance and documentation requested.',
   'documents'=>['completion_report'=>$this->image()],
  ])->assertRedirect();
  $this->assertSame('pending',$report->fresh()->status);
 }
 public function test_auth_registration_login_logout_and_no_role_escalation():void{
  Mail::fake();$this->post('/register',['email'=>'new@example.test','username'=>'newstudent','password'=>'TestPassword!123','password_confirmation'=>'TestPassword!123','role'=>'admin'])->assertRedirect('/email/verify');
  $u=User::where('email','new@example.test')->first();$this->assertSame('student',$u->role);$this->assertNull($u->email_verified_at);$this->assertAuthenticatedAs($u);
  $this->post('/logout')->assertRedirect('/login');$this->assertGuest();
  $this->post('/login',['login'=>'newstudent','password'=>'wrong'])->assertSessionHasErrors('login');
  $this->post('/login',['login'=>'newstudent','password'=>'TestPassword!123'])->assertRedirect('/email/verify');
 }
 public function test_student_can_recover_from_failed_verification_delivery_and_submit_after_verification():void {
  \Illuminate\Support\Facades\Event::listen(NotificationSending::class,function(NotificationSending $event){
   if($event->notification instanceof VerifyEmail)throw new TransportException('Simulated mail provider outage.');
  });
  $this->post('/register',[
   'email'=>'verification@example.test','username'=>'verification-student',
   'password'=>'TestPassword!123','password_confirmation'=>'TestPassword!123',
  ])->assertRedirect('/email/verify')->assertSessionHasErrors('verification');
  $student=User::where('email','verification@example.test')->sole();
  $this->assertAuthenticatedAs($student);
  $this->get('/email/verify')->assertOk()->assertSee('Resend verification email');
  $this->post('/apply/complaint',[])->assertRedirect('/email/verify');

  Notification::fake();
  config(['mail.default'=>'log']);
  app()->instance('env','production');
  $csrfToken='verification-csrf-token';
  $this->withSession(['_token'=>$csrfToken])->post('/email/verification-notification',['_token'=>$csrfToken])
   ->assertRedirect('/email/verify')->assertSessionHasErrors('verification');
  Notification::assertNothingSent();

  app()->instance('env','testing');
  config(['mail.default'=>'smtp']);
  $this->get('/email/verify')->assertOk();
  $this->post('/email/verification-notification')->assertRedirect('/email/verify')->assertSessionHasNoErrors();
  $verificationUrl=null;
  Notification::assertSentTo($student,VerifyEmail::class,function(VerifyEmail $notification)use($student,&$verificationUrl){
   $verificationUrl=$notification->toMail($student)->actionUrl;
   return true;
  });
  $this->assertNotNull($verificationUrl);
  $this->get($verificationUrl)->assertRedirect('/profile/setup');
  $this->assertTrue($student->fresh()->hasVerifiedEmail());
  $this->post('/profile',['name'=>'Test Student','account_type'=>'student','student_id'=>'VERIFY-001','course'=>'BSIT','year_level'=>'1st Year'])
   ->assertRedirect('/home')->assertSessionHasNoErrors();
  Mail::fake();
  $this->post('/apply/complaint',['declaration'=>1,'details'=>[
   'complaint_type'=>'Harassment','incident_date'=>today()->toDateString(),'location'=>'Campus',
   'description'=>'A sample request to confirm the submission flow.',
  ]])->assertRedirect()->assertSessionHasNoErrors();
  $this->assertDatabaseHas('service_requests',['user_id'=>$student->id,'kind'=>'complaint','status'=>'pending']);
 }
 public function test_every_student_screen_and_transaction_form_renders():void{
  $org=Organization::create(['name'=>'Screen Organization']);$u=$this->user('student',['organization_id'=>$org->id]);$this->actingAs($u);
  foreach(['/home','/transactions','/community-link','/profile/setup','/notifications','/email/verify'] as $url)$this->get($url)->assertOk();
  foreach(['values'=>['dashboard','violations','community-service','journal','profile','settings'],'leadership'=>['dashboard','events','organization','members','leaderboards','propose','profile','settings','archives'],'scholar'=>['dashboard','tuition','profile','settings']] as $portal=>$pages)foreach($pages as $page)$this->get('/portal/'.$portal.'/'.$page)->assertOk();
  foreach(array_keys(ServiceRequest::kinds()) as $kind){if($kind==='leadership')$this->get('/apply/'.$kind)->assertRedirect('/portal/leadership');else $this->get('/apply/'.$kind)->assertOk();}
 }
 public function test_all_staff_roles_render_and_students_cannot_access_staff():void{
  $this->actingAs($this->user())->get('/staff')->assertForbidden();
  foreach(['admin','assistant','sfu_head','cvu_head','slu_head'] as $role)$this->actingAs($this->user($role))->get('/staff')->assertOk();
 }
 public function test_documents_are_private_and_requests_are_owner_and_office_scoped():void{
  Storage::fake('local');$u=$this->user();$other=$this->user();$this->actingAs($u)->post('/apply/found',['details'=>['item_name'=>'Wallet','incident_date'=>today()->toDateString(),'location'=>'Campus','description'=>'Wallet found near the campus gate','contact'=>'Office 123'],'documents'=>['item_photo'=>$this->image()],'declaration'=>1])->assertRedirect();
  $q=ServiceRequest::first();$this->assertSame('pending',$q->status);Storage::assertExists($q->files['item_photo']['path']);
  $this->actingAs($other)->get('/requests/'.$q->id)->assertForbidden();$this->get('/requests/'.$q->id.'/files/item_photo')->assertForbidden();
  $this->actingAs($this->user('sfu_head'))->get('/requests/'.$q->id)->assertForbidden();
  $this->actingAs($this->user('cvu_head'))->get('/requests/'.$q->id.'/files/item_photo')->assertOk();
 }
 public function test_unverified_user_cannot_submit_and_no_financial_injection():void{
  $u=$this->user('student',['email_verified_at'=>null]);$this->actingAs($u)->post('/apply/complaint',[])->assertRedirect('/email/verify');
  Storage::fake('local');$u->update(['email_verified_at'=>now()]);$this->actingAs($u)->post('/apply/scholarship',['details'=>['student_type'=>'Undergraduate','semester'=>'1st Semester','academic_year'=>'2026-2027','reason'=>'Apply for scholarship support','discount_rate'=>100],'documents'=>['application'=>UploadedFile::fake()->create('app.pdf',2,'application/pdf'),'photo'=>$this->image(),'registration'=>UploadedFile::fake()->create('reg.pdf',2,'application/pdf')],'declaration'=>1])->assertRedirect();
  $this->assertArrayNotHasKey('discount_rate',ServiceRequest::first()->details);
 }
 public function test_scholarship_eligibility_is_displayed_before_information_and_document_submission():void
 {
  $this->actingAs($this->user());
  foreach(['/scholar/information','/apply/scholarship'] as $page){
   $this->get($page)->assertOk()
    ->assertSeeInOrder(['Scholarship discount eligibility','Form 137 or Form 138','81%','View UCU’s requirements for each grant','<form method="post"'],false);
  }
 }
 public function test_review_transitions_office_isolation_and_notification():void{
  $u=$this->user();$q=$this->request($u,'scholarship');$wrong=$this->user('cvu_head');$this->actingAs($wrong)->post('/staff/requests/'.$q->id,['status'=>'approved','review_note'=>'Approved for scholarship','discount_rate'=>30])->assertForbidden();
  $sfu=$this->user('sfu_head');$this->actingAs($sfu)->post('/staff/requests/'.$q->id,['status'=>'approved','review_note'=>'Approved for scholarship','discount_rate'=>30])->assertRedirect();
  $this->assertSame('unit_approved',$q->fresh()->status);$this->assertSame(30,$q->fresh()->details['discount_rate']);$this->assertSame(1,$u->notifications()->count());
  $this->post('/staff/requests/'.$q->id,['status'=>'rejected','review_note'=>'Attempted second review'])->assertSessionHasErrors('workflow');
  $this->actingAs($this->user('assistant'))->post('/staff/requests/'.$q->id,['status'=>'awaiting_admin','review_note'=>'Verified and forwarded to admin'])->assertRedirect();
  $this->actingAs($this->user('admin'))->post('/staff/requests/'.$q->id,['status'=>'approved','review_note'=>'Final approval confirmed'])->assertRedirect();
  $this->actingAs($u)->get('/portal/scholar/tuition')->assertSee('19,950.00')->assertSee('8,550.00');
 }
 public function test_duplicate_open_request_blocked_and_revision_resubmission():void{
  $u=$this->user();$q=$this->request($u,'clearance',['appointment_date'=>now()->addDays(2)->toDateString(),'schedule'=>'09:00','purpose'=>'Graduation']);
  $this->actingAs($u)->post('/apply/clearance',['details'=>$q->details,'declaration'=>1])->assertSessionHasErrors('workflow');
  $this->actingAs($this->user('cvu_head'))->post('/staff/requests/'.$q->id,['status'=>'needs_revision','review_note'=>'Please clarify your purpose'])->assertRedirect();
  $this->actingAs($u)->post('/requests/'.$q->id.'/revise',['revision'=>'I am requesting graduation clearance.'])->assertRedirect();$this->assertSame('pending',$q->fresh()->status);
 }
 public function test_community_hours_require_review_and_modules_unlock_from_verified_hours():void{
  Storage::fake('local');$u=$this->user();$a=$this->assignment($u);$this->actingAs($u);
  $this->post('/community/journal',['module'=>2,'answer'=>str_repeat('Learning ',10)])->assertSessionHasErrors('workflow');
  $this->post('/community/clock',['action'=>'in','photo'=>$this->image()])->assertRedirect();
  $this->post('/community/clock',['action'=>'in','photo'=>$this->image()])->assertSessionHasErrors('workflow');
  $this->travel(2)->hours();$this->post('/community/clock',['action'=>'out','photo'=>$this->image()])->assertRedirect();$attendance=Attendance::first();$this->assertSame(0,$a->approvedMinutes());
  $staff=$this->user('cvu_head');$this->actingAs($staff)->post('/staff/attendance/'.$attendance->id,['status'=>'approved','review_note'=>'Verified duty attendance'])->assertRedirect();$this->assertGreaterThanOrEqual(120,$a->approvedMinutes());
  $this->post('/staff/assignments/'.$a->id.'/complete')->assertSessionHasErrors('workflow');
  $this->actingAs($u);foreach([1,2,3,4] as $module)$this->post('/community/journal',['module'=>$module,'answer'=>str_repeat('My service learning reflection. ',3)])->assertRedirect();
  $this->actingAs($staff);foreach(Journal::all() as $j)$this->post('/staff/journals/'.$j->id,['status'=>'approved'])->assertRedirect();
  $this->post('/staff/assignments/'.$a->id.'/complete')->assertRedirect();$this->assertSame('completed',$a->fresh()->status);
 }
 public function test_event_capacity_time_window_reflection_and_points_are_verified_once():void{
  $org=Organization::create(['name'=>'Events Organization']);$u=$this->user('student',['organization_id'=>$org->id]);$other=$this->user('student',['organization_id'=>$org->id]);$event=Event::create(['title'=>'Service Day','description'=>'Campus service activity','venue'=>'Campus','starts_at'=>now()->addHour(),'ends_at'=>now()->addHours(3),'capacity'=>1,'points'=>50]);
  $this->actingAs($u)->post('/events/'.$event->id,['action'=>'join'])->assertRedirect();$this->actingAs($other)->post('/events/'.$event->id,['action'=>'join'])->assertSessionHasErrors('workflow');
  $this->actingAs($u)->post('/events/'.$event->id,['action'=>'in'])->assertSessionHasErrors('workflow');$this->travel(90)->minutes();$this->post('/events/'.$event->id,['action'=>'in'])->assertRedirect();$this->travel(60)->minutes();$this->post('/events/'.$event->id,['action'=>'out'])->assertRedirect();
  $this->post('/events/'.$event->id,['action'=>'reflection','reflection'=>'We learned valuable skills while serving the community.'])->assertRedirect();$this->assertSame(0,Point::count());
  $a=Attendance::first();$this->actingAs($this->user('slu_head'))->post('/staff/attendance/'.$a->id,['status'=>'approved','review_note'=>'Participation verified'])->assertRedirect();$this->assertSame(50,(int)Point::sum('amount'));
  $this->post('/staff/attendance/'.$a->id,['status'=>'approved','review_note'=>'Attempt duplicate award'])->assertSessionHasErrors('workflow');$this->assertSame(1,Point::count());
 }
 public function test_event_reflections_respect_slu_event_notification_preferences():void{
  $following=$this->user('slu_head',['preferences'=>['alerts'=>['event_updates'=>true,'submissions'=>false]]]);
  $muted=$this->user('slu_head',['preferences'=>['alerts'=>['event_updates'=>false,'submissions'=>true]]]);
  $organization=Organization::create(['name'=>'Events Organization']);
  $student=$this->user('student',['organization_id'=>$organization->id]);
  $event=Event::create(['title'=>'Service Day','description'=>'Campus service activity','venue'=>'Campus','starts_at'=>now()->subHours(2),'ends_at'=>now()->subHour(),'capacity'=>1,'points'=>50]);
  Attendance::create(['user_id'=>$student->id,'event_id'=>$event->id,'time_in'=>now()->subHours(2),'time_out'=>now()->subHour(),'status'=>'awaiting_reflection']);
  $this->actingAs($student)->post('/events/'.$event->id,['action'=>'reflection','reflection'=>'We learned valuable skills while serving the community.'])->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame('Event attendance ready to verify: '.$event->title,$following->notifications()->sole()->data['title']);
  $this->assertSame(0,$muted->notifications()->count());
 }
 public function test_clearance_requires_completed_community_record():void{
  $u=$this->user();$this->assignment($u);$this->actingAs($u)->post('/apply/clearance',['details'=>['appointment_date'=>now()->addDays(2)->toDateString(),'schedule'=>'09:00','purpose'=>'Graduation'],'declaration'=>1])->assertSessionHasErrors('workflow');
 }
 public function test_organization_posts_are_member_scoped():void{
  $org=Organization::create(['name'=>'Org']);$member=$this->user('student',['organization_id'=>$org->id]);$outsider=$this->user();$this->actingAs($member)->post('/posts',['body'=>'Organization achievement update'])->assertRedirect();$post=$org->posts()->first();
  $this->actingAs($outsider)->post('/posts/'.$post->id.'/comments',['body'=>'Outside comment'])->assertForbidden();
  $this->actingAs($member)->post('/posts/'.$post->id.'/comments',['body'=>'Congratulations!'])->assertRedirect();$this->assertSame(1,$post->comments()->count());
 }
 public function test_two_factor_keeps_user_guest_until_correct_code():void{
  Mail::fake();$u=$this->user('student',['two_factor'=>true]);$this->post('/login',['login'=>$u->email,'password'=>'TestPassword!123'])->assertRedirect('/two-factor');$this->assertGuest();
  $this->post('/two-factor',['code'=>'000000'])->assertSessionHasErrors('code');$this->assertGuest();
  $this->withSession(['challenge'=>['user'=>$u->id,'hash'=>Hash::make('123456'),'expires'=>time()+600,'remember'=>false]])->post('/two-factor',['code'=>'123456'])->assertRedirect('/');$this->assertAuthenticatedAs($u);
 }
}


