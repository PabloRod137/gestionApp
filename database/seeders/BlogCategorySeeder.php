<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use Illuminate\Database\Seeder;

class BlogCategorySeeder extends Seeder
{
    /**
     * Corre el seeder.
     * Inserta las categorías para la sección de blog e informes.
     */
    public function run(): void
    {
        $categorias = [
            ['nombre' => 'Fiscal y Contable'],
            ['nombre' => 'Laboral y Social'],
            ['nombre' => 'Mercantil y Sociedades'],
            ['nombre' => 'Inmobiliario y Comunidades'],
            ['nombre' => 'Compliance Penal'],
            ['nombre' => 'Protección de Datos (RGPD)'],
        ];

        foreach ($categorias as $cat) {
            BlogCategory::create($cat);
        }
    }
}
