<?php

namespace App\Http\Controllers;

use App\Http\Requests\PageAdmin\SaveColourSchemeRequest;
use App\Http\Requests\PageAdmin\UpdateThemeRequest;
use App\Support\SiteThemes;
use Illuminate\Validation\ValidationException;

/**
 * Saving the website theme and a club's own colour schemes. Split out of PageAdminController; the URLs, route names and permissions are unchanged.
 */
class PageThemeController extends Controller
{
    /**
     * Update active website theme layout.
     */
    public function updateTheme(UpdateThemeRequest $request, string $clubSlug)
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        $existingSettings = $club->settings ?? [];
        $existingSettings['website_theme'] = $validated['website_theme'];

        foreach (['font_pairing', 'corner_style'] as $key) {
            if (array_key_exists($key, $validated)) {
                $existingSettings[$key] = $validated[$key] ?: 'theme';
            }
        }
        $club->settings = $existingSettings;
        $club->save();

        return redirect()->back()->with('success', 'Website theme updated successfully.');
    }

    /**
     * Create or update one of the lodge's own named colour schemes. The browser proposes the id of a new scheme;
     * it is only accepted in the expected shape, and a scheme that already has that id is updated in place.
     */
    public function saveColourScheme(SaveColourSchemeRequest $request, string $clubSlug)
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        $schemes = SiteThemes::customSchemes($club);
        $existing = collect($schemes)->search(fn (array $scheme) => $scheme['id'] === $validated['id']);

        if ($existing === false && count($schemes) >= SiteThemes::MAX_CUSTOM_SCHEMES) {
            throw ValidationException::withMessages(['name' => 'You can keep up to '.SiteThemes::MAX_CUSTOM_SCHEMES.' colour schemes. Delete one to make room.']);
        }

        $name = trim(strip_tags($validated['name']));

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Give the colour scheme a name.']);
        }

        $scheme = ['id' => $validated['id'], 'name' => $name, 'primary' => strtolower($validated['primary']), 'accent' => strtolower($validated['accent'])];

        if ($existing === false) {
            $schemes[] = $scheme;
        } else {
            $schemes[$existing] = $scheme;
        }

        $settings = $club->settings ?? [];
        $settings['custom_color_schemes'] = array_values($schemes);
        $club->settings = $settings;
        $club->save();

        return redirect()->back()->with('success', 'Colour scheme saved.');
    }

    /**
     * Remove one of the lodge's colour schemes, unless the live site is using it.
     */
    public function deleteColourScheme(string $clubSlug, string $schemeId)
    {
        $club = $this->findClub($clubSlug);

        if (str_ends_with((string) ($club->settings['website_theme'] ?? ''), ':'.$schemeId)) {
            throw ValidationException::withMessages(['scheme' => 'The live site is using this colour scheme. Apply a different one first, then delete it.']);
        }

        $settings = $club->settings ?? [];
        $settings['custom_color_schemes'] = array_values(array_filter(SiteThemes::customSchemes($club), fn (array $scheme) => $scheme['id'] !== $schemeId));
        $club->settings = $settings;
        $club->save();

        return redirect()->back()->with('success', 'Colour scheme deleted.');
    }
}
