<?php

namespace App\Domains\ClubAccounting\Services\MemberImport;

use App\Domains\ClubAccounting\Models\Member;

/**
 * The member fields a spreadsheet can fill, the header names each is recognised by, and the columns of the
 * roster export. The export and the import template both come from EXPORT_COLUMNS, so a file exported from
 * the directory can be imported straight back.
 */
final class MemberImportFields
{
    /** A virtual field for a single "Name" column, split into first, middle and last name. */
    public const FULL_NAME = 'full_name';

    /** The email on the template's example row, so a row left in by mistake is caught. */
    public const EXAMPLE_EMAIL = 'example.member@example.com';

    /**
     * Field key => label and the longest value the database accepts.
     *
     * @var array<string, array{label: string, max: int, kind: string}>
     */
    public const FIELDS = [
        'grand_lodge_number' => ['label' => 'Hermes / GL ID', 'max' => 50, 'kind' => 'text'],
        'first_name' => ['label' => 'First name', 'max' => 100, 'kind' => 'text'],
        'middle_names' => ['label' => 'Middle names', 'max' => 150, 'kind' => 'text'],
        'last_name' => ['label' => 'Last name', 'max' => 100, 'kind' => 'text'],
        'preferred_name' => ['label' => 'Preferred name', 'max' => 100, 'kind' => 'text'],
        'email' => ['label' => 'Email', 'max' => 255, 'kind' => 'email'],
        'phone' => ['label' => 'Phone', 'max' => 50, 'kind' => 'text'],
        'address_line_1' => ['label' => 'Address line 1', 'max' => 255, 'kind' => 'text'],
        'address_line_2' => ['label' => 'Address line 2', 'max' => 255, 'kind' => 'text'],
        'city' => ['label' => 'City', 'max' => 100, 'kind' => 'text'],
        'county' => ['label' => 'County', 'max' => 100, 'kind' => 'text'],
        'postcode' => ['label' => 'Postcode', 'max' => 20, 'kind' => 'text'],
        'country' => ['label' => 'Country', 'max' => 100, 'kind' => 'text'],
        'masonic_rank' => ['label' => 'Masonic rank', 'max' => 50, 'kind' => 'rank'],
        'grand_rank' => ['label' => 'Grand rank', 'max' => 100, 'kind' => 'grand_rank'],
        'provincial_rank' => ['label' => 'Provincial rank', 'max' => 100, 'kind' => 'provincial_rank'],
        'current_office' => ['label' => 'Current office', 'max' => 40, 'kind' => 'office'],
        'membership_status' => ['label' => 'Membership status', 'max' => 30, 'kind' => 'status'],
        'date_of_initiation' => ['label' => 'Date of initiation', 'max' => 20, 'kind' => 'date'],
        'date_of_passing' => ['label' => 'Date of passing', 'max' => 20, 'kind' => 'date'],
        'date_of_raising' => ['label' => 'Date of raising', 'max' => 20, 'kind' => 'date'],
        'date_of_joining' => ['label' => 'Date of joining', 'max' => 20, 'kind' => 'date'],
        'notes' => ['label' => 'Notes', 'max' => 5000, 'kind' => 'text'],
    ];

    /**
     * Header text => member field, in the order of the roster export.
     *
     * @var array<string, string>
     */
    public const EXPORT_COLUMNS = [
        'Hermes/GL ID' => 'grand_lodge_number',
        'First Name' => 'first_name',
        'Last Name' => 'last_name',
        'Masonic Rank' => 'masonic_rank',
        'Grand Rank' => 'grand_rank',
        'Provincial Rank' => 'provincial_rank',
        'Current Office' => 'current_office',
        'Status' => 'membership_status',
        'Email' => 'email',
        'Phone' => 'phone',
        'Address Line 1' => 'address_line_1',
        'Address Line 2' => 'address_line_2',
        'City' => 'city',
        'Postcode' => 'postcode',
        'Date of Joining' => 'date_of_joining',
        'Date of Initiation' => 'date_of_initiation',
    ];

