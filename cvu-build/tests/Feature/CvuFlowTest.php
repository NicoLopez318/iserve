<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Mail,Hash,Storage};
use App\Models\{User,Assignment,ServiceRequest,Event,Attendance,Organization};
class CvuFlowTest extends TestCase {
 use RefreshDatabase;
 private function person($role='student',$data=[]){return User::create([...['name'=>'Example Student','username'=>uniqid('u'),'email'=>uniqid().'@example.test','password'=>'TestPassword!123','role'=>$role,'account_type'=>'student','student_id'=>uniqid('S'),'course'=>'BSIT','year_level'=>'3rd Year','phone'=>'09123456789','profile_complete'=>true,'email_verified_at'=>now()],...$data]);}
 private function record($u){return Assignment::create(['user_id'=>$u->id,'record_number'=>uniqid('CV-'),'offense'=>'Conduct','severity'=>'Minor','department'=>'IT','supervisor'=>'Head','required_minutes'=>120]);}
 private function identity($u){return ['name'=>$u->name,'student_id'=>$u->student_id,'course'=>$u->course,'year_level'=>$u->year_level,'contact'=>$u->phone,'confirmations'=>[1,1,1]];}
 public function test_student_feedback_is_visible_only_on_the_related_service_record():void
 {
  $student=$this->person(data:['values_verified_at'=>now()]);
  $outsider=$this->person(data:['values_verified_at'=>now()]);
  $first=$this->record($student);
  $second=$this->record($student);
  $this->record($outsider);
  $head=$this->person('cvu_head',['name'=>'CVU Reviewer']);
  $first->feedbackEntries()->create(['author_id'=>$head->id,'message'=>'Please bring the signed attendance slip.']);
  $page='/portal/values/community-service?assignment=';
  $this->actingAs($student)->get($page.$first->id)->assertOk()->assertSee('CVU Feedback')
   ->assertSee('CVU Reviewer')->assertSee('Please bring the signed attendance slip.');
  $this->get($page.$second->id)->assertOk()->assertSee('No feedback for this violation yet.')
   ->assertDontSee('Please bring the signed attendance slip.');
  $this->actingAs($outsider)->get($page.$first->id)->assertNotFound();
 }
 public function test_multiple_violations_keep_service_sessions_and_journals_separate():void
 {
  Storage::fake('local');
  $student=$this->person(data:['values_verified_at'=>now()]);
  $first=$this->record($student);
  $first->update(['offense'=>'Campus conduct']);
  $second=$this->record($student);
  $second->update(['offense'=>'Laboratory safety']);
  $foreign=$this->record($this->person());
  $image=fn()=>UploadedFile::fake()->createWithContent('duty.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a17kAAAAASUVORK5CYII='));
  $page='/portal/values/community-service';
  $clock='/community/clock';
  $this->travelTo(now()->setTime(8,0));
  $this->actingAs($student)->get('/portal/values/violations')->assertOk()->assertSee('Campus conduct')->assertSee('Laboratory safety');
  $this->get('/portal/values')->assertOk()->assertSee('2 ACTIVE');
  $this->get($page.'?assignment='.$foreign->id)->assertNotFound();
  $this->post($clock,['action'=>'in','assignment_id'=>$foreign->id,'photo'=>$image()])->assertNotFound();
  $this->from($page)->post($clock,['action'=>'in','photo'=>$image()])->assertSessionHasErrors('workflow');
  $this->post($clock,['action'=>'in','assignment_id'=>$first->id,'photo'=>$image()])->assertRedirect()->assertSessionHasNoErrors();
  $this->get($page.'?assignment='.$second->id)->assertOk()->assertSee('Another violation has an open duty session.');
  $this->post($clock,['action'=>'out','assignment_id'=>$second->id,'photo'=>$image()])->assertSessionHasErrors('workflow');
  $this->post($clock,['action'=>'in','assignment_id'=>$second->id,'photo'=>$image()])->assertSessionHasErrors('workflow');
  $this->travel(120)->minutes();
  $this->post($clock,['action'=>'out','assignment_id'=>$first->id,'photo'=>$image()])->assertRedirect()->assertSessionHasNoErrors();
  $session=$first->attendances()->sole();
  $this->assertSame(120,$session->minutes);
  $this->assertSame(0,$first->approvedMinutes());
  $session->update(['status'=>'approved']);
  $this->post($clock,['action'=>'in','assignment_id'=>$second->id,'photo'=>$image()])->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame(0,$second->approvedMinutes());
  $journal=['module'=>2,'answer'=>str_repeat('My service learning reflection. ',3)];
  $this->post('/community/journal',$journal+['assignment_id'=>$second->id])->assertSessionHasErrors('workflow');
  $this->post('/community/journal',$journal+['assignment_id'=>$first->id])->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame(2,$first->journals()->sole()->module);
  $this->assertSame(0,$second->journals()->count());
  $this->get($page)->assertOk()->assertSee('Time In is already recorded. Use Time Out to close the session.');
 }
 public function test_completed_community_attendance_notifies_only_the_enabled_unit_head():void {
  $disk=Storage::fake('notification-attendance');
  Storage::set('local',$disk);
  $student=$this->person(data:['values_verified_at'=>now()]);
  $this->record($student);
  $head=$this->person('cvu_head',['account_type'=>'employee']);
  $muted=$this->person('cvu_head',['account_type'=>'employee','preferences'=>['alerts'=>['submissions'=>false]]]);
  $photo=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a17kAAAAASUVORK5CYII=');

  $this->travelTo(now()->setTime(23,55));
  $page='/portal/values/community-service';
  $this->actingAs($student)->get($page)->assertOk()->assertSee('Record Time In first.');
  $this->post('/community/clock',['action'=>'in','photo'=>UploadedFile::fake()->createWithContent('in.png',$photo)])
   ->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame(0,$head->notifications()->count());
  $this->travel(10)->minutes();
  $this->get($page)->assertOk()->assertSee('Time In is already recorded. Use Time Out to close the session.')
   ->assertSee('name="action" value="out"',false);
  $this->post('/community/clock',['action'=>'out','photo'=>UploadedFile::fake()->createWithContent('out.png',$photo)])
   ->assertRedirect()->assertSessionHasNoErrors();
  $this->assertSame(10,Attendance::sole()->minutes);
  $notification=$head->notifications()->sole();
  $this->assertStringEndsWith('/workspace/community#attendance-verification',$notification->data['url']);
  $this->assertSame(0,$muted->notifications()->count());
  $disk->delete($disk->allFiles());
 }
 public function test_event_clock_explains_its_window_and_shows_saved_times():void {
  $organization=Organization::create(['name'=>'Outreach Club']);
  $student=$this->person(data:['organization_id'=>$organization->id]);
  $event=Event::create(['title'=>'Outreach Test','description'=>'Community outreach','venue'=>'Campus',
   'starts_at'=>now()->addMinutes(15),'ends_at'=>now()->addHour(),'status'=>'published','type'=>'leadership']);
  $page='/portal/leadership/events';
  $action='/events/'.$event->id;

  $this->actingAs($student)->post($action,['action'=>'join'])->assertRedirect()->assertSessionHasNoErrors();
  $this->get($page)->assertOk()->assertSee('Time In opens');
  $this->travelTo($event->starts_at->copy()->addMinute());
  $this->get($page)->assertOk()->assertSee('name="action" value="in"',false);
  $this->post($action,['action'=>'in'])->assertRedirect()->assertSessionHasNoErrors();
  $this->get($page)->assertOk()->assertSee('name="action" value="out"',false);
  $this->travel(5)->minutes();
  $this->post($action,['action'=>'out'])->assertRedirect()->assertSessionHasNoErrors();
  $record=Attendance::sole();
  $this->get($page)->assertOk()->assertSee($record->time_in->toAtomString())->assertSee($record->time_out->toAtomString());
  $this->assertSame(5,$record->minutes);
 }
 public function test_values_portal_requires_own_id_confirmation_and_active_record_without_extra_approval(){
  $u=$this->person();$a=$this->record($u);$this->actingAs($u)->get('/portal/values')->assertRedirect('/community-link');
  $this->postJson('/community/journal',['module'=>1,'answer'=>str_repeat('Reflection ',8)])->assertStatus(422);
  $this->post('/cvu/head/assignment/'.$a->id)->assertForbidden();
  $this->post('/community/confirm',['student_id'=>$u->student_id])->assertRedirect('/community-link?linked=1');
  $this->get('/community-link?linked=1')->assertOk()->assertSee('has been successfully linked.')->assertSee('data-link-result',false);
  $this->get('/community-link')->assertOk()->assertSee('Active Record Found')->assertSee('Continue to Community Service')->assertDontSee('awaiting Unit Head approval');
  foreach(['dashboard','violations','community-service','journal','profile','settings'] as $page)$this->get('/portal/values/'.$page)->assertOk();
  $this->assertNull($a->fresh()->approved_at);
  $this->post('/community/journal',['module'=>1,'answer'=>str_repeat('Reflection ',8)])->assertRedirect();
  $a->update(['status'=>'cancelled']);$u->unsetRelation('assignment');
  $this->get('/portal/values')->assertRedirect('/community-link');
 }
 public function test_readmission_slip_is_visible_only_to_the_owner_after_approval(){
  $u=$this->person();$this->actingAs($u)->get('/apply/readmission')->assertOk()->assertSee('Basic Information');
  $this->post('/cvu/submit/readmission',[...$this->identity($u),'details'=>['instructor'=>'Instructor','subject'=>'NSTP','subject_code'=>'NSTP101','schedule'=>'Tue 1PM','semester'=>'1st Semester','academic_year'=>'2026-2027','description'=>'A detailed explanation for the absence.']])->assertRedirect();
  $q=ServiceRequest::firstOrFail();
  $slip='/requests/'.$q->id.'/slip';
  $this->get($slip)->assertForbidden();
  $q->update(['status'=>'approved','details'=>[...$q->details,'issued_by'=>'Approving Officer','issued_at'=>'2026-09-15T10:00:00+08:00']]);
  $this->get('/cvu/submitted/'.$q->id)->assertOk()->assertSee($slip)->assertSee('Approved for Admission');
  $this->get('/requests/'.$q->id)->assertOk()->assertSee($slip);
  $this->get($slip)->assertOk()->assertSee('Approving Officer')->assertSee('Sep 15, 2026');
  $this->actingAs($this->person())->get('/cvu/submitted/'.$q->id)->assertForbidden();
  $this->get($slip)->assertForbidden();
 }
 public function test_clearance_slot_capacity_and_complete_checks(){
  $head=$this->person('cvu_head');$date=now()->addDay()->toDateString();$this->actingAs($head)->post('/cvu/head/availability',['date'=>$date,'start'=>'10:00','capacity'=>1,'open'=>1])->assertRedirect();
  $u=$this->person();$this->actingAs($u)->get('/apply/clearance')->assertOk()->assertSee($date);
  $this->postJson('/cvu/submit/clearance',[...$this->identity($u),'details'=>['appointment_slot'=>$date.' 10:00']])->assertRedirect();$q=ServiceRequest::firstOrFail();
  $other=$this->person();$this->actingAs($other)->postJson('/cvu/submit/clearance',[...$this->identity($other),'details'=>['appointment_slot'=>$date.' 10:00']])->assertStatus(422);
  $this->actingAs($head)->post('/cvu/head/request/'.$q->id,['action'=>'complete'])->assertSessionHasErrors();
  $this->post('/cvu/head/request/'.$q->id,['action'=>'confirm'])->assertRedirect();
  $this->post('/cvu/head/request/'.$q->id,['action'=>'complete','identity'=>1,'hymn'=>1])->assertRedirect();$this->assertSame('approved',$q->fresh()->status);
 }
 public function test_seminar_capacity_eligibility_and_head_access(){
  $head=$this->person('cvu_head');$date=now()->addDay()->toDateString();$this->actingAs($head)->post('/cvu/head/seminar',['title'=>'Orientation','description'=>'Orientation week','category'=>'Character & Values','date'=>$date,'start'=>'08:00','end'=>'12:00','venue'=>'Auditorium','capacity'=>1,'deadline'=>$date,'audience'=>'Students','facilitator'=>'Unit Head','attendance_requirement'=>'required','years'=>['3rd Year'],'courses'=>['BSIT'],'action'=>'publish'])->assertRedirect();
  $event=Event::firstOrFail();$u=$this->person();$this->actingAs($u)->get('/apply/seminar')->assertOk()->assertSee('Orientation');
  $this->get('/cvu/head/seminar')->assertForbidden();
  $this->postJson('/cvu/submit/seminar',[...$this->identity($u),'details'=>['event_id'=>$event->id]])->assertRedirect();
  $other=$this->person();$this->actingAs($other)->postJson('/cvu/submit/seminar',[...$this->identity($other),'details'=>['event_id'=>$event->id]])->assertStatus(422);
  $this->actingAs($head)->get('/cvu/head/seminar?event='.$event->id)->assertOk();$this->post('/cvu/head/attendance/'.Attendance::first()->id,['status'=>'approved'])->assertRedirect();
 }
 public function test_head_views_render_and_years_are_limited(){
  $head=$this->person('cvu_head');$this->actingAs($head);foreach(['readmission','clearance','seminar','community'] as $section)$this->get('/cvu/head/'.$section)->assertOk();
  $this->get('/cvu/head/seminar?create=1')->assertOk()->assertDontSee('5th Year')->assertDontSee('Graduate');
 }
 public function test_student_id_confirmation_has_no_code_and_cannot_link_another_account(){
  Mail::fake();$u=$this->person();$other=$this->person();$this->record($other);
  $this->actingAs($u)->get('/community-link')->assertOk()->assertSee('Confirm Student ID')->assertDontSee('Verification Code');
  $this->postJson('/community/confirm',['student_id'=>$other->student_id])->assertStatus(422);
  $this->assertNull($u->fresh()->values_verified_at);
  $this->post('/community/confirm',['student_id'=>$u->student_id])->assertRedirect('/community-link?linked=1');
  $this->get('/community-link')->assertOk()->assertSee('No Record Found')->assertDontSee('Active Record Found');
  $this->get('/portal/values')->assertRedirect('/community-link');
  $this->post('/community/send-code',['student_id'=>$u->student_id])->assertNotFound();
  $this->post('/community/verify',['code'=>'123456'])->assertNotFound();
  Mail::assertNothingSent();
 }
}
