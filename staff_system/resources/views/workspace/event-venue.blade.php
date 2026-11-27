<div data-venue-field>
    <div class="venue-choices" data-venue-choice hidden>
        <label class="field">
            Venue
            <select @if(!empty($venueError)) aria-invalid="true" aria-describedby="{{ $venueErrorId }}" @endif>
                <option value="">Select a venue</option>
                @foreach($eventVenues as $venue)
                    <option value="{{ $venue }}">{{ $venue }}</option>
                @endforeach
                <option data-other-venue>Other venue</option>
            </select>
        </label>
        <button type="button" class="outline venue-trash" data-remove-venue="{{ route('staff.event-venues.remove') }}"
                aria-label="Delete selected venue" title="Delete selected venue" disabled>
            <span aria-hidden="true">&#x1F5D1;&#xFE0E;</span>
        </button>
    </div>
    <label class="field" data-venue-input>
        Venue name
        <input name="venue" value="{{ $currentVenue ?? '' }}" maxlength="120" required @if(!empty($venueError)) aria-invalid="true" aria-describedby="{{ $venueErrorId }}" @endif>
    </label>
    <input type="hidden" name="custom_venue" value="1" disabled>
    <p class="muted" data-venue-feedback role="status" hidden></p>
    @if(!empty($venueError))<p class="sfu-review-error" id="{{ $venueErrorId }}">{{ $venueError }}</p>@endif
</div>
