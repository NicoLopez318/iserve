<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, Organization, ServiceRequest};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail, Storage, Route};
use Illuminate\Http\UploadedFile;

class LeadershipOnboardingTest extends TestCase
{
    use RefreshDatabase;
    private function student(array $attributes = []): User
    {
        return User::create([...['name'=>'Student','username'=>uniqid('user'),'email'=>uniqid().'@example.test','password'=>'Testing!12345','role'=>'student','account_type'=>'student'], ...$attributes]);
    }
    private function submission(Organization $organization): array
    {
        $files=[];
        foreach (['endorsement','candidacy','student_card','registration'] as $key) $files[$key]=UploadedFile::fake()->create($key.'.pdf',10,'application/pdf');
        return ['name'=>'New Student','student_id'=>'2026-1111','course'=>'BSIT','year_level'=>'1st Year','details'=>['organization_id'=>$organization->id,'position'=>'Secretary'],'documents'=>$files];
    }
    private function staffAcceptanceRoute(): void
    {
        // Exercise the actual staff controller against the same isolated database.
        require_once dirname(base_path()).'/staff_system/app/Http/Controllers/StaffPortalController.php';
        Route::middleware(['web','auth','staff'])->post('/test/organizations/{organization}/member', [\App\Http\Controllers\StaffPortalController::class,'organizationMember']);
    }
    public function test_registration_prompts_verification_and_leadership_form_remains_available(): void
    {
        Mail::fake();
        $this->post('/register',['email'=>'new@example.test','username'=>'newstudent','password'=>'Testing!12345','password_confirmation'=>'Testing!12345','role'=>'admin'])->assertRedirect('/email/verify');
        $this->assertSame('student',auth()->user()->role);
        $this->get('/')->assertOk()->assertSee('Choose a Unit')->assertSee('/apply/leadership');
        $form = $this->get('/apply/leadership')->assertOk()
            ->assertSee('Student Leadership Information')->assertDontSee('Profile Setup')
            ->assertSee('Fields marked with an asterisk')->assertSee('data-leadership-missing',false);
        $this->assertSame(6, substr_count($form->getContent(), 'class="leadership-required-mark" aria-hidden="true"'));
        $this->get('/portal/leadership')->assertRedirect('/apply/leadership');
    }

    public function test_leadership_applications_only_list_and_accept_visible_organizations(): void
    {
        Storage::fake('local');
        Organization::create(['name' => 'Legacy Organization']);
        $visible = Organization::create(['name' => 'Visible Organization']);
        $hidden = Organization::create(['name' => 'Hidden Organization']);
        foreach ([[$visible, true], [$hidden, false]] as [$organization, $isVisible]) {
            DB::table('settings')->insert([
                'key' => 'staff.organization.'.$organization->id,
                'value' => json_encode(['portal_visible' => $isVisible]),
            ]);
        }
        $this->actingAs($this->student())->get('/apply/leadership')->assertOk()
            ->assertSee('Legacy Organization')->assertSee('Visible Organization')->assertDontSee('Hidden Organization');
        $this->post('/apply/leadership', $this->submission($hidden))->assertSessionHasErrors('details.organization_id');
        $this->assertDatabaseCount('service_requests', 0);
        $this->post('/apply/leadership', $this->submission($visible))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($visible->id, ServiceRequest::sole()->details['organization_id']);
    }
    public function test_submission_acceptance_and_portal_access_across_both_apps(): void
    {
        Storage::fake('local'); $this->staffAcceptanceRoute();
        $organization=Organization::create(['name'=>'Student Organization']);
        $student=$this->student(); $this->actingAs($student);
        $this->post('/apply/leadership',$this->submission($organization))->assertRedirect();
        $application=ServiceRequest::sole();
        $this->assertSame('pending',$application->status);
        $this->assertNull($student->fresh()->organization_id);
        $this->assertCount(4,$application->files);
        foreach($application->files as $file) Storage::assertExists($file['path']);
        $this->get('/leadership/submitted/'.$application->id)->assertOk()->assertSee('Documents Submitted Successfully');
        $this->get('/portal/leadership')->assertRedirect('/leadership/submitted/'.$application->id);
        $this->get('/portal/leadership/events')->assertRedirect('/leadership/submitted/'.$application->id);
        $this->get('/apply/activity')->assertForbidden();
        $this->post('/apply/leadership',$this->submission($organization))->assertStatus(422);
        $this->assertDatabaseCount('service_requests',1);
        $this->assertCount(4,Storage::allFiles('requests'));
        $head=$this->student(['role'=>'slu_head']);
        $payload=['application_id'=>$application->id,'user_id'=>$student->id,'position'=>'Secretary'];
        $this->actingAs($head)->post('/test/organizations/'.$organization->id.'/member',$payload)->assertRedirect();
        $this->assertSame('approved',$application->fresh()->status);
        $this->assertSame($organization->id,$student->fresh()->organization_id);
        $this->assertDatabaseCount('notifications',2); // Submission receipt and member acceptance.
        $this->assertSame(1,$student->notifications()->where('data','like','%Your leadership application has been accepted%')->count());
        $this->post('/test/organizations/'.$organization->id.'/member',$payload)->assertStatus(422);
        $this->actingAs($student->fresh())->get('/portal/leadership')->assertOk();
        $this->get('/')->assertOk()->assertSee('href="/portal/leadership"',false);
        $this->get('/leadership/submitted/'.$application->id)->assertRedirect('/portal/leadership');
    }
    public function test_acceptance_rejects_wrong_role_organization_and_unsubmitted_members(): void
    {
        $this->staffAcceptanceRoute();
        $org=Organization::create(['name'=>'A']);$other=Organization::create(['name'=>'B']);
        $student=$this->student();
        $application=ServiceRequest::create(['user_id'=>$student->id,'ticket'=>'LEA-TEST','kind'=>'leadership','details'=>['organization_id'=>$org->id,'position'=>'Member'],'status'=>'pending']);
        $payload=['application_id'=>$application->id,'user_id'=>$student->id,'position'=>'Member'];
        foreach(['student','assistant','admin','sfu_head','cvu_head'] as $role) $this->actingAs($this->student(['role'=>$role]))->post('/test/organizations/'.$org->id.'/member',$payload)->assertForbidden();
        $head=$this->student(['role'=>'slu_head']);
        $this->actingAs($head)->post('/test/organizations/'.$other->id.'/member',$payload)->assertStatus(422);
        $this->post('/test/organizations/'.$org->id.'/member',['user_id'=>$student->id,'position'=>'Member'])->assertStatus(422);
        $this->assertNull($student->fresh()->organization_id);
        foreach(['slu_head','assistant','admin'] as $role){
            $actor=$this->student(['role'=>$role]);
            $this->assertArrayNotHasKey('approved',$application->reviewActions($actor));
            $this->assertArrayNotHasKey('unit_approved',$application->reviewActions($actor));
        }
        $this->actingAs($this->student())->get('/leadership/submitted/'.$application->id)->assertForbidden();
    }
    public function test_incomplete_submission_does_not_create_membership_or_request(): void
    {
        Storage::fake('local');$student=$this->student();$this->actingAs($student);
        $this->post('/apply/leadership',['name'=>'Student'])->assertSessionHasErrors([
            'student_id',
            'documents.endorsement'=>'Upload the Endorsement letter.',
            'documents.candidacy'=>'Upload the Certificate of candidacy.',
            'documents.student_card'=>'Upload the Student ID.',
            'documents.registration'=>'Upload the Enrollment form.',
        ]);
        $this->assertDatabaseCount('service_requests',0);
        $this->assertNull($student->fresh()->organization_id);
    }
}
