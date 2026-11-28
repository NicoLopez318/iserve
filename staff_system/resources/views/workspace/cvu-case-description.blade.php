<section class="cvu-case-description" aria-labelledby="cvu-case-description-title">
    <h3 id="cvu-case-description-title">{{ $descriptionTitle ?? 'Description' }}</h3>
    @if(Str::length($description) > 112)
        <details>
            <summary>
                <span class="cvu-description-preview">{{ Str::limit($description, 112) }}</span>
                <span class="cvu-description-more">Show more</span>
                <span class="cvu-description-less">Show less</span>
            </summary>
            <p>{{ $description }}</p>
        </details>
    @else
        <p>{{ $description ?: 'No description provided.' }}</p>
    @endif
</section>
