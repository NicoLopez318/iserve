<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,DB,Mail};
use App\Models\{User,ServiceRequest,Point,Report};
class ApprovalRoutingTest extends TestCase {
 use RefreshDatabase;
 private function user(string $role):User {return User::create(['name'=>$role,'email'=>uniqid().'@example.test','username'=>uniqid('user'),'password'=>'TestPassword!123','role'=>$role,'account_type'=>$role==='student'?'student':'employee','profile_complete'=>true,'email_verified_at'=>now(),'preferences'=>['email'=>false,'in_app'=>true]]);}
 private function review(User $actor,ServiceRequest $q,string $status){return $this->actingAs($actor)->post('/staff/requests/'.$q->id,['status'=>$status,'review_note'=>'Verified workflow review note','discount_rate'=>$q->kind==='scholarship'?30:null,'identity_verified'=>$q->kind==='clearance'?1:0,'hymn_completed'=>$q->kind==='clearance'?1:0]);}
 public function test_readmission_interview_survives_remarks_and_final_approval_releases_the_slip():void
 {
  $student=$this->user('student');
  $head=$this->user('cvu_head');
  $assistant=$this->user('assistant');
  $admin=$this->user('admin');
  $q=ServiceRequest::create([
   'user_id'=>$student->id,'ticket'=>'RA-WORKFLOW','kind'=>'readmission',
   'details'=>['subject'=>'Mathematics','subject_code'=>'MATH 1','applicant'=>['name'=>'Submitted Student Name']],
   'files'=>['evidence'=>['name'=>'medical-cert.pdf','path'=>'requests/medical-cert.pdf']],
  ]);
  $this->freezeTime();
  $interview=now()->addDay()->format('Y-m-d\TH:i');
  $page='/workspace/readmission?review='.$q->id;
  $interviewPanel=$page.'&panel=interview';
  $schedule='/workspace/request/'.$q->id.'/note';
  $slip='/workspace/request/'.$q->id.'/slip';
  $this->actingAs($head)->get($page)->assertOk()->assertSee('cvu-readmission-dialog')
   ->assertSee('Selected Request Details')->assertSee('Submitted Student Name')->assertSee('MATH 1')
   ->assertSee('medical-cert.pdf')->assertSee('Schedule Interview')->assertSee($interviewPanel)->assertSee('Available after final approval.');
  $this->get($interviewPanel)->assertOk()->assertSee('cvu-readmission-interview-dialog')
   ->assertSee('Interview Date & Time')->assertSee('Remarks for the Student')->assertSee('Save Interview')
   ->assertDontSee('Approved for Admission');
  $this->get($slip)->assertForbidden();
  $this->from($interviewPanel)->post($schedule,['readmission_action'=>'schedule','review_note'=>'Please attend the scheduled interview.'])
   ->assertRedirect($interviewPanel)->assertSessionHasErrors('interview_at');
  $this->post($schedule,['readmission_action'=>'schedule','review_note'=>'Please attend the scheduled interview.','interview_at'=>$interview])
   ->assertRedirect($interviewPanel)->assertSessionHasNoErrors();
  $this->from($page)->post($schedule,['readmission_action'=>'save','review_note'=>'The submitted evidence has been checked.','interview_at'=>''])
   ->assertRedirect($page)->assertSessionHasNoErrors();
  $this->assertSame($interview,$q->fresh()->details['interview_at']);
  $this->review($head,$q,'unit_approved')->assertSessionHasErrors('workflow');
  $this->assertSame('in_review',$q->fresh()->status);

  $this->travel(2)->days();
  $this->post($schedule,['readmission_action'=>'save','status'=>'unit_approved','review_note'=>'Interview completed and request endorsed.'])
   ->assertRedirect($page)->assertSessionHasNoErrors();
  $this->review($assistant,$q,'awaiting_admin')->assertSessionHasNoErrors();
  $this->review($admin,$q,'approved')->assertSessionHasNoErrors();
  $this->assertNotEmpty($q->fresh()->details['issued_at']);
  $this->assertSame('approved',$q->fresh()->status);
  $this->actingAs($head)->get($page)->assertOk()->assertSee($slip)->assertDontSee('Available after final approval.');
  $this->get($slip)->assertOk()->assertSee('Re-Admission Virtual Slip')->assertSee('Approved for Admission')->assertSee('Send to Student');
  $this->post('/workspace/request/'.$q->id.'/disposition',['action'=>'archive','review_note'=>'Completed re-admission record.'])->assertRedirect();
  $this->get('/workspace/readmission')->assertOk()->assertDontSee('RA-WORKFLOW');
  $this->get('/workspace/readmission?archive=1')->assertOk()->assertSee('RA-WORKFLOW');
 }

