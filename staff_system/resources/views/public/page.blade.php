@php
$unit=$unit??($page==='units'?'leadership':null);$current=$unit?$units[$unit]:null;
$asset=fn($name)=>asset('design-assets/public/'.$name.'.png');
$destination=fn($kind)=>match($kind){'community'=>'/community-link','lost-found'=>'/home?choose=lost-found',default=>'/apply/'.$kind};
@endphp
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $current['name']??ucfirst($page) }} · iServe</title><link rel="stylesheet" href="{{ asset('app.css') }}"><link rel="stylesheet" href="{{ asset('public-site.css') }}"><link rel="stylesheet" href="{{ asset('responsive.css') }}?v=20261112"></head>
<body class="public-site"><header class="site-header"><a class="site-brand" href="/"><img src="{{ $asset('brand') }}" alt="UCU and CSLD logos"><span><strong>URDANETA CITY UNIVERSITY</strong><small>CENTER FOR STUDENT LEADERSHIP AND DEVELOPMENT</small></span></a><nav aria-label="Main navigation">
@foreach(['home'=>'Home','services'=>'Services','units'=>'Units','announcements'=>'Announcements','about'=>'About'] as $key=>$label)<a href="{{ $key==='home'?'/':'/'.$key }}" 
@if($page===$key)aria-current="page"
@endif>{{ $label }}</a>
@endforeach<a class="portal-button" href="{{ auth()->check()?'/home':'/login' }}">● &nbsp; Student Portal</a></nav></header>
<main>
@if($page==='home')
<section class="site-hero home-hero" style="--hero:url('{{ $asset('campus') }}')"><div class="hero-copy"><p class="overline">i S E R V E <span></span></p><h1>Student services,<br><em>made clear.</em></h1><p>One trusted place to find every CSLD<br>transaction at Urdaneta City University.</p><a class="site-button red-button" href="/services">Browse all transactions <b>→</b></a><small class="hero-foot">STUDENTS &nbsp; | &nbsp; LEADERS &nbsp; | &nbsp; A BRIGHTER TOMORROW</small></div><div class="campus-mark"><small>PEOPLE<br>PURPOSE<br>PROGRESS</small><b>UCU</b><small>URDANETA CITY<br>UNIVERSITY</small></div></section>
<form class="home-search" action="/services"><h2>What service do you need?</h2><input name="q" aria-label="Search services" placeholder="Search for a transaction, service, or keyword..."><button class="site-button">Find a service <b>→</b></button></form>
<section class="unit-catalog"><small class="overline">EXPLORE OUR SERVICES</small><h2>Choose a unit</h2><div class="unit-cards">
@foreach($units as $key=>$u)<article class="unit-card" style="--unit-photo:url('{{ $asset($key) }}')"><div class="unit-photo"><b>{{ $u['number'] }}</b></div><h3><span class="round-icon"><x-icon :file="$u['icon']" :size="30"/></span><a href="/units/{{ $key }}">{{ $u['name'] }}</a></h3><div class="unit-service-grid">
@foreach(App\Http\Controllers\PublicController::services() as $s)
@if($s['unit']===$key)<a class="service-link" href="{{ $key==='leadership'?'/units/leadership':$destination($s['kind']) }}"><span>▤</span>{{ $s['title'] }}
@if($key==='leadership')<small class="catalog-tag">Catalog being finalized</small>
@else<b>›</b>
@endif</a>
@endif
@endforeach</div><small class="card-motto">{{ ['leadership'=>'LEAD ORGANIZE SERVE','scholarship'=>'ACCESS SUPPORT EMPOWER','values'=>'DISCIPLINE INTEGRITY SERVICE'][$key] }}</small></article>
@endforeach</div></section>
@elseif($page==='services')
<section class="site-hero directory-hero" style="--hero:url('{{ $asset('campus') }}')"><div class="hero-copy"><p class="breadcrumb">Home / Services <span></span></p><h1>Services <em>Directory</em></h1><p>Find the right CSLD transaction by service or unit.</p><form class="hero-search" action="/services"><label for="service-search">Search services</label><div><input id="service-search" name="q" value="{{ $search }}" placeholder="Enter a transaction or keyword">
@if($selected)<input type="hidden" name="unit" value="{{ $selected }}">
@endif<button class="site-button">Search <b>→</b></button></div></form></div><div class="campus-mark"><b>UCU</b><small>URDANETA CITY<br>UNIVERSITY</small></div></section>
<section class="directory-body"><aside class="unit-filter"><h3>Filter by unit</h3><a class="{{ !$selected?'selected':'' }}" href="/services?q={{ urlencode($search) }}">▦ &nbsp; All units</a>
@foreach($units as $key=>$u)<a class="{{ $selected===$key?'selected':'' }}" href="/services?unit={{ $key }}&q={{ urlencode($search) }}"><x-icon :file="$u['icon']" :size="28"/>{{ $u['short'] }}</a>
@endforeach<hr><h3>{{ $services->count() }} services</h3></aside><div class="directory-results"><div class="section-heading"><h2>Services</h2><span>{{ $services->count() }} services</span></div>
@forelse($units as $key=>$u)
@if($services->where('unit',$key)->isNotEmpty())<article class="directory-unit"><b class="unit-number">{{ $u['number'] }}</b><div><h3><span class="round-icon"><x-icon :file="$u['icon']" :size="30"/></span>{{ $u['name'] }}</h3><div class="directory-service-grid {{ $key==='values'?'split':'' }}">
@foreach($services->where('unit',$key) as $s)<div class="service-link"><span>▤</span>{{ $s['title'] }}
@if($key==='leadership')<a class="catalog-tag" href="/units/leadership">Catalog being finalized</a>
@else<a class="outline-button" href="{{ $destination($s['kind']) }}">View service &nbsp; ›</a>
@endif</div>
@endforeach</div></div></article>
@endif
@empty<p>No matching services.</p>
@endforelse 
@if($services->isEmpty())<p class="empty">No services match your search.</p>
@endif</div></section>
@elseif($page==='units')
<section class="site-hero unit-hero" style="--hero:url('{{ $asset($unit) }}')"><p class="breadcrumb">Home / Units / {{ $current['name'] }} <span></span></p><div class="unit-hero-copy"><b class="hero-number">{{ $current['number'] }}</b><div><small class="overline">UNIT {{ $current['number'] }}</small><h1>
@if($unit==='leadership')Student<br><em>Leadership Unit</em>
@elseif($unit==='scholarship')Scholarship and<br><em>Financial Unit</em>
@else Character and<br><em>Values Formation</em>
@endif</h1><p>{{ $current['description'] }}</p></div></div>
@if($unit==='leadership')<p class="catalog-banner">◷ &nbsp; Service catalog is being finalized.</p>
@endif</section>
<section class="unit-detail"><div class="section-heading"><h2>Available transaction{{ $unit==='values'?'s':'' }}</h2>
@if($unit==='values')<span>7 services</span>
@endif</div><div class="transaction-list {{ $unit==='values'?'split':'' }}">
@foreach(App\Http\Controllers\PublicController::services() as $s)
@if($s['unit']===$unit)<article class="transaction-card">
@if($unit==='values')<b class="service-number">{{ sprintf('%02d',array_search($s,App\Http\Controllers\PublicController::services())-1) }}</b>
@endif<span class="tile-icon"><x-icon :file="$s['icon']" :size="38"/></span><h3>{{ $s['title'] }}</h3>
@if($unit==='leadership')<span class="catalog-tag">Catalog being finalized</span>
@else<a class="site-button red-button" href="{{ $destination($s['kind']) }}">View transaction</a>
@endif</article>
@endif
@endforeach</div>
@if($unit!=='values')<div class="transaction-info"><b>ⓘ</b><p>
@if($unit==='leadership')Complete service information will appear here after official confirmation.
@else<strong>Transaction information</strong><br>Open Tuition Discount to view the official process and requirements.
@endif</p></div>
@endif<div class="unit-neighbors">
@foreach($units as $key=>$u)
@if($key!==$unit)<a href="/units/{{ $key }}"><span>‹</span><x-icon :file="$u['icon']" :size="36"/>{{ $u['name'] }}<span>›</span></a>
@endif
@endforeach</div></section>
@elseif($page==='announcements')
<section class="site-hero announcements-hero" style="--hero:url('{{ $asset('announcements') }}')"><p class="breadcrumb">Home / Announcements <span></span></p><div class="unit-hero-copy"><b class="hero-number">A</b><div><small class="overline">CSLD UPDATES</small><h1>Announc<em>ements</em></h1><p>Official notices from the Center for Student Leadership<br>and Development.</p></div></div></section>
<section class="announcements-body"><div><form class="announcement-filters" action="/announcements"><a class="outline-button {{ !$selected?'selected':'' }}" href="/announcements">All units</a>
@foreach($units as $key=>$u)<a class="outline-button {{ $selected===$key?'selected':'' }}" href="/announcements?unit={{ $key }}&q={{ urlencode($search) }}">{{ $u['short'] }}</a>
@endforeach 
@if($selected)<input type="hidden" name="unit" value="{{ $selected }}">
@endif<input name="q" value="{{ $search }}" aria-label="Search announcements" placeholder="Search announcements"><button class="sr-only">Search</button></form>
@forelse($announcements as $a)<article class="published-notice"><small>{{ $units[$a->unit]['name'] }} · {{ \Carbon\Carbon::parse($a->published_at)->format('F j, Y') }}</small>@php($contentMeta=\App\Support\PortalContent::metadata($a->id))
@if(isset($contentMeta['image']))<img src="/content-image/{{$a->id}}" alt="{{$a->title}}" style="max-width:100%;max-height:240px;object-fit:cover">@endif
<h2>{{ $a->title }}</h2><p class="pre-wrap">{{ $a->body }}</p>@if($destination=\App\Support\PortalContent::destination($contentMeta['cta_destination']??null))<a class="outline-button" href="{{$destination}}">{{$contentMeta['cta_label']??'View'}}</a>@endif
</article>
@empty<div class="announcement-empty"><span class="document-circle">▤</span><h2>{{ $search||$selected?'No matching announcements':'No announcements published yet' }}</h2><p>Official CSLD notices will appear here once published.</p><a class="outline-button" href="/services">Explore services &nbsp; ›</a></div>
@endforelse</div><aside class="office-card"><h3><img src="{{ $asset('office') }}" alt="CSLD emblem">Center for Student Leadership and Development</h3>
@foreach($units as $key=>$u)<a href="/units/{{ $key }}"><span class="tile-icon"><x-icon :file="$u['icon']" :size="35"/></span>{{ $u['short'] }}<b>›</b></a>
@endforeach</aside></section>
@else
<section class="site-hero about-hero" style="--hero:url('{{ $asset('about') }}')"><p class="breadcrumb">Home / About <span></span></p><div class="about-hero-copy"><img src="{{ $asset('crest') }}" alt="CSLD crest"><div><small class="overline">ABOUT THE OFFICE</small><h1>Center for Student<br>Leadership and<br><em>Development</em></h1><p>CSLD brings together three student-service units<br>within Urdaneta City University.</p></div></div></section>
<section class="about-body"><div><div class="section-heading"><h2>Our units</h2><hr></div>
@foreach($units as $key=>$u)<a class="about-unit" href="/units/{{ $key }}"><b>{{ $u['number'] }}</b><x-icon :file="$u['icon']" :size="48"/><h3>{{ $u['name'] }}</h3><span class="outline-button">View unit &nbsp; ›</span></a>
@endforeach</div><aside class="access-card"><span class="document-circle">▤</span><h2>Access student services</h2><p>Use iSERVE to find the confirmed<br>transactions offered by each unit.</p><a class="site-button red-button" href="/services">Browse services <b>›</b></a></aside></section>
@endif
</main><footer class="site-footer"><span></span><div><strong>iSERVE</strong><small>Urdaneta City University</small></div><span></span></footer></body></html>
