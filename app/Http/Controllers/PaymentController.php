<?php
 
namespace App\Http\Controllers;
 
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\Payment;
use App\Models\Student;
use App\Notifications\PaymentSuccessfulNotification;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
 
class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
    $query = Payment::with([
    'student',
    'feeStructure.course',
    'feeStructure.semester',
    'feeStructure.academicYear',
    'receivedBy',
]);
 
if ($request->filled('search')) {
 
    $search = $request->search;
 
    $query->whereHas('student', function ($studentQuery) use ($search) {
 
        $studentQuery->where('student_number', 'like', "%{$search}%")
            ->orWhere('first_name', 'like', "%{$search}%")
            ->orWhere('last_name', 'like', "%{$search}%");
 
    });
}
 
if ($request->filled('status')) {
 
    $query->where('status', $request->status);
}
 
if ($request->filled('payment_method')) {
 
    $query->where('payment_method', $request->payment_method);
}
 
$payments = $query
    ->latest()
    ->paginate(10)
    ->withQueryString();
 
    $totalCollected = Payment::where('status', 'completed')
        ->sum('amount');
 
    $thisMonth = Payment::where('status', 'completed')
        ->whereMonth('payment_date', now()->month)
        ->whereYear('payment_date', now()->year)
        ->sum('amount');
 
    $pendingMpesa = Payment::where('status', 'pending')
        ->where('payment_method', 'mpesa')
        ->sum('amount');
 
    return view('payments.index', compact(
        'payments',
        'totalCollected',
        'thisMonth',
        'pendingMpesa'
    ));
}
   
 
    /**
     * Show the form for creating a new resource.
     */
    public function create()
{
    $students = Student::where('status', 'active')
        ->orderBy('first_name')
        ->get();
 
    $feeStructures = FeeStructure::with([
        'course',
        'semester',
        'academicYear',
    ])
    ->latest()
    ->get();
 
    return view('payments.create', compact(
        'students',
        'feeStructures'
    ));
}
 
    /**
     * Store a newly created resource in storage.
     */
   
     public function store(Request $request, MpesaService $mpesa)
    {
      $request->validate([
        'student_id' => ['required', 'exists:students,id'],
        'fee_structure_id' => ['required', 'exists:fee_structures,id'],
        'amount' => ['required', 'numeric', 'min:1'],
        'payment_date' => ['required', 'date'],
 
        'payment_method' => [
            'required',
            'in:mpesa,cash,bank',
        ],
 
        'phone' => [
            'nullable',
            'required_if:payment_method,mpesa',
            'regex:/^2547\d{8}$/',
        ],
    ]);
 
    $reference = 'PAY-' . now()->format('YmdHis') . '-' . Str::upper(
        Str::random(6)
    );
 
    $payment = Payment::create([
        'student_id' => $request->student_id,
        'fee_structure_id' => $request->fee_structure_id,
        'amount' => $request->amount,
        'payment_date' => $request->payment_date,
        'payment_method' => $request->payment_method,
 
        'mpesa_phone' => $request->payment_method === 'mpesa'
            ? $request->phone
            : null,
 
        'status' => 'pending',
 
        'transaction_reference' => $reference,
 
        'receipt_number' => 'REC-' . now()->format('YmdHis') . '-' . Str::upper(
            Str::random(6)
        ),
 
        'received_by' => auth()->id(),
    ]);
 
            if ($request->payment_method === 'mpesa') {
                $result = $mpesa->stkPush(
                    $payment->mpesa_phone,
                    (float) $payment->amount,
                    $payment->transaction_reference
                );
 
                $payment->update([
                    'merchant_request_id' => $result['MerchantRequestID'],
                    'checkout_request_id' => $result['CheckoutRequestID'],
                ]);
            }
           
            else {
                // Cash and bank payments are recorded as completed.
                $payment->update([
                    'status' => 'completed',
                ]);
 
                // Send confirmation email to the student.
                // $payment->student->notify(
                //     new PaymentSuccessfulNotification($payment)
                // );
            }
 
 
    return redirect()
        ->route('payments.index')
        ->with('success', 'Payment recorded successfully.');
}
 
 
 
    public function mpesaCallback(Request $request)
{
 
        Log::info('Raw M-Pesa Callback:', ['payload' => $request->getContent()]);
    $callback = $request->input('Body.stkCallback');
 
    if (!$callback) {
        return response()->json([
            'message' => 'Invalid callback data'
        ], 400);
    }
 
    $checkoutRequestId = $callback['CheckoutRequestID'];
    $resultCode = $callback['ResultCode'];
 
    $payment = Payment::where('checkout_request_id', $checkoutRequestId)->first();
 
    if (!$payment) {
        return response()->json([
            'message' => 'Payment not found'
        ], 404);
    }
 
    if ((int) $resultCode === 0) {
    // Check whether this payment was already completed.
    $wasAlreadyCompleted = $payment->status === 'completed';
 
    $items = $callback['CallbackMetadata']['Item'] ?? [];
    $metadata = collect($items)->keyBy('Name');
 
    $mpesaReceiptNumber =
        $metadata['MpesaReceiptNumber']['Value'] ?? null;
 
    $payment->update([
        'status' => 'completed',
        'mpesa_receipt_number' => $mpesaReceiptNumber,
    ]);
 
    //Send email only on the first successful completion.
    if (!$wasAlreadyCompleted) {
       $payment->student->notify(
       new PaymentSuccessfulNotification($payment));
    }
}
 
else {
    $payment->update([
        'status' => 'failed',
    ]);
}
 
    return response()->json([
        'ResultCode' => 0,
        'ResultDesc' => 'Callback processed successfully'
    ]);
}
 
    /**
     * Display the specified resource.
     */
    public function show(Payment $payment)
{
   
    $payment->load([
        'student',
        'feeStructure.course',
        'feeStructure.semester',
        'feeStructure.academicYear',
        'receivedBy',
    ]);
 
    $totalFee = $payment->feeStructure->amount;
 
    $paidAmount = Payment::where('student_id', $payment->student_id)
        ->where('fee_structure_id', $payment->fee_structure_id)
        ->where('status', 'completed')
        ->sum('amount');
 
    $balance = max($totalFee - $paidAmount, 0);
 
    return view('payments.show', compact(
        'payment',
        'totalFee',
        'paidAmount',
        'balance'
    ));
}
 
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }
 
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }
 
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
 
 
 
    public function getPaymentSummary(Request $request)
{
    $validated = $request->validate([
        'student_id' => ['required', 'exists:students,id'],
        'fee_structure_id' => ['required', 'exists:fee_structures,id'],
    ]);
 
    $feeStructure = FeeStructure::findOrFail(
        $validated['fee_structure_id']
    );
 
    $paidAmount = Payment::where('student_id', $validated['student_id'])
        ->where('fee_structure_id', $feeStructure->id)
        ->where('status', 'completed')
        ->sum('amount');
 
    $balance = $feeStructure->amount - $paidAmount;
 
    return response()->json([
        'total_fee' => $feeStructure->amount,
        'paid' => $paidAmount,
        'balance' => max($balance, 0),
    ]);
}
 
