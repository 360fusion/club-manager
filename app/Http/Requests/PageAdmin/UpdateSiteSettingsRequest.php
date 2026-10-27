<?php

namespace App\Http\Requests\PageAdmin;

use App\Http\Requests\ClubRequest;
use App\Services\PagePublisher;
use App\Support\ClubDomain;
use App\Support\SiteTracking;
use App\Support\UploadRules;
use Closure;
use Illuminate\Validation\Rule;

/**
 * The form posted to PageSiteSettingsController::updateSettings().
 */
class UpdateSiteSettingsRequest extends ClubRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $club = $this->club();

        return [
            'seo_title_suffix' => 'nullable|string|max:255',
            'seo_meta_description' => 'nullable|string|max:1000',
            'custom_domain' => ClubDomain::rule($club),
            'site_redirect_to_domain' => 'boolean',
            'primary_color' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'share_image_url' => ['nullable', 'string', 'max:2048', UploadRules::IMAGE_URL],
            'site_icon_url' => ['nullable', 'string', 'max:2048', UploadRules::IMAGE_URL],
            'noindex_site' => 'boolean',
            'analytics_provider' => ['nullable', Rule::in(SiteTracking::PROVIDERS)],
            'analytics_id' => [
                'nullable', 'string', 'max:100', 'required_if:analytics_provider,google,plausible',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $provider = $this->input('analytics_provider');

                    if ($provider === 'google' && ! preg_match(SiteTracking::GOOGLE_ID, (string) $value)) {
                        $fail('Enter a Google Analytics measurement ID, which looks like G-ABC123XYZ.');
                    }

                    if ($provider === 'plausible' && ! preg_match(SiteTracking::PLAUSIBLE_DOMAIN, (string) $value)) {
                        $fail('Enter the domain your Plausible site is registered under, such as lodge.org.uk.');
                    }
                },
            ],
            'cookie_banner_enabled' => 'boolean',
            'cookie_banner_text' => 'nullable|string|max:500',
            'cookie_banner_link_label' => 'nullable|string|max:60',
            'cookie_banner_link_url' => ['nullable', 'string', 'max:500', 'regex:#^(https?://|/)#i'],
            'revisions_keep' => ['nullable', 'integer', 'min:'.PagePublisher::MIN_KEEP, 'max:'.PagePublisher::MAX_KEEP],
            'revisions_max_age_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'not_found_page_id' => ['nullable', 'integer', Rule::exists('pages', 'id')->where('club_id', $club->id)->whereNull('deleted_at')],
        ];
    }
}
