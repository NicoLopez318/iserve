<label>Receipt Number
    <input name="details[receipt_number]" maxlength="80"
        value="{{ old('details.receipt_number', $paymentDetails['receipt_number'] ?? '') }}" required>
</label>
<label>Payment Amount (₱)
    <input type="number" name="details[payment_amount]" min="0.01" step="0.01"
        value="{{ old('details.payment_amount', $paymentDetails['payment_amount'] ?? '') }}" required>
</label>
