<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Http\Request;

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
                    'icon' => $t->service->icon ?? 'build', // gunakan icon dari service jika ada
                    'color' => '#F57C00',
                    'service_name' => $t->service->name ?? null, // tambahkan nama service
                ];
            });

        return response()->json($transactions);
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
