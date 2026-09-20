<?php

namespace Database\Seeders;

use App\Models\ClubType;
use App\Models\DefaultEmailTemplate;
use App\Models\DefaultOfficerRole;
use App\Models\DefaultRank;
use Illuminate\Database\Seeder;

class ClubTypeSeeder extends Seeder
{
    /**
     * Run the database seeds for Masonic Orders, Companion Orders, Officer Ladders, Ranks, and Email Templates.
     */
    public function run(): void
    {
        // Helper function to create club type and its default officer roles
        $seedOrder = function (array $typeData, array $rolesData) {
            $clubType = ClubType::updateOrCreate(
                ['code' => $typeData['code']],
                $typeData
            );

            foreach ($rolesData as $r) {
                DefaultOfficerRole::updateOrCreate(
                    ['club_type_id' => $clubType->id, 'title' => $r['title']],
                    $r
                );
            }

            return $clubType;
        };

        // 1. Craft Lodge
        $seedOrder([
            'code' => 'craft_lodge',
            'name' => 'Craft Lodge',
            'website_url' => 'https://www.ugle.org.uk',
            'description' => 'Craft Freemasonry is the foundational order of Freemasonry, comprising the three progressive degrees of Entered Apprentice, Fellow Craft, and Master Mason. Governed by the United Grand Lodge of England (UGLE) and sovereign Grand Lodges worldwide.',
            'available_modules' => ['accounting', 'meetings', 'members', 'charity', 'dining', 'communications', 'events'],
            'terminology' => [
                'club' => 'Lodge',
                'master' => 'Worshipful Master',
                'summons' => 'Summons',
                'members' => 'Brethren',
            ],
            'rulers_schema' => [
                'Provincial Grand Master',
                'Deputy Provincial Grand Master',
                'Assistant Provincial Grand Master',
            ],
        ], [
            ['title' => 'Worshipful Master', 'short_code' => 'WM', 'rank_level' => 1, 'is_executive' => true],
            ['title' => 'Senior Warden', 'short_code' => 'SW', 'rank_level' => 2, 'is_executive' => true],
            ['title' => 'Junior Warden', 'short_code' => 'JW', 'rank_level' => 3, 'is_executive' => true],
            ['title' => 'Chaplain', 'short_code' => 'Chap', 'rank_level' => 4, 'is_executive' => false],
            ['title' => 'Treasurer', 'short_code' => 'Treas', 'rank_level' => 5, 'is_executive' => true],
            ['title' => 'Secretary', 'short_code' => 'Sec', 'rank_level' => 6, 'is_executive' => true],
            ['title' => 'Director of Ceremonies', 'short_code' => 'DC', 'rank_level' => 7, 'is_executive' => true],
            ['title' => 'Almoner', 'short_code' => 'Alm', 'rank_level' => 8, 'is_executive' => false],
            ['title' => 'Charity Steward', 'short_code' => 'ChStwd', 'rank_level' => 9, 'is_executive' => false],
            ['title' => 'Membership Officer', 'short_code' => 'MO', 'rank_level' => 10, 'is_executive' => false],
            ['title' => 'Senior Deacon', 'short_code' => 'SD', 'rank_level' => 11, 'is_executive' => false],
            ['title' => 'Junior Deacon', 'short_code' => 'JD', 'rank_level' => 12, 'is_executive' => false],
            ['title' => 'Assistant Director of Ceremonies', 'short_code' => 'ADC', 'rank_level' => 13, 'is_executive' => false],
            ['title' => 'Organist', 'short_code' => 'Org', 'rank_level' => 14, 'is_executive' => false],
            ['title' => 'Assistant Secretary', 'short_code' => 'ASec', 'rank_level' => 15, 'is_executive' => false],
            ['title' => 'Inner Guard', 'short_code' => 'IG', 'rank_level' => 16, 'is_executive' => false],
            ['title' => 'Steward', 'short_code' => 'Stwd', 'rank_level' => 17, 'is_executive' => false],
            ['title' => 'Tyler', 'short_code' => 'Tyler', 'rank_level' => 18, 'is_executive' => false],
        ]);

        // 2. Royal Arch Chapter
        $seedOrder([
            'code' => 'royal_arch',
            'name' => 'Royal Arch Chapter',
            'website_url' => 'https://www.ugle.org.uk/about-us/royal-arch',
            'description' => 'The Supreme Order of the Holy Royal Arch is considered the completion of Pure Antient Freemasonry. Master Masons of four weeks standing or more are eligible to join a Chapter and be Exalted into the degree of Companion.',
            'available_modules' => ['accounting', 'meetings', 'members', 'charity', 'dining', 'communications'],
            'terminology' => [
                'club' => 'Chapter',
                'master' => 'Most Excellent Zerubbabel (M.E.Z.)',
                'summons' => 'Circular & Convocation Notice',
                'members' => 'Companions',
            ],
            'rulers_schema' => [
                'Grand Superintendent',
                'Deputy Grand Superintendent',
                'Second Provincial Grand Principal (H.)',
                'Third Provincial Grand Principal (J.)',
            ],
        ], [
            ['title' => 'First Principal (M.E.Z.)', 'short_code' => 'Z', 'rank_level' => 1, 'is_executive' => true],
            ['title' => 'Second Principal (H.)', 'short_code' => 'H', 'rank_level' => 2, 'is_executive' => true],
            ['title' => 'Third Principal (J.)', 'short_code' => 'J', 'rank_level' => 3, 'is_executive' => true],
            ['title' => 'Scribe Ezra (Scribe E.)', 'short_code' => 'SE', 'rank_level' => 4, 'is_executive' => true],
            ['title' => 'Scribe Nehemiah (Scribe N.)', 'short_code' => 'SN', 'rank_level' => 5, 'is_executive' => false],
            ['title' => 'Treasurer', 'short_code' => 'Treas', 'rank_level' => 6, 'is_executive' => true],
            ['title' => 'Director of Ceremonies', 'short_code' => 'DC', 'rank_level' => 7, 'is_executive' => true],
            ['title' => 'Principal Sojourner', 'short_code' => 'PS', 'rank_level' => 8, 'is_executive' => false],
            ['title' => '1st Assistant Sojourner', 'short_code' => '1AS', 'rank_level' => 9, 'is_executive' => false],
            ['title' => '2nd Assistant Sojourner', 'short_code' => '2AS', 'rank_level' => 10, 'is_executive' => false],
            ['title' => 'Janitor', 'short_code' => 'Jan', 'rank_level' => 11, 'is_executive' => false],
        ]);

        // 3. Mark Master Masons
        $seedOrder([
            'code' => 'mark_lodge',
            'name' => 'Mark Master Masons Lodge',
            'website_url' => 'https://markmasonshall.org/orders/mark-master-masons',
            'description' => 'The Degree of Mark Master Mason builds directly upon the Fellow Craft Degree. Based at Mark Masons\' Hall in London, it focuses on operative stonemasons\' marks, honest workmanship, and the stone which the builders rejected.',
            'available_modules' => ['accounting', 'meetings', 'members', 'charity', 'dining'],
            'terminology' => [
                'club' => 'Mark Lodge',
                'master' => 'Worshipful Master',
                'summons' => 'Summons & Advancement Notice',
                'members' => 'Mark Master Masons',
            ],
            'rulers_schema' => [
                'Provincial Grand Master',
                'Deputy Provincial Grand Master',
                'Assistant Provincial Grand Master',
            ],
        ], [
            ['title' => 'Worshipful Master', 'short_code' => 'WM', 'rank_level' => 1, 'is_executive' => true],
            ['title' => 'Senior Warden', 'short_code' => 'SW', 'rank_level' => 2, 'is_executive' => true],
            ['title' => 'Junior Warden', 'short_code' => 'JW', 'rank_level' => 3, 'is_executive' => true],
            ['title' => 'Master Overseer', 'short_code' => 'MO', 'rank_level' => 4, 'is_executive' => true],
            ['title' => 'Senior Overseer', 'short_code' => 'SO', 'rank_level' => 5, 'is_executive' => false],
            ['title' => 'Junior Overseer', 'short_code' => 'JO', 'rank_level' => 6, 'is_executive' => false],
            ['title' => 'Chaplain', 'short_code' => 'Chap', 'rank_level' => 7, 'is_executive' => false],
            ['title' => 'Treasurer', 'short_code' => 'Treas', 'rank_level' => 8, 'is_executive' => true],
            ['title' => 'Secretary', 'short_code' => 'Sec', 'rank_level' => 9, 'is_executive' => true],
            ['title' => 'Director of Ceremonies', 'short_code' => 'DC', 'rank_level' => 10, 'is_executive' => true],
            ['title' => 'Senior Deacon', 'short_code' => 'SD', 'rank_level' => 11, 'is_executive' => false],
            ['title' => 'Junior Deacon', 'short_code' => 'JD', 'rank_level' => 12, 'is_executive' => false],
            ['title' => 'Inner Guard', 'short_code' => 'IG', 'rank_level' => 13, 'is_executive' => false],
            ['title' => 'Tyler', 'short_code' => 'Tyler', 'rank_level' => 14, 'is_executive' => false],
        ]);

        // 4. Royal Ark Mariners
        $seedOrder([
            'code' => 'royal_ark_mariner',
            'name' => 'Royal Ark Mariner Lodge',
            'website_url' => 'https://markmasonshall.org/orders/royal-ark-mariner',
            'description' => 'The Ancient and Honorable Fraternity of Royal Ark Mariners is anchored in the story of Noah, the Ark, and the Deluge. Candidates must be Mark Master Masons to be Elevated into this fraternity under the jurisdiction of the Grand Master\'s Royal Ark Council.',
            'available_modules' => ['accounting', 'meetings', 'members', 'dining'],
            'terminology' => [
                'club' => 'RAM Lodge',
                'master' => 'Worshipful Commander',
                'summons' => 'Summons & Elevation Notice',
                'members' => 'Royal Ark Mariners',
            ],
            'rulers_schema' => [
                'Provincial Grand Master',
                'Deputy Provincial Grand Master',
            ],
        ], [
            ['title' => 'Worshipful Commander', 'short_code' => 'WC', 'rank_level' => 1, 'is_executive' => true],
            ['title' => 'Senior Warden (Japheth)', 'short_code' => 'SW', 'rank_level' => 2, 'is_executive' => true],
            ['title' => 'Junior Warden (Shem)', 'short_code' => 'JW', 'rank_level' => 3, 'is_executive' => true],
            ['title' => 'Scribe', 'short_code' => 'Scribe', 'rank_level' => 4, 'is_executive' => true],
            ['title' => 'Treasurer', 'short_code' => 'Treas', 'rank_level' => 5, 'is_executive' => true],
            ['title' => 'Director of Ceremonies', 'short_code' => 'DC', 'rank_level' => 6, 'is_executive' => true],
            ['title' => 'Senior Deacon', 'short_code' => 'SD', 'rank_level' => 7, 'is_executive' => false],
            ['title' => 'Junior Deacon', 'short_code' => 'JD', 'rank_level' => 8, 'is_executive' => false],
            ['title' => 'Guardian', 'short_code' => 'Guard', 'rank_level' => 9, 'is_executive' => false],
            ['title' => 'Warder', 'short_code' => 'Warder', 'rank_level' => 10, 'is_executive' => false],
        ]);

        // 5. Rose Croix Chapter (18°)
        $seedOrder([
            'code' => 'rose_croix',
            'name' => 'Rose Croix Chapter (18°)',
            'website_url' => 'https://www.supreme-council.org.uk',
            'description' => 'The Ancient and Accepted Rite (Rose Croix) comprises 33 degrees of Masonry administered by the Supreme Council 33° for England and Wales. Members candidate for the 18th Degree (Knight Rose Croix of Heredom) focus on Christian ethics, philosophy, and brotherly love.',
            'available_modules' => ['accounting', 'meetings', 'members', 'dining'],
            'terminology' => [
                'club' => 'Chapter',
                'master' => 'Most Wise Sovereign',
                'summons' => 'Circular of Perfection',
                'members' => 'Illustrious Brethren',
            ],
            'rulers_schema' => [
                'Inspector General',
                'District Recorder',
            ],
        ], [
            ['title' => 'Most Wise Sovereign', 'short_code' => 'MWS', 'rank_level' => 1, 'is_executive' => true],
            ['title' => 'High Prelate', 'short_code' => 'HP', 'rank_level' => 2, 'is_executive' => true],
            ['title' => 'First General', 'short_code' => '1G', 'rank_level' => 3, 'is_executive' => true],
            ['title' => 'Second General', 'short_code' => '2G', 'rank_level' => 4, 'is_executive' => true],
            ['title' => 'Grand Marshal', 'short_code' => 'GM', 'rank_level' => 5, 'is_executive' => false],
            ['title' => 'Raphael', 'short_code' => 'Raph', 'rank_level' => 6, 'is_executive' => false],
            ['title' => 'Recorder', 'short_code' => 'Rec', 'rank_level' => 7, 'is_executive' => true],
            ['title' => 'Treasurer', 'short_code' => 'Treas', 'rank_level' => 8, 'is_executive' => true],
            ['title' => 'Outer Guard', 'short_code' => 'OG', 'rank_level' => 9, 'is_executive' => false],
        ]);

        // 6. Knights Templar Preceptory
        $seedOrder([
            'code' => 'knights_templar',
            'name' => 'Knights Templar Preceptory',
            'website_url' => 'https://markmasonshall.org/orders/knights-templar',
            'description' => 'The United Religious, Military and Masonic Orders of the Temple and of St John of Jerusalem, Palestine, Rhodes and Malta (Knights Templar). A Christian chivalric order open to Royal Arch Masons requiring a profession of the Trinitarian Christian faith.',
            'available_modules' => ['accounting', 'meetings', 'members', 'dining'],
            'terminology' => [
                'club' => 'Preceptory',
                'master' => 'Eminent Preceptor',
                'summons' => 'Muster Roll & Summons',
                'members' => 'Knights Companion',
            ],
            'rulers_schema' => [
                'Provincial Prior',
                'Provincial Sub-Prior',
                'Provincial Chancellor',
            ],
        ], [
            ['title' => 'Eminent Preceptor', 'short_code' => 'EP', 'rank_level' => 1, 'is_executive' => true],
            ['title' => 'First Constable', 'short_code' => '1C', 'rank_level' => 2, 'is_executive' => true],
            ['title' => 'Second Constable', 'short_code' => '2C', 'rank_level' => 3, 'is_executive' => true],
            ['title' => 'Chaplain', 'short_code' => 'Chap', 'rank_level' => 4, 'is_executive' => false],
            ['title' => 'Treasurer', 'short_code' => 'Treas', 'rank_level' => 5, 'is_executive' => true],
            ['title' => 'Registrar', 'short_code' => 'Reg', 'rank_level' => 6, 'is_executive' => true],
            ['title' => 'Marshal', 'short_code' => 'Marsh', 'rank_level' => 7, 'is_executive' => true],
            ['title' => 'Almoner', 'short_code' => 'Alm', 'rank_level' => 8, 'is_executive' => false],
            ['title' => 'Captain of Guards', 'short_code' => 'CG', 'rank_level' => 9, 'is_executive' => false],
        ]);

        // 7. Order of the Secret Monitor
        $seedOrder([
            'code' => 'secret_monitor',
            'name' => 'Order of the Secret Monitor (OSM)',
            'website_url' => 'https://markmasonshall.org/orders/secret-monitor',
            'description' => 'The Order of the Secret Monitor, or Brotherhood of David and Jonathan, is renowned as the \'Friendly Order\'. It emphasizes mutual assistance, brotherly affection, and self-sacrifice based on the biblical friendship of David and Jonathan.',
            'available_modules' => ['accounting', 'meetings', 'members', 'dining'],
            'terminology' => [
                'club' => 'Conclave',
                'master' => 'Supreme Ruler',
                'summons' => 'Conclave Summons',
                'members' => 'Princes & Brothers',
            ],
            'rulers_schema' => [
                'Provincial Grand Supreme Ruler',
                'Deputy Provincial Grand Supreme Ruler',
            ],
        ], [
            ['title' => 'Supreme Ruler', 'short_code' => 'SR', 'rank_level' => 1, 'is_executive' => true],
            ['title' => 'Counselor', 'short_code' => 'Couns', 'rank_level' => 2, 'is_executive' => true],
            ['title' => 'Guide', 'short_code' => 'Guide', 'rank_level' => 3, 'is_executive' => true],
            ['title' => 'Treasurer', 'short_code' => 'Treas', 'rank_level' => 4, 'is_executive' => true],
            ['title' => 'Secretary', 'short_code' => 'Sec', 'rank_level' => 5, 'is_executive' => true],
            ['title' => 'Director of Ceremonies', 'short_code' => 'DC', 'rank_level' => 6, 'is_executive' => true],
            ['title' => '1st Visiting Deacon', 'short_code' => '1VD', 'rank_level' => 7, 'is_executive' => false],
            ['title' => '2nd Visiting Deacon', 'short_code' => '2VD', 'rank_level' => 8, 'is_executive' => false],
            ['title' => '3rd Visiting Deacon', 'short_code' => '3VD', 'rank_level' => 9, 'is_executive' => false],
            ['title' => '4th Visiting Deacon', 'short_code' => '4VD', 'rank_level' => 10, 'is_executive' => false],
            ['title' => 'Guard', 'short_code' => 'Guard', 'rank_level' => 11, 'is_executive' => false],
            ['title' => 'Sentinel', 'short_code' => 'Sent', 'rank_level' => 12, 'is_executive' => false],
        ]);

        // 8. Red Cross of Constantine
        $seedOrder([
            'code' => 'red_cross_constantine',
            'name' => 'Red Cross of Constantine Conclave',
            'website_url' => 'https://markmasonshall.org/orders/red-cross-of-constantine',
            'description' => 'The Masonic and Military Order of the Red Cross of Constantine and the Appendant Orders of Holy Sepulchre and St John the Evangelist. A Christian Masonic order honoring the Emperor Constantine and the Christian cross victory at the Milvian Bridge.',
            'available_modules' => ['accounting', 'meetings', 'members', 'dining'],
            'terminology' => [
                'club' => 'Conclave',
                'master' => 'Most Puissant Sovereign',
                'summons' => 'Sovereign Circular & Billet',
                'members' => 'Sir Knights',
            ],
            'rulers_schema' => [
                'Intendant-General',
                'Deputy Intendant-General',
            ],
        ], [
            ['title' => 'Most Puissant Sovereign', 'short_code' => 'MPS', 'rank_level' => 1, 'is_executive' => true],
            ['title' => 'Viceroy', 'short_code' => 'Viceroy', 'rank_level' => 2, 'is_executive' => true],
            ['title' => 'Senior General', 'short_code' => 'SG', 'rank_level' => 3, 'is_executive' => true],
            ['title' => 'Junior General', 'short_code' => 'JG', 'rank_level' => 4, 'is_executive' => true],
            ['title' => 'High Prelate', 'short_code' => 'HP', 'rank_level' => 5, 'is_executive' => false],
            ['title' => 'Treasurer', 'short_code' => 'Treas', 'rank_level' => 6, 'is_executive' => true],
            ['title' => 'Recorder', 'short_code' => 'Rec', 'rank_level' => 7, 'is_executive' => true],
            ['title' => 'Prefect', 'short_code' => 'Prefect', 'rank_level' => 8, 'is_executive' => false],
            ['title' => 'Standard Bearer', 'short_code' => 'SB', 'rank_level' => 9, 'is_executive' => false],
            ['title' => 'Herald', 'short_code' => 'Herald', 'rank_level' => 10, 'is_executive' => false],
            ['title' => 'Sentinel', 'short_code' => 'Sent', 'rank_level' => 11, 'is_executive' => false],
        ]);

        // 9. Allied Masonic Degrees
        $seedOrder([
            'code' => 'allied_masonic',
            'name' => 'Allied Masonic Degrees Council (AMD)',
            'website_url' => 'https://markmasonshall.org/orders/allied-masonic-degrees',
            'description' => 'The Grand Council of the Allied Masonic Degrees administers five distinct historic Masonic degrees including St Lawrence the Martyr, Knights of Constantinople, Grand Tilers of Solomon, Red Cross of Babylon, and Grand High Priest.',
            'available_modules' => ['accounting', 'meetings', 'members', 'dining'],
            'terminology' => [
                'club' => 'Council',
                'master' => 'Worshipful Master',
                'summons' => 'Council Summons',
                'members' => 'Brethren',
            ],
            'rulers_schema' => [
                'District Grand Master',
                'Deputy District Grand Master',
            ],
        ], [
            ['title' => 'Worshipful Master', 'short_code' => 'WM', 'rank_level' => 1, 'is_executive' => true],
            ['title' => 'Senior Warden', 'short_code' => 'SW', 'rank_level' => 2, 'is_executive' => true],
            ['title' => 'Junior Warden', 'short_code' => 'JW', 'rank_level' => 3, 'is_executive' => true],
            ['title' => 'Treasurer', 'short_code' => 'Treas', 'rank_level' => 4, 'is_executive' => true],
            ['title' => 'Secretary', 'short_code' => 'Sec', 'rank_level' => 5, 'is_executive' => true],
            ['title' => 'Director of Ceremonies', 'short_code' => 'DC', 'rank_level' => 6, 'is_executive' => true],
            ['title' => 'Senior Deacon', 'short_code' => 'SD', 'rank_level' => 7, 'is_executive' => false],
            ['title' => 'Junior Deacon', 'short_code' => 'JD', 'rank_level' => 8, 'is_executive' => false],
            ['title' => 'Inner Guard', 'short_code' => 'IG', 'rank_level' => 9, 'is_executive' => false],
            ['title' => 'Tyler', 'short_code' => 'Tyler', 'rank_level' => 10, 'is_executive' => false],
        ]);

        // 10. Royal and Select Masters (Cryptic Degrees)
        $seedOrder([
            'code' => 'cryptic_council',
            'name' => 'Royal & Select Masters Council (Cryptic)',
            'website_url' => 'https://markmasonshall.org/orders/royal-select-masters',
            'description' => 'The Order of Royal and Select Masters (Cryptic Masonry) preserves the mystery of the Secret Vault underneath King Solomon\'s Temple. Comprises four core degrees: Select Master, Royal Master, Most Excellent Master, and Super Excellent Master.',
            'available_modules' => ['accounting', 'meetings', 'members', 'dining'],
            'terminology' => [
                'club' => 'Council',
                'master' => 'Thrice Illustrious Master',
                'summons' => 'Cryptic Council Summons',
                'members' => 'Companions',
            ],
            'rulers_schema' => [
                'District Grand Master',
                'Deputy District Grand Master',
            ],
        ], [
            ['title' => 'Thrice Illustrious Master', 'short_code' => 'TIM', 'rank_level' => 1, 'is_executive' => true],
            ['title' => 'Deputy Master', 'short_code' => 'DM', 'rank_level' => 2, 'is_executive' => true],
            ['title' => 'Principal Conductor of Work', 'short_code' => 'PCW', 'rank_level' => 3, 'is_executive' => true],
            ['title' => 'Treasurer', 'short_code' => 'Treas', 'rank_level' => 4, 'is_executive' => true],
            ['title' => 'Recorder', 'short_code' => 'Rec', 'rank_level' => 5, 'is_executive' => true],
            ['title' => 'Captain of Guard', 'short_code' => 'CG', 'rank_level' => 6, 'is_executive' => false],
            ['title' => 'Conductor of Council', 'short_code' => 'CC', 'rank_level' => 7, 'is_executive' => false],
            ['title' => 'Steward', 'short_code' => 'Stwd', 'rank_level' => 8, 'is_executive' => false],
            ['title' => 'Sentinel', 'short_code' => 'Sent', 'rank_level' => 9, 'is_executive' => false],
        ]);

        // 11. Holy Royal Arch Knight Templar Priests
        $seedOrder([
            'code' => 'ktp_tabernacle',
            'name' => 'Knight Templar Priests Tabernacle (KTP)',
            'website_url' => 'https://hkts.org.uk',
            'description' => 'The Holy Royal Arch Knight Templar Priests and Order of Holy Wisdom. An invitational Christian Masonic order for Installed Masters of Craft, Royal Arch, and Knights Templar Preceptories, working 33 appendant degrees.',
            'available_modules' => ['accounting', 'meetings', 'members', 'dining'],
            'terminology' => [
                'club' => 'Tabernacle',
                'master' => 'High Priest',
                'summons' => 'Tabernacle Summons',
                'members' => 'Knight Priests',
            ],
            'rulers_schema' => [
                'Grand Superintendent',
            ],
        ], [
            ['title' => 'High Priest', 'short_code' => 'HP', 'rank_level' => 1, 'is_executive' => true],
            ['title' => '1st Pillar', 'short_code' => '1P', 'rank_level' => 2, 'is_executive' => false],
            ['title' => '2nd Pillar', 'short_code' => '2P', 'rank_level' => 3, 'is_executive' => false],
            ['title' => '3rd Pillar', 'short_code' => '3P', 'rank_level' => 4, 'is_executive' => false],
            ['title' => 'Treasurer', 'short_code' => 'Treas', 'rank_level' => 5, 'is_executive' => true],
            ['title' => 'Scribe', 'short_code' => 'Scribe', 'rank_level' => 6, 'is_executive' => true],
            ['title' => 'Director of Ceremonies', 'short_code' => 'DC', 'rank_level' => 7, 'is_executive' => true],
            ['title' => 'Outer Guard', 'short_code' => 'OG', 'rank_level' => 8, 'is_executive' => false],
        ]);

        // 12. Societas Rosicruciana in Anglia (SRIA)
        $seedOrder([
            'code' => 'sria_college',
            'name' => 'Societas Rosicruciana in Anglia College (SRIA)',
            'website_url' => 'https://sria.uk',
            'description' => 'Societas Rosicruciana in Anglia (SRIA) is a Rosicrucian Masonic society dedicated to scientific, historical, philosophical, and esoteric research. Admission is limited to Master Masons of Christian faith.',
            'available_modules' => ['accounting', 'meetings', 'members', 'dining'],
            'terminology' => [
                'club' => 'College',
                'master' => 'Celebrant',
                'summons' => 'Convocation Summons',
                'members' => 'Fratres',
            ],
            'rulers_schema' => [
                'Chief Adept',
                'Suffragan',
            ],
        ], [
            ['title' => 'Celebrant', 'short_code' => 'Cel', 'rank_level' => 1, 'is_executive' => true],
            ['title' => 'Ex-Celebrant', 'short_code' => 'ExCel', 'rank_level' => 2, 'is_executive' => true],
            ['title' => 'Deputy Celebrant', 'short_code' => 'DepCel', 'rank_level' => 3, 'is_executive' => true],
            ['title' => 'Treasurer', 'short_code' => 'Treas', 'rank_level' => 4, 'is_executive' => true],
            ['title' => 'Secretary', 'short_code' => 'Sec', 'rank_level' => 5, 'is_executive' => true],
            ['title' => 'Director of Ceremonies', 'short_code' => 'DC', 'rank_level' => 6, 'is_executive' => true],
            ['title' => '1st Ancient', 'short_code' => '1Anc', 'rank_level' => 7, 'is_executive' => false],
            ['title' => '2nd Ancient', 'short_code' => '2Anc', 'rank_level' => 8, 'is_executive' => false],
            ['title' => '3rd Ancient', 'short_code' => '3Anc', 'rank_level' => 9, 'is_executive' => false],
            ['title' => '4th Ancient', 'short_code' => '4Anc', 'rank_level' => 10, 'is_executive' => false],
            ['title' => 'Acolyte', 'short_code' => 'Acolyte', 'rank_level' => 11, 'is_executive' => false],
        ]);

        // 13. Royal Order of Scotland
        $seedOrder([
            'code' => 'royal_order_scotland',
            'name' => 'Royal Order of Scotland',
            'website_url' => 'https://royalorderofscotland.org',
            'description' => 'The Royal Order of Scotland is an ancient order headquartered in Edinburgh under the Grand Lodge of the Royal Order of Scotland. Comprises two degrees: Heredom of Kilwinning and Rosy Cross, conferred upon experienced Master Masons.',
            'available_modules' => ['accounting', 'meetings', 'members', 'dining'],
            'terminology' => [
                'club' => 'Provincial Grand Lodge',
                'master' => 'Provincial Grand Master',
                'summons' => 'Billet & Circular',
                'members' => 'Knights of the Rosy Cross',
            ],
            'rulers_schema' => [
                'Provincial Grand Master',
                'Deputy Provincial Grand Master',
            ],
        ], [
            ['title' => 'Provincial Grand Master', 'short_code' => 'PGM', 'rank_level' => 1, 'is_executive' => true],
            ['title' => 'Deputy Provincial Grand Master', 'short_code' => 'DPGM', 'rank_level' => 2, 'is_executive' => true],
            ['title' => 'Senior Provincial Grand Warden', 'short_code' => 'SPGW', 'rank_level' => 3, 'is_executive' => true],
            ['title' => 'Junior Provincial Grand Warden', 'short_code' => 'JPGW', 'rank_level' => 4, 'is_executive' => true],
            ['title' => 'Provincial Grand Secretary', 'short_code' => 'PGSec', 'rank_level' => 5, 'is_executive' => true],
            ['title' => 'Provincial Grand Treasurer', 'short_code' => 'PGTreas', 'rank_level' => 6, 'is_executive' => true],
        ]);

        // 14. Order of the Scarlet Cord
        $seedOrder([
            'code' => 'scarlet_cord',
            'name' => 'Order of the Scarlet Cord Consistory',
            'website_url' => 'https://markmasonshall.org/orders/scarlet-cord',
            'description' => 'The Ancient and Masonic Order of the Scarlet Cord is closely allied with the Order of the Secret Monitor. Based on Rahab and the scarlet cord in Jericho, it works six progressive grades of chivalric and administrative service.',
            'available_modules' => ['accounting', 'meetings', 'members', 'dining'],
            'terminology' => [
                'club' => 'Consistory',
                'master' => 'President',
                'summons' => 'Consistory Summons',
                'members' => 'Companions of the Scarlet Cord',
            ],
            'rulers_schema' => [
                'Provincial Grand Summus',
                'Deputy Provincial Grand Summus',
            ],
        ], [
            ['title' => 'President', 'short_code' => 'Pres', 'rank_level' => 1, 'is_executive' => true],
            ['title' => 'Primus', 'short_code' => 'Primus', 'rank_level' => 2, 'is_executive' => true],
            ['title' => 'Lector', 'short_code' => 'Lector', 'rank_level' => 3, 'is_executive' => false],
            ['title' => 'Registrar', 'short_code' => 'Reg', 'rank_level' => 4, 'is_executive' => true],
            ['title' => 'Treasurer', 'short_code' => 'Treas', 'rank_level' => 5, 'is_executive' => true],
            ['title' => 'Director of Ceremonies', 'short_code' => 'DC', 'rank_level' => 6, 'is_executive' => true],
            ['title' => 'Ostiarius', 'short_code' => 'Ost', 'rank_level' => 7, 'is_executive' => false],
        ]);

        // Default System Email Templates
        DefaultEmailTemplate::updateOrCreate(
            ['template_key' => 'password_reset'],
            [
                'name' => 'Password Reset Request',
                'subject' => '{{club_name}} - Reset Your Account Password',
                'body_html' => '<p>Dear {{member_name}},</p><p>We received a request to reset the password for your account.</p><p><a href="{{reset_url}}" style="display:inline-block;padding:10px 20px;background:#4f46e5;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold;">Reset Password</a></p><p>This link will expire in {{expire_minutes}} minutes. If you did not request a password reset, no further action is required.</p>',
                'available_placeholders' => ['member_name', 'club_name', 'reset_url', 'expire_minutes'],
            ]
        );

        DefaultEmailTemplate::updateOrCreate(
            ['template_key' => 'user_registration'],
            [
                'name' => 'New Member Registration Welcome',
                'subject' => 'Welcome to {{club_name}} - Account Created',
                'body_html' => '<p>Dear {{member_name}},</p><p>Welcome! Your member portal account for {{club_name}} has been created successfully.</p><p>You can log in anytime at <a href="{{login_url}}">{{login_url}}</a> using your registered email: <strong>{{email}}</strong>.</p>',
                'available_placeholders' => ['member_name', 'club_name', 'login_url', 'email'],
            ]
        );

        DefaultEmailTemplate::updateOrCreate(
            ['template_key' => 'account_invitation'],
            [
                'name' => 'Lodge Account Email Invitation',
                'subject' => 'Invitation to Join {{club_name}} Portal',
                'body_html' => '<p>Dear {{member_name}},</p><p>You have been invited by {{club_name}} to activate your online member portal access.</p><p><a href="{{invite_url}}" style="display:inline-block;padding:10px 20px;background:#4f46e5;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold;">Accept Invitation & Setup Password</a></p><p>This invitation link will expire in {{expiry_days}} days.</p>',
                'available_placeholders' => ['member_name', 'club_name', 'invite_url', 'expiry_days'],
            ]
        );

        DefaultEmailTemplate::updateOrCreate(
            ['template_key' => 'meeting_summons'],
            [
                'name' => 'Meeting Summons Circular',
                'subject' => '{{club_name}} - Official Summons for {{meeting_title}} ({{meeting_date}})',
                'body_html' => '<p>Dear Brother {{member_name}},</p><p>You are hereby summoned to attend the {{meeting_title}} of {{club_name}} on <strong>{{meeting_date}}</strong> starting at <strong>{{starts_at}}</strong> at {{venue}}.</p><p>Please record your attendance and dining RSVP in the member portal.</p>',
                'available_placeholders' => ['member_name', 'club_name', 'meeting_title', 'meeting_date', 'starts_at', 'venue'],
            ]
        );

        DefaultEmailTemplate::updateOrCreate(
            ['template_key' => 'dues_notice'],
            [
                'name' => 'Annual Dues Subscription Notice',
                'subject' => '{{club_name}} - Annual Dues Subscription Invoice',
                'body_html' => '<p>Dear {{member_name}},</p><p>Your annual subscription for {{club_name}} of <strong>£{{invoice_amount}}</strong> is now due.</p><p>You can pay securely via the online portal using credit card or direct bank transfer.</p>',
                'available_placeholders' => ['member_name', 'club_name', 'invoice_amount', 'due_date', 'payment_link'],
            ]
        );

        DefaultEmailTemplate::updateOrCreate(
            ['template_key' => 'candidate_welcome'],
            [
                'name' => 'Candidate Welcome & Onboarding Notice',
                'subject' => 'Welcome to {{club_name}}',
                'body_html' => '<p>Dear {{candidate_name}},</p><p>We are delighted to inform you that your application for membership in {{club_name}} has been approved.</p><p>Please log in to complete your profile registration.</p>',
                'available_placeholders' => ['candidate_name', 'club_name', 'portal_link'],
            ]
        );
    }
}
