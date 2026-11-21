<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,DB};
use App\Models\{User,ServiceRequest,Point,Report};
class ApprovalRoutingTest extends TestCase {
 use RefreshDatabase;
 private function user(string $role):User {return User::create(['name'=>$role,'email'=>uniqid().'@example.test','username'=>uniqid('user'),'password'=>'TestPassword!123','role'=>$role,'account_type'=>$role==='student'?'student':'employee','profile_complete'=>true,'email_verified_at'=>now(),'preferences'=>['email'=>false,'in_app'=>true]]);}
 private function review(User $actor,ServiceRequest $q,string $status){return $this->actingAs($actor)->post('/staff/requests/'.$q->id,['status'=>$status,'review_note'=>'Verified workflow review note','discount_rate'=>$q->kind==='scholarship'?30:null,'identity_verified'=>$q->kind==='clearance'?1:0,'hymn_completed'=>$q->kind==='clearance'?1:0]);}
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
  $this->get('/staff')->assertOk()->assertSee('MONITOR-scholarship')->assertSee('MONITOR-complaint')->assertSee('MONITOR-achievement');
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
