@php
    $asset = fn ($name) => asset('design-assets/public/'.$name.'.png');
    $scholarDestination = auth()->user()?->hasScholarPortalAccess() ? '/portal/scholar' : '/scholar/information';
    $destination = fn ($kind) => match ($kind) {
        'scholarship' => $scholarDestination,
        'leadership' => auth()->user()?->organization_id ? '/portal/leadership' : '/apply/leadership',
        'community' => '/community-link',
        'lost-found' => '/lost-found',
        default => '/apply/'.$kind,
    };
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>iServe · Urdaneta City University</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    <link rel="stylesheet" href="{{ asset('responsive.css') }}?v=20261112">
    <link rel="stylesheet" href="{{ asset('public-site.css') }}?v=4">
    <script src="{{ asset('landing.js') }}?v=2" defer></script>
</head>
<body class="public-site">
    <header class="site-header">
        <a class="site-brand" href="/">
            <img src="{{ $asset('brand') }}" alt="">
            <span>
                <strong>Urdaneta City University</strong>
                <small>Center for Student Leadership and Development</small>
            </span>
        </a>
        <nav aria-label="Main navigation">
            <a href="#home" data-section-link="home" aria-current="location">Home</a>
            <a href="#services" data-section-link="services">Services</a>
            <a href="#about" data-section-link="about">About</a>
        </nav>
        <div class="account-actions">
            @auth
                <a class="portal-button" href="{{ route('account.profile') }}">
                    <span class="account-icon" aria-hidden="true"></span>Profile
                </a>
            @else
                <a class="portal-button" href="{{ route('login') }}" aria-label="Log in to Student Portal">Log in</a>
                <a class="portal-button signup-button" href="{{ url('/register') }}">Sign up</a>
            @endauth
        </div>
    </header>
    <main>
        <section id="home" class="landing-section" aria-label="Home">
            @include('public.sections.home')
        </section>
        <section id="services" class="landing-section page-width" aria-labelledby="choose-unit">
            @include('public.sections.services')
        </section>
        <section id="about" class="landing-section page-width" aria-labelledby="about-title">
            @include('public.sections.about')
        </section>
        <section id="announcements" class="landing-section page-width announcements @if($announcements->isEmpty() && !$announcementSearch && !$announcementUnit) announcements-on-request @endif" aria-labelledby="announcements-title">
            @include('public.sections.announcements')
        </section>
    </main>
    <footer class="site-footer">
        <div class="footer-brand"><strong>iSERVE</strong><span>Urdaneta City University — CSLD</span></div>
        <small>© {{ now()->year }} Center for Student Leadership and Development. All rights reserved.</small>
    </footer>
</body>
</html>
