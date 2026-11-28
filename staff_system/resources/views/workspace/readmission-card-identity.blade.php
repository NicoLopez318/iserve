@php
    $details = $item->details ?? [];
    $applicant = $details['applicant'] ?? [];
    $facts = [
        $referenceLabel => $item->ticket,
        'Student ID' => $applicant['student_id'] ?? $item->user->student_id,
        'Student Name' => $applicant['name'] ?? $item->user->name,
        'Course' => $applicant['course'] ?? $item->user->course,
        'Year' => $applicant['year_level'] ?? $item->user->year_level,
        'Instructor' => $details['instructor'] ?? null,
    ];
@endphp
<dl class="readmission-card-grid">
    @foreach($facts as $label => $value)
        <div><dt>{{ $label }}</dt><dd>{{ filled($value) ? $value : '—' }}</dd></div>
    @endforeach
</dl>
