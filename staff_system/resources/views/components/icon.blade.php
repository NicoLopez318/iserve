@props(['file','size'=>24])
@php
$path=public_path('design-assets/'.basename($file));
$svg=is_file($path)?file_get_contents($path):'';
preg_match('/width="([0-9.]+)"/',$svg,$w);preg_match('/height="([0-9.]+)"/',$svg,$h);
$scale=$file==='d8b75.svg'?$size/28:min($size/max(1,(float)($w[1]??24)),$size/max(1,(float)($h[1]??24)));
@endphp
<span class="icon" style="width:{{ $size }}px;height:{{ $size }}px" aria-hidden="true"><img src="{{ asset('design-assets/'.basename($file)) }}" alt="" style="transform:{{ $file==='d8b75.svg'?'translateY(-'.(27*$scale).'px) ':'' }}scale({{ $scale }});transform-origin:top left"></span>


