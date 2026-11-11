Payment Successful

Dear {{ $fee->student->name ?? 'Student' }},

We have received confirmation of your M-Pesa payment for your school fees.

Payment details

- Student: {{ $fee->student->name ?? 'Student' }}
- Amount: KSh {{ number_format($fee->amount, 2) }}
- Payment status: Successful

Thank you for your payment.

Student Management System