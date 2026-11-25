@php
    ['month' => $month, 'days' => $days, 'list' => $eventList, 'all' => $all, 'monthChoices' => $monthChoices] = $eventCalendar;
    $gridStart = $month->startOfWeek(\Carbon\CarbonInterface::SUNDAY);
    $gridEnd = $month->endOfMonth()->endOfWeek(\Carbon\CarbonInterface::SATURDAY);
    $dayColors = $days->keys()->mapWithKeys(fn ($date, $index) => [$date => $index % 4]);
@endphp

<section class="sfu-panel slu-event-calendar" aria-labelledby="slu-event-calendar-title" data-slu-event-calendar>
    <div class="slu-queue-heading">
        <h2 id="slu-event-calendar-title"><span class="slu-event-calendar-icon" aria-hidden="true"></span>Activity Calendar</h2>
        <button type="button" class="sfu-button" data-dialog="create-event"><span aria-hidden="true">+</span> Create Event</button>
    </div>

    <div class="slu-calendar-layout">
        <div class="slu-calendar-navigation">
            <a href="{{ request()->fullUrlWithQuery(['month' => $month->subMonth()->format('Y-m')]) }}" aria-label="Previous month">‹</a>
            <form method="get" action="/workspace/events" data-sfu-filters>
                @if($all)<input type="hidden" name="list" value="all">@endif
                <select name="month" aria-label="Calendar month">
                    @foreach($monthChoices as $choice)
                        <option value="{{ $choice }}" @selected($choice === $month->format('Y-m'))>{{ \Carbon\CarbonImmutable::createFromFormat('!Y-m', $choice)->format('F Y') }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit">Go</button></noscript>
            </form>
            <a href="{{ request()->fullUrlWithQuery(['month' => $month->addMonth()->format('Y-m')]) }}" aria-label="Next month">›</a>
        </div>
        <div class="slu-calendar-grid">
            <table aria-label="{{ $month->format('F Y') }} activity calendar">
                <thead>
                    <tr>
                        @foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $weekday)
                            <th scope="col" aria-label="{{ $weekday }}">{{ substr($weekday, 0, 3) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @for($week = $gridStart; $week->lte($gridEnd); $week = $week->addWeek())
                        <tr>
                            @for($index = 0; $index < 7; $index++)
                                @php
                                    $day = $week->addDays($index);
                                    $date = $day->toDateString();
                                    $count = $days->get($date)?->count() ?? 0;
                                @endphp
                                <td @class(['is-outside-month' => !$day->isSameMonth($month)])>
                                    @if($count)
                                        <a class="slu-calendar-day is-color-{{ $dayColors[$date] }}" href="#calendar-day-{{ $date }}"
                                           @if($day->isToday()) aria-current="date" @endif
                                           aria-label="{{ $day->format('F j, Y') }} · {{ $count }} {{ Str::plural('event', $count) }}">{{ $day->day }}</a>
                                    @else
                                        <time datetime="{{ $date }}" @if($day->isToday()) aria-current="date" @endif>{{ $day->day }}</time>
                                    @endif
                                </td>
                            @endfor
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>

        <aside class="slu-event-agenda" aria-label="Scheduled activities">
            <ul>
                @forelse($days as $date => $scheduled)
                    @foreach($scheduled as $event)
                        <li @if($loop->first) id="calendar-day-{{ $date }}" tabindex="-1" @endif>
                            <button type="button" data-dialog="event-{{ $event->id }}-attendance">
                                <span class="slu-calendar-dot is-color-{{ $dayColors[$date] }}" aria-hidden="true"></span>
                                <span><strong>{{ $event->starts_at->format('F j') }}</strong><small>{{ $event->title }}</small></span>
                            </button>
                        </li>
                    @endforeach
                @empty
                    <li class="slu-agenda-empty">No activities scheduled this month.</li>
                @endforelse
            </ul>
        </aside>
    </div>
</section>

<section class="sfu-panel slu-event-list" aria-labelledby="slu-event-list-title" id="event-list">
    <div class="slu-queue-heading">
        <h2 id="slu-event-list-title">Event List</h2>
        @if($all)
            <a href="{{ request()->fullUrlWithoutQuery('list') }}#event-list">Back to summary</a>
        @else
            <a href="{{ request()->fullUrlWithQuery(['list' => 'all']) }}#event-list">View all <img src="/staff-assets/21-5493.svg" alt=""></a>
        @endif
    </div>
    <div class="sfu-table-wrap slu-queue-table slu-event-table" role="region" aria-label="Event list" tabindex="0">
        <table>
            <thead><tr>
                <th scope="col">Event Title</th><th scope="col">Venue</th><th scope="col">Date</th>
                <th scope="col">Status</th><th scope="col">Registration</th><th scope="col" aria-label="Action"></th>
            </tr></thead>
            <tbody>
                @forelse($eventList as $event)
                    @php
                        $published = $event->status === 'published';
                        $open = $published && $event->starts_at->isFuture() && $event->attendances_count < $event->capacity;
                        $status = $open ? 'Registration Open' : str($event->status)->headline();
                    @endphp
                    <tr id="event-{{ $event->id }}-entry">
                        <td>{{ $event->title }}</td><td>{{ $event->venue }}</td>
                        <td><time datetime="{{ $event->starts_at->toAtomString() }}">{{ $event->starts_at->format('M j, Y') }}</time></td>
                        <td><span class="slu-event-status is-{{ $event->status }}">{{ $status }}</span></td>
                        <td><span class="slu-event-status is-{{ $event->status }}">{{ $event->status === 'draft' ? number_format($event->capacity).' Capacity' : number_format($event->attendances_count).' Registered' }}</span></td>
                        <td><button type="button" class="slu-event-arrow" data-dialog="event-{{ $event->id }}-attendance" aria-label="Open {{ $event->title }}"><img src="/staff-assets/21-5493.svg" alt=""></button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="sfu-empty">No events found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
