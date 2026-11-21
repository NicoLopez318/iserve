<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
class PublicWebsiteTest extends TestCase {
 use RefreshDatabase;
 public function test_public_screens_and_service_filters():void {
  foreach(['/','/services','/units/leadership','/units/scholarship','/units/values','/announcements','/about'] as $url)$this->get($url)->assertOk()->assertSee('Student Portal');
  $this->get('/services?q=tuition')->assertOk()->assertSee('Tuition Discount')->assertSee('1 services')->assertDontSee('Complaints and Concerns');
  $this->get('/services?unit=values')->assertOk()->assertSee('7 services')->assertDontSee('Tuition Discount');
  $this->get('/units/invalid')->assertNotFound();
  $this->get('/apply/scholarship')->assertRedirect('/login');
 }
 public function test_notices_only_publish_with_authorized_office_and_publication_time():void {
  $student=User::create(['name'=>'Student','email'=>'s@test.test','username'=>'s','password'=>'password12345','account_type'=>'student','role'=>'student']);
  $this->actingAs($student)->post('/staff/announcements',['title'=>'Test','body'=>'Private student notice','unit'=>'values','publish'=>1])->assertForbidden();
  $staff=User::create(['name'=>'Head','email'=>'head@test.test','username'=>'head','password'=>'password12345','account_type'=>'employee','role'=>'cvu_head']);
  $this->actingAs($staff)->post('/staff/announcements',['title'=>'Wrong unit','body'=>'Office information','unit'=>'scholarship','publish'=>1])->assertForbidden();
  $this->post('/staff/announcements',['title'=>'Draft notice','body'=>'Internal draft notice','unit'=>'values'])->assertRedirect();
  $this->post('/staff/announcements',['title'=>'Published notice','body'=>'Public office information','unit'=>'values','publish'=>1])->assertRedirect();
  $this->get('/announcements')->assertSee('Published notice')->assertDontSee('Draft notice');
  $this->get('/announcements?unit=scholarship')->assertDontSee('Published notice');
  DB::table('announcements')->where('title','Published notice')->update(['published_at'=>now()->addDay()]);
  $this->get('/announcements')->assertDontSee('Published notice');
 }
}
