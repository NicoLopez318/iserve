<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Storage,DB};
use App\Models\{User,Organization,Event,ServiceRequest,Assignment,Attendance,Point,Report};
use App\Http\Controllers\StaffPortalController;
class StaffManagementTest extends TestCase {
 use RefreshDatabase;

 public function test_slu_add_organization_keeps_draft_details_and_requires_an_eligible_student():void {
  $head=$this->actor('slu_head');$verified=$this->actor('student');$unverified=$this->actor('student');
  $unverified->update(['email_verified_at'=>null]);
  $url='/workspace/organizations';
  $fields=['name'=>'Open Science Society','acronym'=>'OSS','college'=>'CITE','type'=>'Academic','adviser'=>'Dr. Cruz','academic_year'=>'2026-2027'];
  $this->actingAs($head)->get($url)->assertOk()->assertSeeText('Initial Student Leader')->assertSeeText('Portal Access');
  $this->from($url)->post('/workspace/drafts/organization',[...$fields,'president_ref'=>$verified->student_id])
   ->assertRedirect($url)->assertSessionHasNoErrors();
  $draft=\App\Support\StaffDrafts::listing($head,'organization')[0];
  $this->assertSame('2026-2027',$draft['fields']['academic_year']);
  $this->assertSame($verified->student_id,$draft['fields']['president_ref']);
  $this->post($url,[...$fields,'draft_id'=>$draft['id'],'president_id'=>$unverified->id])
   ->assertRedirect($url)->assertSessionHasErrors('president_id');
  $this->assertSame(0,Organization::count());
  $this->post($url,[...$fields,'draft_id'=>$draft['id'],'president_id'=>$verified->id])
   ->assertRedirect($url)->assertSessionHasNoErrors();
  $org=Organization::sole();
  $this->assertSame($org->id,$verified->fresh()->organization_id);
  $this->assertSame('President',$verified->fresh()->position);
  $this->assertSame('2026-2027',json_decode(DB::table('settings')->where('key','staff.organization.'.$org->id)->value('value'),true)['academic_year']);
  $this->assertSame([],\App\Support\StaffDrafts::listing($head,'organization'));
 }

 public function test_slu_organization_directory_shows_metadata_points_and_membership_tab():void {
  $head=$this->actor('slu_head');$student=$this->actor('student');
  $org=Organization::create(['name'=>'Learning Society']);
  $otherOrg=Organization::create(['name'=>'Legacy Organization']);
  $student->update(['organization_id'=>$org->id,'position'=>'President']);
  Point::create(['user_id'=>$student->id,'amount'=>1800,'source'=>'directory-sample','reason'=>'Verified points']);
  DB::table('settings')->insert(['key'=>'staff.organization.'.$org->id,'value'=>json_encode([
   'college'=>'CITE','adviser'=>'Dr. Cruz','acronym'=>'LS','status'=>'for_renewal',
  ])]);
  $files=collect(config('portal.documents.leadership'))->map(fn($label,$key)=>['path'=>"requests/$key.pdf"])->all();
  $membership=ServiceRequest::create(['user_id'=>$student->id,'kind'=>'leadership','ticket'=>'SLU-MEMBER-SAMPLE','details'=>['organization_id'=>(string)$org->id,'position'=>'Vice President'],'files'=>$files]);
  $otherMembership=ServiceRequest::create(['user_id'=>$student->id,'kind'=>'leadership','ticket'=>'SLU-MEMBER-OTHER','status'=>'needs_revision','details'=>['organization_id'=>$otherOrg->id,'position'=>'Member']]);
  ServiceRequest::create(['user_id'=>$student->id,'kind'=>'achievement','ticket'=>'SLU-ACHIEVED','status'=>'approved','details'=>['title'=>'Approved Society Award']]);
  ServiceRequest::create(['user_id'=>$student->id,'kind'=>'achievement','ticket'=>'SLU-PENDING-AWARD','details'=>['title'=>'Pending Society Award']]);

  $this->actingAs($head)->get('/workspace/organizations?organization_search=learning&department=CITE&organization_status=for_renewal')
   ->assertOk()->assertSeeText('Organization Directory')->assertSeeText('Learning Society')->assertSeeText('Dr. Cruz')
   ->assertSeeText('For Renewal')->assertSeeHtml('<td>1,800</td>')->assertDontSeeText('Legacy Organization')
   ->assertSeeHtml('id="organization-'.$org->id.'"')->assertSeeText('Officers')->assertSeeText('Approved Society Award')
   ->assertDontSeeText('Pending Society Award')->assertSeeText('Add / Accept Member')->assertSeeText('Manage Officer Roles');
  $this->get('/workspace/organizations?tab=membership')->assertOk()->assertSeeText($membership->ticket)
   ->assertSeeTextInOrder([$membership->ticket,'Learning Society','Vice President','Complete','New','Review'])
   ->assertSeeTextInOrder([$otherMembership->ticket,'Legacy Organization','Member','Missing','Incomplete','View'])
   ->assertSeeHtml('name="tab" value="membership"')->assertSeeHtml('id="create-organization"')->assertDontSeeHtml('id="organization-'.$org->id.'"');
  $this->get('/workspace/organizations?tab=membership&search=Learning')->assertOk()
   ->assertSeeText($membership->ticket)->assertDontSeeText($otherMembership->ticket);
  $this->get('/workspace/organizations?tab=membership&organization='.$org->id.'&status=pending')->assertOk()
   ->assertSeeText($membership->ticket)->assertDontSeeText($otherMembership->ticket);
  $this->get('/workspace/organizations?tab=membership&organization='.$org->id.'&status=needs_revision')->assertOk()
   ->assertSeeText('No membership applications found.');
  $this->get('/workspace/organizations?review='.$membership->id)->assertOk()->assertSeeHtml('id="transaction-review"');
 }

 public function test_slu_proposal_queue_filters_and_expands_with_live_application_details():void {
  $head=$this->actor('slu_head');
  $student=$this->actor('student');
  $current=Organization::create(['name'=>'Current Student Society']);
  $submitted=Organization::create(['name'=>'Campus Research Club']);
  $student->update(['organization_id'=>$current->id]);
  $files=collect(['pre_intent','pre_matrix','pre_evaluation','pre_resolution','pre_budget'])
   ->mapWithKeys(fn($key)=>[$key=>['path'=>"requests/$key.pdf"]])->all();
  $proposal=ServiceRequest::create([
   'user_id'=>$student->id,'kind'=>'activity','ticket'=>'QUEUE-CAMPUS','status'=>'in_review','files'=>$files,
   'details'=>['title'=>'Campus Research Expo','organization_id'=>(string)$submitted->id,'starts_at'=>now()->addDays(3)->toDateTimeString()],
  ]);
  ServiceRequest::create([
   'user_id'=>$student->id,'kind'=>'activity','ticket'=>'QUEUE-LEGACY',
   'details'=>['title'=>'Volunteer Day','starts_at'=>now()->subDay()->toDateTimeString()],
  ]);
  for($index=0;$index<3;$index++){
   ServiceRequest::create([
    'user_id'=>$student->id,'kind'=>'activity','ticket'=>'QUEUE-OLDER-'.$index,
    'details'=>['title'=>'Earlier Proposal '.$index],'created_at'=>now()->subDays($index+2),
   ]);
  }
  ServiceRequest::create(['user_id'=>$student->id,'kind'=>'leadership','ticket'=>'MEMBERSHIP-ONLY','details'=>['title'=>'Unrelated Membership']]);
  $url='/workspace/proposals';
  $this->actingAs($head)->get($url)->assertOk()->assertSeeText('Proposal Queue')->assertSeeText('View all')
   ->assertSeeTextInOrder(['Campus Research Expo','Campus Research Club','5 of 5 attached','Ready to Endorse'])
   ->assertSeeTextInOrder(['Volunteer Day','Current Student Society','0 of 5 attached','Overdue Review'])
   ->assertDontSeeText('Earlier Proposal 2')->assertDontSeeText('Unrelated Membership');
  $this->get($url.'?queue=all')->assertOk()->assertSeeText('Earlier Proposal 2')->assertSeeText('Back to queue');
  $this->get($url.'?search=Research+Club')->assertOk()->assertSeeText('Campus Research Expo')->assertDontSeeText('Volunteer Day');
  $this->get($url.'?search=Volunteer&organization='.$current->id.'&status=pending')->assertOk()
   ->assertSeeText('Volunteer Day')->assertDontSeeText('Campus Research Expo');
  $this->get($url.'?organization='.$submitted->id.'&status=in_review&review='.$proposal->id)->assertOk()
   ->assertSeeText('Proposal Review')->assertSeeHtml('id="transaction-review"')->assertDontSeeText('Volunteer Day');
 }

