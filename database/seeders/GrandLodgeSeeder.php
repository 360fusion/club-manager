<?php

namespace Database\Seeders;

use App\Models\GrandLodge;
use Illuminate\Database\Seeder;

class GrandLodgeSeeder extends Seeder
{
    /**
     * Seed official Masonic Country Governing Bodies (Grand Lodges).
     */
    public function run(): void
    {
        $grandLodges = [
            // Home Grand Lodges
            [
                'name' => 'United Grand Lodge of England',
                'code' => 'ugle',
                'short_name' => 'UGLE',
                'country' => 'England',
                'website_url' => 'https://www.ugle.org.uk',
                'description' => 'Governing body of Craft Freemasonry in England, Wales, Channel Islands, Isle of Man, and Districts overseas.',
            ],
            [
                'name' => 'Grand Lodge of Scotland',
                'code' => 'glos',
                'short_name' => 'GLoS',
                'country' => 'Scotland',
                'website_url' => 'https://www.grandlodgescotland.com',
                'description' => 'Sovereign governing body for Craft Freemasonry in Scotland and its Provincial & District Grand Lodges worldwide.',
            ],
            [
                'name' => 'Grand Lodge of Ireland',
                'code' => 'gli',
                'short_name' => 'GLI',
                'country' => 'Ireland',
                'website_url' => 'https://freemason.ie',
                'description' => 'The second oldest Grand Lodge in the world, governing Freemasonry across Ireland, Northern Ireland and overseas.',
            ],
            [
                'name' => 'Sovereign Grand Lodge of Malta',
                'code' => 'sglm',
                'short_name' => 'SGLM',
                'country' => 'Malta',
                'website_url' => 'https://sglm.org',
                'description' => 'Sovereign Grand Lodge governing Craft Masonry in the Republic of Malta.',
            ],

            // Europe & Nordic
            [
                'name' => 'Grande Loge Nationale Française',
                'code' => 'glnf',
                'short_name' => 'GLNF',
                'country' => 'France',
                'website_url' => 'https://glnf.fr',
                'description' => 'Regular Grand Lodge governing regular Craft Freemasonry in France.',
            ],
            [
                'name' => 'Vereinigte Großlogen von Deutschland',
                'code' => 'vglvd',
                'short_name' => 'VGLvD',
                'country' => 'Germany',
                'website_url' => 'https://freimaurerei.de',
                'description' => 'United Grand Lodges of Germany representing five federated regular Grand Lodges in Germany.',
            ],
            [
                'name' => 'Grand Lodge Alpina of Switzerland',
                'code' => 'glas',
                'short_name' => 'GLAS',
                'country' => 'Switzerland',
                'website_url' => 'https://freimaurerei.ch',
                'description' => 'Regular Grand Lodge governing Freemasonry across the Swiss cantons.',
            ],
            [
                'name' => 'Gran Logia de España',
                'code' => 'gle',
                'short_name' => 'GLE',
                'country' => 'Spain',
                'website_url' => 'https://gle.org',
                'description' => 'Sovereign Grand Lodge governing regular Freemasonry in Spain and Canary Islands.',
            ],

            // Americas
            [
                'name' => 'Grand Lodge of New York',
                'code' => 'glny',
                'short_name' => 'GLNY',
                'country' => 'United States',
                'website_url' => 'https://nymasons.org',
                'description' => 'Sovereign Masonic jurisdiction for the State of New York.',
            ],
            [
                'name' => 'Grand Lodge of Canada in Ontario',
                'code' => 'glc_ontario',
                'short_name' => 'GLC Ontario',
                'country' => 'Canada',
                'website_url' => 'https://grandlodge.on.ca',
                'description' => 'Governing body for regular Freemasonry in the Province of Ontario.',
            ],
            [
                'name' => 'Grande Loja do Brasil (GOB)',
                'code' => 'gob',
                'short_name' => 'GOB',
                'country' => 'Brazil',
                'website_url' => 'https://gob.org.br',
                'description' => 'Grand Orient of Brazil, the largest regular Masonic jurisdiction in South America.',
            ],

            // Australasia & Asia — Six Sovereign Australian State Grand Lodges
            [
                'name' => 'United Grand Lodge of New South Wales & ACT',
                'code' => 'uglnsw',
                'short_name' => 'UGL NSW & ACT',
                'country' => 'Australia',
                'website_url' => 'https://masons.org.au',
                'description' => 'Governing Grand Lodge for New South Wales and the Australian Capital Territory. Headquartered at the Sydney Masonic Centre. The largest Masonic jurisdiction in Australia.',
            ],
            [
                'name' => 'United Grand Lodge of Victoria',
                'code' => 'uglvic',
                'short_name' => 'UGL Victoria',
                'country' => 'Australia',
                'website_url' => 'https://masonsvictoria.com.au',
                'description' => 'Governing Grand Lodge for Victoria. Headquartered at the Melbourne Masonic Centre, Collins Street. Founded in 1883 from the amalgamation of two rival Grand Lodges.',
            ],
            [
                'name' => 'United Grand Lodge of Queensland',
                'code' => 'uglqld',
                'short_name' => 'UGL Queensland',
                'country' => 'Australia',
                'website_url' => 'https://uglq.org.au',
                'description' => 'Governing Grand Lodge for Queensland. Formed in 1921 by the union of two competing Queensland jurisdictions. Headquartered in Brisbane.',
            ],
            [
                'name' => 'Grand Lodge of South Australia & Northern Territory',
                'code' => 'glsant',
                'short_name' => 'GLSANT',
                'country' => 'Australia',
                'website_url' => 'https://glsa.org.au',
                'description' => 'Governing Grand Lodge for South Australia and the Northern Territory. Covers an enormous geographic area including remote outback lodges. Headquartered in Adelaide.',
            ],
            [
                'name' => 'Grand Lodge of Western Australia',
                'code' => 'glwa',
                'short_name' => 'GL Western Australia',
                'country' => 'Australia',
                'website_url' => 'https://freemasonrywa.org.au',
                'description' => 'Governing Grand Lodge for Western Australia. Founded in 1900 at the height of the gold rush era. Headquartered at the Perth Masonic Centre.',
            ],
            [
                'name' => 'Grand Lodge of Tasmania',
                'code' => 'gltas',
                'short_name' => 'GL Tasmania',
                'country' => 'Australia',
                'website_url' => 'https://freemasonrytasmania.org',
                'description' => 'Governing Grand Lodge for Tasmania and surrounding islands. The smallest of the six Australian Grand Lodges. Many lodges hold colonial-era warrants. Headquartered in Hobart.',
            ],

            [
                'name' => 'Grand Lodge of New Zealand',
                'code' => 'glnz',
                'short_name' => 'GLNZ',
                'country' => 'New Zealand',
                'website_url' => 'https://freemasonsnz.org',
                'description' => 'Sovereign Grand Lodge of Antient Free and Accepted Masons of New Zealand.',
            ],
            [
                'name' => 'Grand Lodge of India',
                'code' => 'gli_india',
                'short_name' => 'GLI India',
                'country' => 'India',
                'website_url' => 'https://grandlodgeofindia.in',
                'description' => 'Sovereign governing body for regular Freemasonry across India.',
            ],

            // Africa
            [
                'name' => 'Grand Lodge of South Africa',
                'code' => 'glsa',
                'short_name' => 'GLSA',
                'country' => 'South Africa',
                'website_url' => 'https://grandlodge.co.za',
                'description' => 'Sovereign Grand Lodge governing regular Freemasonry across South Africa.',
            ],
        ];

        foreach ($grandLodges as $gl) {
            GrandLodge::updateOrCreate(['code' => $gl['code']], $gl);
        }
    }
}
