<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Transaction;
use App\Models\EmergencyRequest;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->query('user_id');

        $transactions = Transaction::with('service')
            ->when($userId, function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'date' => date('Y-m-d', strtotime($t->date)),
                    'amount' => 'Rp. ' . number_format($t->amount, 0, ',', '.'),
                    'icon' => $t->service->icon ?? 'build',
                    'color' => '#F57C00',
                    'service_name' => $t->service->name ?? null,
                ];
            });

        return response()->json($transactions);
    }

    public function getUserTransactions($userId)
    {
        try {
            // Ambil transaksi regular
            $regularTransactions = Transaction::with('service')
                ->where('user_id', $userId)
                ->orderBy('date', 'desc')
                ->get()
                ->map(function ($t) {
                    return [
                        'id' => 'transaction_' . $t->id,
                        'date' => $t->date,
                        'display_date' => Carbon::parse($t->date)->format('d M Y, H:i'),
                        'amount' => $t->amount,
                        'formatted_amount' => 'Rp. ' . number_format($t->amount, 0, ',', '.'),
                        'service_name' => $t->service->name ?? 'Unknown Service',
                        'type' => 'regular',
                        'icon' => $t->service->icon ?? 'build',
                        'color' => '#F57C00',
                        'status' => 'completed'
                    ];
                });

            // Ambil emergency requests
            $emergencyRequests = EmergencyRequest::where('user_id', $userId)
                ->orderBy('request_date', 'desc')
                ->get()
                ->map(function ($er) {
                    return [
                        'id' => 'emergency_' . $er->id,
                        'date' => $er->request_date,
                        'display_date' => Carbon::parse($er->request_date)->format('d M Y, H:i'),
                        'amount' => $er->amount,
                        'formatted_amount' => 'Rp. ' . number_format($er->amount, 0, ',', '.'),
                        'service_name' => $er->service_name ?? 'Emergency Service',
                        'type' => 'emergency',
                        'icon' => 'warning',
                        'color' => '#FF6B6B',
                        'status' => $er->status ?? 'pending'
                    ];
                });

            // Gabungkan dan urutkan berdasarkan tanggal
            $allTransactions = $regularTransactions->concat($emergencyRequests)
                ->sortByDesc('date')
                ->values();

            return response()->json([
                'success' => true,
                'data' => $allTransactions,
                'message' => 'Transactions retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error fetching user transactions: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Failed to fetch transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'garage_id' => 'required|exists:garages,id',
            'user_id' => 'required|exists:users,id',
            'services' => 'required|array',
            'services.*.id' => 'required|exists:services,id',
            'services.*.amount' => 'required|integer',
            'date' => 'required|date',
        ]);

        $createdTransactions = [];

        foreach ($validated['services'] as $serviceData) {
            $service = Service::find($serviceData['id']);

            $transaction = Transaction::create([
                'garage_id' => $validated['garage_id'],
                'user_id' => $validated['user_id'],
                'service_id' => $service->id,
                'amount' => $serviceData['amount'],
                'date' => $validated['date'],
            ]);

            $createdTransactions[] = [
                'id' => $transaction->id,
                'date' => $transaction->date,
                'amount' => 'Rp. ' . number_format($transaction->amount, 0, ',', '.'),
                'service_name' => $service->name,
                'icon' => $service->icon ?? 'build',
                'color' => '#F57C00',
            ];
        }

        return response()->json([
            'message' => 'Transaksi berhasil',
            'data' => $createdTransactions
        ], 201);
    }
}