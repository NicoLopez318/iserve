<label class="sfu-setting-row">
    <span>{{ $label }}</span>
    <span class="sfu-setting-toggle">
        <input type="hidden" name="{{ $name }}" value="0">
        <input type="checkbox" role="switch" aria-label="{{ $label }}" name="{{ $name }}" value="1" @checked($checked)>
        <span class="sfu-switch-track" aria-hidden="true"></span>
        <span class="sfu-switch-on" aria-hidden="true">{{ $onText ?? 'on' }}</span>
        <span class="sfu-switch-off" aria-hidden="true">{{ $offText ?? 'off' }}</span>
    </span>
</label>
