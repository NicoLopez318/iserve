<?php

namespace App\Http\Controllers;

use App\Models\{Event, ServiceRequest};
use App\Support\StaffAlerts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

class EventCompletionController extends Controller
{
    public function create(Request $request, ServiceRequest $serviceRequest)
    {
        $event = $this->eventForProposal($request, $serviceRequest);
        $report = $event ? $this->existingReport($serviceRequest, $event) : null;
        $canSubmit = $event && $event->status === 'published' && $event->ends_at->lte(now()) && !$report;

        return view('transactions.activity-completion', [
            'proposal' => $serviceRequest, 'event' => $event, 'report' => $report, 'canSubmit' => $canSubmit,
        ]);
    }

    public function store(Request $request, ServiceRequest $serviceRequest)
    {
        $event = $this->eventForProposal($request, $serviceRequest);
        abort_unless($event, 422, 'This proposal has no linked event.');
        $values = $request->validate([
            'summary' => 'required|string|min:30|max:5000',
            'report' => 'required|file|mimes:pdf,docx,png,jpg,jpeg|max:10240',
            'declaration' => 'accepted',
        ]);

        $file = $request->file('report');
        $path = $file->store('completion-reports');
        abort_unless($path, 500, 'The report could not be saved.');
        try {
            $report = DB::transaction(function () use ($serviceRequest, $event, $values, $path, $file) {
                $event = Event::lockForUpdate()->findOrFail($event->id);
                abort_unless($event->status === 'published' && $event->ends_at->lte(now()), 422, 'This event cannot accept an after-event report.');
                abort_if($this->existingReport($serviceRequest, $event), 422, 'A report has already been submitted for this event.');
                $report = ServiceRequest::create([
                    'user_id' => $serviceRequest->user_id,
                    'kind' => 'activity',
                    'ticket' => 'ECR-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
                    'details' => [
                        'title' => 'Completion Report: '.$event->title,
                        'description' => $values['summary'],
                        'completion_report' => true,
                        'event_id' => $event->id,
                        'organization_id' => $serviceRequest->details['organization_id'] ?? $serviceRequest->user->organization_id,
                    ],
                    'files' => ['completion_report' => ['path' => $path, 'name' => $file->getClientOriginalName()]],
                ]);
                DB::table('audit_logs')->insert([
                    'user_id' => $serviceRequest->user_id, 'action' => 'request.submitted',
                    'subject' => $report->ticket, 'created_at' => now(),
                ]);

                return $report;
            }, 3);
        } catch (\Throwable $error) {
            Storage::delete($path);
            throw $error;
        }

        StaffAlerts::role('slu_head', 'Event completion report: '.$report->ticket, '/workspace/request/'.$report->id, 'organization_reports');
        return redirect('/requests/'.$report->id)->with('success', 'After-event report submitted for review.');
    }

    private function eventForProposal(Request $request, ServiceRequest $proposal): ?Event
    {
        abort_unless($proposal->user_id === $request->user()->id && $request->user()->account_type === 'student', 403);
        abort_unless($proposal->kind === 'activity' && $proposal->status === 'approved' && empty($proposal->details['completion_report']), 404);

        $prefix = 'staff.event-source.';
        $key = DB::table('settings')->where('key', 'like', $prefix.'%')
            ->where('value->proposal_id', $proposal->id)->where('value->user_id', $proposal->user_id)->value('key');

        return $key ? Event::find((int) substr($key, strlen($prefix))) : null;
    }

    private function existingReport(ServiceRequest $proposal, Event $event): ?ServiceRequest
    {
        return ServiceRequest::where('user_id', $proposal->user_id)->where('kind', 'activity')
            ->where('details->completion_report', true)->where('details->event_id', $event->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])->latest('id')->first();
    }
}
