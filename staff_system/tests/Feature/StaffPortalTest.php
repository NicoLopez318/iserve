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
   ->assertOk()->assertSee('Membership Applications')->assertSee('MEMBERSHIP-REVIEW')->assertSee($preview)
   ->assertDontSee('class="drawer review-drawer"',false);
  $this->get($preview)->assertOk();
 }
 public function test_all_role_screens_render_with_real_empty_data():void{foreach(['admin','assistant','sfu_head','cvu_head','slu_head'] as $role){$actor=$this->actor($role);foreach(StaffPortalController::menus($role) as $page=>$label){$this->actingAs($actor)->get('/workspace/'.$page)->assertOk()->assertSee($label);}}}
 public function test_staff_only_login_and_pages():void{$student=$this->actor('student');$this->post('/login',['login'=>$student->email,'password'=>'TestingPassword123!'])->assertSessionHasErrors('login');$this->assertGuest();$this->actingAs($student)->get('/workspace')->assertForbidden();$sfu=$this->actor('sfu_head');$this->actingAs($sfu)->get('/workspace/organizations')->assertNotFound();}
 public function test_all_units_submit_and_revision_preserves_snapshot_history():void{$assistant=$this->actor('assistant');$admin=$this->actor('admin');foreach(['cvu_head','slu_head','sfu_head'] as $role){$head=$this->actor($role);$this->actingAs($head)->post('/workspace/reports',['title'=>'Monthly unit report','academic_year'=>'2026-2027','semester'=>'1st Semester','summary'=>str_repeat('Verified unit report. ',3),'date_from'=>'2026-09-01','date_to'=>'2026-09-30'])->assertRedirect();$report=Report::latest('id')->first();$this->assertSame('pending',$report->status);$this->actingAs($admin)->post('/workspace/report/'.$report->id,['status'=>'approved','review_note'=>'Reviewed all source records','check_content'=>1,'check_files'=>1,'check_source'=>1])->assertStatus(422);$this->actingAs($assistant)->post('/workspace/report/'.$report->id,['status'=>'needs_revision','review_note'=>'Please correct the attendance totals'])->assertRedirect()->assertSessionHasNoErrors();$this->actingAs($head)->post('/workspace/report/'.$report->id,['status'=>'pending','summary'=>str_repeat('Corrected unit report. ',3)])->assertRedirect()->assertSessionHasNoErrors();$this->assertCount(1,$report->fresh()->snapshot['versions']);$this->actingAs($assistant)->post('/workspace/report/'.$report->id,['status'=>'awaiting_admin','review_note'=>'Source records and attachments verified','check_content'=>1,'check_files'=>1,'check_source'=>1])->assertRedirect();$this->actingAs($admin)->post('/workspace/report/'.$report->id,['status'=>'assistant_revision','review_note'=>'Assistant clarification is required'])->assertRedirect();$this->actingAs($head)->post('/workspace/report/'.$report->id,['status'=>'pending','summary'=>str_repeat('Forbidden owner revision. ',3)])->assertStatus(422);$this->actingAs($assistant)->post('/workspace/report/'.$report->id,['status'=>'awaiting_admin','review_note'=>'Clarification added and source confirmed','check_content'=>1,'check_files'=>1,'check_source'=>1])->assertRedirect();$this->actingAs($admin)->post('/workspace/report/'.$report->id,['status'=>'approved','review_note'=>'Final report review complete','check_content'=>1,'check_files'=>1,'check_source'=>1])->assertRedirect();$this->assertSame('approved',$report->fresh()->status);$this->get('/workspace/report/'.$report->id)->assertOk();$this->get('/workspace/report/'.$report->id.'/receipt')->assertOk();}}
 public function test_cross_unit_private_files_and_reports_are_denied():void{Storage::fake('local');Storage::put('requests/evidence.pdf','PRIVATE');$owner=$this->actor('student');$sfu=$this->actor('sfu_head');$cvu=$this->actor('cvu_head');$q=ServiceRequest::create(['user_id'=>$owner->id,'ticket'=>'PRIVATE-CVU','kind'=>'complaint','details'=>['description'=>'Private case details'],'files'=>['evidence'=>['path'=>'requests/evidence.pdf','name'=>'evidence.pdf']]]);$this->actingAs($sfu)->get('/workspace/request/'.$q->id)->assertForbidden();$this->get('/requests/'.$q->id.'/files/evidence')->assertForbidden();$this->actingAs($cvu)->get('/workspace/request/'.$q->id)->assertOk();$this->get('/requests/'.$q->id.'/files/evidence')->assertOk();}
 public function test_forward_requires_review_checklist_and_zero_data_is_not_seeded():void{$head=$this->actor('cvu_head');$assistant=$this->actor('assistant');$report=Report::create(['user_id'=>$head->id,'title'=>'Empty period report','academic_year'=>'2026-2027','semester'=>'1st Semester','summary'=>str_repeat('No activity recorded. ',3),'snapshot'=>['records'=>[]]]);$this->actingAs($assistant)->post('/workspace/report/'.$report->id,['status'=>'awaiting_admin','review_note'=>'Verified empty period'])->assertSessionHasErrors(['check_content','check_files','check_source']);$this->assertSame(0,ServiceRequest::count());$this->assertSame('pending',$report->fresh()->status);}
}
