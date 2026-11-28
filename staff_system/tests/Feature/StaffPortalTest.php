<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Storage};
use App\Models\{User,Report,ServiceRequest};
use App\Http\Controllers\StaffPortalController;
class StaffPortalTest extends TestCase {
 use RefreshDatabase;
 private function actor(string $role):User{return User::create(['name'=>$role,'email'=>uniqid().'@example.test','username'=>uniqid('staff'),'password'=>'TestingPassword123!','role'=>$role,'account_type'=>$role==='student'?'student':'employee','profile_complete'=>true,'email_verified_at'=>now(),'preferences'=>['email'=>false,'in_app'=>true]]);}
 public function test_admin_can_browse_organization_documents_across_all_submission_statuses():void
 {
  Storage::fake('local');
  Storage::put('requests/agenda.pdf',"%PDF-1.4\n");
  Storage::put('completion-reports/after-event.pdf',"%PDF-1.4\n");
  $organization=\App\Models\Organization::create(['name'=>'Workshop Society']);
  $other=\App\Models\Organization::create(['name'=>'Another Society']);
  $student=$this->actor('student');
  $student->update(['organization_id'=>$other->id]);
  $create=fn($ticket,$kind,$status,$files,$details=[])=>ServiceRequest::create([
   'user_id'=>$student->id,'ticket'=>$ticket,'kind'=>$kind,'status'=>$status,'files'=>$files,'details'=>$details,
  ]);
  $proposal=$create('DOC-PROPOSAL','activity','pending',[
   'pre_matrix'=>['path'=>'requests/agenda.pdf','name'=>'agenda.pdf'],
   'post_evaluation'=>['path'=>'requests/evaluation.pdf','name'=>'evaluation.pdf'],
  ],['organization_id'=>(string)$organization->id,'title'=>'Leadership workshop']);
  $report=$create('DOC-REPORT','activity','rejected',['completion_report'=>'completion-reports/after-event.pdf'],['completion_report'=>true]);
  $create('DOC-ACHIEVEMENT','achievement','approved',['evidence'=>'requests/award.pdf']);
  $create('DOC-EMPTY','activity','pending',[]);
  $create('DOC-PERSONAL','complaint','pending',['evidence'=>'requests/private.pdf']);
  $page='/workspace/organization-documents';
  $this->actingAs($this->actor('assistant'))->get($page)->assertForbidden();
  $this->actingAs($this->actor('admin'))->get($page)->assertOk()
   ->assertSee('agenda.pdf')->assertSee('evaluation.pdf')->assertSee('after-event.pdf')->assertSee('award.pdf')
   ->assertDontSee('DOC-EMPTY')->assertDontSee('DOC-PERSONAL');
  $this->get($page.'?organization='.$organization->id)->assertOk()->assertSee('agenda.pdf')->assertDontSee('after-event.pdf');
  $this->get($page.'?search=award.pdf')->assertOk()->assertSee('DOC-ACHIEVEMENT')->assertDontSee('DOC-PROPOSAL');
  $this->get('/requests/'.$proposal->id.'/files/pre_matrix/view')->assertOk()->assertHeader('Content-Type','application/pdf');
  $this->get('/requests/'.$report->id.'/files/completion_report/view')->assertOk()->assertHeader('Content-Type','application/pdf');
 }
 public function test_admin_view_links_open_one_record_and_keep_the_list_filters():void {
  $admin=$this->actor('admin');
  $student=$this->actor('student');
  foreach(['leadership'=>['slu_head','achievement'],'scholarship'=>['sfu_head','scholarship'],'values'=>['cvu_head','complaint']] as $unit=>[$role,$kind]){
   $report=Report::create(['user_id'=>$this->actor($role)->id,'title'=>'Acknowledged '.$unit,
    'academic_year'=>'2026-2027','semester'=>'1st Semester','summary'=>'A saved report summary for '.$unit,
    'snapshot'=>['records'=>[]],'status'=>'approved']);
   $item=ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'VIEW-'.$unit,'kind'=>$kind,
    'details'=>['description'=>'A saved transaction for '.$unit],'status'=>'approved']);
   $this->actingAs($admin);

