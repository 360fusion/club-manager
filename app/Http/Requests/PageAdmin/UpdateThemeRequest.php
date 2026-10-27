<?php

namespace App\Http\Requests\PageAdmin;

use App\Http\Requests\ClubRequest;
use App\Support\SiteThemes;
use Closure;
use Illuminate\Validation\Rule;

/**
 * The form posted to PageThemeController::updateTheme().
 */
class UpdateThemeRequest extends ClubRequest
{
    /**
     * @var list<string>
     */
    public const FONT_PAIRINGS = ['theme', 'georgia', 'clean', 'palatino', 'lodge'];

    /**
     * @var list<string>
     */
    public const CORNER_STYLES = ['theme', 'square', 'soft', 'round'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $club = $this->club();

        return [
            'website_theme' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($club) {
                if (! is_string($value) || ! SiteThemes::isValidFor($value, $club)) {
                    $fail('Choose one of the available themes.');
                }
            }],
            'font_pairing' => ['nullable', Rule::in(self::FONT_PAIRINGS)],
            'corner_style' => ['nullable', Rule::in(self::CORNER_STYLES)],
        ];
    }
}
