<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Select Transaction · iServe</title><link rel="stylesheet" href="{{ asset('app.css') }}">
<style>
body {
    min-height: 100vh;
    background: linear-gradient(160deg, #223b8b 55%, #162d74);
    color: #fff;
}
.transaction-page { width: min(456px, 84%); margin: 0 auto; padding: 18px 0 32px; }
.transaction-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 28px; }
.transaction-heading h1 { margin: 0; font-size: clamp(24px, 6vw, 34px); letter-spacing: -.05em; }
.transaction-home { display: inline-flex; flex: none; padding: 6px; }
.transaction-card {
    display: grid;
    gap: 15px;
    padding: clamp(30px, 14.7vw, 80px) clamp(20px, 13.2vw, 72px);
    border-radius: 11px;
    background: #354e9a;
}
.transaction-choice, .lost-found-reports a {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 8px;
    border-radius: 8px;
    text-align: center;
    font-size: 13px;
    font-weight: 600;
}
.transaction-choice { background: #ff6d00; }
.transaction-choice:hover { background: #d65500; }
summary.transaction-choice { cursor: pointer; }
summary.transaction-choice::-webkit-details-marker { display: none; }
.lost-found-reports { display: grid; gap: 8px; margin-top: 8px; }
.lost-found-reports a { border: 1px solid #b9c9f9; }
.lost-found-reports a:hover { background: #223b8b; }
.transaction-page :focus-visible { outline: 2px solid #fff; outline-offset: 3px; }
</style><link rel="stylesheet" href="{{ asset('responsive.css') }}?v=20261112"></head>
<body>
<main class="transaction-page">
    <div class="transaction-heading">
        <h1 id="transaction-title">Select Transaction</h1>
        <a class="transaction-home" href="/" aria-label="Return to home">
            <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M12 2 2 11v11h7v-8h6v8h7V11L12 2Z"/></svg>
        </a>
    </div>
    <nav class="transaction-card" aria-labelledby="transaction-title">
        <a class="transaction-choice" href="/apply/complaint">Complaints Concern</a>
        @include('transactions.lost-found')
        <a class="transaction-choice" href="/apply/readmission">Re-Admission</a>
        <a class="transaction-choice" href="/apply/id-replacement">ID Replacement</a>
        <a class="transaction-choice" href="/community-link">Community Service</a>
        <a class="transaction-choice" href="/apply/clearance">Clearance</a>
        <a class="transaction-choice" href="/apply/seminar">Seminars</a>
    </nav>
</main>
</body></html>
