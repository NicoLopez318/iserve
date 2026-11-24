<dialog id="add-scholar" aria-labelledby="add-scholar-title" class="sfu-scholar-dialog">
    <button type="button" class="close-dialog" data-close aria-label="Close">×</button>
    <form method="post" action="/workspace/scholar" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="draft_id" value="{{ old('draft_id') }}">
        <h2 id="add-scholar-title">Add Scholar</h2>
        <div class="sfu-scholar-fields">
            <label>Full name<input name="full_name" value="{{ old('full_name') }}" placeholder="Enter your full name" required maxlength="120"></label>
            <label>Course<input name="course" value="{{ old('course') }}" placeholder="Enter your course" required maxlength="100"></label>
            <label>Student ID<input name="student_id" value="{{ old('student_id') }}" placeholder="Enter your student ID" list="sfu-scholar-students" required maxlength="40"></label>
            <label>Year<input name="year_level" value="{{ old('year_level') }}" placeholder="Enter year level" required maxlength="30"></label>
        </div>
        <datalist id="sfu-scholar-students">
            @foreach($students as $student)
                @if($student->student_id)
                    <option value="{{ $student->student_id }}" data-name="{{ $student->name }}" data-course="{{ $student->course }}" data-year="{{ $student->year_level }}">{{ $student->name }}</option>
                @endif
            @endforeach
        </datalist>
        @error('student_id')<p class="sfu-scholar-error" role="alert">{{ $message }}</p>@enderror

        <section class="sfu-scholar-documents" aria-labelledby="sfu-scholar-details-title">
            <h3 id="sfu-scholar-details-title">Details</h3>
            <div class="sfu-scholar-uploads">
                @foreach([
                    'application' => 'Tuition Fee Discount Application Form',
                    'photo' => 'Copy of 1x1 ID picture',
                    'registration' => 'Copy of the registration form, back to back',
                ] as $key => $label)
                    <div class="sfu-scholar-document">
                        <label for="sfu-scholar-{{ $key }}">
                            <span class="sfu-scholar-upload-label">{{ $label }}</span>
                            <span class="sfu-scholar-picker">
                                Upload <img src="/staff-assets/21-4782.svg" alt="">
                                <input id="sfu-scholar-{{ $key }}" name="{{ $key }}" type="file" accept="{{ $key === 'photo' ? '.jpg,.jpeg,.png' : '.pdf,.jpg,.jpeg,.png' }}" aria-describedby="sfu-scholar-{{ $key }}-name">
                            </span>
                        </label>
                        <span id="sfu-scholar-{{ $key }}-name" class="sfu-scholar-filename" aria-live="polite"></span>
                        @error($key)<p class="sfu-scholar-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
        </section>
        <div class="sfu-scholar-actions">
            <button type="submit" class="sfu-scholar-draft" name="intent" value="draft" formnovalidate>Save as Draft</button>
            <button type="submit" name="intent" value="submit">Add Scholar</button>
        </div>
    </form>
</dialog>