public function studentStatement(Student $student)
{
    $student->load([
        'enrollments.course',
        'enrollments.semester',
        'enrollments.academicYear',
    ]);
 
    $feeStructures = FeeStructure::where(function ($query) use ($student) {
 
    foreach ($student->enrollments as $enrollment) {
 
        $query->orWhere(function ($subQuery) use ($enrollment) {
 
            $subQuery->where('course_id', $enrollment->course_id)
                ->where('semester_id', $enrollment->semester_id)
                ->where(
                    'academic_year_id',
                    $enrollment->academic_year_id
                );
 
        });
 
    }
 
})->get();
 
    $payments = Payment::with([
        'feeStructure.course',
        'feeStructure.semester',
        'feeStructure.academicYear',
        'receivedBy',
    ])
    ->where('student_id', $student->id)
    ->where('status', 'completed')
    ->latest('payment_date')
    ->get();
 
    $statementItems = $feeStructures->map(function ($feeStructure) use ($payments) {
 
    $paid = $payments
        ->where('fee_structure_id', $feeStructure->id)
        ->sum('amount');
 
    return [
        'feeStructure' => $feeStructure,
        'totalFee' => $feeStructure->amount,
        'paid' => $paid,
        'balance' => max($feeStructure->amount - $paid, 0),
    ];
});
 
$statementItems = $statementItems->map(function ($item) use ($payments) {
 
    $item['payments'] = $payments
        ->where('fee_structure_id', $item['feeStructure']->id)
        ->values();
 
    return $item;
});
 
 
    $totalFees = $statementItems->sum('totalFee');
 
    $totalPaid = $statementItems->sum('paid');
 
    $balance = $statementItems->sum('balance');
 
    return view('payments.student-statement', compact(
        'student',
        'payments',
        'statementItems',
        'totalFees',
        'totalPaid',
        'balance'
    ));
}
 public function processPayment(Request $request)
    {
        // ... your payment logic here ...

        $student = User::find($request->student_id);

        // THIS IS THE MAGIC LINE:
        $student->notify(new PaymentSuccessfulNotification());

        return redirect()->back()->with('success', 'Payment successful and email sent!');
    }
}
 