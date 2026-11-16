<?php

namespace Database\Seeders;

use App\Models\Marketplace\ServiceCategory;
use App\Models\Marketplace\TzRegion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Reference data only (regions, starter categories). Safe to re-run: existing
 * rows are kept, so admin edits to categories are never overwritten.
 */
class MarketplaceSeeder extends Seeder
{
    public const REGIONS = [
        'Arusha', 'Dar es Salaam', 'Dodoma', 'Geita', 'Iringa', 'Kagera', 'Kaskazini Pemba', 'Kaskazini Unguja',
        'Katavi', 'Kigoma', 'Kilimanjaro', 'Kusini Pemba', 'Kusini Unguja', 'Lindi', 'Manyara', 'Mara', 'Mbeya',
        'Mjini Magharibi', 'Morogoro', 'Mtwara', 'Mwanza', 'Njombe', 'Pwani', 'Rukwa', 'Ruvuma', 'Shinyanga',
        'Simiyu', 'Singida', 'Songwe', 'Tabora', 'Tanga',
    ];

    public const CATEGORIES = [
        ['Photography & Videography', 'Picha na Video', 'per_slot', 'Capture every moment — photos, films, drone and same-day edits.'],
        ['Master of Ceremony (MC)', 'MC', 'per_slot', 'Hosts who keep the programme flowing and the guests smiling.'],
        ['Decoration & Event Design', 'Mapambo', 'per_day', 'Stage, table and venue styling that brings your theme to life.'],
        ['Catering', 'Chakula', 'per_day', 'Menus, service staff and buffets for every guest count.'],
        ['Venues & Gardens', 'Kumbi na Bustani', 'per_day', 'Halls, gardens and beach venues for ceremonies and receptions.'],
        ['Drinks & Beverages', 'Vinywaji', 'per_day', 'Soft drinks, bar service and beverage supply.'],
        ['Cakes & Bakery', 'Keki', 'per_day', 'Wedding cakes, cupcakes and dessert tables.'],
        ['Bridal Makeup & Beauty', 'Urembo', 'per_slot', 'Makeup, hair, henna and beauty for the bride and her party.'],
        ['Wedding Dresses & Suits', 'Mavazi ya Harusi', 'per_day', 'Gowns, suits and outfits to buy or hire.'],
        ['DJs, Bands & Entertainment', 'Burudani', 'per_slot', 'DJs, live bands, taarab, dancers and performers.'],
        ['Wedding Planners & Coordinators', 'Waratibu wa Harusi', 'per_day', 'Professionals who plan and run the day for you.'],
        ['Transport & Car Hire', 'Usafiri', 'per_slot', 'Wedding cars, buses and guest transport.'],
        ['Tents, Chairs & Equipment', 'Mahema na Viti', 'per_day', 'Tents, chairs, tables, linen and equipment hire.'],
        ['Florists', 'Maua', 'per_day', 'Bouquets, arrangements and floral design.'],
        ['Lighting & Sound', 'Taa na Sauti', 'per_day', 'PA systems, lighting rigs and technical crews.'],
        ['Invitation Cards & Printing', 'Kadi za Mwaliko', 'per_day', 'Invitation design, printing and stationery.'],
        ['Fireworks & Special Effects', 'Fataki na Madoido', 'per_slot', 'Fireworks, sparklers, smoke and stage effects.'],
        ['Honeymoon & Accommodation', 'Fungate na Malazi', 'per_day', 'Hotels, lodges and honeymoon packages.'],
        ['Other Wedding Services', 'Huduma Nyingine', 'per_day', 'Other services for your wedding.'],
    ];

    public function run(): void
    {
        foreach (self::REGIONS as $name) {
            TzRegion::firstOrCreate(['name' => $name]);
        }

        foreach (self::CATEGORIES as $i => [$name, $sw, $scheduling, $description]) {
            ServiceCategory::firstOrCreate(['slug' => Str::slug($name)], [
                'name' => $name, 'name_sw' => $sw, 'description' => $description,
                'scheduling_default' => $scheduling, 'sort' => $i,
            ]);
        }
    }
}
