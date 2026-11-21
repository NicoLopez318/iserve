<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Storage,Mail,Hash};
use App\Models\{User,ServiceRequest,Assignment,Attendance,Journal,Event,Organization,Point};
class WorkflowTest extends TestCase {
 use RefreshDatabase;
 private function image():UploadedFile{return UploadedFile::fake()->createWithContent('photo.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lGQAAAAASUVORK5CYII='));}
 private function user(string $role='student',array $extra=[]):User{return User::create([...['name'=>'Test User','username'=>'user'.random_int(1000,999999),'email'=>uniqid().'@example.test','password'=>'TestPassword!123','role'=>$role,'account_type'=>'student','student_id'=>uniqid(),'course'=>'BSIT','year_level'=>'3rd Year','profile_complete'=>true,'email_verified_at'=>now()],...$extra]);}
 private function request(User $u,string $kind='complaint',array $details=[]):ServiceRequest{return ServiceRequest::create(['user_id'=>$u->id,'kind'=>$kind,'ticket'=>uniqid('T-'),'details'=>$details]);}
 private function assignment(User $u):Assignment{return Assignment::create(['user_id'=>$u->id,'record_number'=>uniqid('CV-'),'offense'=>'Conduct','severity'=>'Minor','department'=>'IT','supervisor'=>'Supervisor','required_minutes'=>120]);}
 public function test_activity_drafts_are_private_and_submit_retained_documents():void{
  Storage::fake('local');$u=$this->user();$this->actingAs($u);
  $this->post('/proposal-draft',['details'=>['title'=>'Saved activity'],'documents'=>['pre_intent'=>$this->image()]])->assertRedirect('/apply/activity');
  $this->assertDatabaseCount('service_requests',0);$this->assertDatabaseHas('proposal_drafts',['user_id'=>$u->id]);
  $this->get('/apply/activity')->assertOk()->assertSee('Saved activity')->assertSee('photo.png');
  $other=$this->user();$this->actingAs($other)->get('/apply/activity')->assertDontSee('Saved activity')->assertDontSee('photo.png');
  $this->actingAs($u)->post('/apply/activity',['declaration'=>1,'details'=>['title'=>'Incomplete']])->assertSessionHasErrors();
  $this->assertDatabaseCount('proposal_drafts',1);
  $this->post('/apply/activity',['declaration'=>1,'details'=>['title'=>'Submitted activity','description'=>'A complete activity description','venue'=>'Campus','starts_at'=>now()->addDays(2)->format('Y-m-d H:i'),'ends_at'=>now()->addDays(2)->addHours(2)->format('Y-m-d H:i'),'capacity'=>50,'points'=>100]])->assertRedirect();
  $q=ServiceRequest::first();$this->assertSame('pending',$q->status);$this->assertSame('photo.png',$q->files['pre_intent']['name']);Storage::assertExists($q->files['pre_intent']['path']);$this->assertDatabaseCount('proposal_drafts',0);
 }
 public function test_moderator_editing_requires_president_and_same_organization():void{
  $org=Organization::create(['name'=>'Test organization']);$other=Organization::create(['name'=>'Other']);
  $president=$this->user('student',['organization_id'=>$org->id,'position'=>'President']);$member=$this->user('student',['organization_id'=>$org->id]);$outsider=$this->user('student',['organization_id'=>$other->id]);
  $this->actingAs($member)->post('/organization/moderators',['moderators'=>[$member->id]])->assertForbidden();
  $this->actingAs($president)->postJson('/organization/moderators',['moderators'=>[$outsider->id]])->assertStatus(422);
  $this->post('/organization/moderators',['moderators'=>[$member->id]])->assertRedirect();$this->assertSame([$member->id],$org->fresh()->moderators);
  $this->get('/portal/leadership/propose')->assertOk()->assertSee('Edit Moderators');
 }
 public function test_auth_registration_login_logout_and_no_role_escalation():void{
  Mail::fake();$this->post('/register',['email'=>'new@example.test','username'=>'newstudent','password'=>'TestPassword!123','password_confirmation'=>'TestPassword!123','role'=>'admin'])->assertRedirect('/profile/setup');
  $u=User::where('email','new@example.test')->first();$this->assertSame('student',$u->role);$this->assertNull($u->email_verified_at);$this->assertAuthenticatedAs($u);
  $this->post('/logout')->assertRedirect('/login');$this->assertGuest();
  $this->post('/login',['login'=>'newstudent','password'=>'wrong'])->assertSessionHasErrors('login');
  $this->post('/login',['login'=>'newstudent','password'=>'TestPassword!123'])->assertRedirect('/profile/setup');
 }
 public function test_every_student_screen_and_transaction_form_renders():void{
  $u=$this->user();$this->actingAs($u);
  foreach(['/home','/transactions','/community-link','/profile/setup','/notifications','/email/verify'] as $url)$this->get($url)->assertOk();
  foreach(['values'=>['dashboard','violations','community-service','journal','profile','settings'],'leadership'=>['dashboard','events','organization','members','leaderboards','propose','profile','settings','archives'],'scholar'=>['dashboard','tuition','profile','settings']] as $portal=>$pages)foreach($pages as $page)$this->get('/portal/'.$portal.'/'.$page)->assertOk();
  foreach(array_keys(ServiceRequest::kinds()) as $kind)$this->get('/apply/'.$kind)->assertOk();
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
  $u=$this->user();$other=$this->user();$event=Event::create(['title'=>'Service Day','description'=>'Campus service activity','venue'=>'Campus','starts_at'=>now()->addHour(),'ends_at'=>now()->addHours(3),'capacity'=>1,'points'=>50]);
  $this->actingAs($u)->post('/events/'.$event->id,['action'=>'join'])->assertRedirect();$this->actingAs($other)->post('/events/'.$event->id,['action'=>'join'])->assertSessionHasErrors('workflow');
  $this->actingAs($u)->post('/events/'.$event->id,['action'=>'in','photo'=>$this->image()])->assertSessionHasErrors('workflow');$this->travel(90)->minutes();$this->post('/events/'.$event->id,['action'=>'in','photo'=>$this->image()])->assertRedirect();$this->travel(60)->minutes();$this->post('/events/'.$event->id,['action'=>'out','photo'=>$this->image()])->assertRedirect();
  $this->post('/events/'.$event->id,['action'=>'reflection','reflection'=>'We learned valuable skills while serving the community.'])->assertRedirect();$this->assertSame(0,Point::count());
  $a=Attendance::first();$this->actingAs($this->user('slu_head'))->post('/staff/attendance/'.$a->id,['status'=>'approved','review_note'=>'Participation verified'])->assertRedirect();$this->assertSame(50,(int)Point::sum('amount'));
  $this->post('/staff/attendance/'.$a->id,['status'=>'approved','review_note'=>'Attempt duplicate award'])->assertSessionHasErrors('workflow');$this->assertSame(1,Point::count());
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
  $this->withSession(['challenge'=>['user'=>$u->id,'hash'=>Hash::make('123456'),'expires'=>time()+600,'remember'=>false]])->post('/two-factor',['code'=>'123456'])->assertRedirect('/home');$this->assertAuthenticatedAs($u);
 }
}


