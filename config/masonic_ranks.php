<?php

/*
 * Starting lists of Grand and Provincial ranks (UGLE, Craft).
 *
 * These are only the starting point. The list a lodge uses is its own copy in `clubs.settings`, taken from its
 * Grand Lodge's list (edited by a superadmin) or, failing that, from here. Built from public sources and from
 * the ranks already recorded against members: check with a Provincial Secretary before relying on the exact
 * titles. Each entry is the abbreviation that is stored and shown in a member's name, and the full title.
 */

return [
    /** Club type codes (see ClubTypeSeeder) that are Masonic bodies and so keep rank lists. */
    'club_type_codes' => [
        'craft_lodge', 'royal_arch', 'mark_lodge', 'royal_ark_mariner', 'rose_croix', 'knights_templar',
        'secret_monitor', 'red_cross_constantine', 'allied_masonic', 'cryptic_council', 'ktp_tabernacle',
        'sria_college', 'royal_order_scotland', 'scarlet_cord',
    ],

    /** Club types that start from the lists below when no Grand Lodge list is set. */
    'starter_club_type_codes' => ['craft_lodge'],

    'grand' => [
        ['abbreviation' => 'PGD', 'title' => 'Past Grand Deacon'],
        ['abbreviation' => 'PSGD', 'title' => 'Past Senior Grand Deacon'],
        ['abbreviation' => 'PJGD', 'title' => 'Past Junior Grand Deacon'],
        ['abbreviation' => 'PGSuptWks', 'title' => 'Past Grand Superintendent of Works'],
        ['abbreviation' => 'PGDC', 'title' => 'Past Grand Director of Ceremonies'],
        ['abbreviation' => 'PDepGDC', 'title' => 'Past Deputy Grand Director of Ceremonies'],
        ['abbreviation' => 'PAGDC', 'title' => 'Past Assistant Grand Director of Ceremonies'],
        ['abbreviation' => 'PGSwdB', 'title' => 'Past Grand Sword Bearer'],
        ['abbreviation' => 'PAGSwdB', 'title' => 'Past Assistant Grand Sword Bearer'],
        ['abbreviation' => 'PGStdB', 'title' => 'Past Grand Standard Bearer'],
        ['abbreviation' => 'PAGStdB', 'title' => 'Past Assistant Grand Standard Bearer'],
        ['abbreviation' => 'PGOrg', 'title' => 'Past Grand Organist'],
        ['abbreviation' => 'PAGOrg', 'title' => 'Past Assistant Grand Organist'],
        ['abbreviation' => 'PGPurs', 'title' => 'Past Grand Pursuivant'],
        ['abbreviation' => 'PAGPurs', 'title' => 'Past Assistant Grand Pursuivant'],
        ['abbreviation' => 'PGStwd', 'title' => 'Past Grand Steward'],
        ['abbreviation' => 'PGChap', 'title' => 'Past Grand Chaplain'],
        ['abbreviation' => 'PGReg', 'title' => 'Past Grand Registrar'],
        ['abbreviation' => 'PGSec', 'title' => 'Past Grand Secretary'],
        ['abbreviation' => 'PGTreas', 'title' => 'Past Grand Treasurer'],
    ],

    'provincial' => [
        ['abbreviation' => 'PPrSGW', 'title' => 'Past Provincial Senior Grand Warden'],
        ['abbreviation' => 'PPrJGW', 'title' => 'Past Provincial Junior Grand Warden'],
        ['abbreviation' => 'PPrGW', 'title' => 'Past Provincial Grand Warden'],
        ['abbreviation' => 'PPrSGD', 'title' => 'Past Provincial Senior Grand Deacon'],
        ['abbreviation' => 'PPrJGD', 'title' => 'Past Provincial Junior Grand Deacon'],
        ['abbreviation' => 'PPrGSuptWks', 'title' => 'Past Provincial Grand Superintendent of Works'],
        ['abbreviation' => 'PPrGDC', 'title' => 'Past Provincial Grand Director of Ceremonies'],
        ['abbreviation' => 'PPrDepGDC', 'title' => 'Past Provincial Deputy Grand Director of Ceremonies'],
        ['abbreviation' => 'PPrAGDC', 'title' => 'Past Provincial Assistant Grand Director of Ceremonies'],
        ['abbreviation' => 'PPrGSwdB', 'title' => 'Past Provincial Grand Sword Bearer'],
        ['abbreviation' => 'PPrAGSwdB', 'title' => 'Past Provincial Assistant Grand Sword Bearer'],
        ['abbreviation' => 'PPrGStdB', 'title' => 'Past Provincial Grand Standard Bearer'],
        ['abbreviation' => 'PPrAGStdB', 'title' => 'Past Provincial Assistant Grand Standard Bearer'],
        ['abbreviation' => 'PPrGOrg', 'title' => 'Past Provincial Grand Organist'],
        ['abbreviation' => 'PPrAGOrg', 'title' => 'Past Provincial Assistant Grand Organist'],
        ['abbreviation' => 'PPrGPurs', 'title' => 'Past Provincial Grand Pursuivant'],
        ['abbreviation' => 'PPrAGPurs', 'title' => 'Past Provincial Assistant Grand Pursuivant'],
        ['abbreviation' => 'PPrGStwd', 'title' => 'Past Provincial Grand Steward'],
        ['abbreviation' => 'PPrGChap', 'title' => 'Past Provincial Grand Chaplain'],
        ['abbreviation' => 'PPrGReg', 'title' => 'Past Provincial Grand Registrar'],
        ['abbreviation' => 'PPrGSec', 'title' => 'Past Provincial Grand Secretary'],
        ['abbreviation' => 'PPrGTreas', 'title' => 'Past Provincial Grand Treasurer'],
    ],
];
