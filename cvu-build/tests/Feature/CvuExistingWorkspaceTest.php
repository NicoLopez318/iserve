<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail,Storage,Route,DB};
use Illuminate\Http\UploadedFile;
use App\Models\{User,ServiceRequest,Event,Attendance,Assignment};

class CvuExistingWorkspaceTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void {
  parent::setUp();config(['hashing.bcrypt.rounds'=>4]);Mail::fake();Storage::fake('local');
  putenv('STAFF_PORTAL_URL=https://goldenrod-turkey-247730.hostingersite.com');
  // Exercise the existing Goldenrod controller without changing its source or UI.
  foreach(['StaffController','StaffPortalController'] as $class)if(!class_exists('Tests\\StaffBridge\\'.$class,false)){
   $source=file_get_contents(dirname(base_path()).'/staff_system/app/Http/Controllers/'.$class.'.php');
   eval('?>'.str_replace('namespace App\\Http\\Controllers;','namespace Tests\\StaffBridge; use App\\Http\\Controllers\\Controller;',$source));
  }
  Route::middleware('web')->group(function(){
   Route::post('/test-staff/review/{serviceRequest}',[\Tests\StaffBridge\StaffController::class,'review']);
   Route::post('/test-staff/note/{serviceRequest}',[\Tests\StaffBridge\StaffPortalController::class,'requestNote']);
   Route::post('/test-staff/seminars',[\Tests\StaffBridge\StaffPortalController::class,'seminar']);
   Route::post('/test-staff/slots',[\Tests\StaffBridge\StaffPortalController::class,'slots']);
   Route::post('/test-staff/attendance/{attendance}',[\Tests\StaffBridge\StaffController::class,'attendance']);
   Route::post('/test-staff/journal/{journal}',[\Tests\StaffBridge\StaffController::class,'journal']);
   Route::post('/test-staff/complete/{assignment}',[\Tests\StaffBridge\StaffController::class,'complete']);
   Route::post('/test-staff/assignment',[\Tests\StaffBridge\StaffController::class,'assignment']);
   Route::post('/test-staff/event/{event}',[\Tests\StaffBridge\StaffPortalController::class,'event']);
  });
 }
 protected function tearDown():void {putenv('STAFF_PORTAL_URL');parent::tearDown();}
 private function user(string $role='student'):User {return User::create(['name'=>'Flow Test','username'=>uniqid('flow'),'email'=>uniqid().'@example.test','student_id'=>uniqid('SID'),'password'=>'Password!12345','role'=>$role,'account_type'=>$role==='student'?'student':'employee','email_verified_at'=>now(),'values_verified_at'=>now(),'profile_complete'=>true,'course'=>'BSIT','year_level'=>'3rd Year','phone'=>'09123456789']);}
 private function identity(User $u):array {return ['name'=>$u->name,'student_id'=>$u->student_id,'course'=>$u->course,'year_level'=>$u->year_level,'contact'=>$u->phone,'confirmations'=>[1,1,1]];}
 private function request(User $u,string $kind,array $details=[]):ServiceRequest {return ServiceRequest::create(['user_id'=>$u->id,'kind'=>$kind,'ticket'=>uniqid('CVU-'),'details'=>$details]);}
 public function test_ivory_has_no_duplicate_management_surface_or_review_writes():void {
  $head=$this->user('cvu_head');$this->actingAs($head)->get('/staff')->assertRedirect('https://goldenrod-turkey-247730.hostingersite.com/workspace');
  foreach(['seminar'=>'seminars','clearance'=>'clearance','readmission'=>'readmission','community'=>'community'] as $old=>$page)$this->get('/cvu/head/'.$old)->assertRedirect('https://goldenrod-turkey-247730.hostingersite.com/workspace/'.$page);
  $q=$this->request($this->user(),'readmission');$this->get('/requests/'.$q->id)->assertRedirect('https://goldenrod-turkey-247730.hostingersite.com/workspace/request/'.$q->id);
  $this->post('/staff/requests/'.$q->id,['status'=>'approved'])->assertNotFound();$this->assertSame('pending',$q->fresh()->status);
  $this->post('/cvu/head/request/'.$q->id,['action'=>'issue'])->assertStatus(405);
  $this->assertFileDoesNotExist(resource_path('views/cvu/head.blade.php'));
  $this->actingAs($this->user())->get('/staff')->assertForbidden();$this->get('/cvu/head/seminar')->assertForbidden();
 }
 public function test_student_readmission_reaches_existing_staff_review_and_approved_slip():void {
  $u=$this->user();$head=$this->user('cvu_head');$this->actingAs($u)->post('/cvu/submit/readmission',[...$this->identity($u),'details'=>['instructor'=>'Instructor','subject'=>'NSTP','subject_code'=>'NSTP1','schedule'=>'Tuesday 1 PM','semester'=>'1st Semester','academic_year'=>'2026-2027','description'=>'Explanation of absence and request to return.']])->assertRedirect();
  $q=ServiceRequest::firstOrFail();$alert=$head->notifications()->firstOrFail()->data;$this->assertSame('https://goldenrod-turkey-247730.hostingersite.com/workspace/request/'.$q->id,$alert['url']);
  $this->actingAs($head)->post('/test-staff/note/'.$q->id,['review_note'=>'Please attend the interview.','interview_at'=>now()->addDay()->toDateTimeString()])->assertRedirect();
  $this->postJson('/test-staff/review/'.$q->id,['status'=>'unit_approved','review_note'=>'Interview has not taken place yet.'])->assertUnprocessable();$this->travel(2)->days();
  foreach([[$head,'unit_approved'],[$this->user('assistant'),'awaiting_admin'],[$this->user('admin'),'approved']] as [$actor,$status])$this->actingAs($actor)->post('/test-staff/review/'.$q->id,['status'=>$status,'review_note'=>'The request has been checked by the office.'])->assertRedirect();
  $this->actingAs($u)->get('/cvu/submitted/'.$q->id)->assertOk()->assertSee('Re-Admission Virtual Slip')->assertSee('Approved for Admission');
  $this->get('/requests/'.$q->id.'/slip')->assertOk()->assertSee('NSTP1')->assertSee('Print / Save PDF');
  $this->actingAs($this->user())->get('/cvu/submitted/'.$q->id)->assertForbidden();
 }
 public function test_existing_staff_seminar_creation_appears_in_ivory_and_students_can_join_multiple():void {
  $head=$this->user('cvu_head');$u=$this->user();
  foreach(['First Seminar','Second Seminar'] as $title){$this->actingAs($head)->post('/test-staff/seminars',['title'=>$title,'description'=>'Student seminar information.','venue'=>'University Hall','starts_at'=>now()->addDays(2)->toDateTimeString(),'ends_at'=>now()->addDays(2)->addHours(2)->toDateTimeString(),'capacity'=>1])->assertRedirect();$event=Event::where('title',$title)->firstOrFail();$this->actingAs($u)->get('/apply/seminar')->assertOk()->assertSee($title);$this->post('/cvu/submit/seminar',[...$this->identity($u),'details'=>['event_id'=>$event->id]])->assertRedirect();}
  $this->assertDatabaseCount('attendances',2);$this->assertDatabaseCount('service_requests',2);
  $event=Event::first();$this->postJson('/cvu/submit/seminar',[...$this->identity($u),'details'=>['event_id'=>$event->id]])->assertUnprocessable();
  $q=ServiceRequest::where('details->event_id',$event->id)->first();$this->post('/requests/'.$q->id.'/cancel')->assertRedirect();$this->assertSame('cancelled',$event->attendances()->first()->status);
  $this->post('/cvu/submit/seminar',[...$this->identity($u),'details'=>['event_id'=>$event->id]])->assertRedirect();$this->assertSame(1,$event->attendances()->where('status','pending')->count());
 }
 public function test_existing_staff_slots_connect_to_student_clearance_and_review_chain():void {
  $head=$this->user('cvu_head');$u=$this->user();$date=now()->addDay()->toDateString();$this->actingAs($head)->post('/test-staff/slots',['date'=>$date,'start'=>'10:00','end'=>'11:00','capacity'=>1,'open'=>1])->assertRedirect();
  $this->actingAs($u)->get('/apply/clearance')->assertOk()->assertSee($date);$this->post('/cvu/submit/clearance',[...$this->identity($u),'details'=>['appointment_slot'=>$date.' 10:00']])->assertRedirect();$q=ServiceRequest::firstOrFail();
  $other=$this->user();$this->actingAs($other)->postJson('/cvu/submit/clearance',[...$this->identity($other),'details'=>['appointment_slot'=>$date.' 10:00']])->assertUnprocessable();
  $this->actingAs($head)->post('/test-staff/review/'.$q->id,['status'=>'unit_approved','review_note'=>'Identity and hymn have been verified.','identity_verified'=>1,'hymn_completed'=>1])->assertRedirect();
  foreach([[$this->user('assistant'),'awaiting_admin'],[$this->user('admin'),'approved']] as [$actor,$status])$this->actingAs($actor)->post('/test-staff/review/'.$q->id,['status'=>$status,'review_note'=>'Clearance reviewed by the office.'])->assertRedirect();
  $this->actingAs($u)->get('/cvu/submitted/'.$q->id)->assertOk()->assertSee('completed');
 }
 public function test_revision_can_replace_original_readmission_evidence_and_notifies_existing_workspace():void {
  $u=$this->user();$head=$this->user('cvu_head');Storage::put('requests/original.pdf','original');$q=$this->request($u,'readmission',['description'=>'Original explanation.']);$q->update(['status'=>'needs_revision','files'=>['evidence'=>['path'=>'requests/original.pdf','name'=>'original.pdf']]]);
  $this->actingAs($u)->get('/requests/'.$q->id)->assertOk()->assertSee('documents[evidence]',false);
  $this->post('/requests/'.$q->id.'/revise',['revision'=>'Additional information supplied for the review.','documents'=>['evidence'=>UploadedFile::fake()->create('new.pdf',1,'application/pdf')]])->assertRedirect();
  $this->assertSame('pending',$q->fresh()->status);Storage::assertMissing('requests/original.pdf');Storage::assertExists($q->fresh()->files['evidence']['path']);$this->assertSame(1,$head->notifications()->count());
 }
 public function test_active_service_blocks_clearance_and_own_record_opens_portal():void {
  $u=$this->user();Assignment::create(['user_id'=>$u->id,'record_number'=>uniqid('CV-'),'offense'=>'Conduct','severity'=>'Minor','department'=>'IT','supervisor'=>'Head','required_minutes'=>120]);$this->actingAs($u)->get('/community-link')->assertOk()->assertSee('Active Record Found');$this->get('/portal/values')->assertOk();
  $this->postJson('/cvu/submit/clearance',[...$this->identity($u),'details'=>['appointment_slot'=>now()->addDay()->toDateString().' 10:00']])->assertUnprocessable();$this->assertDatabaseCount('service_requests',0);
 }
 public function test_other_cvu_transactions_use_existing_staff_chain_and_private_evidence():void {
  $u=$this->user();$head=$this->user('cvu_head');$assistant=$this->user('assistant');$admin=$this->user('admin');$outsider=$this->user();
  foreach(['complaint'=>['complaint_type'=>'Bullying','incident_date'=>today()->toDateString(),'location'=>'Campus','description'=>'Detailed concern submitted to the office.'],'lost'=>['item_name'=>'Wallet','incident_date'=>today()->toDateString(),'location'=>'Library','description'=>'A black wallet was lost at the library.','contact'=>'09123456789'],'found'=>['item_name'=>'Wallet','incident_date'=>today()->toDateString(),'location'=>'Library','description'=>'A wallet was found at the library.','contact'=>'09123456789'],'id-replacement'=>['replacement_reason'=>'Damaged','receipt_number'=>'OR-123','payment_amount'=>'125.50','incident_date'=>today()->toDateString(),'description'=>'The ID is damaged and needs replacement.']] as $kind=>$details){
   $payload=['details'=>$details,'declaration'=>1];if($kind==='found')$payload['documents']=['item_photo'=>UploadedFile::fake()->createWithContent('photo.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lGQAAAAASUVORK5CYII='))];
   $this->actingAs($u)->post('/apply/'.$kind,$payload)->assertRedirect();$q=ServiceRequest::where('kind',$kind)->firstOrFail();$this->get('/requests/'.$q->id)->assertOk();$this->actingAs($outsider)->get('/requests/'.$q->id)->assertForbidden();
   if($kind==='found'){$this->get('/requests/'.$q->id.'/files/item_photo')->assertForbidden();$this->actingAs($head)->get('/requests/'.$q->id.'/files/item_photo')->assertOk();}
   foreach([[$head,'unit_approved'],[$assistant,'awaiting_admin'],[$admin,'approved']] as [$actor,$status])$this->actingAs($actor)->post('/test-staff/review/'.$q->id,['status'=>$status,'review_note'=>'Reviewed through the existing office workflow.'])->assertRedirect();$this->assertSame('approved',$q->fresh()->status);
  }
 }
 public function test_full_seminar_can_finish_review_without_double_counting_own_registration():void {
  $head=$this->user('cvu_head');$u=$this->user();$e=Event::create(['type'=>'seminar','title'=>'Full Seminar','description'=>'An approved student seminar.','venue'=>'Hall','starts_at'=>now()->addDay(),'ends_at'=>now()->addDay()->addHours(2),'capacity'=>1,'status'=>'published']);
  $this->actingAs($u)->post('/cvu/submit/seminar',[...$this->identity($u),'details'=>['event_id'=>$e->id]])->assertRedirect();$q=ServiceRequest::firstOrFail();
  foreach([[$head,'unit_approved'],[$this->user('assistant'),'awaiting_admin'],[$this->user('admin'),'approved']] as [$actor,$status])$this->actingAs($actor)->post('/test-staff/review/'.$q->id,['status'=>$status,'review_note'=>'Registered participant has been reviewed.'])->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame('approved',$q->fresh()->status);$this->assertSame(1,$e->attendances()->count());$a=$e->attendances()->first();
  $this->actingAs($head)->postJson('/test-staff/attendance/'.$a->id,['status'=>'approved','review_note'=>'Cannot verify a future attendance.'])->assertUnprocessable();$this->travel(2)->days();
  $this->post('/test-staff/attendance/'.$a->id,['status'=>'approved','review_note'=>'Participation has been verified by facilitator.'])->assertRedirect()->assertSessionHasNoErrors();$this->assertSame('approved',$a->fresh()->status);$this->assertDatabaseCount('points',0);
  $this->postJson('/test-staff/attendance/'.$a->id,['status'=>'rejected','review_note'=>'Cannot review attendance twice.'])->assertUnprocessable();
 }
 public function test_returned_seminar_attendance_can_be_revised_and_verified():void {
  $u=$this->user();$head=$this->user('cvu_head');$e=Event::create(['type'=>'seminar','title'=>'Attendance Review','description'=>'Student attendance review.','venue'=>'Hall','starts_at'=>now()->subHours(2),'ends_at'=>now()->addHour(),'capacity'=>2,'status'=>'published']);
  $this->actingAs($u)->post('/cvu/submit/seminar',[...$this->identity($u),'details'=>['event_id'=>$e->id]])->assertRedirect();$q=ServiceRequest::firstOrFail();$a=$e->attendances()->first();
  $this->actingAs($head)->post('/test-staff/attendance/'.$a->id,['status'=>'rejected','review_note'=>'Please clarify the attendance information.'])->assertRedirect();$this->assertSame('needs_revision',$q->fresh()->status);
  $this->actingAs($u)->post('/requests/'.$q->id.'/revise',['revision'=>'The requested attendance clarification is supplied.'])->assertRedirect()->assertSessionHasNoErrors();$this->assertSame('pending',$a->fresh()->status);
  $this->actingAs($head)->post('/test-staff/attendance/'.$a->id,['status'=>'approved','review_note'=>'Attendance clarification has been verified.'])->assertRedirect()->assertSessionHasNoErrors();$this->assertSame('approved',$a->fresh()->status);
 }
 public function test_rejecting_seminar_request_releases_seat_and_cancelling_event_notifies_student():void {
  $u=$this->user();$head=$this->user('cvu_head');$e=Event::create(['type'=>'seminar','title'=>'Cancelled Seminar','description'=>'A seminar cancellation test.','venue'=>'Hall','starts_at'=>now()->addDay(),'ends_at'=>now()->addDay()->addHours(2),'capacity'=>1,'status'=>'published']);
  $this->actingAs($u)->post('/cvu/submit/seminar',[...$this->identity($u),'details'=>['event_id'=>$e->id]])->assertRedirect();$q=ServiceRequest::firstOrFail();
  $this->actingAs($head)->post('/test-staff/review/'.$q->id,['status'=>'rejected','review_note'=>'The registration was not accepted.'])->assertRedirect();$this->assertSame('cancelled',$e->attendances()->first()->status);
  $this->actingAs($u)->post('/cvu/submit/seminar',[...$this->identity($u),'details'=>['event_id'=>$e->id]])->assertRedirect();
  $data=['title'=>$e->title,'description'=>$e->description,'venue'=>$e->venue,'starts_at'=>$e->starts_at->toDateTimeString(),'ends_at'=>$e->ends_at->toDateTimeString(),'capacity'=>1,'status'=>'cancelled'];
  $this->actingAs($head)->post('/test-staff/event/'.$e->id,$data)->assertRedirect()->assertSessionHasNoErrors();$this->assertSame('cancelled',ServiceRequest::latest('id')->first()->status);$this->assertSame('cancelled',$e->attendances()->first()->status);$this->assertGreaterThan(0,$u->notifications()->count());
  $this->actingAs($u)->postJson('/cvu/submit/seminar',[...$this->identity($u),'details'=>['event_id'=>$e->id]])->assertUnprocessable();
 }
 public function test_clearance_reschedule_updates_both_slot_keys_and_releases_original_booking():void {
  $head=$this->user('cvu_head');$u=$this->user();$other=$this->user();$date=now()->addDay()->toDateString();$this->actingAs($head);
  foreach(['10:00','11:00'] as $start)$this->post('/test-staff/slots',['date'=>$date,'start'=>$start,'end'=>$start==='10:00'?'10:30':'11:30','capacity'=>1,'open'=>1])->assertRedirect();
  $this->actingAs($u)->post('/cvu/submit/clearance',[...$this->identity($u),'details'=>['appointment_slot'=>$date.' 10:00']])->assertRedirect();$q=ServiceRequest::firstOrFail();
  $this->actingAs($head)->post('/test-staff/note/'.$q->id,['review_note'=>'Appointment rescheduled at student request.','appointment_date'=>$date,'schedule'=>'11:00'])->assertRedirect()->assertSessionHasNoErrors();$this->assertSame($date.' 11:00',$q->fresh()->details['appointment_slot']);$this->assertSame('confirmed',$q->fresh()->details['appointment_status']);
  $this->actingAs($other)->post('/cvu/submit/clearance',[...$this->identity($other),'details'=>['appointment_slot'=>$date.' 10:00']])->assertRedirect()->assertSessionHasNoErrors();
  $third=$this->user();$this->actingAs($third)->postJson('/cvu/submit/clearance',[...$this->identity($third),'details'=>['appointment_slot'=>$date.' 11:00']])->assertUnprocessable();
 }
 public function test_community_verified_hours_and_all_journals_required_before_staff_completion():void {
  $u=$this->user();$head=$this->user('cvu_head');$a=Assignment::create(['user_id'=>$u->id,'record_number'=>uniqid('CV-'),'offense'=>'Conduct','severity'=>'Minor','department'=>'IT','supervisor'=>'Head','required_minutes'=>120]);$this->actingAs($head)->postJson('/test-staff/complete/'.$a->id)->assertUnprocessable();
  $duty=Attendance::create(['user_id'=>$u->id,'assignment_id'=>$a->id,'time_in'=>now()->subHours(2),'time_out'=>now(),'minutes'=>120,'status'=>'pending']);
  $this->post('/test-staff/attendance/'.$duty->id,['status'=>'approved','review_note'=>'Two hours of duty have been verified.'])->assertRedirect();$this->assertSame(120,$a->approvedMinutes());
  $this->postJson('/test-staff/complete/'.$a->id)->assertUnprocessable();foreach(range(1,4) as $module){$this->actingAs($u)->post('/community/journal',['module'=>$module,'answer'=>str_repeat('My service learning reflection. ',4)])->assertRedirect();$j=\App\Models\Journal::where('module',$module)->firstOrFail();$this->actingAs($head)->post('/test-staff/journal/'.$j->id,['status'=>'approved'])->assertRedirect();}
  $this->post('/test-staff/complete/'.$a->id)->assertRedirect()->assertSessionHasNoErrors();$this->assertSame('completed',$a->fresh()->status);
 }
 public function test_existing_seminar_registration_needs_no_community_clock_or_reflection():void {
  $u=$this->user();$head=$this->user('cvu_head');$event=Event::create(['type'=>'seminar','title'=>'Existing Seminar','description'=>'An existing seminar attendance record.','venue'=>'Hall','starts_at'=>now()->subHour(),'ends_at'=>now()->addHour(),'capacity'=>10,'status'=>'published']);$a=Attendance::create(['user_id'=>$u->id,'event_id'=>$event->id,'status'=>'pending']);
  $this->actingAs($this->user('slu_head'))->post('/test-staff/attendance/'.$a->id,['status'=>'approved','review_note'=>'Wrong office cannot verify attendance.'])->assertForbidden();
  $this->actingAs($head)->post('/test-staff/attendance/'.$a->id,['status'=>'approved','review_note'=>'The facilitator verified this student.'])->assertRedirect()->assertSessionHasNoErrors();$this->assertSame('approved',$a->fresh()->status);$this->assertNull($a->fresh()->time_out);
 }
}
