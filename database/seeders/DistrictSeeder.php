<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\GrandLodge;
use Illuminate\Database\Seeder;

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        $ugle = GrandLodge::where('code', 'ugle')->first();
        $ugleId = $ugle?->id;

        $districts = [
            // ─── ACTIVE DISTRICTS ───────────────────────────────────────────
            ['name' => 'District Grand Lodge of Bahrain', 'code' => 'bahrain', 'type' => 'district', 'region' => 'Middle East', 'country' => 'Bahrain'],
            ['name' => 'District Grand Lodge of Bangladesh', 'code' => 'bangladesh', 'type' => 'district', 'region' => 'South Asia', 'country' => 'Bangladesh'],
            ['name' => 'District Grand Lodge of Benin', 'code' => 'benin', 'type' => 'district', 'region' => 'West Africa', 'country' => 'Benin'],
            ['name' => 'District Grand Lodge of Bermuda', 'code' => 'bermuda', 'type' => 'district', 'region' => 'North Atlantic', 'country' => 'Bermuda'],
            ['name' => 'District Grand Lodge of China (Hong Kong & Macau)', 'code' => 'hong_kong', 'type' => 'district', 'region' => 'Far East', 'country' => 'Hong Kong'],
            ['name' => 'District Grand Lodge of East Africa', 'code' => 'east_africa', 'type' => 'district', 'region' => 'East Africa', 'country' => 'Kenya'],
            ['name' => 'District Grand Lodge of East Africa (Coast)', 'code' => 'east_africa_coast', 'type' => 'district', 'region' => 'East Africa', 'country' => 'Kenya'],
            ['name' => 'District Grand Lodge of Egypt and the Sudan', 'code' => 'egypt_sudan', 'type' => 'district', 'region' => 'North Africa', 'country' => 'Egypt'],
            ['name' => 'District Grand Lodge of Gibraltar', 'code' => 'gibraltar', 'type' => 'district', 'region' => 'Europe', 'country' => 'Gibraltar', 'website_url' => 'https://www.freemasons.gi'],
            ['name' => 'District Grand Lodge of Ghana', 'code' => 'ghana', 'type' => 'district', 'region' => 'West Africa', 'country' => 'Ghana'],
            ['name' => 'District Grand Lodge of India (Northern)', 'code' => 'india_northern', 'type' => 'district', 'region' => 'South Asia', 'country' => 'India'],
            ['name' => 'District Grand Lodge of India (Southern)', 'code' => 'india_southern', 'type' => 'district', 'region' => 'South Asia', 'country' => 'India'],
            ['name' => 'District Grand Lodge of Japan', 'code' => 'japan', 'type' => 'district', 'region' => 'Far East', 'country' => 'Japan'],
            ['name' => 'District Grand Lodge of Kenya', 'code' => 'kenya', 'type' => 'district', 'region' => 'East Africa', 'country' => 'Kenya'],
            ['name' => 'District Grand Lodge of Malawi', 'code' => 'malawi', 'type' => 'district', 'region' => 'Southern Africa', 'country' => 'Malawi'],
            ['name' => 'District Grand Lodge of Malta', 'code' => 'malta_district', 'type' => 'district', 'region' => 'Mediterranean', 'country' => 'Malta', 'website_url' => 'https://www.maltamasonicorders.org'],
            ['name' => 'District Grand Lodge of Morocco', 'code' => 'morocco', 'type' => 'district', 'region' => 'North Africa', 'country' => 'Morocco'],
            ['name' => 'District Grand Lodge of New South Wales', 'code' => 'new_south_wales', 'type' => 'district', 'region' => 'Australasia', 'country' => 'Australia'],
            ['name' => 'District Grand Lodge of New Zealand', 'code' => 'new_zealand', 'type' => 'district', 'region' => 'Australasia', 'country' => 'New Zealand'],
            ['name' => 'District Grand Lodge of Nigeria (East)', 'code' => 'nigeria_east', 'type' => 'district', 'region' => 'West Africa', 'country' => 'Nigeria'],
            ['name' => 'District Grand Lodge of Nigeria (Lagos)', 'code' => 'nigeria_lagos', 'type' => 'district', 'region' => 'West Africa', 'country' => 'Nigeria'],
            ['name' => 'District Grand Lodge of Nigeria (West)', 'code' => 'nigeria_west', 'type' => 'district', 'region' => 'West Africa', 'country' => 'Nigeria'],
            ['name' => 'District Grand Lodge of Palestine', 'code' => 'palestine', 'type' => 'district', 'region' => 'Middle East', 'country' => 'Israel'],
            ['name' => 'District Grand Lodge of Papua New Guinea', 'code' => 'papua_new_guinea', 'type' => 'district', 'region' => 'Pacific', 'country' => 'Papua New Guinea'],
            ['name' => 'District Grand Lodge of Philippines', 'code' => 'philippines', 'type' => 'district', 'region' => 'Far East', 'country' => 'Philippines'],
            ['name' => 'District Grand Lodge of Qatar', 'code' => 'qatar', 'type' => 'district', 'region' => 'Middle East', 'country' => 'Qatar'],
            ['name' => 'District Grand Lodge of Sierra Leone', 'code' => 'sierra_leone', 'type' => 'district', 'region' => 'West Africa', 'country' => 'Sierra Leone'],
            ['name' => 'District Grand Lodge of Singapore', 'code' => 'singapore', 'type' => 'district', 'region' => 'South East Asia', 'country' => 'Singapore', 'website_url' => 'https://www.freemasons.org.sg'],
            ['name' => 'District Grand Lodge of Sri Lanka', 'code' => 'sri_lanka', 'type' => 'district', 'region' => 'South Asia', 'country' => 'Sri Lanka'],
            ['name' => 'District Grand Lodge of Tanzania', 'code' => 'tanzania', 'type' => 'district', 'region' => 'East Africa', 'country' => 'Tanzania'],
            ['name' => 'District Grand Lodge of Togo', 'code' => 'togo', 'type' => 'district', 'region' => 'West Africa', 'country' => 'Togo'],
            ['name' => 'District Grand Lodge of Uganda', 'code' => 'uganda', 'type' => 'district', 'region' => 'East Africa', 'country' => 'Uganda'],

            // ─── GROUPS ─────────────────────────────────────────────────────
            ['name' => 'Greece (Zakinthos Island)', 'code' => 'greece_zakinthos', 'type' => 'group', 'region' => 'Europe', 'country' => 'Greece'],
            ['name' => 'Group of Lodges in Iceland', 'code' => 'iceland', 'type' => 'group', 'region' => 'Northern Europe', 'country' => 'Iceland'],
            ['name' => 'Group of Lodges in the Netherlands', 'code' => 'netherlands', 'type' => 'group', 'region' => 'Europe', 'country' => 'Netherlands'],
            ['name' => 'Group of Lodges in Spain (Costa del Sol)', 'code' => 'spain_costa_del_sol', 'type' => 'group', 'region' => 'Europe', 'country' => 'Spain'],

            // ─── DORMANT DISTRICTS ──────────────────────────────────────────
            ['name' => 'District Grand Lodge of Burma', 'code' => 'burma', 'type' => 'dormant', 'region' => 'South East Asia', 'country' => 'Myanmar', 'description' => 'Dormant — Freemasonry is banned in Myanmar (Burma).'],
            ['name' => 'District Grand Lodge of Pakistan', 'code' => 'pakistan', 'type' => 'dormant', 'region' => 'South Asia', 'country' => 'Pakistan', 'description' => 'Dormant — Freemasonry is banned in Pakistan.'],
        ];

        foreach ($districts as $data) {
            District::updateOrCreate(
                ['code' => $data['code']],
                array_merge(['grand_lodge_id' => $ugleId], $data)
            );
        }
    }
}
