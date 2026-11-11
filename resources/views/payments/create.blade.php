@extends('layouts.app')
 
@section('content')
 
<div class="container-fluid">
 
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Record Payment</h1>
            <p class="text-muted mb-0">
                Capture a student payment.
            </p>
        </div>
    </div>
 
    <div class="card shadow-sm">
        <div class="card-body">
 
            <form method="POST" action="{{ route('payments.store') }}">
                @csrf
 
                <div class="row g-3">
 
                    <!-- Student -->
                    <div class="col-md-6">
                        <label for="student_id" class="form-label">
                            Student
                        </label>
 
                        <select
                            name="student_id"
                            id="student_id"
                            class="form-select @error('student_id') is-invalid @enderror"
                            required
                        >
                            <option value="">Select Student</option>
 
                            @foreach($students as $student)
                                <option
                                    value="{{ $student->id }}"
                                    {{ old('student_id') == $student->id ? 'selected' : '' }}
                                >
                                    {{ $student->student_number }}
                                    - {{ $student->first_name }}
                                    {{ $student->last_name }}
                                </option>
                            @endforeach
                        </select>
 
                        @error('student_id')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
 
                    <!-- Fee Structure -->
                        <div class="col-md-6">
                            <label for="fee_structure_id" class="form-label">
                                Fee Structure
                            </label>
 
                            <select
                                name="fee_structure_id"
                                id="fee_structure_id"
                                class="form-select @error('fee_structure_id') is-invalid @enderror"
                                required
                            >
                                <option value="">Select Fee Structure</option>
 
                                @foreach($feeStructures as $feeStructure)
                                    <option
                                        value="{{ $feeStructure->id }}"
                                        {{ old('fee_structure_id') == $feeStructure->id ? 'selected' : '' }}
                                    >
                                        {{ $feeStructure->course->name }}
                                        - KSh {{ number_format($feeStructure->amount, 2) }}
                                    </option>
                                @endforeach
                            </select>
 
                            @error('fee_structure_id')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
 
                    <!-- Amount -->
                    <div class="col-md-6">
                        <label for="amount" class="form-label">
                            Amount
                        </label>
 
                        <input
                            type="number"
                            name="amount"
                            id="amount"
                            class="form-control @error('amount') is-invalid @enderror"
                            value="{{ old('amount') }}"
                            min="1"
                            step="0.01"
                            required
                        >
 
                        @error('amount')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
 
                    <!-- Payment Date -->
                    <div class="col-md-6">
                        <label for="payment_date" class="form-label">
                            Payment Date
                        </label>
 
                        <input
                            type="date"
                            name="payment_date"
                            id="payment_date"
                            class="form-control @error('payment_date') is-invalid @enderror"
                            value="{{ old('payment_date', now()->format('Y-m-d')) }}"
                            required
                        >
 
                        @error('payment_date')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
 
                    <!-- Payment Method -->
                    <div class="col-md-6">
                        <label for="payment_method" class="form-label">
                            Payment Method
                        </label>
 
                        <select
                            name="payment_method"
                            id="payment_method"
                            class="form-select @error('payment_method') is-invalid @enderror"
                            required
                        >
                            <option value="">Select Method</option>
                            <option value="mpesa"
                                {{ old('payment_method') == 'mpesa' ? 'selected' : '' }}>
                                M-Pesa
                            </option>
                            <option value="cash"
                                {{ old('payment_method') == 'cash' ? 'selected' : '' }}>
                                Cash
                            </option>
                            <option value="bank"
                                {{ old('payment_method') == 'bank' ? 'selected' : '' }}>
                                Bank
                            </option>
                        </select>
 
                        @error('payment_method')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
 
                    <!-- M-Pesa Phone -->
                    <div class="col-md-6" id="mpesaPhoneWrapper">
                        <label for="phone" class="form-label">
                            M-Pesa Phone Number
                        </label>
 
                        <input
                            type="text"
                            name="phone"
                            id="phone"
                            class="form-control @error('phone') is-invalid @enderror"
                            value="{{ old('phone') }}"
                            placeholder="2547XXXXXXXX"
                        >
 
                        <div class="form-text">
                            Enter the phone number that should receive the STK Push.
                        </div>
 
                        @error('phone')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
 
                </div>
 
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-credit-card"></i>
                        Continue Payment
                    </button>
 
                    <a href="{{ route('payments.index') }}"
                       class="btn btn-secondary">
                        Cancel
                    </a>
                </div>
 
            </form>
 
        </div>
    </div>
 
</div>
 
@endsection
 
<script>
 
document.addEventListener('DOMContentLoaded', () => {
 
    const paymentMethod =
        document.getElementById('payment_method');
 
    const phoneWrapper =
        document.getElementById('mpesaPhoneWrapper');
 
    const phone =
        document.getElementById('phone');
 
    function toggleMpesaPhone() {
 
        if (paymentMethod.value === 'mpesa') {
 
            phoneWrapper.classList.remove('d-none');
            phone.required = true;
 
        } else {
 
            phoneWrapper.classList.add('d-none');
            phone.required = false;
            phone.value = '';
 
        }
    }
 
    paymentMethod.addEventListener('change', toggleMpesaPhone);
 
    toggleMpesaPhone();
});
 
</script>
 