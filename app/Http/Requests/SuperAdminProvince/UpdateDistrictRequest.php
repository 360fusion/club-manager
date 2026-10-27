<?php

namespace App\Http\Requests\SuperAdminProvince;

use App\Http\Requests\AppRequest;

/**
 * The form posted to SuperAdminProvinceController::updateDistrict().
 */
class UpdateDistrictRequest extends AppRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'grand_lodge_id' => 'nullable|exists:grand_lodges,id',
            'name' => 'required|string|max:150',
            'type' => 'required|in:district,group,dormant',
            'region' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'website_url' => 'nullable|url|max:255',
            'district_grand_master' => 'nullable|string|max:150',
            'district_grand_secretary' => 'nullable|string|max:150',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'town' => 'nullable|string|max:100',
            'county' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:30',
            'telephone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'twitter_url' => 'nullable|url|max:255',
            'facebook_url' => 'nullable|url|max:255',
            'description' => 'nullable|string|max:10000',

        ];
    }
}
