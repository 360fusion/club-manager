<?php

namespace Database\Seeders;

use App\Models\GrandLodge;
use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    /**
     * Run the database seeds for Grand Lodges and Provinces.
     */
    public function run(): void
    {
        // 1. Seed Grand Lodges
        $ugle = GrandLodge::updateOrCreate(
            ['code' => 'ugle'],
            [
                'name' => 'United Grand Lodge of England',
                'short_name' => 'UGLE',
                'country' => 'England',
                'website_url' => 'https://www.ugle.org.uk',
                'description' => 'Governing body of Craft Freemasonry in England, Wales, Channel Islands, Isle of Man, and Districts overseas.',
            ]
        );

        // The starting rank lists go on once; a superadmin's edits are never overwritten by re-running the seeder.
        $ugle->update([
            'grand_ranks' => $ugle->grand_ranks ?? config('masonic_ranks.grand'),
            'provincial_ranks' => $ugle->provincial_ranks ?? config('masonic_ranks.provincial'),
        ]);

        $glos = GrandLodge::updateOrCreate(
            ['code' => 'glos'],
            [
                'name' => 'Grand Lodge of Scotland',
                'short_name' => 'GSLS / GLoS',
                'country' => 'Scotland',
                'website_url' => 'https://www.grandlodgescotland.com',
                'description' => 'Sovereign governing body for Craft Freemasonry in Scotland and its Provincial and District Grand Lodges worldwide.',
            ]
        );

        $gli = GrandLodge::updateOrCreate(
            ['code' => 'gli'],
            [
                'name' => 'Grand Lodge of Ireland',
                'short_name' => 'GLI',
                'country' => 'Ireland',
                'website_url' => 'https://freemason.ie',
                'description' => 'The second oldest Grand Lodge in the world, governing Freemasonry across Ireland, Northern Ireland and overseas.',
            ]
        );

        $sglm = GrandLodge::updateOrCreate(
            ['code' => 'sglm'],
            [
                'name' => 'Sovereign Grand Lodge of Malta',
                'short_name' => 'SGLM',
                'country' => 'Malta',
                'website_url' => 'https://sglm.org',
                'description' => 'Sovereign Grand Lodge governing Craft Masonry in the Republic of Malta.',
            ]
        );

        // 2. UGLE Provinces (48 Official UGLE Provinces)
        $ugleProvinces = [
            // London Metropolitan
            ['name' => 'Metropolitan Grand Lodge of London', 'code' => 'london_metropolitan', 'country' => 'England', 'region' => 'London', 'website_url' => 'https://londonmasons.org.uk'],

            // England - North East & Yorkshire
            ['name' => 'Province of Durham', 'code' => 'durham', 'country' => 'England', 'region' => 'North East', 'website_url' => 'https://durhammasons.org.uk'],
            ['name' => 'Province of Northumberland', 'code' => 'northumberland', 'country' => 'England', 'region' => 'North East', 'website_url' => 'https://northumberlandmasons.org.uk'],
            ['name' => 'Province of Yorkshire West Riding', 'code' => 'yorkshire_wr', 'country' => 'England', 'region' => 'Yorkshire', 'website_url' => 'https://wrprovince.org.uk'],
            ['name' => 'Province of Yorkshire North & East Ridings', 'code' => 'yorkshire_ne', 'country' => 'England', 'region' => 'Yorkshire', 'website_url' => 'https://yorkshiremasons.org.uk'],

            // England - North West
            ['name' => 'Province of Cheshire', 'code' => 'cheshire', 'country' => 'England', 'region' => 'North West', 'website_url' => 'https://cheshiremasons.co.uk'],
            ['name' => 'Province of Cumberland & Westmorland', 'code' => 'cumberland_westmorland', 'country' => 'England', 'region' => 'North West', 'website_url' => 'https://cumberlandwestmorlandmasons.org.uk'],
            ['name' => 'Province of East Lancashire', 'code' => 'east_lancashire', 'country' => 'England', 'region' => 'North West', 'website_url' => 'https://eastlancsmasons.org.uk'],
            ['name' => 'Province of West Lancashire', 'code' => 'west_lancashire', 'country' => 'England', 'region' => 'North West', 'website_url' => 'https://westlancsmasons.org.uk'],

            // England - East Midlands
            ['name' => 'Province of Derbyshire', 'code' => 'derbyshire', 'country' => 'England', 'region' => 'East Midlands', 'website_url' => 'https://derbyshiremasons.org.uk'],
            ['name' => 'Province of Leicestershire & Rutland', 'code' => 'leicestershire_rutland', 'country' => 'England', 'region' => 'East Midlands', 'website_url' => 'https://leicestershireandrutlandmasons.org.uk'],
            ['name' => 'Province of Lincolnshire', 'code' => 'lincolnshire', 'country' => 'England', 'region' => 'East Midlands', 'website_url' => 'https://lincolnshiremasons.org.uk'],
            ['name' => 'Province of Northamptonshire & Huntingdonshire', 'code' => 'northants_hunts', 'country' => 'England', 'region' => 'East Midlands', 'website_url' => 'https://northants-hunts-masons.org.uk'],
            ['name' => 'Province of Nottinghamshire', 'code' => 'nottinghamshire', 'country' => 'England', 'region' => 'East Midlands', 'website_url' => 'https://nottinghamshiremasons.co.uk'],

            // England - West Midlands
            ['name' => 'Province of Herefordshire', 'code' => 'herefordshire', 'country' => 'England', 'region' => 'West Midlands', 'website_url' => 'https://herefordshiremasons.org.uk'],
            ['name' => 'Province of Shropshire', 'code' => 'shropshire', 'country' => 'England', 'region' => 'West Midlands', 'website_url' => 'https://shropshiremasons.org.uk'],
            ['name' => 'Province of Staffordshire', 'code' => 'staffordshire', 'country' => 'England', 'region' => 'West Midlands', 'website_url' => 'https://staffordshiremasons.org.uk'],
            ['name' => 'Province of Warwickshire', 'code' => 'warwickshire', 'country' => 'England', 'region' => 'West Midlands', 'website_url' => 'https://warwickshiremasons.org.uk'],
            ['name' => 'Province of Worcestershire', 'code' => 'worcestershire', 'country' => 'England', 'region' => 'West Midlands', 'website_url' => 'https://worcestershiremasons.org.uk'],

            // England - East of England
            ['name' => 'Province of Bedfordshire', 'code' => 'bedfordshire', 'country' => 'England', 'region' => 'East of England', 'website_url' => 'https://bedfordshiremasons.org.uk'],
            ['name' => 'Province of Cambridgeshire', 'code' => 'cambridgeshire', 'country' => 'England', 'region' => 'East of England', 'website_url' => 'https://cambridgeshiremasons.org.uk'],
            ['name' => 'Province of Essex', 'code' => 'essex', 'country' => 'England', 'region' => 'East of England', 'website_url' => 'https://essexmasons.org.uk'],
            ['name' => 'Province of Hertfordshire', 'code' => 'hertfordshire', 'country' => 'England', 'region' => 'East of England', 'website_url' => 'https://hertfordshiremasons.org.uk'],
            ['name' => 'Province of Norfolk', 'code' => 'norfolk', 'country' => 'England', 'region' => 'East of England', 'website_url' => 'https://norfolkmasons.org.uk'],
            ['name' => 'Province of Suffolk', 'code' => 'suffolk', 'country' => 'England', 'region' => 'East of England', 'website_url' => 'https://suffolkmasons.org.uk'],

            // England - South East
            ['name' => 'Province of Berkshire', 'code' => 'berkshire', 'country' => 'England', 'region' => 'South East', 'website_url' => 'https://berkshiremasons.org.uk'],
            ['name' => 'Province of Buckinghamshire', 'code' => 'buckinghamshire', 'country' => 'England', 'region' => 'South East', 'website_url' => 'https://buckspgl.org'],
            ['name' => 'Province of Hampshire & Isle of Wight', 'code' => 'hampshire_iow', 'country' => 'England', 'region' => 'South East', 'website_url' => 'https://hiowmasons.org.uk'],
            ['name' => 'Province of East Kent', 'code' => 'east_kent', 'country' => 'England', 'region' => 'South East', 'website_url' => 'https://eastkentmasons.org.uk'],
            ['name' => 'Province of West Kent', 'code' => 'west_kent', 'country' => 'England', 'region' => 'South East', 'website_url' => 'https://westkentmasons.org.uk'],
            ['name' => 'Province of Middlesex', 'code' => 'middlesex', 'country' => 'England', 'region' => 'South East', 'website_url' => 'https://pglmiddlesex.org.uk'],
            ['name' => 'Province of Surrey', 'code' => 'surrey', 'country' => 'England', 'region' => 'South East', 'website_url' => 'https://surreymasons.org.uk'],
            ['name' => 'Province of Sussex', 'code' => 'sussex', 'country' => 'England', 'region' => 'South East', 'website_url' => 'https://sussexmasons.org.uk'],
            // England - South West
            ['name' => 'Province of Bristol', 'code' => 'bristol', 'country' => 'England', 'region' => 'South West', 'website_url' => 'https://bristolmasons.co.uk'],
            [
                'name' => 'Province of Cornwall',
                'code' => 'cornwall',
                'country' => 'England',
                'region' => 'South West',
                'website_url' => 'https://pglcornwall.org.uk',
                'provincial_grand_master' => 'David G. Maskell',
                'provincial_grand_secretary' => 'Trevor Conroy',
                'address_line_1' => '7 New Bridge Street',
                'town' => 'Truro',
                'county' => 'Cornwall',
                'postcode' => 'TR1 2AA',
                'telephone' => '01872 276191',
                'email' => 'secretary@cornwallfreemasons.org.uk',
                'description' => 'Provincial Grand Lodge of Cornwall administering 80 Masonic Lodges across Cornwall.',
            ],
            [
                'name' => 'Province of Durham',
                'code' => 'durham',
                'country' => 'England',
                'region' => 'North East',
                'website_url' => 'https://durhammasons.org.uk',
                'provincial_grand_master' => 'John Paul Stenner',
                'provincial_grand_secretary' => 'Michael D. Graham',
                'address_line_1' => '8 The College',
                'town' => 'Durham',
                'county' => 'County Durham',
                'postcode' => 'DH1 3EQ',
                'telephone' => '0191 386 4220',
                'email' => 'provsec@durhammasons.org.uk',
                'description' => 'The Provincial Grand Lodge of Durham presiding over 180 lodges across County Durham, Sunderland, and South Tyneside.',
            ],
            [
                'name' => 'Province of Oxfordshire',
                'code' => 'oxfordshire',
                'country' => 'England',
                'region' => 'South East',
                'website_url' => 'https://oxfordshiremasons.org.uk',
                'provincial_grand_master' => 'James A. Hilditch',
                'provincial_grand_secretary' => 'Alan T. Baverstock',
                'address_line_1' => '333 Banbury Road',
                'town' => 'Oxford',
                'county' => 'Oxfordshire',
                'postcode' => 'OX2 7PP',
                'telephone' => '01865 514111',
                'email' => 'office@oxfordshiremasons.org.uk',
                'description' => 'Provincial Grand Lodge of Oxfordshire presiding over Craft Lodges meeting across Oxford, Banbury, Bicester, Henley, and Witney.',
            ],
            ['name' => 'Province of Devonshire', 'code' => 'devonshire', 'country' => 'England', 'region' => 'South West', 'website_url' => 'https://devonshiremasons.org.uk'],
            ['name' => 'Province of Dorset', 'code' => 'dorset', 'country' => 'England', 'region' => 'South West', 'website_url' => 'https://dorsetmasons.org.uk'],
            ['name' => 'Province of Gloucestershire', 'code' => 'gloucestershire', 'country' => 'England', 'region' => 'South West', 'website_url' => 'https://glosmasons.org.uk'],
            ['name' => 'Province of Somerset', 'code' => 'somerset', 'country' => 'England', 'region' => 'South West', 'website_url' => 'https://somersetmasons.org.uk'],
            ['name' => 'Province of Wiltshire', 'code' => 'wiltshire', 'country' => 'England', 'region' => 'South West', 'website_url' => 'https://wiltshiremasons.org.uk'],

            // Wales
            ['name' => 'Province of Monmouthshire', 'code' => 'monmouthshire', 'country' => 'Wales', 'region' => 'Wales', 'website_url' => 'https://monmouthshiremasons.org.uk'],
            ['name' => 'Province of North Wales', 'code' => 'north_wales', 'country' => 'Wales', 'region' => 'Wales', 'website_url' => 'https://nwmasons.org.uk'],
            ['name' => 'Province of South Wales', 'code' => 'south_wales', 'country' => 'Wales', 'region' => 'Wales', 'website_url' => 'https://southwalesmasons.org.uk'],
            ['name' => 'Province of West Wales', 'code' => 'west_wales', 'country' => 'Wales', 'region' => 'Wales', 'website_url' => 'https://westwalesmasons.org.uk'],

            // Isle of Man
            ['name' => 'Province of Isle of Man', 'code' => 'isle_of_man', 'country' => 'Isle of Man', 'region' => 'Isle of Man', 'website_url' => 'https://freemasons.im'],

            // Channel Islands
            ['name' => 'Province of Guernsey & Alderney', 'code' => 'guernsey_alderney', 'country' => 'Channel Islands', 'region' => 'Channel Islands', 'website_url' => 'https://guernseyfreemasons.org.uk'],
            ['name' => 'Province of Jersey', 'code' => 'jersey', 'country' => 'Channel Islands', 'region' => 'Channel Islands', 'website_url' => 'https://jerseyfreemasons.org.uk'],
        ];

        foreach ($ugleProvinces as $p) {
            $p['grand_lodge_id'] = $ugle->id;
            Province::updateOrCreate(['code' => $p['code']], $p);
        }

        // 3. Grand Lodge of Scotland Provinces
        $scottishProvinces = [
            ['name' => 'Provincial Grand Lodge of Edinburgh', 'code' => 'pgl_edinburgh', 'country' => 'Scotland', 'region' => 'Edinburgh & Lothians', 'website_url' => 'https://www.pgledinburgh.org.uk'],
            ['name' => 'Provincial Grand Lodge of Glasgow', 'code' => 'pgl_glasgow', 'country' => 'Scotland', 'region' => 'Glasgow & Clyde', 'website_url' => 'https://www.pglglasgow.org.uk'],
            ['name' => 'Provincial Grand Lodge of Aberdeenshire East', 'code' => 'pgl_abdn_east', 'country' => 'Scotland', 'region' => 'Aberdeenshire', 'website_url' => 'https://www.pglabdneast.co.uk'],
            ['name' => 'Provincial Grand Lodge of Aberdeenshire West', 'code' => 'pgl_abdn_west', 'country' => 'Scotland', 'region' => 'Aberdeenshire', 'website_url' => 'https://www.pglabdnwest.org.uk'],
            ['name' => 'Provincial Grand Lodge of Ayrshire', 'code' => 'pgl_ayrshire', 'country' => 'Scotland', 'region' => 'South West Scotland', 'website_url' => 'https://www.pglayrshire.org.uk'],
            ['name' => 'Provincial Grand Lodge of Fife and Kinross', 'code' => 'pgl_fife_kinross', 'country' => 'Scotland', 'region' => 'Fife', 'website_url' => 'https://www.pglfifeandkinross.co.uk'],
            ['name' => 'Provincial Grand Lodge of Forfarshire', 'code' => 'pgl_forfarshire', 'country' => 'Scotland', 'region' => 'Angus & Tayside', 'website_url' => 'https://www.pglforfarshire.org.uk'],
            ['name' => 'Provincial Grand Lodge of Lanarkshire Middle Ward', 'code' => 'pgl_lanark_middle', 'country' => 'Scotland', 'region' => 'Lanarkshire', 'website_url' => 'https://www.pglmiddleward.org.uk'],
            ['name' => 'Provincial Grand Lodge of Lanarkshire Upper Ward', 'code' => 'pgl_lanark_upper', 'country' => 'Scotland', 'region' => 'Lanarkshire', 'website_url' => 'https://www.pglupperward.org.uk'],
            ['name' => 'Provincial Grand Lodge of Midlothian', 'code' => 'pgl_midlothian', 'country' => 'Scotland', 'region' => 'Edinburgh & Lothians', 'website_url' => 'https://www.pglmidlothian.org.uk'],
            ['name' => 'Provincial Grand Lodge of Perthshire East', 'code' => 'pgl_perth_east', 'country' => 'Scotland', 'region' => 'Perthshire', 'website_url' => 'https://www.pglperthshire.org.uk'],
            ['name' => 'Provincial Grand Lodge of Renfrewshire East', 'code' => 'pgl_renfrew_east', 'country' => 'Scotland', 'region' => 'Renfrewshire', 'website_url' => 'https://www.pglre.org.uk'],
            ['name' => 'Provincial Grand Lodge of Renfrewshire West', 'code' => 'pgl_renfrew_west', 'country' => 'Scotland', 'region' => 'Renfrewshire', 'website_url' => 'https://www.pglrw.org.uk'],
            ['name' => 'Provincial Grand Lodge of Ross and Cromarty', 'code' => 'pgl_ross_cromarty', 'country' => 'Scotland', 'region' => 'Highlands', 'website_url' => 'https://www.pglrc.org.uk'],
            ['name' => 'Provincial Grand Lodge of Stirlingshire', 'code' => 'pgl_stirlingshire', 'country' => 'Scotland', 'region' => 'Central Scotland', 'website_url' => 'https://www.pglstirlingshire.org.uk'],
            ['name' => 'Provincial Grand Lodge of Inverness-shire', 'code' => 'pgl_inverness', 'country' => 'Scotland', 'region' => 'Highlands', 'website_url' => 'https://www.pglinverness.org.uk'],
            ['name' => 'Provincial Grand Lodge of Dumfriesshire', 'code' => 'pgl_dumfriesshire', 'country' => 'Scotland', 'region' => 'South Scotland', 'website_url' => 'https://www.pgldumfries.org.uk'],
        ];

        foreach ($scottishProvinces as $p) {
            $p['grand_lodge_id'] = $glos->id;
            Province::updateOrCreate(['code' => $p['code']], $p);
        }

        // 4. Grand Lodge of Ireland Provinces
        $irishProvinces = [
            ['name' => 'Provincial Grand Lodge of Antrim', 'code' => 'pgl_antrim', 'country' => 'Ireland', 'region' => 'Ulster / Northern Ireland', 'website_url' => 'https://www.antrimfreemasons.org'],
            ['name' => 'Provincial Grand Lodge of Down', 'code' => 'pgl_down', 'country' => 'Ireland', 'region' => 'Ulster / Northern Ireland', 'website_url' => 'https://www.pgldown.org'],
            ['name' => 'Provincial Grand Lodge of Armagh', 'code' => 'pgl_armagh', 'country' => 'Ireland', 'region' => 'Ulster / Northern Ireland', 'website_url' => 'https://www.armaghfreemasons.org'],
            ['name' => 'Provincial Grand Lodge of Dublin', 'code' => 'pgl_dublin', 'country' => 'Ireland', 'region' => 'Leinster', 'website_url' => 'https://freemason.ie/dublin'],
        ];

        foreach ($irishProvinces as $p) {
            $p['grand_lodge_id'] = $gli->id;
            Province::updateOrCreate(['code' => $p['code']], $p);
        }

        // 5. Sovereign Grand Lodge of Malta Jurisdiction
        $malteseProvinces = [
            ['name' => 'Grand Inspectorate & Lodges of Malta', 'code' => 'sglm_malta', 'country' => 'Malta', 'region' => 'Mediterranean', 'website_url' => 'https://sglm.org'],
        ];

        foreach ($malteseProvinces as $p) {
            $p['grand_lodge_id'] = $sglm->id;
            Province::updateOrCreate(['code' => $p['code']], $p);
        }

        // 6. Masonic halls, linked to the provinces above.
        $this->call(MasonicHallSeeder::class);
    }
}
