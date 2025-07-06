<?php

namespace App\Http\Controllers;

use App\Models\Emergency;
use App\Models\EmergencyRequest;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EmergencyController extends Controller
{
    public function getServices()
    {
        try {
            $services = Emergency::where('is_active', true)->get();
            
            // Format response sesuai dengan yang diharapkan React Native
            $formattedServices = $services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'title' => $service->name,
                    'description' => $service->description,
                    'status' => 'Mulai dari',
                    'price' => 'Rp ' . number_format($service->price, 0, ',', '.'),
                    'amount' => $service->price,
                ];
            });

            return response()->json($formattedServices, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching emergency services: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch emergency services',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createRequest(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id',
                'service_id' => 'required|integer',
                'service_name' => 'required|string',
                'description' => 'required|string',
                'amount' => 'required|numeric|min:0',
                'type' => 'required|string',
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Gunakan database transaction untuk memastikan data konsisten
            DB::beginTransaction();

            try {
                // Ambil service_name dari request (yang dikirim dari frontend)
                $serviceName = $request->service_name;
                
                // Cari emergency service berdasarkan service_id sebagai fallback
                $emergencyService = Emergency::find($request->service_id);
                if ($emergencyService && empty($serviceName)) {
                    $serviceName = $emergencyService->name;
                }

                // Create emergency request SAJA - TIDAK create transaction
                $emergencyRequest = EmergencyRequest::create([
                    'user_id' => $request->user_id,
                    'service_id' => $request->service_id,
                    'service_name' => $serviceName,
                    'description' => $request->description,
                    'amount' => $request->amount,
                    'status' => 'pending',
                    'request_date' => $request->date,
                ]);

                // Log untuk debugging
                Log::info('Emergency request created (no transaction):', [
                    'emergency_request_id' => $emergencyRequest->id,
                    'service_name' => $serviceName,
                    'amount' => $request->amount
                ]);

                DB::commit();

                return response()->json([
                    'message' => 'Emergency request created successfully',
                    'success' => true,
                    'data' => [
                        'emergency_request' => $emergencyRequest
                    ]
                ], 201);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error creating emergency request: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Failed to create emergency request',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function getUserRequests($userId)
    {
        try {
            $requests = EmergencyRequest::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($requests, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching user emergency requests: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch emergency requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateRequestStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:pending,confirmed,completed,cancelled'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $emergencyRequest = EmergencyRequest::findOrFail($id);
            $emergencyRequest->update(['status' => $request->status]);

            // TIDAK lagi update transaction status karena tidak ada transaction yang dibuat

            return response()->json([
                'message' => 'Emergency request status updated successfully',
                'data' => $emergencyRequest
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating emergency request status: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update emergency request status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getEmergencyTransactions($userId)
    {
        try {
            $transactions = Transaction::where('user_id', $userId)
                ->where('type', 'emergency')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($transactions, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching emergency transactions: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch emergency transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get combined history of emergency requests and transactions
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCombinedHistory($userId)
    {
        try {
            // Get emergency requests
            $emergencyRequests = EmergencyRequest::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($request) {
                    return [
                        'id' => $request->id,
                        'type' => 'emergency_request',
                        'name' => $request->service_name,
                        'amount' => $request->amount,
                        'date' => $request->request_date,
                        'formatted_amount' => 'Rp ' . number_format($request->amount, 0, ',', '.'),
                        'formatted_date' => date('d M Y H:i', strtotime($request->request_date)),
                        'status' => $request->status,
                        'description' => $request->description
                    ];
                });

            // Get emergency transactions
            $transactions = Transaction::where('user_id', $userId)
                ->where('type', 'emergency')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'type' => 'transaction',
                        'name' => $transaction->description,
                        'amount' => $transaction->amount,
                        'date' => $transaction->created_at,
                        'formatted_amount' => 'Rp ' . number_format($transaction->amount, 0, ',', '.'),
                        'formatted_date' => date('d M Y H:i', strtotime($transaction->created_at)),
                        'status' => $transaction->status,
                        'description' => $transaction->description
                    ];
                });

            // Combine and sort by date
            $combinedHistory = $emergencyRequests->concat($transactions)
                ->sortByDesc('date')
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'data' => $combinedHistory
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching combined history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch combined history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed history with all fields from both emergency requests and transactions
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDetailedHistory($userId)
    {
        try {
            // Get emergency requests with all fields
            $emergencyRequests = EmergencyRequest::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($request) {
                    return [
                        'id' => $request->id,
                        'type' => 'emergency_request',
                        'service_id' => $request->service_id,
                        'service_name' => $request->service_name,
                        'amount' => $request->amount,
                        'formatted_amount' => 'Rp ' . number_format($request->amount, 0, ',', '.'),
                        'date' => $request->request_date,
                        'formatted_date' => date('d M Y H:i', strtotime($request->request_date)),
                        'status' => $request->status,
                        'description' => $request->description,
                        'created_at' => $request->created_at,
                        'updated_at' => $request->updated_at,
                        'metadata' => [
                            'is_emergency' => true,
                            'original_model' => 'EmergencyRequest'
                        ]
                    ];
                });

            // Get transactions with all fields
            $transactions = Transaction::where('user_id', $userId)
                ->where('type', 'emergency')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'type' => 'transaction',
                        'transaction_id' => $transaction->id,
                        'service_name' => $transaction->description,
                        'amount' => $transaction->amount,
                        'formatted_amount' => 'Rp ' . number_format($transaction->amount, 0, ',', '.'),
                        'date' => $transaction->created_at,
                        'formatted_date' => date('d M Y H:i', strtotime($transaction->created_at)),
                        'status' => $transaction->status,
                        'description' => $transaction->description,
                        'payment_method' => $transaction->payment_method,
                        'created_at' => $transaction->created_at,
                        'updated_at' => $transaction->updated_at,
                        'metadata' => [
                            'is_transaction' => true,
                            'original_model' => 'Transaction'
                        ]
                    ];
                });

            // Combine and sort by date
            $detailedHistory = $emergencyRequests->concat($transactions)
                ->sortByDesc('date')
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'data' => $detailedHistory
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching detailed history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch detailed history',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}