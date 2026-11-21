<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Storage,DB};
use App\Models\{User,Organization,Event,ServiceRequest,Assignment,Point,Report};
use App\Http\Controllers\StaffPortalController;
class StaffManagementTest extends TestCase {
 use RefreshDatabase;
 private function actor(string $role):User{return User::create(['name'=>'Test '.$role,'email'=>uniqid().'@example.test','username'=>uniqid('staff'),'password'=>'TestingPassword123!','role'=>$role,'account_type'=>$role==='student'?'student':'employee','student_id'=>$role==='student'?uniqid('ST-'):null,'course'=>$role==='student'?'BSIT':null,'year_level'=>'2','profile_complete'=>true,'email_verified_at'=>now(),'preferences'=>['email'=>false,'in_app'=>true]]);}
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
  $this->actingAs($sfu)->get('/workspace/dashboard')->assertOk()->assertSee('queue-with-review')->assertSee('Application Review')->assertSee('TFD-PANEL');
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
