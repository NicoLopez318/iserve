<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Storage,Mail};
use Illuminate\Http\UploadedFile;
use App\Models\{User,ServiceRequest,Assignment,Attendance,Journal,Event};

class CvuRegressionTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void {parent::setUp();config(['hashing.bcrypt.rounds'=>4]);Mail::fake();Storage::fake('local');}
 private function person(string $role='student'):User {return User::create(['name'=>'CVU Test Student','email'=>uniqid().'@example.test','username'=>uniqid('cvu'),'student_id'=>uniqid('SID'),'password'=>'TestPassword!123','role'=>$role,'account_type'=>$role==='student'?'student':'employee','email_verified_at'=>now(),'values_verified_at'=>now(),'profile_complete'=>true,'course'=>'BSIT','year_level'=>'3rd Year','phone'=>'09123456789']);}
 private function identity(User $u):array {return ['name'=>$u->name,'student_id'=>$u->student_id,'course'=>'BSIT','year_level'=>'3rd Year','contact'=>$u->phone,'confirmations'=>[1,1,1]];}
 private function seminar(array $extra=[]):Event {return Event::create([...['type'=>'seminar','title'=>uniqid('Seminar '),'description'=>'Student seminar','venue'=>'Campus','starts_at'=>now()->addDays(2),'ends_at'=>now()->addDays(2)->addHours(2),'capacity'=>2,'status'=>'published','metadata'=>['deadline'=>now()->addDay()->toDateString()]],...$extra]);}
 private function record(User $u):Assignment {return Assignment::create(['user_id'=>$u->id,'record_number'=>uniqid('CV-'),'offense'=>'Conduct','severity'=>'Minor','department'=>'IT','supervisor'=>'Unit Head','required_minutes'=>120]);}
 private function request(User $u,string $kind,array $details=[]):ServiceRequest {return ServiceRequest::create(['user_id'=>$u->id,'kind'=>$kind,'ticket'=>uniqid('CVU-'),'details'=>$details]);}
 private function photo():UploadedFile {return UploadedFile::fake()->createWithContent('evidence.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lGQAAAAASUVORK5CYII='));}
 public function test_id_replacement_records_the_payment_and_requires_an_affidavit_for_missing_ids():void
 {
  $student=$this->person();
  $head=$this->person('cvu_head');
  $page='/apply/id-replacement';
  $details=['replacement_reason'=>'Lost','incident_date'=>today()->toDateString(),'description'=>'My ID went missing on campus.'];
  $this->actingAs($student)->get($page)->assertOk()->assertSee('Receipt Number')->assertSee('Affidavit of loss');
  $this->from($page)->post($page,['declaration'=>1,'details'=>$details])
   ->assertRedirect($page)->assertSessionHasErrors(['details.receipt_number','details.payment_amount','documents.evidence']);
  $payment=['receipt_number'=>'000123','payment_amount'=>'125.50'];
  $this->post($page,['declaration'=>1,'details'=>[...$details,...$payment,'replacement_reason'=>'Stolen']])
   ->assertRedirect($page)->assertSessionHasErrors('documents.evidence');
  $this->post($page,['declaration'=>1,'details'=>[...$details,...$payment,'payment_amount'=>'125.501'],'documents'=>['evidence'=>$this->photo()]])
   ->assertRedirect($page)->assertSessionHasErrors('details.payment_amount');
  $this->assertDatabaseCount('service_requests',0);
  $this->post($page,['declaration'=>1,'details'=>$details+$payment+['student_name'=>'Different Name'],
   'documents'=>['evidence'=>UploadedFile::fake()->create('affidavit.pdf',1,'application/pdf')]])
   ->assertRedirect()->assertSessionHasNoErrors();
  $lost=$student->requests()->sole();
  $this->assertSame($student->name,$lost->details['student_name']);
  $this->assertSame($payment,array_intersect_key($lost->details,$payment));
  Storage::assertExists($lost->files['evidence']['path']);
  $this->get('/requests/'.$lost->id)->assertOk()->assertSee('affidavit.pdf')->assertSee('000123');
  $this->get('/notifications')->assertOk()->assertSee('iServe ID replacement request received: '.$lost->ticket);
  $this->assertSame('New ID replacement request: '.$lost->ticket,$head->notifications()->sole()->data['title']);

  $lost->update(['status'=>'needs_revision']);
  $this->get('/requests/'.$lost->id)->assertOk()->assertSee('name="details[receipt_number]"',false);
  $this->post('/requests/'.$lost->id.'/revise',['revision'=>'Corrected the official receipt number.','details'=>['receipt_number'=>'000124']])
   ->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame('000124',$lost->fresh()->details['receipt_number']);
  $this->assertSame($lost->files,$lost->fresh()->files);
  $this->post($page,['declaration'=>1,'details'=>[...$details,...$payment,'replacement_reason'=>'Damaged']])
   ->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame([], $student->requests()->whereKeyNot($lost->id)->sole()->files);
 }

 public function test_student_can_see_the_reprint_status_on_the_id_replacement_record():void
 {
  $student=$this->person();
  $item=$this->request($student,'id-replacement');
  $item->update(['status'=>'approved','details'=>['reprinted_at'=>now()->toIso8601String()]]);
  $page='/requests/'.$item->id;
  $this->actingAs($student)->get($page)->assertOk()->assertSee('Your replacement ID has been reprinted.');
  $item->update(['details'=>[...$item->details,'released'=>true]]);
  $this->get($page)->assertOk()->assertSee('Your replacement ID has been released.');
  $this->actingAs($this->person())->get($page)->assertForbidden();
 }
 public function test_student_can_submit_parent_or_guardian_contact_with_a_complaint():void
 {
  $student=$this->person();
  $page='/apply/complaint';
  $this->actingAs($student)->get($page)->assertOk()->assertSee('Parent or guardian contact (optional)');
  $details=[
   'complaint_type'=>'Bullying','incident_date'=>today()->toDateString(),'location'=>'Campus',
   'description'=>'A detailed complaint for the CVU office to review.',
  ];
  $contact=['parent_guardian_name'=>'Alex Reyes','parent_guardian_relationship'=>'Mother','parent_guardian_phone'=>'+63 912 555 0123'];
  $this->from($page)->post($page,['declaration'=>1,'details'=>[...$details,...$contact,'parent_guardian_phone'=>str_repeat('1',41)]])
   ->assertRedirect($page)->assertSessionHasErrors('details.parent_guardian_phone');
  $this->get($page)->assertOk()->assertSee('Alex Reyes')->assertSee('data-wizard-start="1"',false);
  $this->post($page,['declaration'=>1,'details'=>$details+$contact])->assertRedirect()->assertSessionHasNoErrors();
  $complaint=$student->requests()->sole();
  $this->assertSame($contact,array_intersect_key($complaint->details,$contact));
  $record='/requests/'.$complaint->id;
  $this->get($record)->assertOk()->assertSee('Parent or guardian name')->assertSee('Alex Reyes');
  $this->actingAs($this->person())->get($record)->assertForbidden();
 }
 public function test_other_complaints_require_and_save_the_students_own_type():void
 {
  $page='/apply/complaint';
  $student=$this->person();
  $this->actingAs($student)->get($page)->assertOk()
   ->assertSee('Others</option>',false)->assertSee('name="details[other_complaint]"',false);
  $details=['complaint_type'=>'Other','incident_date'=>today()->toDateString(),'location'=>'Campus',
   'description'=>'A detailed account of what happened on campus.'];
  $this->from($page)->post($page,['declaration'=>1,'details'=>$details])
   ->assertRedirect($page)->assertSessionHasErrors('details.other_complaint');
  $this->get($page)->assertSee('Enter your complaint type when you select Others.')
   ->assertSee('data-wizard-start="1"',false);
  $this->post($page,['declaration'=>1,'details'=>$details+['other_complaint'=>'Unauthorized use of student records']])
   ->assertRedirect()->assertSessionHasNoErrors();
  $complaint=$student->requests()->sole();
  $this->assertSame('Unauthorized use of student records',$complaint->details['other_complaint']);
  $this->get('/requests/'.$complaint->id)->assertOk()->assertSee('Unauthorized use of student records');

  $this->post($page,['declaration'=>1,'details'=>[...$details,'complaint_type'=>'Bullying','other_complaint'=>'Stale custom type']])
   ->assertRedirect()->assertSessionHasNoErrors();
  $this->assertArrayNotHasKey('other_complaint',$student->requests()->whereKeyNot($complaint->id)->sole()->details);
 }
 public function test_cvu_receipts_use_the_applicant_email_and_respect_preferences():void {
  config(['app.url'=>'https://students.example.test']);
  $student=$this->person();
  $optedOut=$this->person();
  $optedOut->update(['preferences'=>['email'=>false]]);
  $sent=[];
  Mail::shouldReceive('raw')->times(3)->andReturnUsing(function(string $body, callable $address)use(&$sent){
   $message=new \Illuminate\Mail\Message(new \Symfony\Component\Mime\Email());
   $address($message);
   $sent[]=[$body,$message->getSymfonyMessage()];
  });

  $this->actingAs($student)->post('/cvu/submit/seminar',[
   ...$this->identity($student),'details'=>['event_id'=>$this->seminar()->id],
  ])->assertRedirect()->assertSessionHasNoErrors();
  $seminarRequest=$student->requests()->sole();
  $student->notifications()->create([
   'id'=>(string) \Illuminate\Support\Str::uuid(),'type'=>'request',
   'data'=>json_encode(['title'=>'Legacy staff update','url'=>'/requests/'.$seminarRequest->id]),
  ]);
  $this->get('/notifications')->assertOk()
   ->assertSee('iServe request received: '.$seminarRequest->ticket)
   ->assertSee('Legacy staff update');
  $this->assertSame($student->email,$sent[0][1]->getTo()[0]->getAddress());
  $this->assertSame('iServe request received: '.$seminarRequest->ticket,$sent[0][1]->getSubject());
  $this->assertStringContainsString('https://students.example.test/requests/'.$seminarRequest->id,$sent[0][0]);

  $details=['complaint_type'=>'Harassment','incident_date'=>today()->toDateString(),'location'=>'Campus','description'=>'A report for the CVU office to review.'];
  $this->actingAs($optedOut)->post('/apply/complaint',['declaration'=>1,'details'=>$details])
   ->assertRedirect()->assertSessionHasNoErrors();
  $this->assertCount(1,$sent);
  $this->assertSame(1,$optedOut->notifications()->count());
  $this->actingAs($student)->post('/apply/complaint',['declaration'=>1,'details'=>$details])
   ->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame($student->email,$sent[1][1]->getTo()[0]->getAddress());
  $complaint=$student->requests()->where('kind','complaint')->sole();
  $complaint->update(['status'=>'needs_revision']);
  $this->post('/requests/'.$complaint->id.'/revise',['revision'=>'Clarified the report for the CVU office.'])
   ->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame('iServe request resubmitted: '.$complaint->ticket,$sent[2][1]->getSubject());
 }
 public function test_new_student_can_submit_a_cvu_request_after_verifying_email():void {
  $student=User::create([
   'name'=>'New Student','email'=>'new-student@example.test','username'=>'new-student',
   'password'=>'TestPassword!123','role'=>'student','account_type'=>'student',
  ]);
  $payload=[
   'name'=>$student->name,'student_id'=>'2026-0001','course'=>'BS Biology',
   'year_level'=>'1st Year','contact'=>'09123456789','confirmations'=>[1,1,1],
   'details'=>['event_id'=>$this->seminar()->id],
  ];

  $student->markEmailAsVerified();
  $this->actingAs($student)->get('/apply/seminar')->assertOk()->assertSee('list="cvu-courses"',false);
  $this->post('/cvu/submit/seminar',$payload)->assertRedirect()->assertSessionHasNoErrors();
  $request=$student->requests()->sole();
  $this->assertSame('BS Biology',$student->fresh()->course);
  $this->assertSame('/requests/'.$request->id,$student->notifications()->sole()->data['url']);
 }
 public function test_seminar_attendance_review_is_owned_by_cvu_and_cannot_be_repeated():void {
  $u=$this->person();$head=$this->person('cvu_head');$event=$this->seminar();$a=Attendance::create(['user_id'=>$u->id,'event_id'=>$event->id,'status'=>'pending','reflection'=>'Registered for the seminar.']);
  $this->actingAs($u)->post('/cvu/head/attendance/'.$a->id,['status'=>'approved'])->assertForbidden();
  $this->actingAs($head)->post('/cvu/head/attendance/'.$a->id,['status'=>'approved'])->assertRedirect();$this->assertSame('approved',$a->fresh()->status);
  $this->postJson('/cvu/head/attendance/'.$a->id,['status'=>'rejected'])->assertUnprocessable();$this->assertSame('approved',$a->fresh()->status);
  $this->assertSame(1,$u->notifications()->count());
 }
 public function test_complaints_lost_found_and_id_replacement_complete_review_chain_and_private_files():void {
  $u=$this->person();$head=$this->person('cvu_head');$assistant=$this->person('assistant');$admin=$this->person('admin');$outsider=$this->person();
  $cases=[
   'complaint'=>['complaint_type'=>'Bullying','incident_date'=>today()->toDateString(),'location'=>'Campus','description'=>'A detailed student concern for review.'],
   'lost'=>['item_name'=>'Wallet','incident_date'=>today()->toDateString(),'location'=>'Library','description'=>'A black wallet was lost in the library.','contact'=>'09123456789'],
   'found'=>['item_name'=>'Wallet','incident_date'=>today()->toDateString(),'location'=>'Library','description'=>'A black wallet was found in the library.','contact'=>'09123456789'],
   'id-replacement'=>['replacement_reason'=>'Damaged','receipt_number'=>'OR-123','payment_amount'=>'125.50','incident_date'=>today()->toDateString(),'description'=>'The student ID is damaged and needs replacement.']];
  foreach($cases as $kind=>$details){
   $this->actingAs($u)->get('/apply/'.$kind)->assertOk();
   $payload=['details'=>$details,'declaration'=>1];if($kind==='found')$payload['documents']=['item_photo'=>$this->photo()];
   $this->post('/apply/'.$kind,$payload)->assertRedirect();$q=ServiceRequest::where('kind',$kind)->firstOrFail();
   $this->get('/requests/'.$q->id)->assertOk();$this->actingAs($outsider)->get('/requests/'.$q->id)->assertForbidden();
   if($kind==='found'){$this->get('/requests/'.$q->id.'/files/item_photo')->assertForbidden();$this->actingAs($head)->get('/requests/'.$q->id.'/files/item_photo')->assertOk();}
   foreach([[$head,'unit_approved'],[$assistant,'awaiting_admin'],[$admin,'approved']] as [$actor,$status])$this->actingAs($actor)->post('/staff/requests/'.$q->id,['status'=>$status,'review_note'=>'The request has been checked and reviewed.'])->assertRedirect();
   $this->assertSame('approved',$q->fresh()->status);
  }
 }
 public function test_nested_request_details_and_revision_render_for_student_and_correct_head():void {
  $u=$this->person();$q=$this->request($u,'readmission',['applicant'=>$this->identity($u),'description'=>'A detailed explanation for returning to class.']);$q->update(['status'=>'needs_revision']);
  $this->actingAs($u)->get('/requests/'.$q->id)->assertOk()->assertSee('Resubmit for Review');
  $this->post('/requests/'.$q->id.'/revise',['revision'=>'Additional explanation requested by the office.'])->assertRedirect();$this->assertSame('pending',$q->fresh()->status);
  $this->actingAs($this->person('cvu_head'))->get('/requests/'.$q->id)->assertOk();
 }
 public function test_student_can_join_different_seminars_but_not_duplicate_same_seminar():void {
  $u=$this->person();$events=[$this->seminar(),$this->seminar()];$this->actingAs($u);
  foreach($events as $event)$this->post('/cvu/submit/seminar',[...$this->identity($u),'details'=>['event_id'=>$event->id]])->assertRedirect();
  $this->assertDatabaseCount('attendances',2);$this->assertDatabaseCount('service_requests',2);
  $this->postJson('/cvu/submit/seminar',[...$this->identity($u),'details'=>['event_id'=>$events[0]->id]])->assertUnprocessable()->assertJsonValidationErrors('details.event_id');
  $this->assertDatabaseCount('attendances',2);$this->get('/apply/seminar')->assertOk()->assertSee('You have joined this seminar.');
  $this->get('/cvu/submitted/'.ServiceRequest::first()->id)->assertOk()->assertSee('Seminar Registration Submitted');
 }
 public function test_cancelled_seminar_registration_releases_slot_and_can_rejoin():void {
  $event=$this->seminar(['capacity'=>1]);$u=$this->person();$other=$this->person();$this->actingAs($u)->post('/cvu/submit/seminar',[...$this->identity($u),'details'=>['event_id'=>$event->id]])->assertRedirect();
  $q=ServiceRequest::firstOrFail();$this->post('/requests/'.$q->id.'/cancel')->assertRedirect();$this->assertSame('cancelled',Attendance::first()->status);
  $this->actingAs($other)->post('/cvu/submit/seminar',[...$this->identity($other),'details'=>['event_id'=>$event->id]])->assertRedirect();
  $this->actingAs($u)->postJson('/cvu/submit/seminar',[...$this->identity($u),'details'=>['event_id'=>$event->id]])->assertUnprocessable();
  $otherRequest=ServiceRequest::where('user_id',$other->id)->first();$this->actingAs($other)->post('/requests/'.$otherRequest->id.'/cancel')->assertRedirect();
  $this->actingAs($u)->post('/cvu/submit/seminar',[...$this->identity($u),'details'=>['event_id'=>$event->id]])->assertRedirect();
  $this->assertSame(1,Attendance::where('status','<>','cancelled')->count());
 }
 public function test_seminar_deadline_eligibility_and_legacy_join_do_not_bypass_rules():void {
  $u=$this->person();$event=$this->seminar(['metadata'=>['deadline'=>now()->subDay()->toDateString()]]);$this->actingAs($u);
  $this->postJson('/cvu/submit/seminar',[...$this->identity($u),'details'=>['event_id'=>$event->id]])->assertUnprocessable()->assertJsonValidationErrors('details.event_id');
  $event->update(['metadata'=>['courses'=>['BSN']]]);$this->postJson('/cvu/submit/seminar',[...$this->identity($u),'details'=>['event_id'=>$event->id]])->assertUnprocessable();
  $this->post('/events/'.$event->id,['action'=>'join'])->assertRedirect('/apply/seminar?event='.$event->id);$this->assertDatabaseCount('attendances',0);
 }
 public function test_readmission_returns_to_subject_details_after_validation_fails():void {
  $student=$this->person();
  $page='/apply/readmission';
  $details=[
   'instructor'=>'Instructor', 'subject'=>'NSTP', 'subject_code'=>'NSTP1', 'schedule'=>'Tuesday 1 PM',
   'semester'=>'1st Semester', 'academic_year'=>'2026-2027', 'description'=>'Too short',
  ];
  $this->actingAs($student)->from($page)->post('/cvu/submit/readmission',[...$this->identity($student),'details'=>$details])
   ->assertRedirect($page)->assertSessionHasErrors('details.description');
  $this->get($page)->assertOk()->assertSee('data-return-to-details',false)->assertSee('NSTP1');
  $this->assertDatabaseCount('service_requests',0);
 }
 public function test_clearance_save_request_info_and_past_slot_validation():void {
  $u=$this->person();$head=$this->person('cvu_head');$q=$this->request($u,'clearance',['appointment_date'=>now()->addDay()->toDateString(),'schedule'=>'10:00']);$this->actingAs($head);
  $this->post('/cvu/head/request/'.$q->id,['action'=>'save'])->assertRedirect();$this->assertSame('in_review',$q->fresh()->status);
  $this->post('/cvu/head/request/'.$q->id,['action'=>'info','remarks'=>'Bring the missing clearance requirements.'])->assertRedirect();$this->assertSame('needs_revision',$q->fresh()->status);
  $q->update(['status'=>'cancelled']);$date=today()->toDateString();$key=$date.' 00:00';DB::table('settings')->insert(['key'=>'staff.clearance-slots','value'=>json_encode([$key=>['date'=>$date,'start'=>'00:00','capacity'=>10,'open'=>true]])]);
  $this->actingAs($u)->postJson('/cvu/submit/clearance',[...$this->identity($u),'details'=>['appointment_slot'=>$key]])->assertUnprocessable();
 }
 public function test_duty_clock_hours_journal_locks_and_final_completion():void {
  $u=$this->person();$head=$this->person('cvu_head');$a=$this->record($u);$this->actingAs($u);
  $this->postJson('/community/journal',['module'=>4,'answer'=>str_repeat('Reflection ',6)])->assertUnprocessable();
  $this->post('/community/clock',['action'=>'in','photo'=>$this->photo()])->assertRedirect();$this->travel(120)->minutes();
  $this->post('/community/clock',['action'=>'out','photo'=>$this->photo()])->assertRedirect();$duty=Attendance::firstOrFail();$this->assertSame(0,$a->approvedMinutes());
  $this->actingAs($head)->post('/staff/attendance/'.$duty->id,['status'=>'approved','review_note'=>'Duty photographs and hours verified.'])->assertRedirect();$this->assertSame(120,$a->approvedMinutes());
  foreach(range(1,4) as $module){$this->actingAs($u)->post('/community/journal',['module'=>$module,'answer'=>str_repeat('My service reflection ',5)])->assertRedirect();$j=Journal::where('module',$module)->firstOrFail();$this->actingAs($head)->post('/staff/journals/'.$j->id,['status'=>'approved'])->assertRedirect();}
  $this->post('/staff/assignments/'.$a->id.'/complete')->assertRedirect();$this->assertSame('completed',$a->fresh()->status);
 }
 public function test_no_record_student_cannot_clock_or_open_another_students_record():void {
  $u=$this->person();$other=$this->person();$this->record($other);$this->actingAs($u);
  $this->get('/community-link')->assertSee('No Record Found');$this->get('/portal/values')->assertRedirect('/community-link');
  $this->postJson('/community/clock',['action'=>'in','photo'=>$this->photo()])->assertUnprocessable();
  $this->get('/cvu/head/community')->assertForbidden();$this->assertDatabaseCount('attendances',0);
 }
}