    /**
     * Header names (lower case, letters and digits only) that identify each field.
     *
     * @var array<string, array<int, string>>
     */
    private const ALIASES = [
        'grand_lodge_number' => ['hermesglid', 'hermesid', 'hermes', 'glid', 'glnumber', 'grandlodgenumber', 'grandlodgeid', 'hermesnumber', 'hermesmemberid'],
        'first_name' => ['firstname', 'forename', 'forenames', 'givenname', 'first', 'christianname'],
        'middle_names' => ['middlenames', 'middlename', 'middle'],
        'last_name' => ['lastname', 'surname', 'familyname', 'last'],
        'preferred_name' => ['preferredname', 'knownas', 'nickname', 'preferred'],
        self::FULL_NAME => ['fullname', 'name', 'membername', 'brothername', 'member'],
        'email' => ['email', 'emailaddress', 'mail', 'emailaddr'],
        'phone' => ['phone', 'telephone', 'mobile', 'phonenumber', 'mobilenumber', 'tel', 'contactnumber', 'telephonenumber'],
        'address_line_1' => ['addressline1', 'address1', 'address', 'street', 'addressline', 'line1', 'streetaddress'],
        'address_line_2' => ['addressline2', 'address2', 'line2'],
        'city' => ['city', 'town', 'towncity', 'cityortown'],
        'county' => ['county', 'state', 'province', 'region'],
        'postcode' => ['postcode', 'postalcode', 'zip', 'zipcode'],
        'country' => ['country'],
        'masonic_rank' => ['masonicrank', 'rank', 'masonictitle'],
        'grand_rank' => ['grandrank', 'glrank', 'grandlodgerank'],
        'provincial_rank' => ['provincialrank', 'provrank', 'prov', 'provincial'],
        'current_office' => ['currentoffice', 'office', 'lodgeoffice', 'position'],
        'membership_status' => ['status', 'membershipstatus', 'memberstatus'],
        'date_of_initiation' => ['dateofinitiation', 'initiated', 'initiationdate', 'initiation', 'dateinitiated'],
        'date_of_passing' => ['dateofpassing', 'passed', 'passingdate', 'datepassed'],
        'date_of_raising' => ['dateofraising', 'raised', 'raisingdate', 'dateraised'],
        'date_of_joining' => ['dateofjoining', 'joined', 'joiningdate', 'joindate', 'datejoined', 'dateofjoin'],
        'notes' => ['notes', 'note', 'comments', 'remarks'],
    ];

    /**
     * The template's example row, in EXPORT_COLUMNS order.
     *
     * @return array<int, string>
     */
    public static function exampleRow(): array
    {
        return ['1234567', 'John', 'Smith', 'Bro', '', '', 'Member / Brethren', 'Active Member', self::EXAMPLE_EMAIL, '07700 900123', '1 High Street', '', 'Oxford', 'OX1 1AA', '2015-06-01', '2014-03-10'];
    }

    /**
     * Every target a column can be matched to: field key => label, with the split-name option first.
     *
     * @return array<string, string>
     */
    public static function targets(): array
    {
        return [self::FULL_NAME => 'Full name (split into first and last)']
            + array_map(fn (array $field) => $field['label'], self::FIELDS);
    }

    public static function label(string $key): string
    {
        return $key === self::FULL_NAME ? 'Full name' : (self::FIELDS[$key]['label'] ?? $key);
    }

    /**
     * Lower case, letters and digits only: "Hermes/GL ID" and "hermes gl id" compare equal.
     */
    public static function normalise(string $header): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($header)) ?? '';
    }

    /**
     * Guess which field each column holds. A field is given to the first column that names it; a column no
     * alias recognises stays unmatched (null).
     *
     * @param  array<int, string>  $headers
     * @return array<int, string|null>
     */
    public static function autoMap(array $headers): array
    {
        $byAlias = [];

        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $byAlias[$alias] = $field;
            }
        }

        $taken = [];
        $map = [];

        foreach ($headers as $index => $header) {
            $field = $byAlias[self::normalise($header)] ?? null;

            if ($field !== null && ! isset($taken[$field])) {
                $taken[$field] = true;
                $map[$index] = $field;
            } else {
                $map[$index] = null;
            }
        }

        // A separate first and last name column is better than a combined one.
        if (isset($taken['first_name'], $taken['last_name'])) {
            $map = array_map(fn ($field) => $field === self::FULL_NAME ? null : $field, $map);
        }

        return $map;
    }

    /**
     * A signature of a file's headers, so a saved mapping can be found again for the same spreadsheet layout.
     *
     * @param  array<int, string>  $headers
     */
    public static function signature(array $headers): string
    {
        return hash('sha256', implode('|', array_map([self::class, 'normalise'], $headers)));
    }

    /**
     * The roster export row for a member, in EXPORT_COLUMNS order.
     *
     * @return array<int, mixed>
     */
    public static function exportRow(Member $member): array
    {
        return array_map(fn (string $field) => match ($field) {
            'current_office' => $member->current_office?->label(),
            'membership_status' => $member->membership_status?->label(),
            default => self::valueOf($member, $field),
        }, array_values(self::EXPORT_COLUMNS));
    }

    /**
     * A member's value for a field as plain text: dates as Y-m-d, statuses and offices as their stored value.
     */
    public static function valueOf(Member $member, string $field): ?string
    {
        $value = $member->{$field};

        return match (true) {
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            $value instanceof \BackedEnum => (string) $value->value,
            $value === null => null,
            default => (string) $value,
        };
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, string|null>
     */
    public static function snapshot(Member $member, array $fields): array
    {
        $values = [];

        foreach ($fields as $field) {
            $values[$field] = self::valueOf($member, $field);
        }

        return $values;
    }
}
