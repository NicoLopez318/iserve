<dialog id="create-organization" class="slu-org-create-dialog" aria-labelledby="slu-org-create-title">
    <button type="button" class="close-dialog" data-close aria-label="Close Add Organization">×</button>
    <form method="post" action="/workspace/organizations" data-slu-org-create data-verify-url="{{ route('staff.organizations.verify-leader') }}">
        @csrf
        <input type="hidden" name="draft_id" value="{{ old('draft_id') }}">
        <input type="hidden" name="president_id" value="{{ old('president_id') }}">
        <h2 id="slu-org-create-title">Add Organization</h2>
        <p class="slu-org-create-intro">Create the organization record and assign its initial management.</p>

        <div class="slu-org-create-card">
            <section aria-labelledby="slu-create-details">
                <h3 id="slu-create-details">Organization Details</h3>
                <div class="slu-create-fields">
                    <label>Organization Name
                        <input name="name" value="{{ old('name') }}" placeholder="Enter organization name" maxlength="150" required>
                        @error('name')<span class="sfu-review-error" role="alert">{{ $message }}</span>@enderror
                    </label>
                    <label>Acronym
                        <input name="acronym" value="{{ old('acronym') }}" placeholder="Enter acronym" pattern="[A-Z0-9]{2,10}" maxlength="10" required>
                        <small>2–10 uppercase letters or numbers (e.g. JITS)</small>
                        @error('acronym')<span class="sfu-review-error" role="alert">{{ $message }}</span>@enderror
                    </label>
                    <label>College / Department
                        <input name="college" list="slu-known-departments" value="{{ old('college') }}" placeholder="Select college or department" maxlength="100" required>
                    </label>
                    <label>Organization Type
                        <select name="type" required>
                            <option value="">Select organization type</option>
                            @foreach(['Academic','Non-Academic'] as $type)
                                <option @selected(old('type') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Adviser
                        <span class="slu-create-search"><img src="/staff-assets/21-4727.svg" alt=""><input name="adviser" list="slu-known-advisers" value="{{ old('adviser') }}" placeholder="Search faculty or staff" maxlength="120" required></span>
                        <small>Search iServe staff or enter the adviser’s name.</small>
                    </label>
                    <label>Academic Year
                        <select name="academic_year">
                            @foreach($orgForm['academicYears'] as $year)
                                <option @selected(old('academic_year', $orgForm['academicYear']) === $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <datalist id="slu-known-departments">@foreach($orgForm['departments'] as $department)<option value="{{ $department }}">@endforeach</datalist>
                <datalist id="slu-known-advisers">@foreach($orgForm['advisers'] as $adviser)<option value="{{ $adviser }}">@endforeach</datalist>
                <details class="slu-create-description">
                    <summary>Add a description (optional)</summary>
                    <label class="sr-only" for="slu-create-description">Description</label>
                    <textarea id="slu-create-description" name="description" maxlength="3000">{{ old('description') }}</textarea>
                </details>
            </section>

            <section aria-labelledby="slu-create-leader">
                <h3 id="slu-create-leader">Initial Student Leader</h3>
                <div class="slu-create-leader-fields">
                    <label>Student ID
                        <span class="slu-create-search"><img src="/staff-assets/21-4727.svg" alt=""><input name="president_ref" list="slu-eligible-presidents" value="{{ old('president_ref') }}" placeholder="Search student ID (e.g. 2023-12345)" maxlength="120" aria-describedby="slu-leader-message" required></span>
                    </label>
                    <button type="button" class="slu-create-verify" data-verify-organization-leader>Verify</button>
                </div>
                <small id="slu-leader-message" class="slu-create-help" data-org-leader-message role="status">Enter a student ID, then select Verify.</small>
                @error('president_id')<p class="sfu-review-error" role="alert">{{ $message }}</p>@enderror
                <datalist id="slu-eligible-presidents">
                    @foreach($students->filter(fn($student) => $student->hasVerifiedEmail() && !$student->organization_id) as $student)
                        <option value="{{ $student->student_id ?: 'Account #'.$student->id }}" data-user-id="{{ $student->id }}">{{ $student->name }}</option>
                    @endforeach
                </datalist>
                <div class="slu-create-student-preview" aria-live="polite">
                    <span class="slu-create-student-icon"><img src="/staff-assets/21-5331.svg" alt=""></span>
                    <dl>
                        <div><dt>Student Name</dt><dd data-org-leader-name>—</dd></div>
                        <div><dt>Program</dt><dd data-org-leader-program>—</dd></div>
                        <div><dt>Enrollment Status</dt><dd data-org-leader-enrollment>—</dd></div>
                    </dl>
                </div>
                <small class="slu-create-help">Student details come from iServe and are read-only here.</small>
            </section>

            <section aria-labelledby="slu-create-portal">
                <h3 id="slu-create-portal">Portal Access</h3>
                <div class="slu-create-portal-row">
                    <label class="slu-portal-switch">
                        <input type="hidden" name="portal_visible" value="0">
                        <input type="checkbox" id="slu-portal-visible" role="switch" name="portal_visible" value="1" aria-describedby="slu-portal-visible-help" @checked(old('portal_visible', true))>
                        <span aria-hidden="true"></span>
                    </label>
                    <p><label for="slu-portal-visible">Show in Student Leadership Portal</label><small id="slu-portal-visible-help">Allow students to select this organization on the leadership application.</small></p>
                </div>
            </section>
        </div>

        <div class="slu-create-actions">
            <button type="button" class="quiet" data-close>Cancel</button>
            <button type="submit" class="quiet" formaction="/workspace/drafts/organization" formnovalidate>Save as Draft</button>
            <button type="submit" class="sfu-button">Create Organization</button>
        </div>
    </form>
</dialog>