 public function test_slu_achievement_submissions_show_evidence_and_filter_existing_categories():void {
  $head=$this->actor('slu_head');
  $student=$this->actor('student');
  $current=Organization::create(['name'=>'Campus Society']);
  $submitted=Organization::create(['name'=>'Research Circle']);
  $student->update(['organization_id'=>$current->id]);
  DB::table('settings')->insert(['key'=>'staff.points-rules','value'=>json_encode(['achievement'=>250])]);
  $submission=ServiceRequest::create([
   'user_id'=>$student->id,'kind'=>'achievement','ticket'=>'ACH-RESEARCH','details'=>[
    'title'=>'Science Expo Award','category'=>'Research','award'=>'First Place','achievement_date'=>'2026-09-20',
    'organization_id'=>(string)$submitted->id,
   ],'files'=>['evidence'=>['path'=>'requests/certificate.pdf'],'photo'=>'requests/photo.png'],
  ]);
  $legacy=ServiceRequest::create([
   'user_id'=>$student->id,'kind'=>'achievement','ticket'=>'ACH-LEGACY','status'=>'needs_revision',
   'details'=>['title'=>'Legacy Volunteer Award','award'=>'Service'],
  ]);
  $approved=ServiceRequest::create([
   'user_id'=>$student->id,'kind'=>'achievement','ticket'=>'ACH-APPROVED','status'=>'approved',
   'details'=>['title'=>'Verified Campus Award','awarded_points'=>100],
  ]);
  Point::create(['user_id'=>$student->id,'amount'=>180,'source'=>'achievement:'.$approved->id,'reason'=>'Verified award']);
  ServiceRequest::create([
   'user_id'=>$student->id,'kind'=>'achievement','ticket'=>'ADJ-HIDDEN',
   'details'=>['title'=>'Unrelated point adjustment','adjustment'=>true],
  ]);
  $url='/workspace/achievements';
  $this->actingAs($head)->get($url)->assertOk()->assertSeeText('Achievements Submissions')
   ->assertSeeTextInOrder(['Science Expo Award','Research Circle','Sep 20, 2026','2 Files','250','Pending Review','Review'])
   ->assertSeeTextInOrder(['Legacy Volunteer Award','Campus Society','No evidence','Missing Evidence','View'])
   ->assertSeeTextInOrder(['Verified Campus Award','180','Approved','View'])
   ->assertDontSeeText('Unrelated point adjustment');
  $this->get($url.'?search=Research+Circle&status=pending&organization='.$submitted->id.'&category=Research')->assertOk()
   ->assertSeeText('Science Expo Award')->assertDontSeeText('Legacy Volunteer Award');
  $this->get($url.'?category=Service')->assertOk()->assertSeeText('Legacy Volunteer Award')->assertDontSeeText('Science Expo Award');
  $reviewUrl=$url.'?review='.$submission->id;
  $this->get($reviewUrl)->assertOk()->assertSeeHtml('class="slu-achievement-dialog"')
   ->assertSeeTextInOrder(['Achievement Review','Science Expo Award','Supporting Documentation','certificate.pdf',
    'Verification Checklist','Manual verification','No matching submission','Recommended Points',
    'Portal Impact','+250 points after final approval','Return for Evidence','Endorse for 250 Points'])
   ->assertSeeHtml('name="awarded_points" min="-1000" max="1000"')
   ->assertSeeHtml('value="250" aria-describedby="slu-achievement-points-help"')
   ->assertSee('/files/evidence/view');
  $this->get($url.'?review='.$legacy->id)->assertOk()->assertSeeText('No review yet.');
  $this->get($url.'?review='.$approved->id)->assertOk()->assertSeeText('+180 points awarded')
   ->assertDontSeeText('Return for Evidence');

  $possibleDuplicate=ServiceRequest::create([
   'user_id'=>$student->id,'kind'=>'achievement','ticket'=>'ACH-MATCH','details'=>$submission->details,'files'=>$submission->files,
  ]);
  $this->get($reviewUrl)->assertOk()->assertSeeText('Possible match');
  $this->from($reviewUrl)->post('/staff/requests/'.$submission->id,['status'=>'needs_revision'])
   ->assertRedirect($reviewUrl)->assertSessionHasErrors('review_note');
  $this->assertSame('pending',$submission->fresh()->status);
  $this->from($reviewUrl)->post('/staff/requests/'.$possibleDuplicate->id,['status'=>'needs_revision','review_note'=>'Please attach the official certificate.'])
   ->assertRedirect($reviewUrl)->assertSessionHasNoErrors();
  $this->assertSame('needs_revision',$possibleDuplicate->fresh()->status);

  $decision='/staff/requests/'.$submission->id;
  $this->from($reviewUrl)->post($decision,['status'=>'unit_approved','awarded_points'=>500])
   ->assertRedirect($reviewUrl)->assertSessionHasNoErrors();
  $this->assertSame('unit_approved',$submission->fresh()->status);
  $this->assertNull($submission->fresh()->review_note);
  $this->assertDatabaseMissing('points',['source'=>'achievement:'.$submission->id]);
  $this->actingAs($this->actor('assistant'))->post($decision,['status'=>'awaiting_admin','review_note'=>'Checked the recommendation.'])
   ->assertRedirect()->assertSessionHasNoErrors();
  $this->actingAs($this->actor('admin'))->post($decision,['status'=>'approved','review_note'=>'Final approval.'])
   ->assertRedirect()->assertSessionHasNoErrors();
  $this->assertDatabaseHas('points',['source'=>'achievement:'.$submission->id,'amount'=>500]);
 }

