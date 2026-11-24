<section class="sfu-stats" aria-label="Application totals">
    @foreach($design['cards'] as $card)
        <div class="sfu-stat">
            <span class="sfu-stat-icon"><img src="/staff-assets/{{ $card['icon'] }}" alt=""></span>
            <div><strong>{{ $card['value'] }}</strong><span>{{ $card['label'] }}</span></div>
        </div>
    @endforeach
</section>
