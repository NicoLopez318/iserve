<div class="site-hero" style="--hero: url('{{ $asset('campus') }}')">
    <p class="directory-label">iServe Student Directory</p>
    <h1>Student services, <em>made clear.</em></h1>
    <p class="hero-description">Find every CSLD transaction at Urdaneta City University in one straightforward place.</p>
    <form class="hero-search" action="/#services" role="search">
        <span class="search-icon" aria-hidden="true"></span>
        <input name="service_q" type="search" aria-label="Search services" maxlength="200"
               value="{{ $serviceSearch }}" placeholder="Search for a transaction, service or keyword…">
        <button class="portal-button">Find services</button>
    </form>
    <div class="popular-services">
        <span>Popular:</span>
        @foreach (['Tuition Discount', 'ID Replacement', 'Clearance', 'Re-admission'] as $title)
            <a href="/?{{ http_build_query(['service_q' => $title]) }}#services">{{ $title }}</a>
        @endforeach
    </div>
</div>
