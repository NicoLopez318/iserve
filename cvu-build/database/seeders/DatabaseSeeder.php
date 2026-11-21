<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\{User,Organization,Event,Assignment,Point,ServiceRequest};
class DatabaseSeeder extends Seeder {
 public function run():void{
  if(!app()->environment(['local','testing']))throw new \RuntimeException('Demo seeding is restricted to local and test environments.');
  $org=Organization::firstOrCreate(['name'=>'Funtime Organization'],['description'=>'Connect, serve, and grow as a student leader.']);
  foreach(['admin'=>'System Administrator','sfu_head'=>'Scholarship Head','cvu_head'=>'Community Values Head','slu_head'=>'Student Leadership Head','student'=>'Franco Fernandez','employee'=>'Juan Dela Cruz','guardian'=>'Maria Fernandez'] as $role=>$name){
   $staff=in_array($role,['admin','sfu_head','cvu_head','slu_head']);
   $u=User::firstOrCreate(['email'=>$role.'@iserve.test'],['name'=>$name,'username'=>$role,'password'=>'DemoPass!2026','role'=>$staff?$role:'student','account_type'=>$staff?'employee':$role,'profile_complete'=>true,'email_verified_at'=>now(),'student_id'=>$role==='student'?'2023-2636':null,'course'=>$role==='student'?'BSIT':null,'year_level'=>$role==='student'?'3rd Year':null,'office'=>$role==='employee'?'IT Department':null,'organization_id'=>$role==='student'?$org->id:null,'position'=>$role==='student'?'President':null]);
   if($role==='student'){
    Assignment::firstOrCreate(['record_number'=>'CV-2026-DEMO'],['user_id'=>$u->id,'offense'=>'Campus conduct reminder','severity'=>'Minor','department'=>'IT Department','supervisor'=>'Juan Dela Cruz','required_minutes'=>1200,'note'=>'Demonstration record. Hours require staff verification.']);
    Point::firstOrCreate(['source'=>'demo:welcome'],['user_id'=>$u->id,'amount'=>150,'reason'=>'Demo leadership orientation']);
    ServiceRequest::firstOrCreate(['ticket'=>'SCH-2026-DEMO'],['user_id'=>$u->id,'kind'=>'scholarship','status'=>'approved','details'=>['student_type'=>'Undergraduate','semester'=>'1st Semester','academic_year'=>'2026–2027','reason'=>'Demonstration scholarship','discount_rate'=>30],'review_note'=>'Demo award approved.']);
   }
  }
  foreach(['University Clean Drive','Leadership Seminar','Community Outreach'] as $i=>$title)Event::firstOrCreate(['title'=>$title],['description'=>'Join fellow students in service, learning, and leadership.','venue'=>'University Chapel','starts_at'=>now()->addDays($i+2)->setTime(8,0),'ends_at'=>now()->addDays($i+2)->setTime(12,0),'points'=>50+25*$i,'capacity'=>100,'type'=>$i===1?'seminar':'leadership']);
 }
}
