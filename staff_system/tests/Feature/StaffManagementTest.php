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
  Organization::create(['name'=>'Legacy Organization']);
  $student->update(['organization_id'=>$org->id,'position'=>'President']);
  Point::create(['user_id'=>$student->id,'amount'=>1800,'source'=>'directory-sample','reason'=>'Verified points']);
  DB::table('settings')->insert(['key'=>'staff.organization.'.$org->id,'value'=>json_encode([
   'college'=>'CITE','adviser'=>'Dr. Cruz','acronym'=>'LS','status'=>'for_renewal',
  ])]);
  $membership=ServiceRequest::create(['user_id'=>$student->id,'kind'=>'leadership','ticket'=>'SLU-MEMBER-SAMPLE','details'=>['organization_id'=>$org->id]]);
  ServiceRequest::create(['user_id'=>$student->id,'kind'=>'achievement','ticket'=>'SLU-ACHIEVED','status'=>'approved','details'=>['title'=>'Approved Society Award']]);
  ServiceRequest::create(['user_id'=>$student->id,'kind'=>'achievement','ticket'=>'SLU-PENDING-AWARD','details'=>['title'=>'Pending Society Award']]);

  $this->actingAs($head)->get('/workspace/organizations?organization_search=learning&department=CITE&organization_status=for_renewal')
   ->assertOk()->assertSeeText('Organization Directory')->assertSeeText('Learning Society')->assertSeeText('Dr. Cruz')
   ->assertSeeText('For Renewal')->assertSeeHtml('<td>1,800</td>')->assertDontSeeText('Legacy Organization')
   ->assertSeeHtml('id="organization-'.$org->id.'"')->assertSeeText('Officers')->assertSeeText('Approved Society Award')
   ->assertDontSeeText('Pending Society Award')->assertSeeText('Add / Accept Member')->assertSeeText('Manage Officer Roles');
  $this->get('/workspace/organizations?tab=membership')->assertOk()->assertSeeText($membership->ticket)
   ->assertSeeHtml('name="tab" value="membership"')->assertDontSeeHtml('id="organization-'.$org->id.'"');
  $this->get('/workspace/organizations?review='.$membership->id)->assertOk()->assertSeeHtml('id="transaction-review"');
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
 public function test_populated_screens_and_management_forms():void{
  $student=$this->actor('student');$heads=[];foreach(['admin','assistant','sfu_head','cvu_head','slu_head'] as $role)$heads[$role]=$this->actor($role);
  $this->actingAs($heads['slu_head'])->post('/workspace/organizations',['name'=>'Test Organization','description'=>'Verified university organization','president_id'=>$student->id,'acronym'=>'TEST','college'=>'CITE','type'=>'Academic','adviser'=>'Verified Adviser'])->assertRedirect()->assertSessionHasNoErrors();$org=Organization::first();$this->assertSame($org->id,$student->fresh()->organization_id);
  $this->post('/staff/events',['title'=>'Leadership Seminar','description'=>'Verified event description','venue'=>'University Hall','starts_at'=>now()->addDays(2)->format('Y-m-d H:i'),'ends_at'=>now()->addDays(2)->addHours(2)->format('Y-m-d H:i'),'capacity'=>100,'points'=>50,'type'=>'leadership'])->assertRedirect()->assertSessionHasNoErrors();
  $this->actingAs($heads['cvu_head'])->post('/workspace/seminars',['title'=>'Character Seminar','description'=>'Character development seminar','venue'=>'University Hall','starts_at'=>now()->addDays(3)->format('Y-m-d H:i'),'ends_at'=>now()->addDays(3)->addHours(2)->format('Y-m-d H:i'),'capacity'=>100])->assertRedirect()->assertSessionHasNoErrors();
  $this->post('/workspace/assignments',['user_id'=>$student->id,'offense'=>'Complaint CMP-TEST','severity'=>'Minor','department'=>'Library','supervisor'=>'Unit Supervisor','required_hours'=>12,'start_date'=>today()->format('Y-m-d'),'target_date'=>today()->addDays(7)->format('Y-m-d'),'duty_start'=>'08:00','duty_end'=>'12:00'])->assertRedirect()->assertSessionHasNoErrors();
  foreach(array_keys(ServiceRequest::kinds()) as $kind)ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'TEST-'.$kind,'kind'=>$kind,'details'=>['title'=>'Test '.$kind,'description'=>'Source details','subject'=>'NSTP','academic_year'=>'2026-2027','semester'=>'1st Semester','starts_at'=>now()->addDays(3)->toIso8601String()]]);
  foreach($heads as $role=>$actor){Report::create(['user_id'=>$actor->id,'title'=>'Unit Activity Report','academic_year'=>'2026-2027','semester'=>'1st Semester','summary'=>str_repeat('Verified report snapshot. ',3),'snapshot'=>['records'=>[['ticket'=>'TEST','name'=>$student->name,'student_id'=>$student->student_id,'kind'=>'complaint','status'=>'pending']],'version'=>1]]);foreach(StaffPortalController::menus($role) as $page=>$label)$this->actingAs($actor)->get('/workspace/'.$page)->assertOk();}
 }
 public function test_negative_adjustment_applies_only_after_admin_approval():void{$student=$this->actor('student');$org=Organization::create(['name'=>'Test Organization']);$student->update(['organization_id'=>$org->id]);$head=$this->actor('slu_head');$assistant=$this->actor('assistant');$admin=$this->actor('admin');$this->actingAs($head)->post('/workspace/adjustments',['user_id'=>$student->id,'amount'=>-100,'reason'=>'Late organization completion report'])->assertRedirect()->assertSessionHasNoErrors();$q=ServiceRequest::first();$this->assertSame(0,Point::count());$this->actingAs($admin)->post('/staff/requests/'.$q->id,['status'=>'approved','review_note'=>'Reviewed valid evidence'])->assertSessionHasErrors('workflow');$this->actingAs($assistant)->post('/staff/requests/'.$q->id,['status'=>'awaiting_admin','review_note'=>'Verified adjustment for final review'])->assertRedirect()->assertSessionHasNoErrors();$this->actingAs($admin)->post('/staff/requests/'.$q->id,['status'=>'approved','review_note'=>'Approved documented adjustment'])->assertRedirect()->assertSessionHasNoErrors();$this->assertSame(-100,(int)Point::first()->amount);$this->post('/staff/requests/'.$q->id,['status'=>'approved','review_note'=>'Repeated approval attempt'])->assertSessionHasErrors('workflow');$this->assertSame(1,Point::count());}
 public function test_configured_clearance_capacity_is_shared_with_user_submission():void{$head=$this->actor('cvu_head');$date=today()->addDays(3)->format('Y-m-d');$this->actingAs($head)->post('/workspace/slots',['date'=>$date,'start'=>'10:30','end'=>'11:00','capacity'=>1,'open'=>1])->assertRedirect()->assertSessionHasNoErrors();$a=$this->actor('student');$b=$this->actor('student');$input=['declaration'=>1,'details'=>['appointment_date'=>$date,'schedule'=>'10:30','purpose'=>'University clearance']];$this->actingAs($a)->post('/apply/clearance',$input)->assertRedirect()->assertSessionHasNoErrors();$this->actingAs($b)->post('/apply/clearance',$input)->assertSessionHasErrors('workflow');$this->assertSame(1,ServiceRequest::count());}
 public function test_report_files_and_settings_backup_are_scoped():void{Storage::fake('local');$head=$this->actor('cvu_head');$wrong=$this->actor('sfu_head');$assistant=$this->actor('assistant');$this->actingAs($head)->post('/workspace/reports',['title'=>'Unit Activity Report','academic_year'=>'2026-2027','semester'=>'1st Semester','summary'=>str_repeat('Verified unit activity snapshot. ',3),'date_from'=>'2026-09-01','date_to'=>'2026-09-30','attachments'=>[UploadedFile::fake()->create('report.pdf',20,'application/pdf')]])->assertRedirect()->assertSessionHasNoErrors();$report=Report::first();$this->actingAs($wrong)->get('/workspace/report/'.$report->id.'/file/0')->assertForbidden();$this->actingAs($assistant)->get('/workspace/report/'.$report->id.'/file/0')->assertOk();$this->actingAs($head)->post('/workspace/settings',['theme'=>'Dark','date_format'=>'dd/mm/yyyy','time_format'=>'24','in_app'=>1,'email'=>0,'alerts'=>['feedback'=>0]])->assertRedirect()->assertSessionHasNoErrors();$this->assertSame('Dark',$head->fresh()->preferences['theme']);$this->get('/workspace/backup')->assertOk()->assertDownload();}
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
    'capacity'=>40,'points'=>10,'revision'=>'Updated the planned attendance.',
   ],
   'files'=>['pre_intent'=>['path'=>'requests/letter.pdf','name'=>'letter.pdf']],
  ]);
  $head=$this->actor('slu_head');$assistant=$this->actor('assistant');
  $headPage='/workspace/proposals?review='.$proposal->id;
  $adminPage='/workspace/leadership?review='.$proposal->id;
  $decision='/staff/requests/'.$proposal->id;
  $this->actingAs($head)->get($headPage)->assertOk()->assertSee('transaction-review')->assertSee('PROP-PANEL')
   ->assertSee('Proposal Review')->assertSee('University Chapel')->assertSee('Letter of Intent')
   ->assertSee('Updated the planned attendance.')->assertSee('Endorse to Assistant')
   ->assertSee('class="proposal-decision"',false)->assertDontSee('class="drawer review-drawer"',false);
  $this->actingAs($admin)->get($adminPage)->assertOk()
   ->assertSee('Proposal Details')->assertDontSee('class="proposal-decision"',false);
  $this->actingAs($head)->post($decision,['status'=>'unit_approved','review_note'=>'Proposal and documents reviewed.'])
   ->assertRedirect()->assertSessionHasNoErrors();
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
