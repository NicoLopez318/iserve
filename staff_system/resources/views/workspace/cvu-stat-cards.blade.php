<section class="cvu-stat-grid" aria-label="{{ $label }}">
    @foreach($design['cards'] as $card)
        <div class="cvu-stat">
            <span class="cvu-stat-icon"><img src="/staff-assets/{{ $card['icon'] }}" alt=""></span>
            <div><strong>{{ $card['value'] }}</strong><span>{{ $card['label'] }}</span></div>
        </div>
    @endforeach
</section>
