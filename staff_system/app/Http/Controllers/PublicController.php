<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PublicController extends Controller {
 public static function units(): array {return [
  'leadership'=>['number'=>'01','name'=>'Student Leadership Unit','short'=>'Student Leadership','icon'=>'f941e.svg','description'=>'Student leadership and university organization services.'],
  'scholarship'=>['number'=>'02','name'=>'Scholarship and Financial Unit','short'=>'Scholarship and Financial','icon'=>'bb3ad.svg','description'=>"Access the unit’s confirmed financial-support transaction."],
  'values'=>['number'=>'03','name'=>'Character and Values Formation','short'=>'Character and Values Formation','icon'=>'a067a.svg','description'=>"Access the unit’s confirmed student-support transactions."]
 ];}
 public static function services(): array {return [
  ['unit'=>'leadership','title'=>'Student Leadership / University Organization','kind'=>'leadership','icon'=>'f941e.svg'],
  ['unit'=>'scholarship','title'=>'Tuition Discount','kind'=>'scholarship','icon'=>'bb3ad.svg'],
  ['unit'=>'values','title'=>'Complaints and Concerns','kind'=>'complaint','icon'=>'7ffe5.svg'],
  ['unit'=>'values','title'=>'Lost & Found','kind'=>'lost-found','icon'=>'0596a.svg'],
  ['unit'=>'values','title'=>'Re-admission','kind'=>'readmission','icon'=>'c1105.svg'],
  ['unit'=>'values','title'=>'ID Replacement','kind'=>'id-replacement','icon'=>'25646.svg'],
  ['unit'=>'values','title'=>'Community Service','kind'=>'community','icon'=>'f941e.svg'],
  ['unit'=>'values','title'=>'Seminars','kind'=>'seminar','icon'=>'19391.svg'],
  ['unit'=>'values','title'=>'Clearance','kind'=>'clearance','icon'=>'c1105.svg']
 ];}
 public function page(Request $r,string $page='home',?string $unit=null){
  abort_unless(in_array($page,['home','services','units','announcements','about']),404);
  $units=self::units();if($unit)abort_unless(isset($units[$unit]),404);
  $selected=$unit??$r->query('unit');$search=trim((string)$r->query('q',''));
  $services=collect(self::services())->filter(fn($s)=>(!$selected||$s['unit']===$selected)&&(!$search||str_contains(strtolower($s['title']),strtolower($search))));
  $announcements=DB::table('announcements')->whereNotNull('published_at')->where('published_at','<=',now())->when($selected,fn($q)=>$q->where('unit',$selected))->when($search,fn($q)=>$q->where(fn($q)=>$q->where('title','like','%'.$search.'%')->orWhere('body','like','%'.$search.'%')))->orderByDesc('published_at')->get();
  $announcements=$announcements->filter(fn($a)=>\App\Support\PortalContent::active($a,$r->user()));return view('public.page',compact('page','unit','units','selected','services','search','announcements'));
 }
 public function contentImage(Request $r,int $id){$item=DB::table('announcements')->find($id);abort_unless($item,404);$staff=$r->user()?->isStaff()??false;abort_unless($staff||($item->published_at&&\Carbon\Carbon::parse($item->published_at)->lte(now())&&\App\Support\PortalContent::active($item,$r->user())),404);$meta=\App\Support\PortalContent::metadata($id);$file=$meta['image']??null;abort_unless($file&&\Illuminate\Support\Facades\Storage::exists($file['path']),404);return \Illuminate\Support\Facades\Storage::response($file['path']);}
 public function announcement(Request $r){
  abort_unless($r->user()->isStaff(),403);
  $v=$r->validate(['title'=>'required|string|max:180','body'=>'required|string|max:10000','unit'=>'required|in:leadership,scholarship,values','publish'=>'nullable|boolean']);
  $roles=['leadership'=>'slu_head','scholarship'=>'sfu_head','values'=>'cvu_head'];abort_unless($r->user()->role==='admin'||$r->user()->role===$roles[$v['unit']],403);
  DB::table('announcements')->insert(['title'=>$v['title'],'body'=>$v['body'],'unit'=>$v['unit'],'user_id'=>$r->user()->id,'published_at'=>$r->boolean('publish')?now():null,'created_at'=>now(),'updated_at'=>now()]);
  return back()->with('success','Announcement saved.');
 }
}
