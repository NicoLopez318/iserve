<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail,Hash};
use App\Models\{User,Assignment,ServiceRequest,Event,Attendance};
class CvuFlowTest extends TestCase {
 use RefreshDatabase;
 private function person($role='student',$data=[]){return User::create([...['name'=>'Example Student','username'=>uniqid('u'),'email'=>uniqid().'@example.test','password'=>'TestPassword!123','role'=>$role,'account_type'=>'student','student_id'=>uniqid('S'),'course'=>'BSIT','year_level'=>'3rd Year','phone'=>'09123456789','profile_complete'=>true,'email_verified_at'=>now()],...$data]);}
 private function record($u){return Assignment::create(['user_id'=>$u->id,'record_number'=>uniqid('CV-'),'offense'=>'Conduct','severity'=>'Minor','department'=>'IT','supervisor'=>'Head','required_minutes'=>120]);}
 private function identity($u){return ['name'=>$u->name,'student_id'=>$u->student_id,'course'=>$u->course,'year_level'=>$u->year_level,'contact'=>$u->phone,'confirmations'=>[1,1,1]];}
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
 public function test_readmission_submit_review_interview_issue_and_private_slip(){
  $u=$this->person();$this->actingAs($u)->get('/apply/readmission')->assertOk()->assertSee('Basic Information');
  $this->post('/cvu/submit/readmission',[...$this->identity($u),'details'=>['instructor'=>'Instructor','subject'=>'NSTP','subject_code'=>'NSTP101','schedule'=>'Tue 1PM','semester'=>'1st Semester','academic_year'=>'2026-2027','description'=>'A detailed explanation for the absence.']])->assertRedirect();
  $q=ServiceRequest::firstOrFail();$head=$this->person('cvu_head');$this->actingAs($head)->postJson('/cvu/head/request/'.$q->id,['action'=>'issue'])->assertStatus(422);
  $this->post('/cvu/head/request/'.$q->id,['action'=>'interview','interview_at'=>now()->addDay()->format('Y-m-d H:i'),'remarks'=>'Attend interview.'])->assertRedirect();
  $this->travel(2)->days();$this->post('/cvu/head/request/'.$q->id,['action'=>'issue','remarks'=>'Approved after interview.'])->assertRedirect();
  $this->post('/cvu/head/request/'.$q->id,['action'=>'send'])->assertRedirect();$this->assertNotNull($q->fresh()->details['sent_at']);
  $this->actingAs($u)->get('/cvu/submitted/'.$q->id)->assertOk()->assertSee('Re-Admission Virtual Slip');
  $this->actingAs($this->person())->get('/cvu/submitted/'.$q->id)->assertForbidden();
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