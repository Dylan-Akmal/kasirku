<?php

namespace Database\Seeders;

use App\Models\StoreSetting;
use Illuminate\Database\Seeder;

class StoreSettingSeeder extends Seeder
{
    public function run(): void
    {
        StoreSetting::updateOrCreate(
            ['id' => 1],
            [
                'store_name' => 'Toko Kasirku',
                'address' => 'Jl. Contoh No. 1, Bandung',
                'phone' => '081234567890',
                'receipt_footer' => 'Terima kasih sudah berbelanja!',
            ]
        );
    }
}