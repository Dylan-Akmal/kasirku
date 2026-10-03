<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // [nama, kategori, sku, harga beli, harga jual, stok]
        $products = [
            ['Indomie Goreng', 'Makanan', '8999999000011', 2800, 3500, 100],
            ['Indomie Soto', 'Makanan', '8999999000012', 2700, 3400, 80],
            ['Roti Tawar', 'Makanan', '8999999000013', 12000, 15000, 20],
            ['Aqua 600ml', 'Minuman', '8999999000021', 2500, 3500, 120],
            ['Teh Botol 450ml', 'Minuman', '8999999000022', 3500, 5000, 60],
            ['Kopi Sachet', 'Minuman', '8999999000023', 1200, 2000, 150],
            ['Chitato 68g', 'Snack', '8999999000031', 8500, 11000, 40],
            ['Oreo 133g', 'Snack', '8999999000032', 7000, 9500, 35],
            ['Beras 5kg', 'Sembako', '8999999000041', 62000, 70000, 15],
            ['Minyak Goreng 1L', 'Sembako', '8999999000042', 15000, 18000, 24],
            ['Gula Pasir 1kg', 'Sembako', '8999999000043', 14000, 17000, 4],
            ['Sabun Mandi', 'Kebersihan', '8999999000051', 3000, 4500, 50],
        ];

        foreach ($products as [$name, $category, $sku, $buy, $sell, $stock]) {
            Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'category_id' => Category::where('name', $category)->value('id'),
                    'name' => $name,
                    'price_buy' => $buy,
                    'price_sell' => $sell,
                    'stock' => $stock,
                    'min_stock' => 5,
                ]
            );
        }
    }
}