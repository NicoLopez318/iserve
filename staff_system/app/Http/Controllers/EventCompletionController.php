<?php
namespace App\Http\Controllers;
use App\Models\{Event,ServiceRequest};
use App\Support\{EventScoring,StaffAlerts};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;

class EventCompletionController extends Controller
{
    public function store(Request $request,Event $event)
    {
        $source=EventScoring::source($event);
        abort_unless(($source['user_id']??null)===$request->user()->id,403);
        $values=$request->validate(['summary'=>'required|string|min:30|max:5000','report'=>'required|file|mimes:pdf,docx,png,jpg,jpeg|max:10240','declaration'=>'accepted']);
        $file=$request->file('report');$path=$file->store('completion-reports');
        try{$item=DB::transaction(function()use($event,$request,$values,$file,$path,$source){
            $event=Event::lockForUpdate()->findOrFail($event->id);
            abort_unless($event->ends_at->lte(now())&&!in_array($event->status,['cancelled','completed']),422,'This event cannot accept a completion report.');
            abort_if(ServiceRequest::where('user_id',$request->user()->id)->where('kind','activity')->where('details->completion_report',true)->where('details->event_id',$event->id)->whereIn('status',['pending','in_review','needs_revision','unit_approved','awaiting_admin','approved'])->exists(),422,'A completion report already exists. Revise the existing report when requested.');
            $proposal=ServiceRequest::find($source['proposal_id']??null);
            $item=ServiceRequest::create(['user_id'=>$request->user()->id,'kind'=>'activity','ticket'=>'ECR-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),'details'=>['title'=>'Completion Report: '.$event->title,'description'=>$values['summary'],'completion_report'=>true,'event_id'=>$event->id,'organization_id'=>$proposal?->details['organization_id']??$request->user()->organization_id],'files'=>['completion_report'=>['path'=>$path,'name'=>$file->getClientOriginalName()]]]);
            DB::table('audit_logs')->insert(['user_id'=>$request->user()->id,'action'=>'request.submitted','subject'=>$item->ticket,'created_at'=>now()]);
            return $item;
        });}catch(\Throwable $error){Storage::delete($path);throw $error;}
        StaffAlerts::role('slu_head','Event completion report: '.$item->ticket,'/workspace/request/'.$item->id,'organization_reports');
        return redirect('/requests/'.$item->id)->with('success','Completion report submitted for SLU, Assistant and Admin review.');
    }
}
