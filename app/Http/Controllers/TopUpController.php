<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\TopUp;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Notification;
use Midtrans\Transaction as MidtransTransaction;

class TopUpController extends Controller
{
    public function __construct()
    {
        // Midtrans Configuration
        Config::$serverKey = config('midtrans.server_key');
        Config::$clientKey = config('midtrans.client_key');
        Config::$isProduction = config('midtrans.is_production', false);
        Config::$isSanitized = config('midtrans.is_sanitized', true);
        Config::$is3ds = config('midtrans.is_3ds', true);
    }

    /**
     * Get user balance
     */
    public function getSaldo(Request $request)
    {
        try {
            $userId = $request->user()->id ?? $request->input('user_id');
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User ID is required'
                ], 400);
            }

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'saldo' => $user->saldo ?? 0,
                'message' => 'Balance retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error getting balance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get balance'
            ], 500);
        }
    }

    /**
     * Create top up transaction
     */
    public function createTopUp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:1000|max:10000000',
            'payment_type' => 'required|string|in:DANA,BCA Virtual Account,BNI Virtual Account,BRI Virtual Account,Mandiri Virtual Account,GoPay,OVO,ShopeePay,QRIS',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $user = User::find($request->user_id);
            $amount = (int) $request->amount;
            $paymentMethod = $request->payment_type;

            // Generate unique order ID with timestamp to avoid duplicates
            $orderId = 'TOPUP-' . $user->id . '-' . time() . '-' . rand(1000, 9999);

            // Get Midtrans payment type
            $midtransPaymentType = $this->mapToMidtransPaymentType($paymentMethod);

            // Create top up record first to ensure order_id exists
            $topUp = TopUp::create([
                'user_id' => $user->id,
                'order_id' => $orderId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_type' => $midtransPaymentType,
                'status' => 'pending',
            ]);

            // Prepare transaction details
            $transactionDetails = [
                'order_id' => $orderId,
                'gross_amount' => $amount,
            ];

            $customerDetails = [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '08123456789',
            ];

            $itemDetails = [
                [
                    'id' => 'topup',
                    'price' => $amount,
                    'quantity' => 1,
                    'name' => 'Top Up Balance',
                ]
            ];

            // Prepare Midtrans parameters - This is the key fix
            $params = [
                'transaction_details' => $transactionDetails,
                'customer_details' => $customerDetails,
                'item_details' => $itemDetails,
                'expiry' => [
                    'unit' => 'minutes',
                    'duration' => 60
                ]
            ];

            // Add enabled payments - Only enable the selected payment method
            $enabledPayments = $this->getEnabledPayments($paymentMethod);
            if (!empty($enabledPayments)) {
                $params['enabled_payments'] = $enabledPayments;
            }

            // Add specific parameters based on payment method
            switch ($midtransPaymentType) {
                case 'bank_transfer':
                    $bankCode = $this->getBankCode($paymentMethod);
                    if ($bankCode) {
                        $params['bank_transfer'] = [
                            'bank' => $bankCode
                        ];
                    }
                    break;
                    
                case 'echannel':
                    $params['echannel'] = [
                        'bill_info1' => 'Payment For:',
                        'bill_info2' => 'Top Up Balance',
                    ];
                    break;
                    
                case 'gopay':
                    $params['gopay'] = [
                        'enable_callback' => true,
                        'callback_url' => url('/api/topup/notification')
                    ];
                    break;
                    
                case 'shopeepay':
                    $params['shopeepay'] = [
                        'callback_url' => url('/api/topup/notification')
                    ];
                    break;
                    
                case 'qris':
                    $params['qris'] = [
                        'acquirer' => 'gopay' // or 'airpay_shopee'
                    ];
                    break;
                    
                case 'cstore':
                    $params['cstore'] = [
                        'store' => 'indomaret', // or 'alfamart'
                        'message' => 'Top Up Balance'
                    ];
                    break;
            }

            Log::info('Midtrans params:', $params);

            // Get snap token from Midtrans
            $snapToken = Snap::getSnapToken($params);

            // Generate redirect URL
            $redirectUrl = config('midtrans.is_production')
                ? 'https://app.midtrans.com/snap/v2/vtweb/' . $snapToken
                : 'https://app.sandbox.midtrans.com/snap/v2/vtweb/' . $snapToken;

            // Update top up record with snap token and redirect URL
            $topUp->update([
                'snap_token' => $snapToken,
                'redirect_url' => $redirectUrl,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'snap_token' => $snapToken,
                    'redirect_url' => $redirectUrl,
                    'order_id' => $orderId,
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                ],
                'message' => 'Top up transaction created successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Top up error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create top up: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Midtrans notification
     */
    public function handleNotification(Request $request)
    {
        try {
            $notification = new Notification();
            
            $orderId = $notification->order_id;
            $transactionStatus = $notification->transaction_status;
            $fraudStatus = $notification->fraud_status;
            $paymentType = $notification->payment_type;
            $grossAmount = $notification->gross_amount;

            Log::info('Received payment notification', [
                'order_id' => $orderId,
                'status' => $transactionStatus,
                'payment_type' => $paymentType,
                'amount' => $grossAmount,
                'fraud_status' => $fraudStatus
            ]);

            // Verify the transaction exists
            $topUp = TopUp::where('order_id', $orderId)->first();
            
            if (!$topUp) {
                Log::error('Top up not found: ' . $orderId);
                return response()->json(['message' => 'Order not found'], 404);
            }

            // Verify signature key
            $signatureKey = $notification->signature_key;
            $expectedSignature = hash('sha512', $orderId . $transactionStatus . $grossAmount . config('midtrans.server_key'));
            
            if ($signatureKey !== $expectedSignature) {
                Log::error('Invalid signature key', [
                    'received' => $signatureKey,
                    'expected' => $expectedSignature
                ]);
                return response()->json(['message' => 'Invalid signature'], 401);
            }

            // Skip if already processed
            if ($topUp->status === 'success') {
                Log::info('Transaction already processed: ' . $orderId);
                return response()->json(['message' => 'Already processed']);
            }

            DB::beginTransaction();

            try {
                switch ($transactionStatus) {
                    case 'capture':
                        if ($fraudStatus === 'challenge') {
                            $topUp->update(['status' => 'challenge']);
                            Log::info('Transaction flagged as challenge: ' . $orderId);
                        } elseif ($fraudStatus === 'accept') {
                            $this->completeTopUp($topUp);
                            Log::info('Transaction captured and accepted: ' . $orderId);
                        }
                        break;
                        
                    case 'settlement':
                        $this->completeTopUp($topUp);
                        Log::info('Transaction settled: ' . $orderId);
                        break;
                        
                    case 'pending':
                        $topUp->update(['status' => 'pending']);
                        Log::info('Transaction pending: ' . $orderId);
                        break;
                        
                    case 'deny':
                        $topUp->update(['status' => 'failed']);
                        Log::info('Transaction denied: ' . $orderId);
                        break;
                        
                    case 'expire':
                        $topUp->update(['status' => 'expired']);
                        Log::info('Transaction expired: ' . $orderId);
                        break;
                        
                    case 'cancel':
                        $topUp->update(['status' => 'cancelled']);
                        Log::info('Transaction cancelled: ' . $orderId);
                        break;
                        
                    default:
                        Log::warning('Unknown transaction status: ' . $transactionStatus . ' for order: ' . $orderId);
                        break;
                }

                DB::commit();
                return response()->json(['message' => 'Notification processed successfully']);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error processing notification: ' . $e->getMessage());
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Notification handler error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['message' => 'Error processing notification'], 500);
        }
    }

    /**
     * Check payment status
     */
    public function checkPaymentStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $orderId = $request->order_id;
            $topUp = TopUp::where('order_id', $orderId)->first();

            if (!$topUp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            // For pending transactions, check with Midtrans
            if (in_array($topUp->status, ['pending', 'challenge'])) {
                try {
                    $status = MidtransTransaction::status($orderId);
                    Log::info('Midtrans status check result:', [
                        'order_id' => $orderId,
                        'status' => $status->transaction_status,
                        'payment_type' => $status->payment_type ?? 'N/A'
                    ]);
                    
                    $this->updateStatusFromMidtrans($topUp, $status);
                    $topUp->refresh();
                } catch (\Exception $e) {
                    Log::warning('Midtrans status check failed: ' . $e->getMessage());
                    // Don't fail the request if Midtrans check fails
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $topUp->order_id,
                    'status' => $topUp->status,
                    'amount' => $topUp->amount,
                    'payment_method' => $topUp->payment_method,
                    'created_at' => $topUp->created_at->toDateTimeString(),
                    'paid_at' => $topUp->paid_at ? $topUp->paid_at->toDateTimeString() : null,
                ],
                'message' => 'Status retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Status check error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check status'
            ], 500);
        }
    }

    /**
     * Complete a successful top up
     */
    private function completeTopUp(TopUp $topUp)
    {
        $topUp->update([
            'status' => 'success',
            'paid_at' => now(),
        ]);

        $user = $topUp->user;
        $previousBalance = $user->saldo ?? 0;
        $user->increment('saldo', $topUp->amount);
        $user->refresh();

        // Record transaction
        Transaction::create([
            'user_id' => $user->id,
            'type' => 'topup',
            'amount' => $topUp->amount,
            'description' => 'Top up via ' . $topUp->payment_method,
            'status' => 'success',
            'reference_id' => $topUp->order_id,
            'balance_before' => $previousBalance,
            'balance_after' => $user->saldo,
        ]);

        Log::info('Top up completed successfully', [
            'user_id' => $user->id,
            'amount' => $topUp->amount,
            'previous_balance' => $previousBalance,
            'new_balance' => $user->saldo
        ]);
    }

    /**
     * Helper: Get enabled payments for specific payment method
     */
    private function getEnabledPayments(string $paymentMethod): array
    {
        $paymentMap = [
            'DANA' => ['dana'],
            'BCA Virtual Account' => ['bca_va'],
            'BNI Virtual Account' => ['bni_va'],
            'BRI Virtual Account' => ['bri_va'],
            'Mandiri Virtual Account' => ['echannel'],
            'GoPay' => ['gopay'],
            'OVO' => ['ovo'],
            'ShopeePay' => ['shopeepay'],
            'QRIS' => ['qris'],
        ];

        return $paymentMap[$paymentMethod] ?? [];
    }

    /**
     * Helper: Map payment method to Midtrans type
     */
    private function mapToMidtransPaymentType(string $paymentMethod): string
    {
        $mapping = [
            'DANA' => 'dana',
            'BCA Virtual Account' => 'bank_transfer',
            'BNI Virtual Account' => 'bank_transfer',
            'BRI Virtual Account' => 'bank_transfer',
            'Mandiri Virtual Account' => 'echannel',
            'GoPay' => 'gopay',
            'OVO' => 'ovo',
            'ShopeePay' => 'shopeepay',
            'QRIS' => 'qris',
        ];

        return $mapping[$paymentMethod] ?? 'bank_transfer';
    }

    /**
     * Helper: Get bank code for virtual accounts
     */
    private function getBankCode(string $paymentMethod): string
    {
        $bankMap = [
            'BCA Virtual Account' => 'bca',
            'BNI Virtual Account' => 'bni',
            'BRI Virtual Account' => 'bri',
        ];

        return $bankMap[$paymentMethod] ?? '';
    }

    /**
     * Update status from Midtrans response
     */
    private function updateStatusFromMidtrans(TopUp $topUp, $midtransStatus)
    {
        $status = $midtransStatus->transaction_status;
        $fraudStatus = $midtransStatus->fraud_status ?? null;

        Log::info('Updating status from Midtrans', [
            'order_id' => $topUp->order_id,
            'current_status' => $topUp->status,
            'midtrans_status' => $status,
            'fraud_status' => $fraudStatus
        ]);

        DB::beginTransaction();

        try {
            switch ($status) {
                case 'capture':
                    if ($fraudStatus === 'accept') {
                        $this->completeTopUp($topUp);
                    } elseif ($fraudStatus === 'challenge') {
                        $topUp->update(['status' => 'challenge']);
                    }
                    break;
                    
                case 'settlement':
                    $this->completeTopUp($topUp);
                    break;
                    
                case 'pending':
                    $topUp->update(['status' => 'pending']);
                    break;
                    
                case 'deny':
                    $topUp->update(['status' => 'failed']);
                    break;
                    
                case 'expire':
                    $topUp->update(['status' => 'expired']);
                    break;
                    
                case 'cancel':
                    $topUp->update(['status' => 'cancelled']);
                    break;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating status from Midtrans: ' . $e->getMessage());
            throw $e;
        }
    }
}