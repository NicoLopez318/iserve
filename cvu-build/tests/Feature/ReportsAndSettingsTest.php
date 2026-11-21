<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\{User,Report,ServiceRequest};
class ReportsAndSettingsTest extends TestCase {
 use RefreshDatabase;
 private function user(string $role):User{return User::create(['name'=>$role,'email'=>$role.'@example.test','username'=>$role,'role'=>$role,'account_type'=>'student','password'=>'TestPassword!123','profile_complete'=>true,'email_verified_at'=>now()]);}
 public function test_reports_preserve_snapshot_and_only_admin_can_review_once():void {
  $student=$this->user('student');$sfu=$this->user('sfu_head');$admin=$this->user('admin');$cvu=$this->user('cvu_head');
  $q=ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'SCH-test','kind'=>'scholarship','status'=>'approved','details'=>['academic_year'=>'2026-2027','semester'=>'1st Semester','discount_rate'=>30]]);
  $this->actingAs($sfu)->post('/staff/reports',['title'=>'Semester awards','academic_year'=>'2026-2027','semester'=>'1st Semester','summary'=>'Verified scholarship awards for the current academic term.'])->assertRedirect();
  $report=Report::first();$this->assertCount(1,$report->snapshot);$this->assertSame(30,$report->snapshot[0]['rate']);$q->update(['details'=>['discount_rate'=>50]]);$this->assertSame(30,$report->fresh()->snapshot[0]['rate']);
  $this->actingAs($cvu)->get('/staff/reports')->assertForbidden();$this->post('/staff/reports/'.$report->id,['status'=>'approved','review_note'=>'Approved semester report'])->assertForbidden();
  $this->actingAs($admin)->post('/staff/reports/'.$report->id,['status'=>'approved','review_note'=>'Attempt to bypass assistant'])->assertSessionHasErrors('workflow');
  $this->actingAs($this->user('assistant'))->post('/staff/reports/'.$report->id,['status'=>'awaiting_admin','review_note'=>'Verified and forwarded report'])->assertRedirect();
  $this->actingAs($admin)->get('/staff/reports')->assertOk();$this->post('/staff/reports/'.$report->id,['status'=>'approved','review_note'=>'Approved semester report'])->assertRedirect();$this->assertSame('approved',$report->fresh()->status);
  $this->post('/staff/reports/'.$report->id,['status'=>'rejected','review_note'=>'Attempt a second decision'])->assertSessionHasErrors('workflow');
 }
 public function test_fee_settings_are_admin_only_and_rounding_totals_match_fee_lines():void{
  $admin=$this->user('admin');$student=$this->user('student');$sfu=$this->user('sfu_head');$payload=['tuition'=>'1.01','laboratory'=>'1.01','miscellaneous'=>'1.01','academic_year'=>'2026-2027','semester'=>'1st Semester'];
  $this->actingAs($sfu)->post('/staff/settings',$payload)->assertForbidden();$this->actingAs($admin)->post('/staff/settings',$payload)->assertRedirect();$this->assertSame('101',DB::table('settings')->where('key','tuition')->value('value'));
  ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'SCH-rounding','kind'=>'scholarship','status'=>'approved','details'=>['discount_rate'=>30]]);
  $this->actingAs($student)->get('/portal/scholar/tuition')->assertOk()->assertSee('0.90')->assertSee('2.13');
 }
 public function test_seeded_student_views_and_both_other_profile_types_render():void{
  $this->seed();$student=User::where('username','student')->first();$this->actingAs($student);
  foreach(['/portal/values/community-service','/portal/values/journal','/portal/leadership/dashboard','/portal/leadership/events','/portal/leadership/organization','/portal/leadership/members'] as $path)$this->get($path)->assertOk();
  foreach(['employee','guardian'] as $role){$u=User::where('username',$role)->first();$this->actingAs($u)->get('/profile/setup')->assertOk();$this->get('/home')->assertOk();$this->get('/portal/values/dashboard')->assertForbidden();}
 }
 public function test_request_notifications_respect_disabled_preferences():void{
  $student=$this->user('student');$student->update(['preferences'=>['in_app'=>false,'email'=>false]]);$staff=$this->user('cvu_head');
  $q=ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'COM-preferences','kind'=>'complaint','details'=>['description'=>'Test request']]);
  $this->actingAs($staff)->post('/staff/requests/'.$q->id,['status'=>'approved','review_note'=>'Request review completed.'])->assertRedirect();
  $this->assertSame('unit_approved',$q->fresh()->status);$this->assertSame(0,$student->notifications()->count());
 }
}
