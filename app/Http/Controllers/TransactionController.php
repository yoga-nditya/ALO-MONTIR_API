<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Transaction;
use App\Models\EmergencyRequest;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->query('user_id');
        
        if (!$userId) {
            return response()->json([
                'message' => 'User ID is required'
            ], 400);
        }

        // Get regular transactions with consistent date format
        $transactions = Transaction::with('service')
            ->where('user_id', $userId)
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'date' => $t->date->format('Y-m-d H:i:s'), // Format konsisten
                    'display_date' => $t->date->format('d M Y'), // Untuk tampilan
                    'amount' => $t->amount,
                    'formatted_amount' => 'Rp ' . number_format($t->amount, 0, ',', '.'),
                    'service_name' => $t->service->name ?? 'Service',
                    'type' => 'regular',
                    'icon' => $t->service->icon ?? 'build',
                    'color' => '#F57C00'
                ];
            });

        // Get emergency requests with consistent date format
        $emergencies = EmergencyRequest::where('user_id', $userId)
            ->orderBy('request_date', 'desc')
            ->get()
            ->map(function ($e) {
                return [
                    'id' => 'emergency_' . $e->id,
                    'date' => $e->request_date->format('Y-m-d H:i:s'), // Format konsisten
                    'display_date' => $e->request_date->format('d M Y'), // Untuk tampilan
                    'amount' => $e->amount,
                    'formatted_amount' => 'Rp ' . number_format($e->amount, 0, ',', '.'),
                    'service_name' => $e->service_name,
                    'type' => 'emergency',
                    'icon' => 'warning',
                    'color' => '#FF5252',
                    'status' => $e->status
                ];
            });

        // Combine and sort by date
        $allTransactions = $transactions->merge($emergencies)
            ->sortByDesc(function ($item) {
                return $item['date'];
            })
            ->values();

        return response()->json($allTransactions);
    }

    public function getUserTransactions($userId)
    {
        // Reuse the same logic as index but with direct user ID
        return $this->index(new Request(['user_id' => $userId]));
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
                'date' => $transaction->date->format('Y-m-d H:i:s'),
                'display_date' => $transaction->date->format('d M Y'),
                'amount' => $transaction->amount,
                'formatted_amount' => 'Rp ' . number_format($transaction->amount, 0, ',', '.'),
                'service_name' => $service->name,
                'type' => 'regular',
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