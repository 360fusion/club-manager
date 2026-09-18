<?php

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $club = Club::where('slug', 'lodge-of-fraternity')->first();
        if (! $club) {
            return;
        }

        $adminUser = User::where('email', 'admin@example.com')->first();
        $memberUser = User::where('email', 'member@example.com')->first();
        $coachUser = User::where('email', 'coach@example.com')->first();
        $treasurerUser = User::where('email', 'treasurer@example.com')->first();
        $pendingUser = User::where('email', 'pending@example.com')->first();
        $benUser = User::where('email', 'ben@360fusion.co.uk')->first();

        $members = [
            [
                'club_id' => $club->id,
                'user_id' => $adminUser?->id,
                'title' => 'WBro',
                'first_name' => 'Alex',
                'last_name' => 'Morgan',
                'email' => 'admin@example.com',
                'phone' => '07700 900001',
                'masonic_rank' => 'WBro',
                'grand_rank' => 'PAGDC',
                'provincial_rank' => 'PPrGSuptWks',
                'grand_lodge_number' => 'GL-88401',
                'membership_status' => MembershipStatus::Active,
                'current_office' => LodgeOffice::WorshipfulMaster,
                'date_of_initiation' => '2012-04-15',
                'date_of_passing' => '2012-10-20',
                'date_of_raising' => '2013-03-12',
                'date_of_joining' => '2015-01-10',
            ],
            [
                'club_id' => $club->id,
                'user_id' => $memberUser?->id,
                'title' => 'Bro',
                'first_name' => 'Taylor',
                'last_name' => 'Swift',
                'email' => 'member@example.com',
                'phone' => '07700 900002',
                'masonic_rank' => 'Bro',
                'grand_lodge_number' => 'GL-92104',
                'membership_status' => MembershipStatus::Active,
                'current_office' => LodgeOffice::JuniorWarden,
                'date_of_initiation' => '2019-02-14',
                'date_of_passing' => '2019-09-10',
                'date_of_raising' => '2020-01-18',
            ],
            [
                'club_id' => $club->id,
                'user_id' => $coachUser?->id,
                'title' => 'WBro',
                'first_name' => 'Marcus',
                'last_name' => 'Vance',
                'email' => 'coach@example.com',
                'phone' => '07700 900003',
                'masonic_rank' => 'WBro',
                'provincial_rank' => 'PPrSGD',
                'grand_lodge_number' => 'GL-76320',
                'membership_status' => MembershipStatus::Active,
                'current_office' => LodgeOffice::DirectorOfCeremonies,
                'date_of_initiation' => '2008-11-05',
                'date_of_passing' => '2009-04-12',
                'date_of_raising' => '2009-11-20',
            ],
            [
                'club_id' => $club->id,
                'user_id' => $treasurerUser?->id,
                'title' => 'WBro',
                'first_name' => 'Elena',
                'last_name' => 'Rostova',
                'email' => 'treasurer@example.com',
                'phone' => '07700 900004',
                'masonic_rank' => 'WBro',
                'provincial_rank' => 'PPrGStwd',
                'grand_lodge_number' => 'GL-81944',
                'membership_status' => MembershipStatus::Active,
                'current_office' => LodgeOffice::Treasurer,
                'date_of_initiation' => '2010-06-18',
                'date_of_passing' => '2010-11-22',
                'date_of_raising' => '2011-05-14',
            ],
            [
                'club_id' => $club->id,
                'user_id' => $pendingUser?->id,
                'title' => 'Bro',
                'first_name' => 'Jordan',
                'last_name' => 'Lee',
                'email' => 'pending@example.com',
                'phone' => '07700 900005',
                'masonic_rank' => 'Bro',
                'grand_lodge_number' => 'GL-98210',
                'membership_status' => MembershipStatus::Active,
                'current_office' => LodgeOffice::InnerGuard,
                'date_of_initiation' => '2023-01-15',
            ],
            [
                'club_id' => $club->id,
                'user_id' => $benUser?->id,
                'title' => 'Bro',
                'first_name' => 'Ben',
                'last_name' => 'Kenyon',
                'email' => 'ben@360fusion.co.uk',
                'phone' => '07700 900006',
                'masonic_rank' => 'Bro',
                'grand_lodge_number' => 'GL-99321',
                'membership_status' => MembershipStatus::Active,
                'current_office' => LodgeOffice::Steward,
                'date_of_initiation' => '2022-05-10',
                'date_of_passing' => '2022-11-15',
                'date_of_raising' => '2023-04-18',
            ],
            [
                'club_id' => $club->id,
                'title' => 'WBro',
                'first_name' => 'Arthur',
                'last_name' => 'Pendelton',
                'email' => 'arthur.pendelton@example.com',
                'phone' => '07700 900123',
                'masonic_rank' => 'WBro',
                'grand_rank' => 'PJGD',
                'provincial_rank' => 'PPrGReg',
                'grand_lodge_number' => 'GL-64201',
                'membership_status' => MembershipStatus::Active,
                'current_office' => LodgeOffice::IPM,
                'date_of_initiation' => '1998-03-10',
                'date_of_passing' => '1998-10-14',
                'date_of_raising' => '1999-02-22',
            ],
            [
                'club_id' => $club->id,
                'title' => 'WBro',
                'first_name' => 'Charles',
                'last_name' => 'Montgomery',
                'email' => 'secretary@lodge-fraternity.org',
                'phone' => '07700 900456',
                'masonic_rank' => 'WBro',
                'provincial_rank' => 'PPrGW',
                'grand_lodge_number' => 'GL-71239',
                'membership_status' => MembershipStatus::Active,
                'current_office' => LodgeOffice::Secretary,
                'date_of_initiation' => '2005-09-14',
                'date_of_passing' => '2006-03-20',
                'date_of_raising' => '2006-11-10',
            ],
            [
                'club_id' => $club->id,
                'title' => 'Bro',
                'first_name' => 'David',
                'last_name' => 'Sterling',
                'email' => 'david.sterling@example.com',
                'phone' => '07700 900789',
                'masonic_rank' => 'Bro',
                'grand_lodge_number' => 'GL-91024',
                'membership_status' => MembershipStatus::Active,
                'current_office' => LodgeOffice::SeniorWarden,
                'date_of_initiation' => '2018-05-12',
                'date_of_passing' => '2018-11-18',
                'date_of_raising' => '2019-04-09',
            ],
            [
                'club_id' => $club->id,
                'title' => 'Bro',
                'first_name' => 'George',
                'last_name' => 'Harrison',
                'email' => 'george.harrison@example.com',
                'masonic_rank' => 'Bro',
                'grand_lodge_number' => 'GL-93451',
                'membership_status' => MembershipStatus::Active,
                'current_office' => LodgeOffice::SeniorDeacon,
                'date_of_initiation' => '2020-10-08',
                'date_of_passing' => '2021-04-14',
                'date_of_raising' => '2021-10-22',
            ],
            [
                'club_id' => $club->id,
                'title' => 'Bro',
                'first_name' => 'Robert',
                'last_name' => 'Langdon',
                'email' => 'robert.langdon@example.com',
                'masonic_rank' => 'Bro',
                'grand_lodge_number' => 'GL-94512',
                'membership_status' => MembershipStatus::Active,
                'current_office' => LodgeOffice::JuniorDeacon,
                'date_of_initiation' => '2021-03-16',
                'date_of_passing' => '2021-09-21',
                'date_of_raising' => '2022-02-15',
            ],
            [
                'club_id' => $club->id,
                'title' => 'WBro',
                'first_name' => 'Edward',
                'last_name' => 'Blake',
                'email' => 'edward.blake@example.com',
                'masonic_rank' => 'WBro',
                'provincial_rank' => 'PPrGSwdB',
                'grand_lodge_number' => 'GL-68912',
                'membership_status' => MembershipStatus::Active,
                'current_office' => LodgeOffice::Almoner,
                'date_of_initiation' => '2002-01-20',
                'date_of_passing' => '2002-06-18',
                'date_of_raising' => '2003-01-14',
            ],
            [
                'club_id' => $club->id,
                'title' => 'Bro',
                'first_name' => 'Thomas',
                'last_name' => 'Wright',
                'email' => 'thomas.wright@example.com',
                'masonic_rank' => 'Bro',
                'grand_lodge_number' => 'GL-95123',
                'membership_status' => MembershipStatus::Active,
                'current_office' => LodgeOffice::CharitySteward,
                'date_of_initiation' => '2021-11-09',
                'date_of_passing' => '2022-04-12',
                'date_of_raising' => '2022-10-18',
            ],
            [
                'club_id' => $club->id,
                'title' => 'VWBro',
                'first_name' => 'Henry',
                'last_name' => 'Cavendish',
                'email' => 'henry.cavendish@example.com',
                'masonic_rank' => 'VWBro',
                'grand_rank' => 'PGSwdB',
                'grand_lodge_number' => 'GL-51204',
                'membership_status' => MembershipStatus::Honorary,
                'current_office' => LodgeOffice::Chaplain,
                'date_of_initiation' => '1985-04-10',
                'date_of_passing' => '1985-11-12',
                'date_of_raising' => '1986-03-18',
            ],
        ];

        foreach ($members as $data) {
            Member::updateOrCreate(
                [
                    'club_id' => $data['club_id'],
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                ],
                $data
            );
        }

        // Also seed Bath RFC club members
        $bathClub = Club::where('slug', 'bath-rfc')->first();
        if ($bathClub) {
            $bathMembers = [
                [
                    'club_id' => $bathClub->id,
                    'user_id' => $adminUser?->id,
                    'title' => 'Mr',
                    'first_name' => 'Alex',
                    'last_name' => 'Morgan',
                    'email' => 'admin@example.com',
                    'masonic_rank' => 'Bro',
                    'membership_status' => MembershipStatus::Active,
                    'current_office' => LodgeOffice::Member,
                ],
                [
                    'club_id' => $bathClub->id,
                    'user_id' => $memberUser?->id,
                    'title' => 'Mr',
                    'first_name' => 'Taylor',
                    'last_name' => 'Swift',
                    'email' => 'member@example.com',
                    'masonic_rank' => 'Bro',
                    'membership_status' => MembershipStatus::Active,
                    'current_office' => LodgeOffice::Member,
                ],
                [
                    'club_id' => $bathClub->id,
                    'user_id' => $coachUser?->id,
                    'title' => 'Mr',
                    'first_name' => 'Marcus',
                    'last_name' => 'Vance',
                    'email' => 'coach@example.com',
                    'masonic_rank' => 'Bro',
                    'membership_status' => MembershipStatus::Active,
                    'current_office' => LodgeOffice::Member,
                ],
            ];

            foreach ($bathMembers as $bData) {
                Member::updateOrCreate(
                    [
                        'club_id' => $bData['club_id'],
                        'first_name' => $bData['first_name'],
                        'last_name' => $bData['last_name'],
                    ],
                    $bData
                );
            }
        }
    }

    public function down(): void
    {
        Member::whereIn('grand_lodge_number', [
            'GL-88401', 'GL-92104', 'GL-76320', 'GL-81944', 'GL-98210', 'GL-99321',
            'GL-64201', 'GL-71239', 'GL-91024', 'GL-93451', 'GL-94512', 'GL-68912',
            'GL-95123', 'GL-51204',
        ])->delete();
    }
};
