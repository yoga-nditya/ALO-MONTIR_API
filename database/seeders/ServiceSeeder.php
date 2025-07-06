<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Service;


class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            ['name' => 'Servis Berkala', 'icon' => 'build', 'amount' => 100000],
            ['name' => 'Servis Vanbelt', 'icon' => 'car-sport', 'amount' => 80000],
            ['name' => 'Rem', 'icon' => 'construct', 'amount' => 60000],
            ['name' => 'Lampu', 'icon' => 'bulb', 'amount' => 30000],
            ['name' => 'Aki', 'icon' => 'battery-charging', 'amount' => 150000],
        ];

        foreach ($services as $service) {
            Service::create(array_merge($service, ['garage_id' => 1])); 
        }
    }
}
