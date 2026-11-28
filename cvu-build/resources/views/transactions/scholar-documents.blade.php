<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Scholar Information · iServe</title><link rel="stylesheet" href="{{ asset('app.css') }}"><link rel="stylesheet" href="{{ asset('scholar-onboarding.css') }}"><script src="{{ asset('scholar-documents.js') }}" defer></script><link rel="stylesheet" href="{{ asset('responsive.css') }}?v=20261112"></head>
<body class="scholar-information-screen">
<a class="scholar-home" href="/" aria-label="Return to home"><svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path fill="currentColor" d="M12 2 2 11v11h7v-8h6v8h7V11L12 2Z"/></svg></a>
<main class="scholar-information"><h1>Scholar Information</h1>
@include('transactions.scholar-eligibility')
<section class="scholar-card scholar-document-card"><h2>Upload documents</h2>@include('components.flash')
<p class="scholar-document-note">Ask the Scholarship and Financial Unit how to submit any required documents without an upload field below.</p>
<form method="post" action="/apply/scholarship" enctype="multipart/form-data">@csrf
@foreach(['application'=>'Tuition Fee Discount Application Form','photo'=>'Copy of 1×1 ID picture','registration'=>'Copy of the registration form, back to back'] as $key=>$label)
<div class="scholar-document-row"><label for="scholar-file-{{ $key }}">{{ $label }}</label><div class="scholar-upload-control"><span aria-hidden="true">Upload</span><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="m8 13 7-7a3 3 0 0 1 4 4l-9 9a5 5 0 0 1-7-7l9-9a2 2 0 0 1 3 3l-9 9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><input id="scholar-file-{{ $key }}" type="file" name="documents[{{ $key }}]" accept="{{ $key==='photo'?'.jpg,.jpeg,.png':'.pdf,.jpg,.jpeg,.png' }}" aria-describedby="scholar-filename-{{ $key }}" required></div><span class="scholar-filename" id="scholar-filename-{{ $key }}" aria-live="polite"></span></div>
@endforeach
<button class="scholar-next" type="submit">Submit</button>
</form></section></main>
</body></html>