 public function test_cvu_head_can_share_only_approved_readmission_slips():void
 {
  config(['app.student_portal_url'=>'https://students.example.test']);
  $student=$this->user('student');
  $item=ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'RA-SLIP','kind'=>'readmission','details'=>[]]);
  $slip='/workspace/request/'.$item->id.'/slip';
  $this->actingAs($this->user('cvu_head'))->post($slip.'/send')->assertForbidden();
  $item->update(['status'=>'approved']);
  $this->actingAs($this->user('assistant'))->post($slip.'/send')->assertForbidden();
  $this->actingAs($this->user('cvu_head'))->post($slip.'/send')->assertRedirect($slip);
  $alert=$student->notifications()->sole()->data;
  $this->assertSame('Re-Admission Virtual Slip: RA-SLIP',$alert['title']);
  $this->assertSame('https://students.example.test/requests/'.$item->id.'/slip',$alert['url']);
  $student->update(['preferences'=>['email'=>false,'in_app'=>false]]);
  $this->post($slip.'/send')->assertRedirect($slip)->assertSessionHas('success','The slip is available in the student portal. The student has turned off notifications.');
  $this->assertSame(1,$student->notifications()->count());
 }

 public function test_cvu_schedule_and_review_email_use_the_student_site():void {
  config(['app.student_portal_url'=>'https://students.example.test']);
  $student=$this->user('student');
  $student->update(['preferences'=>['email'=>true,'in_app'=>false]]);
  $head=$this->user('cvu_head');
  $request=ServiceRequest::create([
   'user_id'=>$student->id,'ticket'=>'CVU-EMAIL','kind'=>'readmission','details'=>[],
  ]);
  $sent=[];
  Mail::shouldReceive('raw')->twice()->andReturnUsing(function(string $body, callable $address)use(&$sent){
   $message=new \Illuminate\Mail\Message(new \Symfony\Component\Mime\Email());
   $address($message);
   $sent[]=[$body,$message->getSymfonyMessage()];
  });

  $this->actingAs($head)->post('/workspace/request/'.$request->id.'/note',[
   'review_note'=>'Interview scheduled.','interview_at'=>now()->addDay()->toDateTimeString(),
  ])->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame($student->email,$sent[0][1]->getTo()[0]->getAddress());
  $this->assertSame('iServe schedule update: CVU-EMAIL',$sent[0][1]->getSubject());
  $this->assertStringContainsString('https://students.example.test/cvu/submitted/'.$request->id,$sent[0][0]);

  $this->review($head,$request,'needs_revision')->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame('iServe request update: CVU-EMAIL',$sent[1][1]->getSubject());
  $this->assertStringContainsString('https://students.example.test/requests/'.$request->id,$sent[1][0]);
  $this->assertSame(0,$student->notifications()->count());
 }
 public function test_id_replacement_review_requires_the_receipt_details_and_affidavit():void
 {
  Storage::fake('local');
  Storage::put('requests/affidavit.pdf',"%PDF-1.4\n");
  $student=$this->user('student');
  $head=$this->user('cvu_head');
  $item=ServiceRequest::create([
   'user_id'=>$student->id,'ticket'=>'IDR-TEST','kind'=>'id-replacement',
   'details'=>['replacement_reason'=>'Stolen'],
  ]);
  $page='/workspace/id-replacement?review='.$item->id;
  $note='/workspace/request/'.$item->id.'/note';
  $slip='/workspace/request/'.$item->id.'/slip';
  $approval=['id_replacement_action'=>'approve','review_note'=>'Replacement details reviewed.'];
  $this->actingAs($head)->from($page);
  $this->post($note,$approval)->assertSessionHasErrors([
   'workflow'=>'An affidavit of loss is required before approving a lost or stolen ID replacement.',
  ]);
  $item->update(['files'=>['evidence'=>['path'=>'requests/affidavit.pdf','name'=>'affidavit.pdf']]]);
  $this->post($note,$approval)->assertSessionHasErrors([
   'workflow'=>'A receipt number and valid payment amount are required before approving ID replacement.',
  ]);
  $item->update(['details'=>[...$item->details,'receipt_number'=>'000124','payment_amount'=>'125.50']]);
  $this->get($page)->assertOk()->assertSee('cvu-id-replacement-dialog')->assertSee('Selected Request Details')
   ->assertSee('000124')->assertSee('125.50')->assertSee('affidavit.pdf')->assertSee('Approve Request');
  $this->get($slip)->assertForbidden();
  $this->from($page)->post($note,['id_replacement_action'=>'save','review_note'=>'Payment and supporting file received.'])
   ->assertRedirect($page)->assertSessionHasNoErrors();
  $this->assertSame('in_review',$item->fresh()->status);
  $this->post($note,$approval)->assertSessionHasNoErrors();
  $this->assertSame('unit_approved',$item->fresh()->status);
  $this->assertSame($student->name,$item->fresh()->details['student_name']);
 }
 public function test_cvu_id_replacement_review_can_request_more_information():void
 {
  $student=$this->user('student');
  $item=ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'IDR-INFO','kind'=>'id-replacement','details'=>[]]);
  $page='/workspace/id-replacement?review='.$item->id;
  $remarks='Please attach a clearer copy of your receipt.';
  $this->actingAs($this->user('cvu_head'))->from($page)->post('/workspace/request/'.$item->id.'/note',[
   'id_replacement_action'=>'request-info','review_note'=>$remarks,
  ])->assertRedirect($page)->assertSessionHasNoErrors();
  $this->assertSame('needs_revision',$item->fresh()->status);
  $this->assertSame($remarks,$item->fresh()->review_note);
 }
 public function test_cvu_marks_approved_ids_reprinted_and_notifies_the_applicant_once():void
 {
  config(['app.student_portal_url'=>'https://students.example.test']);
  $student=$this->user('student');
  $student->update(['preferences'=>['email'=>true,'in_app'=>true]]);
  $item=ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'IDR-REPRINT','kind'=>'id-replacement','details'=>[]]);
  $page='/workspace/id-replacement?review='.$item->id;
  $action='/workspace/request/'.$item->id.'/disposition';
  $payload=['action'=>'reprint'];
  $url='https://students.example.test/requests/'.$item->id;
  $mail=[];
  Mail::shouldReceive('raw')->once()->andReturnUsing(function(string $body,callable $address)use(&$mail){
   $message=new \Illuminate\Mail\Message(new \Symfony\Component\Mime\Email());
   $address($message);
   $mail=[$body,$message->getSymfonyMessage()];
  });
  $this->actingAs($this->user('admin'))->post($action,$payload)->assertForbidden();
  $this->actingAs($this->user('cvu_head'))->postJson($action,$payload)->assertUnprocessable();
  $item->update(['status'=>'approved']);
  $this->get($page)->assertOk()->assertSee('Mark ID as Reprinted')->assertSee('Print Slip');
  $this->get('/workspace/request/'.$item->id.'/slip')->assertOk();
  $this->get('/workspace/request/'.$item->id)->assertOk()->assertSee('Mark ID as Reprinted');
  $this->from($page)->post($action,$payload)->assertRedirect($page)->assertSessionHasNoErrors();
  $this->post($action,$payload)->assertRedirect($page)->assertSessionHasNoErrors();
  $this->assertNotEmpty($item->fresh()->details['reprinted_at']);
  $this->assertArrayNotHasKey('released',$item->fresh()->details);
  $this->get($page)->assertOk()->assertDontSee('Mark ID as Reprinted')->assertSee('Release ID');
  $alert=$student->notifications()->sole()->data;
  $this->assertSame('Replacement ID reprinted: IDR-REPRINT',$alert['title']);
  $this->assertSame($url,$alert['url']);
  $this->assertStringContainsString($url,$mail[0]);
  $this->assertSame($student->email,$mail[1]->getTo()[0]->getAddress());
 }
 public function test_admin_membership_rejection_requires_a_reason_and_notifies_the_applicant():void {
  $student=$this->user('student');
  $admin=$this->user('admin');
  $application=ServiceRequest::create([
   'user_id'=>$student->id,'ticket'=>'MEMBERSHIP-REJECT','kind'=>'leadership','details'=>[],
  ]);
  $reviewPage='/workspace/leadership?review='.$application->id;
  $decisionUrl='/staff/requests/'.$application->id;
  $reason='The endorsement letter does not identify the selected organization.';

  $this->actingAs($admin)->get($reviewPage)->assertOk()->assertDontSee('Reject application');
  $this->post($decisionUrl,['status'=>'rejected','review_note'=>$reason])->assertSessionHasErrors('workflow');
  $application->update(['status'=>'awaiting_admin']);
  $this->get($reviewPage)->assertOk()->assertSee('Reason for rejection')->assertSee('Confirm rejection');
  $this->get('/workspace/request/'.$application->id)->assertOk()->assertSee('Confirm rejection');
  $this->from($reviewPage)->post($decisionUrl,['status'=>'rejected','review_note'=>'   '])
   ->assertRedirect($reviewPage)->assertSessionHasErrors(['review_note'=>'The reason for rejection field is required.']);
  $this->assertSame('awaiting_admin',$application->fresh()->status);

  $this->from($reviewPage)->post($decisionUrl,['status'=>'rejected','review_note'=>$reason])
   ->assertRedirect($reviewPage)->assertSessionHasNoErrors();
  $this->get($reviewPage)->assertOk()->assertSee('Reason for rejection')->assertSee($reason)->assertDontSee('Confirm rejection');
  $this->assertDatabaseHas('service_requests',[
   'id'=>$application->id,'status'=>'rejected','review_note'=>$reason,'reviewed_by'=>$admin->id,
  ]);
  $this->assertDatabaseHas('audit_logs',['subject'=>$application->ticket,'action'=>'request.rejected','details'=>$reason]);
  $this->assertSame($reason,$student->notifications()->sole()->data['message']);
 }
 public function test_all_transaction_types_require_unit_approval_and_assistant_forwarding():void {
  $student=$this->user('student');$assistant=$this->user('assistant');$admin=$this->user('admin');$heads=[];foreach(['sfu_head','cvu_head','slu_head'] as $role)$heads[$role]=$this->user($role);
  foreach(array_keys(ServiceRequest::kinds()) as $kind){
   $q=ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'FLOW-'.$kind,'kind'=>$kind,'details'=>['title'=>'Test '.$kind]]);
   $this->review($admin,$q,'approved')->assertSessionHasErrors('workflow');
   $this->review($assistant,$q,'awaiting_admin')->assertSessionHasErrors('workflow');
   $wrong=$q->office()==='cvu_head'?$heads['sfu_head']:$heads['cvu_head'];$this->review($wrong,$q,'unit_approved')->assertForbidden();
   $this->review($heads[$q->office()],$q,'approved')->assertRedirect();$this->assertSame('unit_approved',$q->fresh()->status);
   $this->review($assistant,$q,'approved')->assertSessionHasErrors('workflow');
   $this->review($admin,$q,'approved')->assertSessionHasErrors('workflow');
   $this->review($assistant,$q,'awaiting_admin')->assertRedirect();$this->assertSame('awaiting_admin',$q->fresh()->status);
   $this->review($heads[$q->office()],$q,'approved')->assertSessionHasErrors('workflow');
  }
  $this->assertSame(0,Point::count());
 }
 public function test_revision_restarts_chain_and_final_approval_applies_benefits_once():void {
  $student=$this->user('student');$head=$this->user('slu_head');$assistant=$this->user('assistant');$admin=$this->user('admin');
  $q=ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'ACH-chain','kind'=>'achievement','details'=>['title'=>'Verified achievement','description'=>'Achievement evidence']]);
  $this->review($head,$q,'unit_approved')->assertRedirect();$this->assertSame(0,Point::count());
  $this->review($assistant,$q,'needs_revision')->assertRedirect();
  $this->actingAs($student)->post('/requests/'.$q->id.'/revise',['revision'=>'Updated achievement evidence for review.'])->assertRedirect();$this->assertSame('pending',$q->fresh()->status);
  $this->review($assistant,$q,'awaiting_admin')->assertSessionHasErrors('workflow');
  $this->review($head,$q,'unit_approved')->assertRedirect();$this->review($assistant,$q,'awaiting_admin')->assertRedirect();
  $this->review($admin,$q,'needs_revision')->assertRedirect();
  $this->actingAs($student)->post('/requests/'.$q->id.'/revise',['revision'=>'Further corrections for final approval.'])->assertRedirect();
  $this->review($admin,$q,'approved')->assertSessionHasErrors('workflow');
  $this->review($head,$q,'unit_approved')->assertRedirect();$this->review($assistant,$q,'awaiting_admin')->assertRedirect();$this->assertSame(0,Point::count());
  $this->review($admin,$q,'approved')->assertRedirect();$this->assertSame('approved',$q->fresh()->status);$this->assertSame($admin->id,$q->fresh()->reviewed_by);$this->assertSame(100,(int)Point::sum('amount'));
  $this->review($admin,$q,'approved')->assertSessionHasErrors('workflow');$this->assertSame(1,Point::count());
  $this->actingAs($assistant)->get('/requests/'.$q->id)->assertOk()->assertSee('Review History')->assertSee('assistant')->assertSee('admin');
 }
 public function test_assistant_monitors_all_units_with_private_documents_but_cannot_manage_staff():void {
  Storage::fake('local');Storage::put('requests/proof.txt','Private evidence');$student=$this->user('student');$assistant=$this->user('assistant');
  foreach(['scholarship','complaint','achievement'] as $kind){$q=ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'MONITOR-'.$kind,'kind'=>$kind,'details'=>['title'=>'Test request'],'files'=>['evidence'=>['path'=>'requests/proof.txt','name'=>'proof.txt']]]);$this->actingAs($assistant)->get('/requests/'.$q->id)->assertOk();$this->get('/requests/'.$q->id.'/files/evidence')->assertOk();}
  $this->get('/workspace/inbox')->assertOk()->assertSee('MONITOR-scholarship')->assertSee('MONITOR-complaint')->assertSee('MONITOR-achievement');
  $export=$this->get('/staff/export')->assertOk();$this->assertStringContainsString('MONITOR-scholarship',$export->streamedContent());
  $this->post('/staff/users',['name'=>'Unauthorized','email'=>'unauthorized@example.test','username'=>'unauthorized','role'=>'admin','password'=>'TestPassword!123'])->assertForbidden();
  $this->post('/staff/settings',[])->assertForbidden();$this->post('/staff/announcements',['title'=>'Unauthorized notice','body'=>'Valid but unauthorized notice','unit'=>'values','publish'=>1])->assertForbidden();
 }
 public function test_head_reports_return_for_revision_then_forward_to_admin():void {
  $head=$this->user('sfu_head');$otherHead=$this->user('sfu_head');$assistant=$this->user('assistant');$admin=$this->user('admin');
  $report=Report::create(['user_id'=>$head->id,'title'=>'Term report','academic_year'=>'2026-2027','semester'=>'1st Semester','summary'=>'Original scholarship report summary.','snapshot'=>[['ticket'=>'SCH-report','name'=>'Student','student_id'=>'2026-001','rate'=>30]]]);
  $this->actingAs($assistant)->get('/staff/reports')->assertOk();
  $this->post('/staff/reports/'.$report->id,['status'=>'approved','review_note'=>'Attempt unauthorized final approval'])->assertSessionHasErrors('workflow');
  $this->post('/staff/reports/'.$report->id,['status'=>'needs_revision','review_note'=>'Please clarify report information'])->assertRedirect();
  $this->actingAs($otherHead)->post('/staff/reports/'.$report->id.'/revise',['summary'=>'Corrected summary from another head.'])->assertForbidden();
  $this->actingAs($head)->post('/staff/reports/'.$report->id.'/revise',['summary'=>'Corrected scholarship report summary.'])->assertRedirect();$this->assertSame('pending',$report->fresh()->status);$this->assertSame(30,$report->fresh()->snapshot[0]['rate']);
  $this->actingAs($admin)->post('/staff/reports/'.$report->id,['status'=>'approved','review_note'=>'Attempt bypass assistant review'])->assertSessionHasErrors('workflow');
  $this->actingAs($assistant)->post('/staff/reports/'.$report->id,['status'=>'awaiting_admin','review_note'=>'Checked and forwarded to administrator'])->assertRedirect();
  $this->actingAs($admin)->post('/staff/reports/'.$report->id,['status'=>'approved','review_note'=>'Final report approval confirmed'])->assertRedirect();$this->assertSame('approved',$report->fresh()->status);
 }
}