   foreach(['/workspace/inbox','/workspace/'.$unit] as $page){
    $list=$page.'?status=approved&reports=1&transactions=1';
    $reportUrl=$list.'&report='.$report->id;
    $itemUrl=$list.'&review='.$item->id;
    $this->get($list)->assertOk()->assertSee('data-role="admin"',false)
     ->assertSee('<td class="table-action-cell">',false)->assertSee(url($reportUrl))->assertSee(url($itemUrl));
    $this->get($reportUrl)->assertOk()->assertSee('id="report-review"',false)->assertDontSee('class="drawer review-drawer"',false)
     ->assertSee($report->summary)->assertSee(url($itemUrl))->assertDontSee('id="transaction-review"',false);
    $this->get($itemUrl)->assertOk()->assertSee('id="transaction-review"',false)->assertDontSee('class="drawer review-drawer"',false)
     ->assertSee($item->details['description'])->assertSee(url($reportUrl))->assertDontSee('id="report-review"',false);
   }
   $this->get('/workspace/dashboard?report='.$report->id)->assertOk()->assertSee($report->summary);
  }
 }

 public function test_staff_attachment_view_opens_documents_inline_and_downloads_other_files():void {
  Storage::fake('local');
  Storage::put('requests/evidence.pdf',"%PDF-1.4\n");
  Storage::put('requests/legacy.pdf','<html><body>Attachment content</body></html>');
  $student=$this->actor('student');
  $item=ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'VIEW-FILES','kind'=>'complaint',
   'details'=>[],'files'=>['evidence'=>['path'=>'requests/evidence.pdf','name'=>'evidence.pdf'],'legacy'=>'requests/legacy.pdf']]);
  $files='/requests/'.$item->id.'/files/';
  $this->actingAs($this->actor('admin'))->get('/workspace/values?review='.$item->id)
   ->assertOk()->assertSee($files.'evidence/view')->assertSee('legacy.pdf');
  $this->get($files.'evidence/view')->assertOk()->assertHeader('Content-Type','application/pdf')
   ->assertHeader('Content-Disposition','inline; filename=evidence.pdf');
  $this->get($files.'legacy/view')->assertOk()->assertDownload('legacy.pdf');
  $this->get($files.'missing/view')->assertNotFound();
  $this->actingAs($this->actor('sfu_head'))->get($files.'evidence/view')->assertForbidden();
  $this->actingAs($student)->get($files.'evidence/view')->assertForbidden();
 }

 public function test_admin_and_slu_head_notifications_use_the_workspace_and_preserve_existing_alerts():void {
  $admin=$this->actor('admin');
  $head=$this->actor('slu_head');
  \App\Support\StaffAlerts::role('slu_head','Unit proposal submitted','/workspace/proposals');
  $admin->notifications()->create([
   'id'=>\Illuminate\Support\Str::uuid(),'type'=>'staff-workflow',
   'data'=>['title'=>'Proposal forwarded','message'=>'Ready for final review.','url'=>'/workspace/leadership'],
  ]);

  $this->actingAs($admin)->get('/notifications')->assertOk()
   ->assertSee('Staff navigation')->assertSee('notification-entry-new',false)
   ->assertSee('Proposal forwarded')->assertSee('Ready for final review.')
   ->assertDontSee('Unit proposal submitted');
  $this->assertSame(0,$admin->unreadNotifications()->count());
  $this->assertSame(1,$head->unreadNotifications()->count());
  $this->get('/notifications')->assertOk()->assertDontSee('notification-entry-new',false);

  $this->actingAs($head)->get('/notifications')->assertOk()
   ->assertSee('Staff navigation')->assertSee('Unit proposal submitted')
   ->assertSee('notification-entry-content',false)->assertSee('/workspace/proposals')
   ->assertDontSee('Proposal forwarded');
  $this->assertSame(0,$head->unreadNotifications()->count());
  $emptyAdmin=$this->actor('admin');
  $this->actingAs($emptyAdmin)->get('/notifications')->assertOk()->assertSee('No notifications yet');
 }
 public function test_staff_inbox_uses_local_links_and_clears_legacy_unread_alerts():void {
  foreach(['assistant','sfu_head','cvu_head'] as $role){
   $recipient=$this->actor($role);
   $recipient->notifications()->create(['id'=>\Illuminate\Support\Str::uuid(),'type'=>'staff-workflow',
    'data'=>json_encode(['title'=>'Verification ready','url'=>'http://127.0.0.1:8002/workspace/community#attendance-verification'])]);
   $this->actingAs($recipient)->get('/workspace')->assertOk()->assertSee('Notifications (1 unread)');
   $this->get('/notifications')->assertOk()->assertSee('Verification ready')
    ->assertSee('href="/workspace/community#attendance-verification"',false);
   $this->assertSame(0,$recipient->unreadNotifications()->count());
  }
 }
 public function test_overdue_review_alerts_follow_preferences_and_are_sent_once():void {
  $assistant=$this->actor('assistant');
  $muted=$this->actor('assistant');
  $muted->update(['preferences'=>['alerts'=>['overdue'=>false]]]);
  $admin=$this->actor('admin');
  $owner=$this->actor('cvu_head');
  foreach(['pending'=>'Assistant review','awaiting_admin'=>'Admin review'] as $status=>$title){
   Report::create(['user_id'=>$owner->id,'title'=>$title,'academic_year'=>'2026-2027','semester'=>'1st Semester',
    'summary'=>'A report that is waiting for review.', 'snapshot'=>['records'=>[]],'status'=>$status,'created_at'=>now()->subDays(2)]);
  }
  $this->artisan('notifications:overdue-reports')->assertExitCode(0);
  $this->artisan('notifications:overdue-reports')->assertExitCode(0);
  $this->assertSame('Overdue report: Assistant review',$assistant->notifications()->sole()->data['title']);
  $this->assertSame('Overdue report: Admin review',$admin->notifications()->sole()->data['title']);
  $this->assertSame(0,$muted->notifications()->count());
 }
 public function test_membership_images_can_be_reviewed_inline_by_authorized_staff():void {
  Storage::fake('local');
  $image=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a17kAAAAASUVORK5CYII=');
  Storage::put('requests/student-card.png',$image);
  Storage::put('requests/endorsement.pdf',"%PDF-1.4\n");
  Storage::put('requests/mislabeled.png',"%PDF-1.4\n");
  $student=$this->actor('student');
  $application=ServiceRequest::create([
   'user_id'=>$student->id,'ticket'=>'MEMBERSHIP-REVIEW','kind'=>'leadership','details'=>[],
   'files'=>[
    'student_card'=>'requests/student-card.png',
    'endorsement'=>['path'=>'requests/endorsement.pdf','name'=>'endorsement.pdf'],
    'mislabeled'=>['path'=>'requests/mislabeled.png','name'=>'mislabeled.png'],
   ],
  ]);
  $files='/requests/'.$application->id.'/files/';
  $preview=$files.'student_card/preview';
  $this->get($preview)->assertRedirect('/login');
  $this->actingAs($student)->get($preview)->assertForbidden();
  $this->actingAs($this->actor('sfu_head'))->get($preview)->assertForbidden();

  $this->actingAs($this->actor('admin'))->get('/workspace/leadership?review='.$application->id)
   ->assertOk()->assertSee('Preview student-card.png')->assertSee($preview)
   ->assertDontSee($files.'endorsement/preview')->assertDontSee('class="drawer review-drawer"',false);
  $this->get('/workspace/request/'.$application->id)->assertOk()->assertSee($preview);
  $response=$this->get($preview)->assertOk()->assertHeader('Content-Type','image/png')
   ->assertHeader('X-Content-Type-Options','nosniff');
  $this->assertStringStartsWith('inline',$response->headers->get('Content-Disposition'));
  $this->assertSame($image,$response->streamedContent());
  $this->get($files.'student_card')->assertDownload('student-card.png');
  $this->get($files.'endorsement')->assertDownload('endorsement.pdf');
  $this->get($files.'mislabeled/preview')->assertNotFound();
  $this->get($files.'missing/preview')->assertNotFound();

  $this->actingAs($this->actor('slu_head'))->get('/workspace/organizations?review='.$application->id)
   ->assertOk()->assertSee('Leadership Application Review')->assertSee('MEMBERSHIP-REVIEW')->assertSee($preview)
   ->assertSeeTextInOrder(['Student Information','Enrollment Status','Application Details','Requirement Checklist',
    'Endorsement Letter','Certificate of Candidacy','Scanned Copy of ID','Copy of Enrollment Form','Reviewer Remarks'])
   ->assertSeeHtml('alt="Attached"')->assertSeeHtml('alt="Missing"')
   ->assertSee($files.'endorsement/view')
   ->assertDontSee('class="drawer review-drawer"',false);
  $this->get($preview)->assertOk();
 }

 public function test_membership_dialog_decisions_keep_the_unit_approval_workflow():void {
  $head=$this->actor('slu_head');
  $student=$this->actor('student');
  $organization=\App\Models\Organization::create(['name'=>'Campus Technology Society']);
  $application=ServiceRequest::create([
   'user_id'=>$student->id,'ticket'=>'MEMBERSHIP-DECISION','kind'=>'leadership',
   'details'=>['organization_id'=>(string)$organization->id,'position'=>'Secretary','academic_year'=>'2026-2027',
    'applicant'=>['name'=>'Applicant at Submission','student_id'=>'2023-2636','course'=>'BS Information Technology','year_level'=>'3rd Year']],
  ]);
  $url='/workspace/organizations?tab=membership&review='.$application->id;
  $decision='/staff/requests/'.$application->id;
  $this->actingAs($head)->get($url)->assertOk()->assertSeeTextInOrder([
   'Leadership Application Review','Applicant at Submission','2023-2636','BS Information Technology','3rd Year',
   'Campus Technology Society','Secretary','2026-2027','Return for Correction','Reject Application','Approve & Send to Assistant',
  ]);

  foreach(['needs_revision','rejected'] as $status){
   $this->from($url)->post($decision,['status'=>$status])->assertRedirect($url)->assertSessionHasErrors('review_note');
   $this->assertSame('pending',$application->fresh()->status);
  }
  $this->from($url)->post($decision,['status'=>'unit_approved'])->assertRedirect($url)->assertSessionHasNoErrors();
  $this->assertSame('unit_approved',$application->fresh()->status);
  $this->assertNull($application->fresh()->review_note);
  $this->assertNull($student->fresh()->organization_id);
  $this->get($url)->assertOk()->assertSeeText('Unit Approved')->assertDontSeeText('Approve & Send to Assistant');
 }
 public function test_all_role_screens_render_with_real_empty_data():void{foreach(['admin','assistant','sfu_head','cvu_head','slu_head'] as $role){$actor=$this->actor($role);foreach(StaffPortalController::menus($role) as $page=>$label){$this->actingAs($actor)->get('/workspace/'.$page)->assertOk()->assertSee($label);}}}

 public function test_slu_can_verify_an_available_student_and_create_an_organization():void
 {
  $head=$this->actor('slu_head');
  $student=$this->actor('student');
  $student->update(['student_id'=>'SLU-2026-01','course'=>'Information Technology']);
  $unverified=$this->actor('student');
  $unverified->update(['student_id'=>'SLU-2026-02','email_verified_at'=>null]);
  $verify='/workspace/organizations/verify-leader';
  $this->actingAs($this->actor('cvu_head'))->postJson($verify,['student_ref'=>$student->student_id])->assertForbidden();
  $this->actingAs($head)->postJson($verify,['student_ref'=>'unknown'])->assertNotFound()
   ->assertJsonPath('message','No iServe student account matches that ID.');
  $this->postJson($verify,['student_ref'=>$unverified->student_id])->assertUnprocessable()
   ->assertJsonPath('message','This student must verify their iServe email before they can lead an organization.');

  $leader=$this->postJson($verify,['student_ref'=>'  slu-2026-01  '])->assertOk()
   ->assertJsonPath('reference',$student->student_id)->assertJsonPath('program','Information Technology')
   ->json('id');
  $organization=[
   'name'=>'Technology Society','acronym'=>'TECH','college'=>'Computing','type'=>'Academic',
   'adviser'=>'Test Adviser','president_id'=>$leader,
  ];
  $this->from('/workspace/organizations')->post('/workspace/organizations',$organization+['president_ref'=>$unverified->student_id])
   ->assertRedirect('/workspace/organizations')->assertSessionHasErrors('president_id');
  $this->post('/workspace/organizations',$organization+['president_ref'=>$student->student_id])
   ->assertRedirect('/workspace/organizations')->assertSessionHasNoErrors();
  $this->assertDatabaseHas('organizations',['name'=>'Technology Society']);
  $this->assertSame('Technology Society',$student->fresh()->organization->name);
  $this->assertSame('President',$student->fresh()->position);
  $metadata=DB::table('settings')->where('key','staff.organization.'.$student->fresh()->organization_id)->value('value');
  $this->assertTrue(json_decode($metadata,true)['portal_visible']);
  $this->postJson($verify,['student_ref'=>$student->student_id])->assertUnprocessable()
   ->assertJsonPath('message','This student already belongs to an organization.');
 }

 public function test_slu_can_save_and_create_an_organization_with_portal_visibility_off():void
 {
  $head=$this->actor('slu_head');
  $student=$this->actor('student');
  $page='/workspace/organizations';
  $details=[
   'name'=>'Staff-Only Society','acronym'=>'SOS','college'=>'Computing','type'=>'Academic',
   'adviser'=>'Test Adviser','president_id'=>(string)$student->id,'portal_visible'=>'0',
  ];
  $this->actingAs($head)->from($page)->post('/workspace/drafts/organization',$details)
   ->assertRedirect($page)->assertSessionHasNoErrors();
  $draft=collect(\App\Support\StaffDrafts::listing($head,'organization'))->sole();
  $this->assertSame('0',$draft['fields']['portal_visible']);
  $this->post($page,$details+['draft_id'=>$draft['id']])->assertRedirect($page)->assertSessionHasNoErrors();
  $metadata=DB::table('settings')->where('key','staff.organization.'.$student->fresh()->organization_id)->value('value');
  $this->assertFalse(json_decode($metadata,true)['portal_visible']);
  $this->get($page)->assertOk()->assertSee('Staff-Only Society');
 }

 public function test_cvu_dashboard_shows_only_actionable_unit_requests_and_opens_the_review():void
 {
  $student=$this->actor('student');
  $head=$this->actor('cvu_head');
  $createRequest=fn($ticket,$kind,$status,$details=[])=>ServiceRequest::create([
   'user_id'=>$student->id,'ticket'=>$ticket,'kind'=>$kind,'status'=>$status,'details'=>$details,
  ]);
  $older=$createRequest('CVU-OLDER','lost','pending');
  foreach(range(1,3) as $number)$createRequest('CVU-PRIOR-'.$number,'readmission','pending');
  $item=$createRequest('CVU-REVIEW','complaint','in_review');
  $createRequest('CVU-ARCHIVED','complaint','pending',['archived'=>true]);
  $createRequest('CVU-APPROVED','complaint','approved');
  $createRequest('SFU-PENDING','scholarship','pending');

  $dashboard=$this->actingAs($head)->get('/workspace/dashboard')->assertOk()
   ->assertSee('Today’s Summary')->assertSee('Items Needing Review')->assertSee('Community Service Overview')
   ->assertSee('Review '.$item->ticket)
   ->assertDontSee($older->ticket)->assertDontSee('CVU-ARCHIVED')->assertDontSee('CVU-APPROVED')
   ->assertDontSee('SFU-PENDING');
  $this->assertSame(5,$dashboard->viewData('cvuReviewQueue')->total());
  $this->get('/workspace/dashboard?reviews=2')->assertOk()->assertSee('Review '.$older->ticket);
  $this->get('/workspace/dashboard?review='.$item->id)->assertOk()->assertSee('id="transaction-review"',false);
 }

 public function test_cvu_complaints_keep_search_status_and_archive_filters_on_the_review_queue():void
 {
  $student=$this->actor('student');
  $head=$this->actor('cvu_head');
  $active=ServiceRequest::create([
   'user_id'=>$student->id,'ticket'=>'CMP-ACTIVE','kind'=>'complaint','status'=>'pending',
   'details'=>['respondent'=>'Jamie Santos','complaint_type'=>'Bullying'],
  ]);
  ServiceRequest::create([
   'user_id'=>$student->id,'ticket'=>'CMP-ARCHIVED','kind'=>'complaint','status'=>'approved',
   'details'=>['respondent'=>'Jamie Santos','complaint_type'=>'Harassment','archived'=>true],
  ]);
  $custom=ServiceRequest::create([
   'user_id'=>$student->id,'ticket'=>'CMP-CUSTOM','kind'=>'complaint','status'=>'pending',
   'details'=>['complaint_type'=>'Other','other_complaint'=>'Unauthorized use of student records'],
  ]);

  $this->actingAs($head)->get('/workspace/complaints?search=Jamie&status=pending')->assertOk()
   ->assertSee('Find Archived')->assertSee('1 archived')->assertSee('CMP-ACTIVE')->assertDontSee('CMP-ARCHIVED');
  $this->get('/workspace/complaints?search=Bullying&status=pending&review='.$active->id)
   ->assertOk()->assertSee('id="transaction-review"',false)->assertSee('Jamie Santos');
  $this->get('/workspace/complaints?search=Bullying&status=approved')->assertOk()
   ->assertSee('No complaints found.');
  $this->get('/workspace/complaints?archive=1&search=Harassment&status=approved')->assertOk()
   ->assertSee('Archived Complaints')->assertSee('Back to Active')->assertSee('CMP-ARCHIVED')
   ->assertDontSee('CMP-ACTIVE');
  $page='/workspace/complaints?search=student+records';
  $this->get($page)->assertOk()->assertSee('CMP-CUSTOM')->assertSee('Unauthorized use of student records')->assertDontSee('CMP-ACTIVE');
  $this->get($page.'&review='.$custom->id)->assertOk()->assertSee('id="transaction-review"',false)
   ->assertSeeInOrder(['Type of Complaint','Unauthorized use of student records']);
 }

 public function test_cvu_complaint_review_preserves_the_unit_workflow():void
 {
  $student=$this->actor('student');
  $head=$this->actor('cvu_head');
  $complaint=ServiceRequest::create([
   'user_id'=>$student->id,'ticket'=>'CMP-DETAILS','kind'=>'complaint','status'=>'pending',
   'details'=>['incident_date'=>'2026-09-13','incident_time'=>'10:30','description'=>str_repeat('Incident details. ',9)],
   'files'=>['evidence'=>['path'=>'requests/incident.jpg','name'=>'incident.jpg','size'=>2400000]],
  ]);
  $page='/workspace/complaints?review='.$complaint->id;
  $submit='/workspace/request/'.$complaint->id.'/note';
  $remarks='Verify the attached incident evidence.';
  $interview=now()->addDays(2)->format('Y-m-d\TH:i');

  $this->actingAs($head)->get($page)->assertOk()->assertSee('Complaints Details')
   ->assertSee('Sep 13, 2026, 10:30 AM')->assertSee('Show more')->assertSee('incident.jpg');
  $this->from($page)->post($submit,['complaint_action'=>'schedule','review_note'=>$remarks])
   ->assertRedirect($page)->assertSessionHasErrors('interview_at');
  $this->from($page)->post($submit,['complaint_action'=>'schedule','review_note'=>$remarks,'interview_at'=>$interview])
   ->assertRedirect($page)->assertSessionHasNoErrors();
  $this->assertSame('in_review',$complaint->fresh()->status);
  $this->from($page)->post($submit,['complaint_action'=>'save','review_note'=>$remarks,'interview_at'=>''])
   ->assertRedirect($page)->assertSessionHasNoErrors();
  $this->assertSame($interview,$complaint->fresh()->details['interview_at']);
  $this->from($page)->post($submit,['complaint_action'=>'save','review_note'=>$remarks,'status'=>'approved'])
   ->assertRedirect($page)->assertSessionHasErrors('status');
  $this->assertSame('in_review',$complaint->fresh()->status);
  $this->from($page)->post($submit,['complaint_action'=>'request-info','review_note'=>$remarks])
   ->assertRedirect($page)->assertSessionHasNoErrors();
  $this->assertSame('needs_revision',$complaint->fresh()->status);

  $complaint->refresh()->update(['status'=>'pending']);
  $this->from($page)->post($submit,['complaint_action'=>'save','review_note'=>$remarks,'status'=>'unit_approved'])
   ->assertRedirect($page)->assertSessionHasNoErrors();
  $this->assertSame('unit_approved',$complaint->fresh()->status);
 }

 public function test_cvu_can_record_a_guardian_contact_and_schedule_a_case_conference():void
 {
  $student=$this->actor('student');
  $head=$this->actor('cvu_head');
  $complaint=ServiceRequest::create([
   'user_id'=>$student->id,'ticket'=>'CMP-CONFERENCE','kind'=>'complaint','status'=>'pending',
   'details'=>['description'=>'Complaint submitted before guardian contacts were collected.'],
  ]);
  $page='/workspace/complaints?review='.$complaint->id;
  $contactUrl='/workspace/request/'.$complaint->id.'/parent-guardian';
  $contact=['parent_guardian_name'=>'Alex Reyes','parent_guardian_relationship'=>'Guardian','parent_guardian_phone'=>'+63 912 555 0123'];
  $this->actingAs($this->actor('sfu_head'))->post($contactUrl,$contact)->assertForbidden();
  $this->actingAs($head)->get($page)->assertOk()->assertSee('No contact recorded.');
  $this->from($page)->post($contactUrl,$contact)->assertRedirect($page)->assertSessionHasNoErrors();
  $this->assertSame('pending',$complaint->fresh()->status);
  $this->assertSame($contact,array_intersect_key($complaint->fresh()->details,$contact));

  $meeting=now()->addDays(2)->format('Y-m-d\TH:i');
  $this->from($page)->post('/workspace/request/'.$complaint->id.'/note',[
   'complaint_action'=>'schedule','meeting_type'=>'Case Conference','interview_at'=>$meeting,
   'review_note'=>'The student may attend with their guardian.',
  ])->assertRedirect($page)->assertSessionHasNoErrors();
  $this->get($page)->assertOk()->assertSeeInOrder(['Scheduled case conference',$meeting,'Parent or guardian contact','Alex Reyes']);
  $this->assertSame($contact,array_intersect_key($complaint->fresh()->details,$contact));
  $this->assertSame('Case Conference',$complaint->fresh()->details['meeting_type']);
  $this->from($page)->post($contactUrl,['parent_guardian_phone'=>''])->assertRedirect($page);
  $this->assertArrayNotHasKey('parent_guardian_phone',$complaint->fresh()->details);
  $this->assertSame('Alex Reyes',$complaint->fresh()->details['parent_guardian_name']);
 }

 public function test_cvu_lost_and_found_lists_only_reports_and_tracks_claim_release():void
 {
  $student=$this->actor('student');
  $head=$this->actor('cvu_head');
  $report=fn($ticket,$kind,$status,$details)=>ServiceRequest::create([
   'user_id'=>$student->id,'ticket'=>$ticket,'kind'=>$kind,'status'=>$status,'details'=>$details,
  ]);
  $report('LF-LOST','lost','pending',['item_name'=>'Umbrella']);
  $claim=$report('LF-CLAIM','found','approved',['item_name'=>'Wallet']);
  $report('LF-ARCHIVED','lost','cancelled',['archived'=>true]);
  $report('OTHER-ARCHIVED','complaint','cancelled',['archived'=>true]);

  $page='/workspace/lost-found';
  $this->actingAs($head)->get($page)->assertOk()->assertSee('Lost &amp; Found Record',false)
   ->assertSee('1 archived')->assertSee('LF-LOST')->assertSee('LF-CLAIM')->assertDontSee('LF-ARCHIVED');
  $this->get($page.'?search=Lost%20Report')->assertOk()->assertSee('LF-LOST')->assertDontSee('LF-CLAIM');
  $this->get($page.'?search=Umbrella')->assertOk()->assertSee('LF-LOST')->assertDontSee('LF-CLAIM');
  $this->get($page.'?status=claim_pending')->assertOk()->assertSee('LF-CLAIM')->assertDontSee('LF-LOST');
  $this->get($page.'?archive=1')->assertOk()->assertSee('LF-ARCHIVED')->assertDontSee('OTHER-ARCHIVED')->assertDontSee('LF-LOST');
  $review=$page.'?review='.$claim->id;
  $this->get($review)->assertOk()->assertSee('id="transaction-review"',false)->assertSee('Release Item / Slip');
  $this->from($review)->post('/workspace/request/'.$claim->id.'/disposition',['action'=>'release','review_note'=>'Ownership verified at pickup.'])
   ->assertRedirect($review)->assertSessionHasNoErrors();
  $this->get($page.'?status=released')->assertOk()->assertSee('LF-CLAIM')->assertDontSee('LF-LOST');
  $this->get($page.'?status=claim_pending')->assertOk()->assertDontSee('LF-CLAIM');
 }

 public function test_cvu_lost_and_found_drawer_uses_the_existing_review_actions():void
 {
  $student=$this->actor('student');
  $head=$this->actor('cvu_head');
  $report=ServiceRequest::create([
   'user_id'=>$student->id,'ticket'=>'LF-DETAILS','kind'=>'lost','status'=>'pending',
   'details'=>['item_name'=>'Black Wallet','description'=>str_repeat('Identifying details. ',8),'location'=>'Library'],
   'files'=>['item_photo'=>['path'=>'requests/wallet.jpg','name'=>'wallet.jpg']],
  ]);
  $page='/workspace/lost-found?review='.$report->id;
  $submit='/workspace/request/'.$report->id.'/note';
  $remarks='Student confirmed the identifying details.';
  $this->actingAs($head)->get($page)->assertOk()->assertSee('Selected Item Details')
   ->assertSee('Black Wallet')->assertSee('wallet.jpg')->assertSee('Show more')
   ->assertSee('Validate Claim')->assertSee('More Report Details');
  $this->from($page)->post($submit,['lost_found_action'=>'save','review_note'=>$remarks])
   ->assertRedirect($page)->assertSessionHasNoErrors();
  $this->assertSame('in_review',$report->fresh()->status);
  $this->from($page)->post($submit,['lost_found_action'=>'request-info','review_note'=>$remarks])
   ->assertRedirect($page)->assertSessionHasNoErrors();
  $this->assertSame('needs_revision',$report->fresh()->status);
  $report->refresh()->update(['status'=>'pending']);
  $this->from($page)->post($submit,['lost_found_action'=>'validate','review_note'=>$remarks])
   ->assertRedirect($page)->assertSessionHasNoErrors();
  $this->assertSame('unit_approved',$report->fresh()->status);
  $this->get($page)->assertOk()->assertSee('Unit Approved')->assertDontSee('Validate Claim');
 }
 public function test_staff_only_login_and_pages():void{$student=$this->actor('student');$this->post('/login',['login'=>$student->email,'password'=>'TestingPassword123!'])->assertSessionHasErrors('login');$this->assertGuest();$this->actingAs($student)->get('/workspace')->assertForbidden();$sfu=$this->actor('sfu_head');$this->actingAs($sfu)->get('/workspace/organizations')->assertNotFound();}
 public function test_all_units_submit_and_revision_preserves_snapshot_history():void{$assistant=$this->actor('assistant');$admin=$this->actor('admin');foreach(['cvu_head','slu_head','sfu_head'] as $role){$head=$this->actor($role);$this->actingAs($head)->post('/workspace/reports',['title'=>'Monthly unit report','academic_year'=>'2026-2027','semester'=>'1st Semester','summary'=>str_repeat('Verified unit report. ',3),'date_from'=>'2026-09-01','date_to'=>'2026-09-30'])->assertRedirect();$report=Report::latest('id')->first();$this->assertSame('pending',$report->status);$this->actingAs($admin)->post('/workspace/report/'.$report->id,['status'=>'approved','review_note'=>'Reviewed all source records','check_content'=>1,'check_files'=>1,'check_source'=>1])->assertStatus(422);$this->actingAs($assistant)->post('/workspace/report/'.$report->id,['status'=>'needs_revision','review_note'=>'Please correct the attendance totals'])->assertRedirect()->assertSessionHasNoErrors();$this->actingAs($head)->post('/workspace/report/'.$report->id,['status'=>'pending','summary'=>str_repeat('Corrected unit report. ',3)])->assertRedirect()->assertSessionHasNoErrors();$this->assertCount(1,$report->fresh()->snapshot['versions']);$this->actingAs($assistant)->post('/workspace/report/'.$report->id,['status'=>'awaiting_admin','review_note'=>'Source records and attachments verified','check_content'=>1,'check_files'=>1,'check_source'=>1])->assertRedirect();$this->actingAs($admin)->post('/workspace/report/'.$report->id,['status'=>'assistant_revision','review_note'=>'Assistant clarification is required'])->assertRedirect();$this->actingAs($head)->post('/workspace/report/'.$report->id,['status'=>'pending','summary'=>str_repeat('Forbidden owner revision. ',3)])->assertStatus(422);$this->actingAs($assistant)->post('/workspace/report/'.$report->id,['status'=>'awaiting_admin','review_note'=>'Clarification added and source confirmed','check_content'=>1,'check_files'=>1,'check_source'=>1])->assertRedirect();$this->actingAs($admin)->post('/workspace/report/'.$report->id,['status'=>'approved','review_note'=>'Final report review complete','check_content'=>1,'check_files'=>1,'check_source'=>1])->assertRedirect();$this->assertSame('approved',$report->fresh()->status);$this->get('/workspace/report/'.$report->id)->assertOk();$this->get('/workspace/report/'.$report->id.'/receipt')->assertOk();}}
 public function test_cross_unit_private_files_and_reports_are_denied():void{Storage::fake('local');Storage::put('requests/evidence.pdf','PRIVATE');$owner=$this->actor('student');$sfu=$this->actor('sfu_head');$cvu=$this->actor('cvu_head');$q=ServiceRequest::create(['user_id'=>$owner->id,'ticket'=>'PRIVATE-CVU','kind'=>'complaint','details'=>['description'=>'Private case details'],'files'=>['evidence'=>['path'=>'requests/evidence.pdf','name'=>'evidence.pdf']]]);$this->actingAs($sfu)->get('/workspace/request/'.$q->id)->assertForbidden();$this->get('/requests/'.$q->id.'/files/evidence')->assertForbidden();$this->actingAs($cvu)->get('/workspace/request/'.$q->id)->assertOk();$this->get('/requests/'.$q->id.'/files/evidence')->assertOk();}
 public function test_forward_requires_review_checklist_and_zero_data_is_not_seeded():void{$head=$this->actor('cvu_head');$assistant=$this->actor('assistant');$report=Report::create(['user_id'=>$head->id,'title'=>'Empty period report','academic_year'=>'2026-2027','semester'=>'1st Semester','summary'=>str_repeat('No activity recorded. ',3),'snapshot'=>['records'=>[]]]);$this->actingAs($assistant)->post('/workspace/report/'.$report->id,['status'=>'awaiting_admin','review_note'=>'Verified empty period'])->assertSessionHasErrors(['check_content','check_files','check_source']);$this->assertSame(0,ServiceRequest::count());$this->assertSame('pending',$report->fresh()->status);}
}
