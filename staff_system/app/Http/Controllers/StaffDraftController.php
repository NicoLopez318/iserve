<?php
namespace App\Http\Controllers;

use App\Support\StaffDrafts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffDraftController extends Controller
{
    public function store(Request $request, string $kind)
    {
        $roles = ['organization' => 'slu_head', 'assignment' => 'cvu_head'];
        abort_unless(isset($roles[$kind]) && $request->user()->role === $roles[$kind], 403);
        $fields = $kind === 'organization'
            ? ['name', 'acronym', 'college', 'type', 'adviser', 'academic_year', 'president_id', 'president_ref', 'description']
            : ['user_id', 'offense', 'severity', 'required_hours', 'department', 'supervisor', 'start_date', 'target_date', 'duty_start', 'duty_end', 'note'];
        $rules = array_fill_keys($fields, 'nullable|string|max:3000');
        if ($kind === 'organization') $rules['portal_visible'] = 'sometimes|boolean';
        $rules['draft_id'] = 'nullable|uuid';
        $values = $request->validate($rules);
        $id = $values['draft_id'] ?? null;
        StaffDrafts::assertOwned($request->user(), $kind, $id);
        unset($values['draft_id']);
        $details = $kind === 'organization' ? array_diff_key($values, ['academic_year' => true, 'portal_visible' => true]) : $values;
        abort_unless(count(array_filter($details, fn ($value) => $value !== null && trim($value) !== '')), 422, 'Enter some details before saving a draft.');
        $id ??= (string) Str::uuid();
        DB::table('settings')->updateOrInsert(['key' => StaffDrafts::prefix($request->user(), $kind).$id], ['value' => json_encode(['id' => $id, 'fields' => $values, 'saved_at' => now()->toIso8601String()])]);
        return back()->with('success', 'Draft saved. No live record was created.');
    }
}
