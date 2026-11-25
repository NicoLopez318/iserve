@php
    ['library' => $library, 'preview' => $preview] = $contentLibrary;
    $statuses = ['draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published', 'expired' => 'Expired'];
    $filters = [
        'type' => ['Content type', 'All Types', array_combine(App\Support\PortalContent::TYPES, App\Support\PortalContent::TYPES)],
        'location' => ['Portal section', 'All Sections', array_combine(App\Support\PortalContent::LOCATIONS, App\Support\PortalContent::LOCATIONS)],
        'status' => ['Publication status', 'All Statuses', $statuses],
    ];
    $studentPortal = rtrim(config('app.student_portal_url'), '/');
@endphp

<section class="sfu-panel slu-content-library" aria-labelledby="slu-content-title">
    <div class="slu-content-library-header">
        <div class="slu-points-heading-copy">
            <img src="/staff-assets/21-5492.svg" alt="">
            <div>
                <h2 id="slu-content-title">Content Library</h2>
                <p>Manage editable portal content.</p>
            </div>
        </div>

        <form class="sfu-filters slu-content-filters" method="get" action="/workspace/content" data-sfu-filters>
            <div class="sfu-search">
                <button type="submit" aria-label="Apply content filters"><img src="/staff-assets/21-4727.svg" alt=""></button>
                <input type="search" name="search" aria-label="Search portal content" placeholder="Search content..."
                       value="{{ request()->string('search') }}">
            </div>
            @foreach($filters as $name => [$label, $placeholder, $options])
                <select name="{{ $name }}" aria-label="{{ $label }}">
                    <option value="">{{ $placeholder }}</option>
                    @foreach($options as $value => $option)
                        <option value="{{ $value }}" @selected(request($name) === $value)>{{ $option }}</option>
                    @endforeach
                </select>
            @endforeach
            <button type="button" class="sfu-button" data-dialog="portal-content">Add Content</button>
        </form>
    </div>

    <div class="sfu-table-wrap slu-queue-table slu-content-table" role="region" aria-label="Portal content library" tabindex="0">
        <table>
            <thead><tr>
                <th scope="col">Content</th><th scope="col">Type</th><th scope="col">Portal Location</th>
                <th scope="col">Schedule</th><th scope="col">Status</th><th scope="col">Action</th>
            </tr></thead>
            <tbody>
                @forelse($library as $entry)
                    <tr>
                        <td><span class="slu-content-item">
                            @if($entry['image'])
                                <img class="slu-content-thumbnail" src="{{ $entry['image'] }}" alt="" loading="lazy">
                            @else
                                <span class="slu-content-thumbnail is-placeholder" aria-hidden="true"></span>
                            @endif
                            {{ $entry['item']->title }}
                        </span></td>
                        <td>{{ $entry['type'] }}</td><td>{{ $entry['location'] }}</td>
                        <td>
                            @if($entry['start'])
                                <time datetime="{{ $entry['start']->toAtomString() }}">{{ $entry['start']->format('M j, Y') }}</time>
                                @if($entry['end'])<br>to <time datetime="{{ $entry['end']->toAtomString() }}">{{ $entry['end']->format('M j, Y') }}</time>@endif
                            @else
                                —
                            @endif
                        </td>
                        <td><span class="slu-content-status is-{{ $entry['status'] }}">{{ $statuses[$entry['status']] }}</span></td>
                        <td><button type="button" class="sfu-button" data-dialog="content-{{ $entry['item']->id }}" aria-label="Edit {{ $entry['item']->title }}">Edit</button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="sfu-empty">No matching portal content.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($library->hasPages())
        <div class="sfu-queue-footer">{{ $library->onEachSide(1)->links('workspace.sfu-pagination', ['paginationLabel' => 'Content pages']) }}</div>
    @endif
</section>

<section class="sfu-panel slu-content-preview" aria-labelledby="slu-content-preview-title">
    <div class="slu-queue-heading">
        <h2 id="slu-content-preview-title"><span class="slu-content-preview-icon" aria-hidden="true"></span>Student Portal Preview</h2>
        <a href="{{ $studentPortal }}/announcements?unit=leadership" target="_blank" rel="noopener">Open student portal</a>
    </div>
    @if($preview->isNotEmpty())
        <div class="slu-content-carousel" data-content-carousel role="group" aria-label="Published content preview">
            <div class="slu-content-slides">
                @foreach($preview as $entry)
                    @php
                        $destination = App\Support\PortalContent::destination($entry['meta']['cta_destination'] ?? null);
                    @endphp
                    <article class="slu-content-slide" data-content-slide @unless($loop->first) hidden @endunless
                             aria-label="{{ $loop->iteration }} of {{ $preview->count() }}">
                        @if($entry['image'])
                            <img src="{{ $entry['image'] }}" alt="" @unless($loop->first) loading="lazy" @endunless>
                        @endif
                        <div class="slu-content-slide-copy">
                            <span>{{ $entry['type'] }}</span>
                            <h3>{{ $entry['item']->title }}</h3>
                            @if($destination)
                                <a class="sfu-button" href="{{ $studentPortal }}{{ $destination }}" target="_blank" rel="noopener">{{ $entry['meta']['cta_label'] ?? 'View' }}</a>
                            @endif
                        </div>
                    </article>
                @endforeach
                @if($preview->count() > 1)
                    <button type="button" class="slu-content-carousel-arrow is-prev" data-content-prev aria-label="Previous preview">‹</button>
                    <button type="button" class="slu-content-carousel-arrow is-next" data-content-next aria-label="Next preview">›</button>
                @endif
            </div>
            @if($preview->count() > 1)
                <div class="slu-content-carousel-dots" role="group" aria-label="Choose a preview">
                    @foreach($preview as $entry)
                        <button type="button" data-content-dot="{{ $loop->index }}" aria-label="Preview {{ $entry['item']->title }}"
                                @if($loop->first) aria-current="true" @endif></button>
                    @endforeach
                </div>
            @endif
            <span class="sr-only" data-content-slide-status aria-live="polite"></span>
        </div>
    @else
        <p class="slu-content-preview-empty">Published content will appear here.</p>
    @endif
</section>

@push('dialogs')
    @foreach($library as $entry)
        @php
            $a = $entry['item'];
        @endphp
        <dialog id="content-{{ $a->id }}" aria-labelledby="content-edit-{{ $a->id }}">
            <button type="button" class="close-dialog" data-close aria-label="Close">×</button>
            <form class="panel" action="/workspace/content/{{ $a->id }}" method="post" enctype="multipart/form-data">
                @csrf
                <h2 id="content-edit-{{ $a->id }}">Edit Portal Content</h2>
                <label class="field">Title<input name="title" value="{{ $a->title }}" required maxlength="180"></label>
                <label class="field">Description<textarea name="body" required maxlength="10000">{{ $a->body }}</textarea></label>
                <label class="field">Start Date &amp; Time<input type="datetime-local" name="published_at" value="{{ $entry['start']?->format('Y-m-d\TH:i') }}"></label>
                @include('workspace.content-fields', ['contentMetadata' => $entry['meta']])
                <label class="check"><input type="checkbox" name="publish" value="1" @checked($a->published_at)>Publish / Schedule</label>
                <div class="actions"><button type="submit">Save Changes</button></div>
            </form>
        </dialog>
    @endforeach
@endpush