 public function test_slu_dashboard_shows_scoped_action_queue_deadlines_and_activity():void {
  $head=$this->actor('slu_head');$student=$this->actor('student');$assistant=$this->actor('assistant');
  $org=Organization::create(['name'=>'Leadership Test Society']);
  $student->update(['organization_id'=>$org->id]);
  $proposal=ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'SLU-QUEUE','kind'=>'activity','details'=>['title'=>'Leadership Summit Sample']]);
  $closed=ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'SLU-CLOSED','kind'=>'achievement','status'=>'approved','details'=>['title'=>'Closed Achievement Sample']]);
  ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'SFU-PRIVATE','kind'=>'scholarship','details'=>['title'=>'SFU Scoped Sample']]);
  $event=Event::create(['title'=>'Upcoming Leadership Summit','description'=>'Event description','venue'=>'Auditorium','starts_at'=>now()->addDays(2),'ends_at'=>now()->addDays(3),'type'=>'leadership']);
  $reportedEvent=Event::create(['title'=>'Submitted Report Event','description'=>'Event description','venue'=>'Auditorium','starts_at'=>now()->subDays(2),'ends_at'=>now()->subDay(),'type'=>'leadership']);
  DB::table('settings')->insert([
   ['key'=>'staff.event-source.'.$event->id,'value'=>json_encode(['report_due_at'=>now()->addDays(4)->toIso8601String()])],
   ['key'=>'staff.event-source.'.$reportedEvent->id,'value'=>json_encode(['report_due_at'=>now()->addDay()->toIso8601String()])],
  ]);
  ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'SLU-REPORT','kind'=>'activity','details'=>['completion_report'=>true,'event_id'=>$reportedEvent->id]]);
  DB::table('audit_logs')->insert(['user_id'=>$assistant->id,'action'=>'request.needs_revision','subject'=>$proposal->ticket,'details'=>'Fixture feedback','created_at'=>now()]);

  $this->actingAs($head)->get('/workspace/dashboard')->assertOk()->assertSeeText('Action Queue')
   ->assertSeeText('Leadership Summit Sample')->assertSeeText('Upcoming Leadership Summit')
   ->assertSeeText('Completion report due')->assertSeeText('returned a submission for revision')
   ->assertDontSeeText('Submitted Report Event')->assertDontSeeText('SFU Scoped Sample')
   ->assertDontSeeHtml('aria-label="View Closed Achievement Sample"');
  $this->get('/workspace/dashboard?queue=all')->assertOk()->assertSeeHtml('aria-label="View Closed Achievement Sample"');
  $this->get('/workspace/dashboard?review='.$proposal->id)->assertOk()->assertSeeHtml('id="transaction-review"');
  $this->get('/workspace/events')->assertOk()->assertSeeHtml('id="event-'.$event->id.'-entry"');
 }

 public function test_sfu_settings_save_defaults_and_password_without_overwriting_notification_preferences():void {
  $head=$this->actor('sfu_head');$url='/workspace/settings';
  $this->actingAs($head)->get($url)->assertOk()->assertSeeText('System Preferences')
   ->assertSeeText('Report returned by Assistant')->assertSeeText('Data Backup & Security');
  $this->from($url)->post($url,['save'=>'changes','theme'=>'Light','date_format'=>'mm/dd/yyyy','time_format'=>'12',
   'in_app'=>1,'email'=>0,'two_factor'=>1,'alerts'=>['feedback'=>1,'report_returned'=>0,'report_acknowledged'=>1],
  ])->assertRedirect($url)->assertSessionHasNoErrors();
  $this->post($url,['save'=>'defaults','theme'=>'Dark','date_format'=>'dd/mm/yyyy','time_format'=>'24','in_app'=>0,'two_factor'=>0])
   ->assertRedirect($url)->assertSessionHasNoErrors();
  $head->refresh();
  $this->assertSame('Dark',$head->preferences['theme']);
  $this->assertTrue($head->preferences['in_app']);
  $this->assertTrue((bool)$head->two_factor);
  $this->assertSame(0,$head->preferences['alerts']['report_returned']);

  \App\Support\StaffAlerts::account($head,'Returned sample','/workspace/reports','report_returned');
  \App\Support\StaffAlerts::account($head,'Acknowledged sample','/workspace/reports','report_acknowledged');
  $this->assertSame('Acknowledged sample',$head->notifications()->sole()->data['title']);
  $this->post($url,['save'=>'password','current_password'=>'TestingPassword123!','password'=>'UpdatedPassword123!',
   'password_confirmation'=>'UpdatedPassword123!',
  ])->assertRedirect($url)->assertSessionHasNoErrors();
  $this->assertTrue(\Illuminate\Support\Facades\Hash::check('UpdatedPassword123!',$head->fresh()->password));
  $this->assertSame('Dark',$head->fresh()->preferences['theme']);
 }

 public function test_slu_settings_save_sections_and_track_verified_logins():void {
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-17 08:14'));
  $head=$this->actor('slu_head');
  $other=$this->actor('slu_head');
  DB::table('audit_logs')->insert([
   ['user_id'=>$head->id,'action'=>'account.login','subject'=>$head->email,'details'=>'Staff portal login','created_at'=>now()],
   ['user_id'=>$other->id,'action'=>'account.login','subject'=>'private-login@example.test','details'=>'Other login','created_at'=>now()],
  ]);
  $url='/workspace/settings';
  $this->actingAs($head)->get($url)->assertOk()
   ->assertSeeTextInOrder(['Account','Student Leadership Unit Head','System Preferences','Notifications',
    'New Organization Proposals','Event & Attendance Updates','Organization Report Submissions','Assistant Feedback & Revision Requests',
    'Data Backup & Security','Last login','09/17/2026','View Login Activity'])
   ->assertDontSeeText('private-login@example.test')->assertSeeHtml('id="login-activity"');

  $defaults=['theme'=>'Light','date_format'=>'mm/dd/yyyy','time_format'=>'12'];
  $this->from($url)->post($url,[...$defaults,'save'=>'changes','in_app'=>1,'email'=>0,'two_factor'=>1,
   'alerts'=>['proposals'=>0,'event_updates'=>1,'organization_reports'=>0,'feedback'=>1,'submissions'=>1]])
   ->assertRedirect($url)->assertSessionHasNoErrors();
  $this->from($url)->post($url,['save'=>'defaults','theme'=>'Dark','date_format'=>'dd/mm/yyyy','time_format'=>'24'])
   ->assertRedirect($url)->assertSessionHasNoErrors();
  $head->refresh();
  $this->assertSame('Dark',$head->preferences['theme']);
  $this->assertTrue((bool)$head->two_factor);
  $this->assertSame(0,$head->preferences['alerts']['proposals']);
  $this->assertTrue($head->preferences['in_app']);
  $this->get($url)->assertOk()->assertSeeText('17/09/2026 · 08:14');

  foreach(['proposals'=>'Proposal alert','event_updates'=>'Attendance alert','organization_reports'=>'Organization report alert','report_returned'=>'Assistant feedback alert'] as $category=>$title){
   \App\Support\StaffAlerts::account($head,$title,'/workspace',$category);
  }
  $this->assertEqualsCanonicalizing(['Attendance alert','Assistant feedback alert'],$head->notifications()->get()->map(fn($notice)=>$notice->data['title'])->all());
  $this->from($url)->post($url,['save'=>'password','current_password'=>'TestingPassword123!','password'=>'ChangedPassword123!','password_confirmation'=>'ChangedPassword123!'])
   ->assertRedirect($url)->assertSessionHasNoErrors();
  $this->assertTrue(\Illuminate\Support\Facades\Hash::check('ChangedPassword123!',$head->fresh()->password));
  $this->assertSame('Dark',$head->fresh()->preferences['theme']);
  $this->get('/workspace/backup')->assertDownload();

  $this->post('/logout');
  $this->withSession(['challenge'=>['user'=>$head->id,'remember'=>false,
   'hash'=>\Illuminate\Support\Facades\Hash::make('123456'),'expires'=>time()+60]])
   ->post('/two-factor',['code'=>'123456'])->assertRedirect('/staff');
  $this->assertSame(2,DB::table('audit_logs')->where('user_id',$head->id)->where('action','account.login')->count());
 }

 public function test_sfu_reports_filters_by_term_and_status_and_shows_snapshot_counts():void {
  $head=$this->actor('sfu_head');$other=$this->actor('sfu_head');
  $fields=['user_id'=>$head->id,'academic_year'=>'2026-2027','semester'=>'1st Semester','summary'=>str_repeat('Verified activity. ',3),'status'=>'pending'];
  $report=Report::create([...$fields,'title'=>'First Semester Sample','snapshot'=>['records'=>[['ticket'=>'TFD-1'],['ticket'=>'TFD-2']]]]);
  Report::create([...$fields,'title'=>'Other Semester Sample','semester'=>'2nd Semester','snapshot'=>[]]);
  Report::create([...$fields,'title'=>'Local Draft Sample','status'=>'draft','snapshot'=>[]]);
  Report::create([...$fields,'title'=>'Another Staff Report','user_id'=>$other->id,'snapshot'=>[]]);

  $url='/workspace/reports?'.http_build_query(['search'=>'Sample','semester'=>'1st Semester','status'=>'pending']);
  $this->actingAs($head)->get($url)->assertOk()->assertSeeText('First Semester Sample')
   ->assertSeeHtml('<td>2</td>')->assertSeeHtml('aria-label="Track First Semester Sample"')
   ->assertSeeHtml('<th scope="col">Application</th>')->assertDontSeeHtml('data-dialog="prepare-report"')
   ->assertSeeText('Showing 1 to 1 of 1 report')->assertSeeHtml('aria-label="Report pages"')
   ->assertDontSeeText('Other Semester Sample')->assertDontSeeText('Local Draft Sample')->assertDontSeeText('Another Staff Report');
  $this->get($url.'&report='.$report->id)->assertOk()->assertSeeHtml('id="report-review"');
 }

 public function test_sfu_validation_accepts_blank_remarks_and_compliance_requires_feedback():void {
  $head=$this->actor('sfu_head');$student=$this->actor('student');
  $application=ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'TFD-REMARKS','kind'=>'scholarship','details'=>[]]);
  $review='/staff/requests/'.$application->id;
  $dashboard='/workspace/dashboard?review='.$application->id;
  $queue='/workspace/applications?review='.$application->id;
  $this->actingAs($head)->get($dashboard)->assertOk()->assertSeeHtml('form="sfu-review-form"')
   ->assertSeeHtml('data-dialog="sfu-validate"');
  $this->get($queue)->assertOk()->assertSeeHtml('class="sfu-review-dialog"')->assertSeeText('Requirement Checklist')
   ->assertSeeHtml('form="sfu-review-form"')->assertSeeHtml('id="sfu-validate"');
  $this->actingAs($head)->from($dashboard)->post($review,['status'=>'needs_revision','review_note'=>''])
   ->assertRedirect($dashboard)->assertSessionHasErrors('review_note');
  $this->assertSame('pending',$application->fresh()->status);

  $this->post($review,['status'=>'unit_approved','discount_rate'=>30,'review_note'=>''])
   ->assertRedirect()->assertSessionHasNoErrors();
  $application->refresh();
  $this->assertSame('unit_approved',$application->status);
  $this->assertSame(30,$application->details['discount_rate']);
  $this->assertNull($application->review_note);
  $this->get($dashboard)->assertOk()->assertSeeText('Return for Compliance')->assertSeeText('Validate Application')
   ->assertSeeText('Complete applications are normally processed within one day.')
   ->assertSeeText('This application has passed SFU review.')
   ->assertSeeHtml('value="needs_revision"  disabled')->assertDontSeeHtml('data-dialog="sfu-validate"');
  $this->get($queue)->assertOk()->assertSeeHtml('aria-label="View TFD-REMARKS"')->assertSeeText('Return for Compliance')
   ->assertSeeText('Validate Application')->assertSeeText('Complete applications are normally processed within one day.')
   ->assertSeeHtml('value="needs_revision"  disabled')->assertDontSeeHtml('id="sfu-validate"');
 }

 public function test_sfu_scholar_draft_keeps_private_uploads_until_submission():void {
  $disk=Storage::fake('local');
  $head=$this->actor('sfu_head');$other=$this->actor('sfu_head');$student=$this->actor('student');
  $fields=['student_id'=>$student->student_id,'full_name'=>$student->name,'course'=>$student->course,'year_level'=>$student->year_level];
  $this->actingAs($head)->from('/workspace/applications')->post('/workspace/scholar',[
   ...$fields,'intent'=>'draft','application'=>UploadedFile::fake()->create('discount-form.pdf',20,'application/pdf'),
  ])->assertRedirect('/workspace/applications')->assertSessionHasNoErrors();
  $draft=\App\Support\StaffDrafts::listing($head,'scholar')[0];
  $path=$draft['files']['application']['path'];
  $disk->assertExists($path);
  $this->assertSame(0,ServiceRequest::count());
  $this->get('/workspace/applications')->assertOk()->assertSee('Continue Draft')->assertSee('discount-form.pdf')->assertDontSee($path);
  $this->actingAs($other)->get('/workspace/applications')->assertOk()->assertDontSee('discount-form.pdf');
  $this->post('/workspace/scholar',[...$fields,'draft_id'=>$draft['id'],'intent'=>'draft'])->assertNotFound();

  $this->actingAs($head)->from('/workspace/applications')->post('/workspace/scholar',[...$fields,'draft_id'=>$draft['id']])
   ->assertRedirect('/workspace/applications')->assertSessionHasErrors(['photo','registration']);
  $disk->assertExists($path);
  $pixel=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
  $this->post('/workspace/scholar',[
   ...$fields,'draft_id'=>$draft['id'],'photo'=>UploadedFile::fake()->createWithContent('photo.png',$pixel),
   'registration'=>UploadedFile::fake()->create('registration.pdf',20,'application/pdf'),
  ])->assertRedirect()->assertSessionHasNoErrors();
  $application=ServiceRequest::sole();
  $this->assertSame($student->id,$application->user_id);
  $this->assertSame($path,$application->files['application']['path']);
  $this->assertCount(3,$application->files);
  $this->assertSame([],\App\Support\StaffDrafts::listing($head,'scholar'));
  $disk->assertExists($path);
 }
 private function actor(string $role):User{return User::create(['name'=>'Test '.$role,'email'=>uniqid().'@example.test','username'=>uniqid('staff'),'password'=>'TestingPassword123!','role'=>$role,'account_type'=>$role==='student'?'student':'employee','student_id'=>$role==='student'?uniqid('ST-'):null,'course'=>$role==='student'?'BSIT':null,'year_level'=>'2','profile_complete'=>true,'email_verified_at'=>now(),'preferences'=>['email'=>false,'in_app'=>true]]);}
 public function test_attendance_photos_appear_under_their_respective_times():void {
  Storage::set('local',Storage::fake('staff-attendance-evidence'));
  $photo=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a17kAAAAASUVORK5CYII=');
  Storage::put('time-in.png',$photo);
  Storage::put('time-out.png',$photo);
  $student=$this->actor('student');
  $cvu=$this->actor('cvu_head');
  $slu=$this->actor('slu_head');
  $assignment=Assignment::create(['user_id'=>$student->id,'record_number'=>'ATTENDANCE-1','offense'=>'Case',
   'severity'=>'Minor','department'=>'Library','supervisor'=>'Unit Supervisor','required_minutes'=>120]);
  $event=Event::create(['title'=>'Leadership workshop','description'=>'Workshop for student leaders.',
   'venue'=>'Training Annex','starts_at'=>now()->subHours(2),'ends_at'=>now()]);

  foreach([
   ['/workspace/community',$cvu,$slu,['assignment_id'=>$assignment->id]],
   ['/workspace/events',$slu,$cvu,['event_id'=>$event->id]],
  ] as [$page,$head,$otherHead,$context]){
   $record=Attendance::create([...$context,'user_id'=>$student->id,'status'=>'pending',
    'time_in'=>now()->setTime(8,0),'time_out'=>now()->setTime(9,0),'photo_in'=>'time-in.png','photo_out'=>'time-out.png']);
   $evidence='/staff/attendance/'.$record->id.'/evidence/';
   $this->actingAs($head)->get($page)->assertOk()->assertDontSee('<th>Evidence</th>',false)
    ->assertDontSee('<th>Documentation</th>',false)
    ->assertSeeInOrder(['<th>Time In</th>','<th>Time Out</th>','8:00 AM</time>',$evidence.'in?preview=1',
     '9:00 AM</time>',$evidence.'out?preview=1'],false);
   $this->get($evidence.'in?preview=1')->assertOk()->assertHeader('Content-Type','image/png');
   $this->get($evidence.'out')->assertOk()->assertDownload('time-out.png');
   $this->actingAs($otherHead)->get($evidence.'in?preview=1')->assertForbidden();
  }
  Storage::delete(['time-in.png','time-out.png']);
 }
 public function test_slu_activity_calendar_navigates_months_and_opens_event_attendance():void {
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-01 08:00'));
  $head=$this->actor('slu_head');
  $eventFields=['description'=>'University activity','venue'=>'Mini-Gym','type'=>'leadership','capacity'=>20,'status'=>'published'];
  $first=Event::create([...$eventFields,'title'=>'Clean Drive','starts_at'=>'2026-10-05 08:00','ends_at'=>'2026-10-05 09:00']);
  $second=Event::create([...$eventFields,'title'=>'Leadership Workshop','starts_at'=>'2026-10-05 10:00','ends_at'=>'2026-10-05 11:00']);
  $later=Event::create([...$eventFields,'title'=>'Next Month Event','starts_at'=>'2026-11-15 10:00','ends_at'=>'2026-11-15 11:00']);
  $student=$this->actor('student');
  $record=Attendance::create(['user_id'=>$student->id,'event_id'=>$first->id,'status'=>'pending',
   'time_in'=>'2026-10-05 08:00','time_out'=>'2026-10-05 09:00','reflection'=>'Completed this university activity.']);
  $url='/workspace/events?month=2026-10';
  $this->actingAs($head)->get($url)->assertOk()
   ->assertSeeText('Activity Calendar')->assertSeeText('Event List')->assertSeeText('Registration Open')
   ->assertSeeHtml('aria-label="October 2026 activity calendar"')->assertSeeHtml('datetime="2026-09-27"')
   ->assertSeeHtml('href="#calendar-day-2026-10-05"')->assertSeeHtml('aria-label="October 5, 2026 · 2 events"')
   ->assertSeeHtml('id="event-'.$first->id.'-entry"')->assertSeeHtml('id="event-'.$second->id.'-entry"')
   ->assertDontSeeHtml('id="event-'.$later->id.'-entry"')
   ->assertSeeHtml('data-dialog="event-'.$first->id.'-attendance"')->assertSeeHtml('data-dialog="event-'.$first->id.'"');
  $this->get('/workspace/events?month=2026-11')->assertOk()
   ->assertSeeHtml('id="event-'.$later->id.'-entry"')->assertDontSeeHtml('id="event-'.$first->id.'-entry"');
  $this->get($url.'&list=all')->assertOk()->assertSeeHtml('id="event-'.$later->id.'-entry"');
  $this->from($url)->post('/staff/attendance/'.$record->id,['status'=>'approved','review_note'=>'Verified attendance.',
   '_dialog'=>'event-'.$first->id.'-attendance'])->assertRedirect($url)->assertSessionHas('attendance_dialog','event-'.$first->id.'-attendance');
  $this->assertSame('approved',$record->fresh()->status);
 }

 public function test_create_event_reopens_with_field_errors_and_saves_valid_details():void
 {
  $head=$this->actor('slu_head');
  $page='/workspace/events';
  $this->actingAs($head)->from($page)->post('/staff/events',[])
   ->assertRedirect($page)->assertSessionHasErrorsIn('createEvent',['title','description','venue','starts_at','ends_at','capacity','points']);
  $this->get($page)->assertOk()->assertSee('open data-auto-open',false)
   ->assertSee('Enter an event title.')->assertSee('Select a venue or enter a venue name.');

  $start=now()->addDay();
  $fields=[
   'title'=>'Leadership workshop','description'=>'Practice running a student workshop.','venue'=>'Training Annex',
   'starts_at'=>$start->format('Y-m-d\TH:i'),'ends_at'=>$start->copy()->subHour()->format('Y-m-d\TH:i'),
   'capacity'=>0,'points'=>1001,'type'=>'leadership',
  ];
  $this->from($page)->post('/staff/events',$fields)
   ->assertRedirect($page)->assertSessionHasErrorsIn('createEvent',['ends_at','capacity','points']);
  $this->get($page)->assertOk()->assertSee('The end date and time must be after the start date and time.')
   ->assertSee('value="Leadership workshop"',false)->assertSee('value="Training Annex"',false);
  $this->assertDatabaseCount('events',0);
  $this->from($page)->post('/staff/events',[...$fields,'starts_at'=>['invalid']])
   ->assertRedirect($page)->assertSessionHasErrorsIn('createEvent',['starts_at']);
  $this->from($page)->post('/staff/events',[
   ...$fields,'ends_at'=>$start->copy()->addHour()->format('Y-m-d\TH:i'),'capacity'=>100,'points'=>10,
  ])->assertRedirect($page)->assertSessionHasNoErrors();
  $this->assertDatabaseHas('events',['title'=>'Leadership workshop','capacity'=>100,'points'=>10]);
 }

 public function test_new_event_venues_remain_available_after_an_event_moves():void {
  $head=$this->actor('slu_head');
  $fields=[
   'title'=>'Leadership workshop','description'=>'Practice running a student workshop.',
   'starts_at'=>now()->addDays(2)->format('Y-m-d H:i'),
   'ends_at'=>now()->addDays(2)->addHour()->format('Y-m-d H:i'),
   'capacity'=>20,'points'=>0,'type'=>'leadership','status'=>'published',
  ];
  $this->actingAs($head)->post('/staff/events',[...$fields,'venue'=>'Training Annex'])
   ->assertRedirect()->assertSessionHasNoErrors();
  $event=Event::sole();
  $this->get('/workspace/events')->assertOk()->assertSee('<option value="Training Annex">',false);

  $this->post('/workspace/event/'.$event->id,[...$fields,'venue'=>'Media Studio'])
   ->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame('Media Studio',$event->fresh()->venue);
  $this->get('/workspace/events')->assertOk()
   ->assertSee('<option value="Training Annex">',false)->assertSee('<option value="Media Studio">',false);

  $this->post('/workspace/event/'.$event->id,[...$fields,'venue'=>'Media Studio'])
   ->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame(['Media Studio','Training Annex'],Event::venueChoices(collect())->values()->all());
 }

 public function test_slu_can_create_past_and_future_events_with_separate_date_rules():void
 {
  $head=$this->actor('slu_head');
  $page='/workspace/events';
  $common=['description'=>'Event details for the activity calendar.','venue'=>'Campus','capacity'=>100,'points'=>0,'type'=>'leadership'];
  $past=[...$common,'title'=>'Past workshop','event_period'=>'past','starts_at'=>now()->subDays(2)->format('Y-m-d\TH:i'),'ends_at'=>now()->subDay()->format('Y-m-d\TH:i')];
  $future=[...$common,'title'=>'Future workshop','event_period'=>'future','starts_at'=>now()->addDay()->format('Y-m-d\TH:i'),'ends_at'=>now()->addDays(2)->format('Y-m-d\TH:i')];

  $this->actingAs($head)->get($page)->assertOk()->assertSee('Create Past Event')->assertSee('Create Future Event');
  $this->from($page)->post('/staff/events',[...$past,'ends_at'=>$future['ends_at']])
   ->assertRedirect($page)->assertSessionHasErrorsIn('createPastEvent','ends_at');
  $response=$this->get($page)->assertOk();
  $this->assertMatchesRegularExpression('/<dialog id="create-past-event"[^>]*open data-auto-open/',$response->getContent());
  $this->post('/staff/events',[...$future,'starts_at'=>$past['starts_at']])
   ->assertRedirect($page)->assertSessionHasErrorsIn('createEvent','starts_at');
  $this->assertDatabaseCount('events',0);
  $this->post('/staff/events',$past)->assertRedirect($page)->assertSessionHasNoErrors();
  $this->post('/staff/events',$future)->assertRedirect($page)->assertSessionHasNoErrors();
  $this->assertDatabaseHas('events',['title'=>'Past workshop','status'=>'completed']);
  $this->assertDatabaseHas('events',['title'=>'Future workshop','status'=>'published']);
 }
 public function test_removing_a_venue_hides_it_without_changing_existing_events():void {
  $fields=[
   'title'=>'Demo workshop','description'=>'Practice running a student workshop.','venue'=>'Training Annex',
   'starts_at'=>now()->addDays(2)->format('Y-m-d H:i'),
   'ends_at'=>now()->addDays(2)->addHour()->format('Y-m-d H:i'),
   'capacity'=>20,'points'=>0,'type'=>'leadership','status'=>'published',
  ];
  $event=Event::create($fields);
  $venue=['venue'=>$event->venue];
  $head=$this->actor('slu_head');
  $this->actingAs($this->actor('admin'))->deleteJson('/workspace/event-venues',$venue)->assertForbidden();
  $this->actingAs($head)->get('/workspace/events')->assertOk()
   ->assertSee('Delete selected venue')->assertSee('Delete venue?')->assertSee('>Cancel</button>',false)
   ->assertSee('>Delete</button>',false)->assertSee('<option value="Training Annex">',false);

  $this->deleteJson('/workspace/event-venues',$venue)->assertNoContent();
  $this->get('/workspace/events')->assertOk()->assertDontSee('<option value="Training Annex">',false);
  $this->assertSame('Training Annex',$event->fresh()->venue);
  $this->post('/workspace/event/'.$event->id,[...$fields,'title'=>'Renamed workshop','custom_venue'=>1])
   ->assertRedirect()->assertSessionHasNoErrors();
  $this->get('/workspace/events')->assertOk()->assertDontSee('<option value="Training Annex">',false);

  $this->post('/staff/events',[...$fields,'title'=>'New workshop','custom_venue'=>1])
   ->assertRedirect()->assertSessionHasNoErrors();
  $this->get('/workspace/events')->assertOk()->assertSee('<option value="Training Annex">',false);
 }
 public function test_cvu_head_can_check_pending_service_and_send_assignment_feedback():void
 {
  $head=$this->actor('cvu_head');
  $student=$this->actor('student');
  $other=$this->actor('student');
  $record=fn($user,$reference,$status)=>$user->assignments()->create([
   'record_number'=>$reference,'offense'=>'Campus conduct','severity'=>'Minor',
   'department'=>'Library','supervisor'=>'Unit Supervisor','required_minutes'=>120,'status'=>$status,
  ]);
  $active=$record($student,'CV-PENDING','active');
  $record($student,'CV-FINISHED','completed');
  $record($other,'CV-OTHER','completed');
  $active->attendances()->create(['user_id'=>$student->id,'minutes'=>60,'status'=>'approved']);
  $active->attendances()->create(['user_id'=>$student->id,'minutes'=>60,'status'=>'pending']);
  $active->journals()->create(['module'=>1,'answer'=>'Service reflection submitted.','status'=>'approved']);
  $page='/workspace/community?check_student='.$student->id;
  $feedbackUrl='/workspace/assignment/'.$active->id.'/feedback';
  $message='Please complete your remaining duty hour and submit the next journal module.';
  $this->actingAs($this->actor('slu_head'))->post($feedbackUrl,['feedback'=>$message])->assertForbidden();
  $this->actingAs($head)->get($page)->assertOk()->assertSee('1 active assignment.')
   ->assertSee('1.00 hours remaining')->assertSee('1 session awaiting review')->assertSee('1/4 journal modules approved');
  $this->get('/workspace/community?check_student='.$other->id)->assertOk()->assertSee('No pending community service.');
  $this->get('/workspace/community?check_student='.$head->id)->assertNotFound();
  $this->from($page)->post($feedbackUrl,['feedback'=>' '])->assertRedirect($page)->assertSessionHasErrors('feedback');
  $this->post($feedbackUrl,['feedback'=>$message])->assertRedirect($page)->assertSessionHasNoErrors();
  $this->post($feedbackUrl,['feedback'=>'Bring your verification slip to the office.'])->assertRedirect($page)->assertSessionHasNoErrors();
  $this->assertSame([$head->id,$head->id],$active->feedbackEntries()->pluck('author_id')->all());
  $this->assertSame(2,$student->notifications()->count());
  $this->assertStringEndsWith('/portal/values/community-service?assignment='.$active->id,$student->notifications()->first()->data['url']);
  $this->assertSame(0,$other->notifications()->count());
  $this->get($page)->assertOk()->assertSee('Feedback to Student')->assertSeeInOrder(['Bring your verification slip',$message]);
 }

 public function test_cvu_can_assign_multiple_violations_and_verify_each_record_separately():void
 {
  $head=$this->actor('cvu_head');
  $student=$this->actor('student');
  $page='/workspace/community';
  $fields=['user_id'=>$student->id,'severity'=>'Minor','department'=>'Library','supervisor'=>'Unit Supervisor',
   'required_hours'=>2,'start_date'=>today()->toDateString(),'target_date'=>today()->addWeek()->toDateString(),
   'duty_start'=>'08:00','duty_end'=>'12:00'];
  $this->actingAs($head)->from($page)->post('/workspace/assignments',$fields+['offense'=>'Campus conduct'])
   ->assertRedirect($page)->assertSessionHasNoErrors();
  $this->post('/workspace/assignments',[...$fields,'offense'=>'Laboratory safety','severity'=>'Major'])
   ->assertRedirect($page)->assertSessionHasNoErrors();
  [$first,$second]=$student->assignments()->orderBy('id')->get()->all();
  $session=$first->attendances()->create([
   'user_id'=>$student->id,'time_in'=>now()->subHours(2),'time_out'=>now(),'minutes'=>120,'status'=>'pending',
  ]);
  $this->get($page)->assertOk()->assertSee('Add Violation')->assertSee('Campus conduct')->assertSee('Laboratory safety');
  $this->post('/staff/attendance/'.$session->id,['status'=>'approved','review_note'=>'Verified time in and time out.'])
   ->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame(120,$first->approvedMinutes());
  $this->assertSame(0,$second->approvedMinutes());
  $this->assertStringContainsString('?assignment='.$first->id,$student->notifications()->sole()->data['url']);
  foreach(range(1,4) as $module)$first->journals()->create(['module'=>$module,'answer'=>'Verified service reflection.','status'=>'approved']);
  $this->post('/staff/assignments/'.$first->id.'/complete')->assertRedirect()->assertSessionHas('success','Record completed. The student still has active service assignments.');
  $this->assertSame('completed',$first->fresh()->status);
  $this->assertSame('active',$second->fresh()->status);
 }

 public function test_community_service_hours_are_validated_by_violation_severity():void
 {
  $page='/workspace/community';
  $policyUrl=$page.'/hour-limits';
  $head=$this->actor('cvu_head');
  $student=$this->actor('student');
  $majorStudent=$this->actor('student');
  $limits=['Minor'=>['min'=>'4','max'=>'8'],'Major'=>['min'=>'12','max'=>'20']];
  $assignment=[
   'user_id'=>$student->id,'offense'=>'Student conduct','severity'=>'Minor','department'=>'Library',
   'supervisor'=>'Unit Supervisor','required_hours'=>12,'start_date'=>today()->toDateString(),
   'target_date'=>today()->addWeek()->toDateString(),'duty_start'=>'08:00','duty_end'=>'12:00',
  ];
  $this->actingAs($this->actor('slu_head'))->post($policyUrl,$limits)->assertForbidden();
  $this->actingAs($head)->get($page)->assertOk()->assertSee('The general 1–500 hour limit applies');
  $this->from($page)->post('/workspace/assignments',$assignment)->assertRedirect($page)->assertSessionHasNoErrors();
  $minor=Assignment::sole();
  $this->post($policyUrl,[...$limits,'Minor'=>['min'=>'8','max'=>'4']])
   ->assertRedirect($page)->assertSessionHasErrors('Minor.max');
  $this->post($policyUrl,$limits)->assertRedirect($page)->assertSessionHasNoErrors();
  $this->get($page)->assertOk()->assertSee('data-max-hours="8"',false)->assertSee('data-min-hours="12"',false);

  $major=[...$assignment,'user_id'=>$majorStudent->id,'severity'=>'Major'];
  $this->post('/workspace/assignments',[...$major,'required_hours'=>8])
   ->assertRedirect($page)->assertSessionHasErrors(['required_hours'=>'Major violations require 12 to 20 community service hours.']);
  $this->post('/staff/assignments',[...$major,'severity'=>'Minor','required_hours'=>9])
   ->assertRedirect($page)->assertSessionHasErrors(['required_hours'=>'Minor violations require 4 to 8 community service hours.']);
  $this->post('/workspace/assignments',$major)->assertRedirect($page)->assertSessionHasNoErrors();
  $this->assertSame(720,$majorStudent->assignment()->firstOrFail()->required_minutes);

  $edit='/workspace/assignment/'.$minor->id;
  $this->post($edit,[...$assignment,'department'=>'Records'])->assertRedirect($page)->assertSessionHasNoErrors();
  $this->assertSame(720,$minor->fresh()->required_minutes);
  $this->post($edit,[...$assignment,'required_hours'=>9])->assertRedirect($page)->assertSessionHasErrors('required_hours');
  $this->post($edit,[...$assignment,'required_hours'=>8])->assertRedirect($page)->assertSessionHasNoErrors();
  $this->assertSame(480,$minor->fresh()->required_minutes);
 }

 public function test_populated_screens_and_management_forms():void{
  $student=$this->actor('student');$heads=[];foreach(['admin','assistant','sfu_head','cvu_head','slu_head'] as $role)$heads[$role]=$this->actor($role);
  $this->actingAs($heads['slu_head'])->post('/workspace/organizations',['name'=>'Test Organization','description'=>'Verified university organization','president_id'=>$student->id,'acronym'=>'TEST','college'=>'CITE','type'=>'Academic','adviser'=>'Verified Adviser'])->assertRedirect()->assertSessionHasNoErrors();$org=Organization::first();$this->assertSame($org->id,$student->fresh()->organization_id);
  $this->post('/staff/events',['title'=>'Leadership Seminar','description'=>'Verified event description','venue'=>'University Hall','starts_at'=>now()->addDays(2)->format('Y-m-d H:i'),'ends_at'=>now()->addDays(2)->addHours(2)->format('Y-m-d H:i'),'capacity'=>100,'points'=>50,'type'=>'leadership'])->assertRedirect()->assertSessionHasNoErrors();
  $this->actingAs($heads['cvu_head'])->post('/workspace/seminars',['title'=>'Character Seminar','description'=>'Character development seminar','venue'=>'University Hall','starts_at'=>now()->addDays(3)->format('Y-m-d H:i'),'ends_at'=>now()->addDays(3)->addHours(2)->format('Y-m-d H:i'),'capacity'=>100])->assertRedirect()->assertSessionHasNoErrors();
  $this->post('/workspace/assignments',['user_id'=>$student->id,'offense'=>'Complaint CMP-TEST','severity'=>'Minor','department'=>'Library','supervisor'=>'Unit Supervisor','required_hours'=>12,'start_date'=>today()->format('Y-m-d'),'target_date'=>today()->addDays(7)->format('Y-m-d'),'duty_start'=>'08:00','duty_end'=>'12:00'])->assertRedirect()->assertSessionHasNoErrors();
  foreach(array_keys(ServiceRequest::kinds()) as $kind)ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'TEST-'.$kind,'kind'=>$kind,'details'=>['title'=>'Test '.$kind,'description'=>'Source details','subject'=>'NSTP','academic_year'=>'2026-2027','semester'=>'1st Semester','starts_at'=>now()->addDays(3)->toIso8601String()]]);
  foreach($heads as $role=>$actor){Report::create(['user_id'=>$actor->id,'title'=>'Unit Activity Report','academic_year'=>'2026-2027','semester'=>'1st Semester','summary'=>str_repeat('Verified report snapshot. ',3),'snapshot'=>['records'=>[['ticket'=>'TEST','name'=>$student->name,'student_id'=>$student->student_id,'kind'=>'complaint','status'=>'pending']],'version'=>1]]);foreach(StaffPortalController::menus($role) as $page=>$label)$this->actingAs($actor)->get('/workspace/'.$page)->assertOk();}
 }
 public function test_slu_leaderboards_show_point_breakdowns_filters_and_review_links():void {
  $head=$this->actor('slu_head');
  $researcher=$this->actor('student');$volunteer=$this->actor('student');
  $research=Organization::create(['name'=>'Research Circle']);$service=Organization::create(['name'=>'Service Club']);
  $researcher->update(['organization_id'=>$research->id]);$volunteer->update(['organization_id'=>$service->id]);
  foreach([[$research,'CITE'],[$service,'CBA']] as [$organization,$department]){
   DB::table('settings')->insert(['key'=>'staff.organization.'.$organization->id,'value'=>json_encode(['college'=>$department])]);
  }
  $event=Event::create(['title'=>'Tech Workshop','description'=>'Student activity','venue'=>'Lab','type'=>'leadership',
   'starts_at'=>'2026-09-01 09:00','ends_at'=>'2026-09-01 11:00']);
  $attendance=Attendance::create(['user_id'=>$researcher->id,'event_id'=>$event->id,'status'=>'approved']);
  $award=ServiceRequest::create(['user_id'=>$researcher->id,'kind'=>'achievement','ticket'=>'RANK-AWARD','status'=>'approved',
   'details'=>['title'=>'Research Award','academic_year'=>'2026-2027','semester'=>'1st Semester']]);
  $penalty=ServiceRequest::create(['user_id'=>$researcher->id,'kind'=>'achievement','ticket'=>'RANK-PENALTY','status'=>'approved',
   'details'=>['title'=>'Late Report Penalty','adjustment'=>true]]);
  $pending=ServiceRequest::create(['user_id'=>$researcher->id,'kind'=>'achievement','ticket'=>'RANK-PENDING','status'=>'unit_approved',
   'details'=>['title'=>'Point Adjustment','adjustment'=>true,'description'=>'Advisor correction','awarded_points'=>50]]);
  foreach([
   [$researcher,'attendance:'.$attendance->id,120,'Workshop attendance','2026-09-01 11:00'],
   [$volunteer,'legacy-award',200,'Recorded service credit','2026-09-01 12:00'],
   [$researcher,'achievement:'.$award->id,300,'Research Award','2026-09-02 09:00'],
   [$researcher,'achievement:'.$penalty->id,-20,'Late Report Penalty','2026-09-02 10:00'],
  ] as [$student,$source,$amount,$reason,$date]){
   Point::create(['user_id'=>$student->id,'source'=>$source,'amount'=>$amount,'reason'=>$reason,'created_at'=>$date]);
  }
  $url='/workspace/points';
  $this->actingAs($head)->get($url)->assertOk()
   ->assertSeeTextInOrder(['Current Rankings','Participation','Achievement','Adjustment','Total Points','Research Circle','120','300','-20','400'])
   ->assertSeeHtml('aria-label="Up 1 place"')->assertSeeHtml('aria-label="Down 1 place"')
   ->assertSeeText('Point Ledger')->assertSeeText('Pending Adjustments')->assertSeeText('Advisor correction')->assertSeeText('+50')
   ->assertSee('/workspace/request/'.$award->id)->assertSee('/workspace/request/'.$penalty->id)
   ->assertDontSeeText('Workshop attendance');
  $this->get($url.'?ledger=all')->assertOk()->assertSeeText('Workshop attendance')->assertSee('#event-'.$event->id.'-attendance');
  $this->get($url.'?department=CITE&term='.urlencode('1st Semester 2026-2027'))->assertOk()
   ->assertSeeTextInOrder(['Research Circle','0','300','0','300'])->assertSeeText('Research Award')
   ->assertViewHas('leadershipPoints',fn($data)=>$data['rankings']->pluck('organization.name')->all()===['Research Circle'])
   ->assertDontSeeText('Late Report Penalty');
  $this->get($url.'?review='.$pending->id)->assertOk()->assertSeeHtml('id="transaction-review"')->assertSeeText('RANK-PENDING');
 }

 public function test_scoring_rules_dialog_uses_saved_values_and_audit_history():void {
  $head=$this->actor('slu_head');
  $student=$this->actor('student');
  ServiceRequest::create(['user_id'=>$student->id,'kind'=>'achievement','ticket'=>'RULES-AWARD','details'=>['title'=>'New award']]);
  ServiceRequest::create(['user_id'=>$student->id,'kind'=>'achievement','ticket'=>'RULES-ADJ','status'=>'unit_approved',
   'details'=>['title'=>'Point Adjustment','adjustment'=>true,'awarded_points'=>25]]);
  $url='/workspace/points';
  $this->actingAs($head)->get($url)->assertOk()
   ->assertSeeHtml('id="scoring-rules"')->assertSeeText('Leadership Levels')->assertSeeTextInOrder(['Leader','Amasser','Hoarder','Stockpiler'])
   ->assertSeeTextInOrder(['Calculation Valid','Yes','1 Pending Award Excluded','1 Adjustment Excluded'])
   ->assertSee('/portal/leadership/leaderboards')->assertSeeText('No scoring rule changes recorded.')
   ->assertSeeText('Per event');

  $values=['event'=>200,'achievement'=>500,'report'=>50,'participant'=>10,'penalty'=>100,'_dialog'=>'scoring-rules-edit'];
  $this->actingAs($this->actor('assistant'))->post('/workspace/points-rules',$values)->assertForbidden();
  $this->actingAs($head)->from($url)->post('/workspace/points-rules',[...$values,'event'=>1001])
   ->assertRedirect($url)->assertSessionHasErrors('event');
  $this->get($url)->assertOk()->assertSeeHtml('aria-invalid="true"')->assertSeeHtml('open data-auto-open');
  $this->from($url)->post('/workspace/points-rules',$values)->assertRedirect($url)
   ->assertSessionHasNoErrors()->assertSessionHas('scoring_rules_saved',true);
  $this->assertDatabaseHas('audit_logs',['action'=>'points.rules','user_id'=>$head->id]);
  $this->get($url)->assertOk()->assertSeeTextInOrder(['Completed Approved Event','+200','Approved Achievement','+500','On-Time Completion Report','+50','Verified Participant','+10','Late Report Deduction','−100'])
   ->assertSeeTextInOrder(['Rule History','Recent saved scoring rule changes.',$head->name,'+200'])
   ->assertDontSeeText('No scoring rule changes recorded.');
 }

 public function test_negative_adjustment_applies_only_after_admin_approval():void{$student=$this->actor('student');$org=Organization::create(['name'=>'Test Organization']);$student->update(['organization_id'=>$org->id]);$head=$this->actor('slu_head');$assistant=$this->actor('assistant');$admin=$this->actor('admin');$this->actingAs($head)->post('/workspace/adjustments',['user_id'=>$student->id,'amount'=>-100,'reason'=>'Late organization completion report'])->assertRedirect()->assertSessionHasNoErrors();$q=ServiceRequest::first();$this->assertSame(0,Point::count());$this->actingAs($admin)->post('/staff/requests/'.$q->id,['status'=>'approved','review_note'=>'Reviewed valid evidence'])->assertSessionHasErrors('workflow');$this->actingAs($assistant)->post('/staff/requests/'.$q->id,['status'=>'awaiting_admin','review_note'=>'Verified adjustment for final review'])->assertRedirect()->assertSessionHasNoErrors();$this->actingAs($admin)->post('/staff/requests/'.$q->id,['status'=>'approved','review_note'=>'Approved documented adjustment'])->assertRedirect()->assertSessionHasNoErrors();$this->assertSame(-100,(int)Point::first()->amount);$this->post('/staff/requests/'.$q->id,['status'=>'approved','review_note'=>'Repeated approval attempt'])->assertSessionHasErrors('workflow');$this->assertSame(1,Point::count());}
 public function test_configured_clearance_capacity_is_shared_with_user_submission():void{$head=$this->actor('cvu_head');$date=today()->addDays(3)->format('Y-m-d');$this->actingAs($head)->post('/workspace/slots',['date'=>$date,'start'=>'10:30','end'=>'11:00','capacity'=>1,'open'=>1])->assertRedirect()->assertSessionHasNoErrors();$a=$this->actor('student');$b=$this->actor('student');$input=['declaration'=>1,'details'=>['appointment_date'=>$date,'schedule'=>'10:30','purpose'=>'University clearance']];$this->actingAs($a)->post('/apply/clearance',$input)->assertRedirect()->assertSessionHasNoErrors();$this->actingAs($b)->post('/apply/clearance',$input)->assertSessionHasErrors('workflow');$this->assertSame(1,ServiceRequest::count());}
 public function test_slu_reports_show_snapshot_coverage_and_filter_recorded_periods():void {
  $head=$this->actor('slu_head');
  $other=$this->actor('slu_head');
  $fields=['user_id'=>$head->id,'academic_year'=>'2026-2027','semester'=>'1st Semester','summary'=>str_repeat('Recorded organization activity. ',3)];
  $monthly=Report::create([...$fields,'title'=>'Organization Activity Summary','status'=>'draft','snapshot'=>[
   'date_from'=>'2026-09-01','date_to'=>'2026-09-30','records'=>[['ticket'=>'SLU-01'],['ticket'=>'SLU-02']],
   'activity'=>[['service'=>'Event approvals','completed'=>2,'pending'=>0]],
   'files'=>[['name'=>'Activity evidence.pdf','path'=>'staff-reports/evidence.pdf']],
  ]]);
  $semester=Report::create([...$fields,'title'=>'Organization Compliance Report','status'=>'pending',
   'snapshot'=>[['ticket'=>'SLU-03']]]);
  $returned=Report::create([...$fields,'title'=>'Achievement Audit','status'=>'needs_revision',
   'snapshot'=>['date_from'=>'2026-10-01','date_to'=>'2026-10-31','records'=>[]]]);
  Report::create([...$fields,'user_id'=>$other->id,'title'=>'Private Other Unit Report','status'=>'draft','snapshot'=>[]]);
  $url='/workspace/reports';
  $this->actingAs($head)->get($url)->assertOk()
   ->assertSeeTextInOrder(['Prepare Unit Report','Draft Reports','Returned Reports','Unit Reports','Source Coverage'])
   ->assertSeeTextInOrder(['Organization Activity Summary','September 2026','2 source records','1 activity summary','Draft','Edit'])
   ->assertSeeTextInOrder(['Organization Compliance Report','1st Semester','2026-2027','1 source record','Under Assistant Review','Track'])
   ->assertSeeTextInOrder(['Achievement Audit','October 2026','0 source records','Returned for Revision','Revise'])
   ->assertDontSeeText('Private Other Unit Report')->assertSeeHtml('id="prepare-report"');
  $this->get($url.'?period=month%3A2026-09&status=draft')->assertOk()
   ->assertSeeText('Organization Activity Summary')->assertDontSeeText('Organization Compliance Report')->assertDontSeeText('Achievement Audit');
  $this->get($url.'?period='.urlencode('term:1st Semester:2026-2027').'&search=RPT-SLU-'.str_pad($semester->id,4,'0',STR_PAD_LEFT))
   ->assertOk()->assertSeeText('Organization Compliance Report')->assertDontSeeText('Organization Activity Summary');
  $this->get($url.'?status=needs_revision&report='.$returned->id)->assertOk()
   ->assertSeeHtml('class="slu-report-dialog"')->assertSeeText('Revision required')
   ->assertSeeText('Submit to Assistant')->assertSeeText('Save Draft');
  $reviewUrl=$url.'?status=draft&report='.$monthly->id;
  $this->get($reviewUrl)->assertOk()
   ->assertSeeTextInOrder(['Unit Report Review','Organization Activity Summary','September 2026','Prepared by',
    'Report Contents','Event approvals','Activity evidence.pdf','Source Validation','Duplicate references','None found',
    'Approval Route','Unit Head','Draft','Assistant','Pending','Admin','Pending','Unit Head Note'])
   ->assertSee('/workspace/report/'.$monthly->id.'/file/0');
  $update='/workspace/report/'.$monthly->id;
  $summary=str_repeat('Updated monthly report. ',3);
  $this->from($reviewUrl)->post($update,['summary'=>$summary,'status'=>'draft','unit_note'=>'Draft clarification.'])
   ->assertRedirect($reviewUrl)->assertSessionHasNoErrors();
  $this->assertSame('Draft clarification.',$monthly->fresh()->snapshot['unit_note']);
  $this->from($reviewUrl)->post($update,['summary'=>$summary,'status'=>'pending','unit_note'=>'Please review the revised source list.'])
   ->assertRedirect($reviewUrl)->assertSessionHasNoErrors();
  $this->assertSame('pending',$monthly->fresh()->status);
  $this->assertSame('Draft clarification.',collect($monthly->fresh()->snapshot['versions'])->last()['unit_note']);
  $this->get($reviewUrl)->assertOk()->assertSeeText('Under review')->assertSeeText('Please review the revised source list.')
   ->assertDontSeeText('Submit to Assistant');
  $this->actingAs($this->actor('assistant'))->get($update)->assertOk()->assertSeeText('Please review the revised source list.');
 }

 public function test_report_files_and_settings_backup_are_scoped():void{Storage::fake('local');$head=$this->actor('cvu_head');$wrong=$this->actor('sfu_head');$assistant=$this->actor('assistant');$this->actingAs($head)->post('/workspace/reports',['title'=>'Unit Activity Report','academic_year'=>'2026-2027','semester'=>'1st Semester','summary'=>str_repeat('Verified unit activity snapshot. ',3),'date_from'=>'2026-09-01','date_to'=>'2026-09-30','attachments'=>[UploadedFile::fake()->create('report.pdf',20,'application/pdf')]])->assertRedirect()->assertSessionHasNoErrors();$report=Report::first();$this->actingAs($wrong)->get('/workspace/report/'.$report->id.'/file/0')->assertForbidden();$this->actingAs($assistant)->get('/workspace/report/'.$report->id.'/file/0')->assertOk();$this->actingAs($head)->post('/workspace/settings',['theme'=>'Dark','date_format'=>'dd/mm/yyyy','time_format'=>'24','in_app'=>1,'email'=>0,'alerts'=>['feedback'=>0]])->assertRedirect()->assertSessionHasNoErrors();$this->assertSame('Dark',$head->fresh()->preferences['theme']);$this->get('/workspace/backup')->assertOk()->assertDownload();}
 public function test_slu_content_library_filters_and_previews_current_publications():void {
  $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-25 09:00'));
  $head=$this->actor('slu_head');
  $details=['body'=>'University content for the student portal.','audience'=>'All Students'];
  $this->actingAs($head);
  foreach([
   ['Leadership Week','Announcement Banner','Student Dashboard','2026-09-24 09:00','2026-10-03 18:00',true],
   ['Campus Clean Drive','Featured Event','Event & Activities','2026-09-20 09:00',null,true],
   ['Proposal Deadline Reminder','Notification','Student Dashboard','2026-09-28 09:00',null,true],
   ['Organization Spotlight','Organization Feature','Student Dashboard',null,null,false],
   ['Old Evidence Guide','Helping Content','Achievement Form','2026-09-01 09:00','2026-09-10 18:00',true],
  ] as [$title,$type,$location,$start,$end,$publish]){
   $this->post('/workspace/content',[...$details,'title'=>$title,'content_type'=>$type,'portal_location'=>$location,
    'published_at'=>$start,'ends_at'=>$end,'publish'=>$publish,'cta_label'=>'View Events','cta_destination'=>'events'])
    ->assertRedirect()->assertSessionHasNoErrors();
  }
  $url='/workspace/content';
  $this->get($url)->assertOk()->assertSeeText('Content Library')->assertSeeText('Student Portal Preview')
   ->assertSeeTextInOrder(['Leadership Week','Announcement Banner','Student Dashboard','Sep 24, 2026','Oct 3, 2026','Published'])
   ->assertSeeTextInOrder(['Proposal Deadline Reminder','Scheduled'])
   ->assertSeeTextInOrder(['Organization Spotlight','Draft'])
   ->assertSeeTextInOrder(['Old Evidence Guide','Expired'])
   ->assertSeeHtml('data-content-prev')->assertSeeHtml('data-content-next')
   ->assertViewHas('contentLibrary',fn($data)=>$data['preview']->pluck('item.title')->all()===['Leadership Week','Campus Clean Drive']);
  $this->get($url.'?'.http_build_query(['search'=>'deadline','type'=>'Notification','location'=>'Student Dashboard','status'=>'scheduled']))
   ->assertOk()->assertViewHas('contentLibrary',fn($data)=>collect($data['library']->items())->pluck('item.title')->all()===['Proposal Deadline Reminder']);
  $this->get($url.'?search=unknown')->assertOk()->assertSeeText('No matching portal content.')
   ->assertSeeText('Student Portal Preview');
 }

 public function test_content_schedule_audience_and_images_are_private_until_published():void{
 Storage::fake('local');$head=$this->actor('slu_head');$data=['title'=>'Leadership Week','body'=>'Join the university leadership seminars.','content_type'=>'Announcement Banner','portal_location'=>'Student Dashboard','audience'=>'All Students','cta_label'=>'View Events','cta_destination'=>'events','image'=>UploadedFile::fake()->createWithContent('banner.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='))];
 $this->actingAs($head)->post('/workspace/content',$data)->assertRedirect()->assertSessionHasNoErrors();$id=DB::table('announcements')->value('id');$this->get('/content-image/'.$id)->assertOk();
 $this->post('/logout');$this->get('/content-image/'.$id)->assertNotFound();$this->get('/announcements')->assertDontSee('Leadership Week');
 $this->actingAs($head)->post('/workspace/content/'.$id,[...$data,'publish'=>1,'published_at'=>now()->subHour()->format('Y-m-d H:i'),'ends_at'=>now()->addDay()->format('Y-m-d H:i')])->assertRedirect()->assertSessionHasNoErrors();
 $this->post('/logout');$this->get('/content-image/'.$id)->assertOk();$this->get('/announcements')->assertSee('Leadership Week')->assertSee('/portal/leadership/events',false);
 DB::table('settings')->where('key','staff.content.'.$id)->update(['value'=>json_encode(['ends_at'=>now()->subMinute()->toIso8601String()])]);$this->get('/announcements')->assertDontSee('Leadership Week');$this->get('/content-image/'.$id)->assertNotFound();
 }
 public function test_drafts_are_private_and_only_create_live_records_when_completed():void{
  $student=$this->actor('student');$head=$this->actor('slu_head');$other=$this->actor('slu_head');
  $this->actingAs($head)->post('/workspace/drafts/organization',['name'=>'Draft Organization'])->assertRedirect();$draft=\App\Support\StaffDrafts::listing($head,'organization')[0];$this->assertSame(0,Organization::count());$this->get('/workspace/organizations')->assertSee('Continue Draft')->assertSee('Draft Organization');
  $this->actingAs($other)->get('/workspace/organizations')->assertDontSee('Draft Organization');
  $values=['draft_id'=>$draft['id'],'name'=>'Draft Organization','description'=>'Student organization','president_id'=>$student->id,'acronym'=>'DRAFT','college'=>'CITE','type'=>'Academic','adviser'=>'Unit Adviser'];
  $this->post('/workspace/organizations',$values)->assertNotFound();$this->assertSame(0,Organization::count());
  $this->actingAs($head)->post('/workspace/organizations',$values)->assertRedirect()->assertSessionHasNoErrors();$this->assertSame(1,Organization::count());$this->assertSame([],\App\Support\StaffDrafts::listing($head,'organization'));
  $cvu=$this->actor('cvu_head');$this->actingAs($cvu)->post('/workspace/drafts/assignment',['offense'=>'Pending service details'])->assertRedirect();$draft=\App\Support\StaffDrafts::listing($cvu,'assignment')[0];$this->assertSame(0,Assignment::count());
  $this->post('/workspace/assignments',['draft_id'=>$draft['id'],'user_id'=>$student->id,'offense'=>'Pending service details','severity'=>'Minor','department'=>'Library','supervisor'=>'Unit Supervisor','required_hours'=>12,'start_date'=>today()->format('Y-m-d'),'target_date'=>today()->addDays(7)->format('Y-m-d'),'duty_start'=>'08:00','duty_end'=>'12:00'])->assertRedirect()->assertSessionHasNoErrors();$this->assertSame(1,Assignment::count());$this->assertSame([],\App\Support\StaffDrafts::listing($cvu,'assignment'));
  $this->actingAs($student)->post('/workspace/drafts/assignment',['note'=>'Unauthorized'])->assertForbidden();
 }
 public function test_review_panels_are_scoped_and_sfu_keeps_the_application_queue_visible():void{
  $student=$this->actor('student');$sfu=$this->actor('sfu_head');$cvu=$this->actor('cvu_head');$item=ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'TFD-PANEL','kind'=>'scholarship','details'=>['academic_year'=>'2026-2027']]);
  $this->actingAs($sfu)->get('/workspace/dashboard')->assertOk()->assertSee('sfu-applications-title')->assertSee('Application Review')->assertSee('TFD-PANEL');
  $admin=$this->actor('admin');
  $this->actingAs($admin)->get('/workspace/scholarship?review='.$item->id)->assertOk()
   ->assertSee('transaction-review')->assertSee('Application Review')->assertDontSee('class="drawer review-drawer"',false);
  $this->actingAs($cvu)->get('/workspace/complaints?review='.$item->id)->assertNotFound();
  $case=ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'CMP-PANEL','kind'=>'complaint','details'=>['reason'=>'Test complaint']]);
  $this->get('/workspace/complaints?review='.$case->id)->assertOk()->assertSee('transaction-review')->assertSee('CMP-PANEL')
   ->assertSee('class="drawer review-drawer"',false);
  $proposal=ServiceRequest::create([
   'user_id'=>$student->id,'ticket'=>'PROP-PANEL','kind'=>'activity',
   'details'=>[
    'title'=>'Test proposal','description'=>'A practical leadership workshop.','venue'=>'University Chapel',
    'starts_at'=>now()->addDays(2)->toDateTimeString(),'ends_at'=>now()->addDays(2)->addHour()->toDateTimeString(),
    'capacity'=>40,'points'=>10,'budget'=>45000,'revision'=>'Updated the planned attendance.',
   ],
   'files'=>['pre_intent'=>['path'=>'requests/letter.pdf','name'=>'letter.pdf'],'post_narrative'=>['path'=>'requests/narrative.pdf','name'=>'narrative.pdf']],
  ]);
  $head=$this->actor('slu_head');$assistant=$this->actor('assistant');
  $headPage='/workspace/proposals?review='.$proposal->id;
  $adminPage='/workspace/leadership?review='.$proposal->id;
  $decision='/staff/requests/'.$proposal->id;
  $this->actingAs($head)->get($headPage)->assertOk()->assertSee('transaction-review')->assertSee('PROP-PANEL')
   ->assertSeeTextInOrder(['Proposal Review','Event Date','University Chapel','Expected Participants','₱45,000',
    'Requirement Documents','Pre-Activity','Letter of Intent','Post-Activity','Narrative Report','Reviewer Remarks'])
   ->assertSeeHtml('alt="Not attached"')->assertSee('/files/post_narrative/view')
   ->assertSee('Updated the planned attendance.')->assertSee('Endorse to Assistant')
   ->assertSee('Return for Revision')->assertSeeHtml('class="slu-proposal-dialog"')
   ->assertSee('class="proposal-decision"',false)->assertDontSee('class="drawer review-drawer"',false);
  $this->from($headPage)->post($decision,['status'=>'needs_revision'])->assertRedirect($headPage)->assertSessionHasErrors('review_note');
  $this->assertSame('pending',$proposal->fresh()->status);
  $this->actingAs($admin)->get($adminPage)->assertOk()
   ->assertSee('Proposal Details')->assertDontSee('class="proposal-decision"',false);
  $this->actingAs($head)->from($headPage)->post($decision,['status'=>'unit_approved'])
   ->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame('unit_approved',$proposal->fresh()->status);
  $this->assertNull($proposal->fresh()->review_note);
  $this->actingAs($assistant)->post($decision,['status'=>'awaiting_admin','review_note'=>'Endorsement checked and forwarded.'])
   ->assertRedirect()->assertSessionHasNoErrors();
  $this->actingAs($admin)->get($adminPage)->assertOk()->assertSee('Proposal Review')->assertSee('Final approval')
   ->assertSee('class="proposal-decision"',false)->assertDontSee('class="drawer review-drawer"',false);
  $this->get('/workspace/request/'.$proposal->id)->assertOk()->assertSee('Proposal Review')->assertSee('Final approval');
  $this->post($decision,['status'=>'approved','review_note'=>'Approved for publication.'])
   ->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame('approved',$proposal->fresh()->status);
  $this->assertDatabaseHas('events',['title'=>'Test proposal','venue'=>'University Chapel']);
 }
}
