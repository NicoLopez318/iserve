<section class="unit-catalog" id="choose-unit"><small class="overline">EXPLORE OUR SERVICES</small><h2>Choose a unit</h2><div class="unit-cards">
@foreach($units as $key=>$u)<article class="unit-card" style="--unit-photo:url('{{ $asset($key) }}')"><div class="unit-photo"><b>{{ $u['number'] }}</b></div><h3><span class="round-icon"><x-icon :file="$u['icon']" :size="30"/></span><a href="{{ $key==='values'?'/transactions':$destination($key) }}">{{ $u['name'] }}</a></h3><div class="unit-service-grid">
@foreach(App\Http\Controllers\PublicController::services() as $s)
@if($s['unit']===$key)<a class="service-link" href="{{ $destination($s['kind']) }}"><span>▤</span>{{ $s['title'] }}
@if($key==='leadership')<b>›</b>
@else<b>›</b>
@endif</a>
@endif
@endforeach</div><small class="card-motto">{{ ['leadership'=>'LEAD ORGANIZE SERVE','scholarship'=>'ACCESS SUPPORT EMPOWER','values'=>'DISCIPLINE INTEGRITY SERVICE'][$key] }}</small></article>
@endforeach</div></section>

@foreach($units as $unit=>$current)
<div id="unit-{{ $unit }}" class="landing-unit-detail">
<section class="site-hero unit-hero" style="--hero:url('{{ $asset($unit) }}')"><p class="breadcrumb">Home / Units / {{ $current['name'] }} <span></span></p><div class="unit-hero-copy"><b class="hero-number">{{ $current['number'] }}</b><div><small class="overline">UNIT {{ $current['number'] }}</small><h1>
@if($unit==='leadership')Student<br><em>Leadership Unit</em>
@elseif($unit==='scholarship')Scholarship and<br><em>Financial Unit</em>
@else Character and<br><em>Values Formation</em>
@endif</h1><p>{{ $current['description'] }}</p></div></div>
@if($unit==='leadership')
@endif</section>
<section class="unit-detail"><div class="section-heading"><h2>Available transaction{{ $unit==='values'?'s':'' }}</h2>
@if($unit==='values')<span>7 services</span>
@endif</div><div class="transaction-list {{ $unit==='values'?'split':'' }}">
@foreach(App\Http\Controllers\PublicController::services() as $s)
@if($s['unit']===$unit)<article class="transaction-card">
@if($unit==='values')<b class="service-number">{{ sprintf('%02d',array_search($s,App\Http\Controllers\PublicController::services())-1) }}</b>
@endif<span class="tile-icon"><x-icon :file="$s['icon']" :size="38"/></span><h3>{{ $s['title'] }}</h3>
@if($unit==='leadership')<a class="site-button red-button" href="{{ $destination('leadership') }}">View transaction</a>
@else<a class="site-button red-button" href="{{ $destination($s['kind']) }}">View transaction</a>
@endif</article>
@endif
@endforeach</div>
@if($unit!=='values')<div class="transaction-info"><b>ⓘ</b><p>
@if($unit==='leadership')Student Leadership / University Organization
@else<strong>Transaction information</strong><br>Open Tuition Discount to view the official process and requirements.
@endif</p></div>
@endif<div class="unit-neighbors">
@foreach($units as $key=>$u)
@if($key!==$unit)<a href="/#unit-{{ $key }}"><span>‹</span><x-icon :file="$u['icon']" :size="36"/>{{ $u['name'] }}<span>›</span></a>
@endif
@endforeach</div></section>

</div>
@endforeach
