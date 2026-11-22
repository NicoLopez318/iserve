<?php
namespace App\Http\Controllers;

use App\Models\{Organization, ServiceRequest, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LeadershipOnboardingController extends Controller
{
    public function form(Request $request)
    {
        abort_unless(!$request->user()->isStaff() && $request->user()->account_type === 'student', 403);
        if ($request->user()->organization_id) return redirect('/portal/leadership');
        $pending = $request->user()->requests()->where('kind', 'leadership')
            ->whereIn('status', ['pending', 'in_review', 'unit_approved', 'awaiting_admin'])->latest()->first();
        if ($pending) return redirect('/leadership/submitted/'.$pending->id);
        $revision = $request->user()->requests()->where('kind', 'leadership')->where('status', 'needs_revision')->latest()->first();
        if ($revision) return redirect('/requests/'.$revision->id);
        return view('transactions.leadership', ['organizations' => Organization::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless(!$user->isStaff() && $user->account_type === 'student', 403);
        $rules = [
            'name' => 'required|string|max:120',
            'student_id' => ['required','string','max:40',Rule::unique('users')->ignore($user->id)],
            'course' => 'required|string|max:100',
            'year_level' => 'required|in:1st Year,2nd Year,3rd Year,4th Year,5th Year,Graduate',
            'details.organization_id' => 'required|integer|exists:organizations,id',
            'details.position' => 'required|string|max:100',
        ];
        $documentAttributes = [];
        foreach (config('portal.documents.leadership') as $key => $label) {
            $rules['documents.'.$key] = 'required|file|mimes:pdf,jpg,jpeg,png|max:5120';
            $documentAttributes['documents.'.$key] = $label;
        }
        $validated = $request->validate($rules, ['documents.*.required' => 'Upload the :attribute.'], $documentAttributes);
        $files = [];
        try {
            foreach ($request->file('documents', []) as $key => $file) {
                if (isset($rules['documents.'.$key])) $files[$key] = ['path' => $file->store('requests'), 'name' => $file->getClientOriginalName()];
            }
            $application = DB::transaction(function () use ($user, $validated, $files) {
                $owner = User::lockForUpdate()->findOrFail($user->id);
                abort_if($owner->organization_id, 422, 'You are already an accepted organization member.');
                abort_if($owner->requests()->where('kind', 'leadership')->whereIn('status', ['pending','in_review','needs_revision','unit_approved','awaiting_admin'])->exists(), 422, 'Your application is already awaiting review.');
                $owner->update([...array_intersect_key($validated, array_flip(['name','student_id','course','year_level'])), 'profile_complete' => true]);
                return ServiceRequest::create([
                    'user_id' => $owner->id, 'kind' => 'leadership', 'status' => 'pending',
                    'ticket' => 'LEA-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
                    'details' => [...$validated['details'], 'applicant' => array_intersect_key($validated, array_flip(['name','student_id','course','year_level']))],
                    'files' => $files,
                ]);
            });
        } catch (\Throwable $error) {
            foreach ($files as $file) Storage::delete($file['path']);
            throw $error;
        }
        \App\Support\StaffAlerts::role('slu_head', 'New leadership application: '.$application->ticket, '/workspace/organizations?review='.$application->id);
        if ($user->preferences['in_app'] ?? true) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'request',
                'data' => json_encode([
                    'title' => 'Leadership application received: '.$application->ticket,
                    'url' => '/requests/'.$application->id,
                    'message' => 'Your application has been submitted and is waiting for review by the Student Leadership Unit Head.',
                ]),
            ]);
        }
        return redirect('/leadership/submitted/'.$application->id);
    }

    public function submitted(Request $request, ServiceRequest $serviceRequest)
    {
        abort_unless($serviceRequest->user_id === $request->user()->id && $serviceRequest->kind === 'leadership', 403);
        if ($serviceRequest->status === 'approved' && $request->user()->organization_id) return redirect('/portal/leadership');
        if (!in_array($serviceRequest->status, ['pending','in_review','unit_approved','awaiting_admin'])) return redirect('/requests/'.$serviceRequest->id);
        return view('transactions.leadership-submitted', ['application' => $serviceRequest]);
    }
}
