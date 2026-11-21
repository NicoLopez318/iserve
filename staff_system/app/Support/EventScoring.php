<?php
namespace App\Support;
use App\Models\{Event,Point,ServiceRequest};
use Illuminate\Support\Facades\DB;

class EventScoring
{
    public static function source(Event $event): array
    {
        return json_decode(DB::table('settings')->where('key','staff.event-source.'.$event->id)->value('value')??'[]',true)??[];
    }

    public static function register(Event $event, ServiceRequest $proposal): void
    {
        DB::table('settings')->updateOrInsert(['key'=>'staff.event-source.'.$event->id],['value'=>json_encode(['proposal_id'=>$proposal->id,'user_id'=>$proposal->user_id,'registration_closes_at'=>$event->starts_at->toIso8601String(),'report_due_at'=>$event->ends_at->copy()->addDays(7)->toIso8601String()])]);
    }

    public static function approveCompletion(ServiceRequest $report): void
    {
        $event=Event::lockForUpdate()->findOrFail($report->details['event_id']);
        $source=self::source($event);
        abort_unless(($source['user_id']??null)===$report->user_id && ServiceRequest::where('id',$source['proposal_id']??0)->where('status','approved')->exists(),422,'An approved proposal owned by the applicant is required.');
        abort_unless($event->ends_at->lte(now())&&$event->status!=='cancelled',422,'The event has not finished or was cancelled.');
        $rules=json_decode(DB::table('settings')->where('key','staff.points-rules')->value('value')??'[]',true)??[];
        $amount=(int)($rules['event']??0);
        if($amount)Point::firstOrCreate(['source'=>'event-completion:'.$event->id],['user_id'=>$report->user_id,'amount'=>$amount,'reason'=>'Completed approved event: '.$event->title]);
        $onTime=$report->created_at->lte(\Carbon\Carbon::parse($source['report_due_at']));
        $amount=$onTime?(int)($rules['report']??0):-(int)($rules['penalty']??0);
        if($amount)Point::firstOrCreate(['source'=>'completion-report:'.$event->id],['user_id'=>$report->user_id,'amount'=>$amount,'reason'=>($onTime?'On-time completion report: ':'Late completion report: ').$event->title]);
        $event->update(['status'=>'completed']);
    }
}
