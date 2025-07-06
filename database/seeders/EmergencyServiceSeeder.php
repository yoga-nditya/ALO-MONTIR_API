<?php

namespace Database\Seeders;

use App\Models\Emergency;
use Illuminate\Database\Seeder;

class EmergencyServiceSeeder extends Seeder
{
    public function run()
    {
        $services = [
            [
                'name' => 'Motor Mogok',
                'description' => 'Mesin tidak bisa dihidupkan',
                'price' => 150000,
                'icon' => 'car',
                'color' => '#F57C00',
            ],
            [
                'name' => 'Ban Bocor',
                'description' => 'Kerusakan ban mendadak',
                'price' => 75000,
                'icon' => 'car-sport',
                'color' => '#FF5722',
            ],
            [
                'name' => 'Isi Bensin Cadangan',
                'description' => 'Bensin habis di jalan',
                'price' => 50000,
                'icon' => 'car',
                'color' => '#4CAF50',
            ],
        ];

        foreach ($services as $service) {
            Emergency::create($service);
        }
    }
}