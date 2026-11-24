@if($paginator->hasPages() || (($showSinglePage ?? false) && $paginator->total() > 0))
    <nav class="sfu-pagination" aria-label="{{ $paginationLabel ?? 'Application pages' }}">
        @if($paginator->onFirstPage())
            <span aria-disabled="true" aria-label="Previous page">‹</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">‹</a>
        @endif

        @foreach($elements as $element)
            @if(is_string($element))
                <span>{{ $element }}</span>
            @else
                @foreach($element as $number => $url)
                    @if($number === $paginator->currentPage())
                        <span aria-current="page" aria-label="Page {{ $number }}">{{ $number }}</span>
                    @else
                        <a href="{{ $url }}" aria-label="Page {{ $number }}">{{ $number }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">›</a>
        @else
            <span aria-disabled="true" aria-label="Next page">›</span>
        @endif
    </nav>
@endif
