@php
    $account = auth()->user();
    $preferences = $account->preferences ?? [];
    $alerts = $preferences['alerts'] ?? [];
    $notificationOptions = [
        'submissions' => 'New application submitted',
        'resubmitted' => 'Incomplete requirements resubmitted',
        'feedback' => 'Admin action completed',
        'report_returned' => 'Report returned by Assistant',
        'report_acknowledged' => 'Report acknowledged by Admin',
    ];
    $lastLogin = $audit->first();
    $loginFormat = (($preferences['date_format'] ?? '') === 'dd/mm/yyyy' ? 'd/m/Y' : 'm/d/Y')
        .' · '.(($preferences['time_format'] ?? '') === '24' ? 'H:i' : 'g:i A');
@endphp

<form class="sfu-settings-form" method="post" action="/workspace/settings" data-sfu-settings>
    @csrf
    <div class="sfu-settings-grid">
        <section class="sfu-panel sfu-settings-panel" aria-labelledby="sfu-account-title">
            <h2 id="sfu-account-title">Account</h2>
            <div class="sfu-settings-account">
                <div class="sfu-settings-avatar"><img src="/staff-assets/21-5331.svg" alt=""></div>
                <div class="sfu-account-info">
                    <strong>{{ $account->name }}</strong>
                    <p>Scholarships &amp; Financial Unit Head</p>
                    <a href="mailto:{{ $account->email }}"><img src="/staff-assets/21-5332.svg" alt="">{{ $account->email }}</a>
                </div>
            </div>
            <button type="button" class="sfu-button sfu-outline sfu-setting-panel-action" data-dialog="change-password">Change Password</button>
        </section>

        <section class="sfu-panel sfu-settings-panel" aria-labelledby="sfu-preferences-title">
            <h2 id="sfu-preferences-title">System Preferences</h2>
            <div class="sfu-settings-selects">
                @foreach([
                    'theme' => ['Theme', ['Light' => 'Light', 'Dark' => 'Dark']],
                    'date_format' => ['Date Format', ['mm/dd/yyyy' => 'mm/dd/yyyy', 'dd/mm/yyyy' => 'dd/mm/yyyy']],
                    'time_format' => ['Time Format', ['12' => '12-Hour (AM/PM)', '24' => '24-Hour']],
                ] as $key => [$label, $options])
                    <label class="sfu-setting-row">
                        <span>{{ $label }}</span>
                        <select name="{{ $key }}">
                            @foreach($options as $value => $option)
                                <option value="{{ $value }}" @selected((string) old($key, $preferences[$key] ?? array_key_first($options)) === (string) $value)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
            </div>
            <button type="submit" class="sfu-button sfu-setting-panel-action" name="save" value="defaults">Save Unit Defaults</button>
        </section>

        <section class="sfu-panel sfu-settings-panel" aria-labelledby="sfu-alerts-title">
            <h2 id="sfu-alerts-title">Notifications</h2>
            <div class="sfu-settings-notifications">
                @foreach($notificationOptions as $key => $label)
                    @include('workspace.sfu-setting-toggle', [
                        'label' => $label, 'name' => 'alerts['.$key.']',
                        'checked' => old('alerts.'.$key, $alerts[$key] ?? (str_starts_with($key, 'report_') ? ($alerts['feedback'] ?? true) : true)),
                    ])
                @endforeach
                @include('workspace.sfu-setting-toggle', ['label' => 'Email Notifications', 'name' => 'email', 'checked' => old('email', $preferences['email'] ?? false)])
            </div>
            <details class="sfu-in-app-options">
                <summary>In-app delivery</summary>
                @include('workspace.sfu-setting-toggle', ['label' => 'In-app notifications', 'name' => 'in_app', 'checked' => old('in_app', $preferences['in_app'] ?? true)])
            </details>
        </section>

        <section class="sfu-panel sfu-settings-panel" aria-labelledby="sfu-security-title">
            <h2 id="sfu-security-title">Data Backup &amp; Security</h2>
            <div class="sfu-settings-security">
                <div class="sfu-setting-row"><span>Backup Data</span><a class="sfu-button" href="/workspace/backup">Backup Now</a></div>
                @include('workspace.sfu-setting-toggle', ['label' => 'Two-step verification', 'name' => 'two_factor', 'checked' => old('two_factor', $account->two_factor), 'onText' => 'Enabled', 'offText' => 'Disabled'])
                <div class="sfu-setting-row"><span>Last login</span><span>{{ $lastLogin ? \Carbon\Carbon::parse($lastLogin->created_at)->format($loginFormat) : 'No login recorded' }}</span></div>
            </div>
            <button type="button" class="sfu-button sfu-outline sfu-setting-panel-action" data-dialog="login-activity">View Login Activity</button>
        </section>
    </div>

    <footer class="sfu-settings-footer">
        <p class="sfu-settings-status" role="status" data-sfu-settings-status>
            <img src="/staff-assets/21-4770.svg" alt=""><span>{{ $errors->any() ? 'Changes were not saved' : 'All changes saved' }}</span>
        </p>
        <div class="sfu-settings-footer-actions">
            <a class="sfu-button sfu-outline" href="/workspace/settings">Cancel</a>
            <button type="submit" class="sfu-button" name="save" value="changes">Save Changes</button>
        </div>
    </footer>
</form>

@push('dialogs')
    <dialog id="change-password" aria-labelledby="sfu-password-title">
        <button type="button" class="close-dialog" data-close aria-label="Close">×</button>
        <form class="panel" method="post" action="/workspace/settings">
            @csrf
            <h2 id="sfu-password-title">Change Password</h2>
            <label class="field">Current Password<input type="password" name="current_password" autocomplete="current-password" required></label>
            @error('current_password')<p class="sfu-review-error" role="alert">{{ $message }}</p>@enderror
            <label class="field">New Password<input type="password" name="password" minlength="12" autocomplete="new-password" required></label>
            @error('password')<p class="sfu-review-error" role="alert">{{ $message }}</p>@enderror
            <label class="field">Confirm New Password<input type="password" name="password_confirmation" autocomplete="new-password" required></label>
            <div class="actions"><button name="save" value="password">Save Changes</button></div>
        </form>
    </dialog>
    <dialog id="login-activity" aria-labelledby="sfu-login-title">
        <button type="button" class="close-dialog" data-close aria-label="Close">×</button>
        <section class="panel">
            <h2 id="sfu-login-title">Login Activity</h2>
            @forelse($audit as $entry)
                <div class="summary-line"><span>{{ $entry->subject }}</span><time>{{ \Carbon\Carbon::parse($entry->created_at)->format($loginFormat) }}</time></div>
            @empty
                <p class="empty">No staff login activity recorded yet.</p>
            @endforelse
        </section>
    </dialog>
@endpush
