@php
    $isPastEvent = $eventPeriod === 'past';
    $dialogId = $isPastEvent ? 'create-past-event' : 'create-event';
    $heading = $isPastEvent ? 'Create Past Event' : 'Create Future Event';
    $eventErrors = $errors->getBag($isPastEvent ? 'createPastEvent' : 'createEvent');
    $eventValue = function (string $field, $default = '') use ($eventErrors) {
        $value = $eventErrors->any() ? old($field, $default) : $default;
        return is_scalar($value) ? $value : '';
    };
@endphp

<dialog id="{{ $dialogId }}" aria-labelledby="{{ $dialogId }}-title" @if($eventErrors->any()) open data-auto-open @endif>
    <button type="button" class="close-dialog" data-close aria-label="Close">×</button>
    <form class="panel" method="post" action="/staff/events" novalidate>
        @csrf
        <h2 id="{{ $dialogId }}-title">{{ $heading }}</h2>
        <input type="hidden" name="type" value="leadership">
        <input type="hidden" name="event_period" value="{{ $eventPeriod }}">
        @if($eventErrors->any())
            <div class="errors" role="alert">
                <p>Correct the highlighted fields to create the event.</p>
                @foreach(['type', 'custom_venue', 'event_period'] as $field)
                    @if($eventErrors->has($field))<p>{{ $eventErrors->first($field) }}</p>@endif
                @endforeach
            </div>
        @endif
        <div class="form-grid">
            <label class="field full">Title
                <input name="title" value="{{ $eventValue('title') }}" required maxlength="150" @if($eventErrors->has('title')) aria-invalid="true" aria-describedby="{{ $dialogId }}-title-error" @endif>
                @if($eventErrors->has('title'))<span class="sfu-review-error" id="{{ $dialogId }}-title-error">{{ $eventErrors->first('title') }}</span>@endif
            </label>
            <label class="field full">Description
                <textarea name="description" required maxlength="3000" @if($eventErrors->has('description')) aria-invalid="true" aria-describedby="{{ $dialogId }}-description-error" @endif>{{ $eventValue('description') }}</textarea>
                @if($eventErrors->has('description'))<span class="sfu-review-error" id="{{ $dialogId }}-description-error">{{ $eventErrors->first('description') }}</span>@endif
            </label>
            @include('workspace.event-venue', ['currentVenue' => $eventValue('venue'), 'venueError' => $eventErrors->first('venue'), 'venueErrorId' => $dialogId.'-venue-error'])
            <label class="field">Capacity
                <input type="number" name="capacity" min="1" max="5000" value="{{ $eventValue('capacity', 100) }}" required @if($eventErrors->has('capacity')) aria-invalid="true" aria-describedby="{{ $dialogId }}-capacity-error" @endif>
                @if($eventErrors->has('capacity'))<span class="sfu-review-error" id="{{ $dialogId }}-capacity-error">{{ $eventErrors->first('capacity') }}</span>@endif
            </label>
            @foreach(['starts_at' => 'Start Date & Time', 'ends_at' => 'End Date & Time'] as $field => $label)
                <label class="field">{{ $label }}
                    <input type="datetime-local" name="{{ $field }}" value="{{ str_replace(' ', 'T', $eventValue($field)) }}" required @if($eventErrors->has($field)) aria-invalid="true" aria-describedby="{{ $dialogId }}-{{ $field }}-error" @endif>
                    @if($eventErrors->has($field))<span class="sfu-review-error" id="{{ $dialogId }}-{{ $field }}-error">{{ $eventErrors->first($field) }}</span>@endif
                </label>
            @endforeach
            <label class="field">Participation Points
                <input type="number" name="points" min="0" max="1000" value="{{ $eventValue('points', $rules['participant'] ?? 0) }}" required @if($eventErrors->has('points')) aria-invalid="true" aria-describedby="{{ $dialogId }}-points-error" @endif>
                @if($eventErrors->has('points'))<span class="sfu-review-error" id="{{ $dialogId }}-points-error">{{ $eventErrors->first('points') }}</span>@endif
            </label>
        </div>
        <div class="actions"><button>{{ $heading }}</button></div>
    </form>
</dialog>
