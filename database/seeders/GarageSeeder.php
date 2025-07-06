<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GarageSeeder extends Seeder
{
    public function run()
    {
        DB::table('garages')->insert([
            [
                'name' => 'Bintang Makmur Motor',
                'location' => 'Jl. Purwodadi Ujung No.178F',
                'mechanic_name' => 'Wahyu',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Gans Project',
                'location' => 'Jl. Spokat, Gang 2 Spokat',
                'mechanic_name' => 'Ganang Nugraha',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Amomo Motor Cycle',
                'location' => 'Jl. Rambutan, Sidomulyo Timur, Kec. Marpoyan Damai, Kota Pekanbaru',
                'mechanic_name' => 'Depet Arwan',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Kemayoran Service',
                'location' => 'Jl. HR. Soebrantas No.25A, Simpang Baru, Kec. Tampan, Kota Pekanbaru',
                'mechanic_name' => 'Ropi',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
