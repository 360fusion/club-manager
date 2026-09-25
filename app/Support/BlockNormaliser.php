<?php

namespace App\Support;

/**
 * Shapes the newer website-builder blocks when a page is saved: text is reduced to plain text and
 * length-capped, choice fields fall back to their default (the first allowed value), numbers are
 * clamped and item lists are capped. It never trusts what the editor sent, so a hand-made request
 * cannot store anything the renderer does not expect.
 *
 * Called from App\Casts\SanitizedHtmlBlocks after the URL-scheme and rich-text passes.
 */
class BlockNormaliser
{
    /**
     * @param  array<int|string, mixed>  $block
     * @return array<int|string, mixed>
     */
    public static function normalise(array $block): array
    {
        // Any block can be narrowed and positioned; a block that never set them is left as it is.
        if (array_key_exists('block_width', $block)) {
            $block['block_width'] = self::choice($block['block_width'], ['full', 'three_quarter', 'half']);
        }

        if (array_key_exists('block_align', $block)) {
            $block['block_align'] = self::choice($block['block_align'], ['center', 'left', 'right']);
        }

        // Any element can be switched off (kept on the page in the builder, left out of the public site).
        if (array_key_exists('hidden', $block)) {
            $block['hidden'] = self::flag($block['hidden'], false);
        }

        if (array_key_exists('section', $block)) {
            $block['section'] = self::section($block['section']);
        }

        return match ($block['type'] ?? null) {
            'youtube' => self::youtube($block),
            'cta_banner' => self::ctaBanner($block),
            'button' => self::linkBlock($block, 'url'),
            'hero' => self::hero($block),
            'feature_cards' => self::featureCards($block),
            'stats' => self::stats($block),
            'slideshow' => self::slideshow($block),
            'quote_motto' => self::quoteMotto($block),
            'section_heading' => self::sectionHeading($block),
            'faq' => self::faq($block),
            'map' => self::map($block),
            'downloads' => self::downloads($block),
            'calendar' => self::calendar($block),
            default => $block,
        };
    }

    /**
     * A YouTube block only keeps a link that parses to a real video id (stored canonically, with the id
     * beside it), plain-text name and description, whole-second times and known choice values.
     *
     * @param  array<int|string, mixed>  $block
     * @return array<int|string, mixed>
     */
    private static function youtube(array $block): array
    {
        $video = YouTubeUrl::parse(is_string($block['url'] ?? null) ? $block['url'] : '');

        $block['url'] = $video ? YouTubeUrl::watchUrl($video['id']) : '';
        $block['video_id'] = $video['id'] ?? '';

        $block['title'] = self::text($block['title'] ?? '', 200);
        $block['description'] = self::text($block['description'] ?? '', 2000);
        $block['button_label'] = self::text($block['button_label'] ?? '', 100);

        $block['layout'] = self::choice($block['layout'] ?? null, ['stacked', 'video_only', 'side_left', 'side_right']);
        $block['width'] = self::choice($block['width'] ?? null, ['standard', 'narrow', 'wide']);
        $block['aspect'] = self::choice($block['aspect'] ?? null, ['16:9', '4:3', '21:9', '1:1', '9:16']);
        $block['text_align'] = self::choice($block['text_align'] ?? null, ['left', 'center']);

        $start = self::seconds($block['start'] ?? null);
        $end = self::seconds($block['end'] ?? null);
        $block['start'] = $start;
        $block['end'] = $end !== null && $end > ($start ?? 0) ? $end : null;

        foreach (['loop', 'captions', 'button_enabled', 'button_new_tab'] as $flag) {
            $block[$flag] = self::flag($block[$flag] ?? null, false);
        }

        $block['show_youtube_link'] = self::flag($block['show_youtube_link'] ?? null, true);

        return $block;
    }

    /**
     * @param  array<int|string, mixed>  $block
     * @return array<int|string, mixed>
     */
    private static function ctaBanner(array $block): array
    {
        $block['eyebrow'] = self::text($block['eyebrow'] ?? '', 80);
        $block['heading'] = self::text($block['heading'] ?? '', 200);
        $block['text'] = self::text($block['text'] ?? '', 500);
        $block['button_label'] = self::text($block['button_label'] ?? '', 80);
        $block['button2_label'] = self::text($block['button2_label'] ?? '', 80);

        $block['style'] = self::choice($block['style'] ?? null, ['bold', 'soft', 'image', 'panel']);
        $block['overlay'] = self::choice($block['overlay'] ?? null, ['medium', 'light', 'strong']);
        $block['align'] = self::choice($block['align'] ?? null, ['center', 'left']);
        $block['size'] = self::choice($block['size'] ?? null, ['normal', 'compact', 'large']);

        $block['button_new_tab'] = self::flag($block['button_new_tab'] ?? null, false);
        $block['button2_new_tab'] = self::flag($block['button2_new_tab'] ?? null, false);

        $block['button_url'] = self::link($block['button_url'] ?? '');
        $block['button2_url'] = self::link($block['button2_url'] ?? '');

        // The "panel" style: a card with a photo (round by default) beside the text.
        $block['image_shape'] = self::choice($block['image_shape'] ?? null, ['circle', 'rounded', 'square']);
        $block['image_side'] = self::choice($block['image_side'] ?? null, ['left', 'right']);
        $block['text_style'] = self::choice($block['text_style'] ?? null, ['normal', 'italic']);

        return $block;
    }

    /**
     * The optional "Section" settings any block can carry: how its strip of the page looks (background, tone,
     * spacing). An empty or malformed value collapses to "auto", which leaves the theme's own look untouched.
     *
     * @return array<string, mixed>
     */
    public static function section(mixed $section): array
    {
        $section = is_array($section) ? $section : [];

        $bg = self::choice($section['bg'] ?? null, ['none', 'tint', 'primary', 'accent', 'custom', 'image']);
        $color = self::hexColour($section['bg_color'] ?? null);
        $image = self::link($section['bg_image'] ?? '');

        // A custom colour or image with nothing behind it would leave an invisible band.
        if (($bg === 'custom' && $color === '') || ($bg === 'image' && $image === '')) {
            $bg = 'none';
        }

        return [
            'mode' => self::choice($section['mode'] ?? null, ['auto', 'contained', 'band']),
            'bg' => $bg,
            'bg_color' => $color,
            'bg_image' => $image,
            'overlay' => self::choice($section['overlay'] ?? null, ['medium', 'light', 'strong']),
            'tone' => self::choice($section['tone'] ?? null, ['auto', 'light', 'dark']),
            'padding' => self::choice($section['padding'] ?? null, ['auto', 'none', 'sm', 'md', 'lg', 'xl']),
            'anchor' => mb_substr(trim((string) preg_replace('/[^a-z0-9-]+/', '-', strtolower(is_string($section['anchor'] ?? null) ? $section['anchor'] : '')), '-'), 0, 60),
        ];
    }

    /**
     * The Hero Banner. Older heroes have only a title, subtitle and one button, and keep the fixed "Official Club
     * Website" label unless the editor now hides or replaces it.
     *
     * @param  array<int|string, mixed>  $block
     * @return array<int|string, mixed>
     */
    private static function hero(array $block): array
    {
        $block = self::linkBlock($block, 'cta_link');

        if (array_key_exists('cta2_link', $block)) {
            $block['cta2_link'] = self::link($block['cta2_link']);
        }

        foreach (['eyebrow' => 80, 'cta2_text' => 80] as $key => $limit) {
            if (array_key_exists($key, $block)) {
                $block[$key] = self::text($block[$key], $limit);
            }
        }

        if (array_key_exists('hide_eyebrow', $block)) {
            $block['hide_eyebrow'] = self::flag($block['hide_eyebrow'], false);
        }

        foreach (['overlay' => ['medium', 'light', 'strong'], 'align' => ['auto', 'center', 'left'], 'height' => ['normal', 'compact', 'tall']] as $key => $allowed) {
            if (array_key_exists($key, $block)) {
                $block[$key] = self::choice($block[$key], $allowed);
            }
        }

        return $block;
    }

    /**
     * Icon cards: a heading over a grid of icon, title and short text. Used for "what we offer" and, on a dark
     * section, for a lodge's values.
     *
     * @param  array<int|string, mixed>  $block
     * @return array<int|string, mixed>
     */
    private static function featureCards(array $block): array
    {
        $block['eyebrow'] = self::text($block['eyebrow'] ?? '', 80);
        $block['heading'] = self::text($block['heading'] ?? '', 200);
        $block['intro'] = self::text($block['intro'] ?? '', 500);

        $block['columns'] = self::choice($block['columns'] ?? null, [3, 2, 4]);
        $block['card_style'] = self::choice($block['card_style'] ?? null, ['soft', 'outlined', 'plain']);
        $block['icon_style'] = self::choice($block['icon_style'] ?? null, ['plain', 'circle']);
        $block['align'] = self::choice($block['align'] ?? null, ['center', 'left']);

        $block['show_divider'] = self::flag($block['show_divider'] ?? null, true);
        $block['numbered'] = self::flag($block['numbered'] ?? null, false);

        $items = [];

        foreach (self::items($block['items'] ?? []) as $index => $item) {
            if (count($items) >= 12) {
                break;
            }

            $title = self::text($item['title'] ?? '', 120);
            $text = self::text($item['text'] ?? '', 500);
            $icon = BlockIcons::clean($item['icon'] ?? null);

            if ($title === '' && $text === '' && $icon === '') {
                continue;
            }

            $items[] = [
                'id' => self::id($item['id'] ?? null, 'card-'.$index),
                'icon' => $icon,
                'title' => $title,
                'text' => $text,
                'link' => self::link($item['link'] ?? ''),
                'link_label' => self::text($item['link_label'] ?? '', 60),
            ];
        }

        $block['items'] = $items;

        return $block;
    }

    /**
     * A row of headline figures ("150+ years of history"). The number is kept as text so "1,200" or "£1m" work;
     * only a plain whole number is animated by the renderer.
     *
     * @param  array<int|string, mixed>  $block
     * @return array<int|string, mixed>
     */
    private static function stats(array $block): array
    {
        $block['heading'] = self::text($block['heading'] ?? '', 200);
        $block['icon_style'] = self::choice($block['icon_style'] ?? null, ['circle', 'plain']);
        $block['count_up'] = self::flag($block['count_up'] ?? null, true);

        $items = [];

        foreach (self::items($block['items'] ?? []) as $index => $item) {
            if (count($items) >= 8) {
                break;
            }

            $number = self::text($item['number'] ?? '', 20);
            $label = self::text($item['label'] ?? '', 80);

            if ($number === '' && $label === '') {
                continue;
            }

            $items[] = [
                'id' => self::id($item['id'] ?? null, 'stat-'.$index),
                'icon' => BlockIcons::clean($item['icon'] ?? null),
                'number' => $number,
                'suffix' => self::text($item['suffix'] ?? '', 8),
                'label' => $label,
            ];
        }

        $block['items'] = $items;

        return $block;
    }

    /**
     * A photo slideshow, optionally with a heading, text and buttons laid over the photos. Only slides with a
     * photo are kept (the photo address was already checked for a safe scheme), the timing is held to a sensible
     * range and every choice falls back to its default.
     *
     * @param  array<int|string, mixed>  $block
     * @return array<int|string, mixed>
     */
    private static function slideshow(array $block): array
    {
        $block['eyebrow'] = self::text($block['eyebrow'] ?? '', 80);
        $block['heading'] = self::text($block['heading'] ?? '', 200);
        $block['text'] = self::text($block['text'] ?? '', 500);
        $block['button_label'] = self::text($block['button_label'] ?? '', 80);
        $block['button2_label'] = self::text($block['button2_label'] ?? '', 80);
        $block['button_url'] = self::link($block['button_url'] ?? '');
        $block['button2_url'] = self::link($block['button2_url'] ?? '');

        $block['height'] = self::choice($block['height'] ?? null, ['normal', 'compact', 'tall', 'screen']);
        $block['overlay'] = self::choice($block['overlay'] ?? null, ['medium', 'none', 'light', 'strong']);
        $block['align'] = self::choice($block['align'] ?? null, ['left', 'center']);
        $block['effect'] = self::choice($block['effect'] ?? null, ['fade', 'zoom']);

        $interval = self::number($block['interval'] ?? null, 2, 30);
        $block['interval'] = $interval === null ? 5 : (int) round($interval);

        $block['autoplay'] = self::flag($block['autoplay'] ?? null, true);
        $block['show_dots'] = self::flag($block['show_dots'] ?? null, true);
        $block['show_arrows'] = self::flag($block['show_arrows'] ?? null, true);
        $block['pause_on_hover'] = self::flag($block['pause_on_hover'] ?? null, true);
        $block['full_width'] = self::flag($block['full_width'] ?? null, false);

        $slides = [];

        foreach (self::items($block['slides'] ?? []) as $index => $slide) {
            if (count($slides) >= 12) {
                break;
            }

            $image = is_string($slide['image_url'] ?? null) ? trim($slide['image_url']) : '';

            if ($image === '') {
                continue;
            }

            $slides[] = [
                'id' => self::id($slide['id'] ?? null, 'slide-'.$index),
                'image_url' => $image,
                'alt' => self::text($slide['alt'] ?? '', 200),
                'caption' => self::text($slide['caption'] ?? '', 200),
            ];
        }

        $block['slides'] = $slides;

        return $block;
    }

    /**
     * A motto or closing statement: a large serif heading, a short divider, a paragraph and an italic tagline.
     *
     * @param  array<int|string, mixed>  $block
     * @return array<int|string, mixed>
     */
    private static function quoteMotto(array $block): array
    {
        $block['heading'] = self::text($block['heading'] ?? '', 200);
        $block['text'] = self::text($block['text'] ?? '', 1500);
        $block['tagline'] = self::text($block['tagline'] ?? '', 200);
        $block['show_divider'] = self::flag($block['show_divider'] ?? null, true);

        return $block;
    }

    /**
     * A standalone section title: small label, serif heading, divider and a short introduction.
     *
     * @param  array<int|string, mixed>  $block
     * @return array<int|string, mixed>
     */
    private static function sectionHeading(array $block): array
    {
        $block['eyebrow'] = self::text($block['eyebrow'] ?? '', 80);
        $block['title'] = self::text($block['title'] ?? '', 200);
        $block['intro'] = self::text($block['intro'] ?? '', 500);
        $block['align'] = self::choice($block['align'] ?? null, ['center', 'left']);
        $block['show_divider'] = self::flag($block['show_divider'] ?? null, true);

        return $block;
    }

    /**
     * A #RRGGBB colour, or an empty string.
     */
    public static function hexColour(mixed $value): string
    {
        return is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', trim($value)) === 1 ? strtolower(trim($value)) : '';
    }

    /**
     * A block whose one link is a button (the Button Link element, the Hero Banner's button): the link is completed
     * like the banner's, so a bare "example.com" opens the other site.
     *
     * @param  array<int|string, mixed>  $block
     * @return array<int|string, mixed>
     */
    private static function linkBlock(array $block, string $key): array
    {
        if (array_key_exists($key, $block)) {
            $block[$key] = self::link($block[$key]);
        }

        return $block;
    }

    /**
     * A link that will open properly: a page on this site (/...), an anchor (#...), mailto:, tel: or a web address.
     * Anything else that is written like a web address ("example.com/page") gets https:// put in front, because
     * without it the browser would treat it as a page on this site and the link would not open.
     */
    public static function link(mixed $value): string
    {
        $url = trim(is_string($value) ? $value : '');

        // Already a usable start, or another scheme (javascript:, data:...) which is left for the validators to refuse.
        // A port ("example.org:8080") is not a scheme, so that is still completed.
        if ($url === '' || preg_match('#^(https?://|mailto:|tel:|/|\#)#i', $url) || preg_match('#^[a-z][a-z0-9+.\-]*:(?!\d)#i', $url)) {
            return $url;
        }

        return 'https://'.$url;
    }

    /**
     * @param  array<int|string, mixed>  $block
     * @return array<int|string, mixed>
     */
    private static function faq(array $block): array
    {
        $block['heading'] = self::text($block['heading'] ?? '', 200);
        $block['intro'] = self::text($block['intro'] ?? '', 500);

        $block['behaviour'] = self::choice($block['behaviour'] ?? null, ['single', 'multiple', 'expanded']);
        $block['columns'] = self::choice($block['columns'] ?? null, [1, 2]);

        $block['first_open'] = self::flag($block['first_open'] ?? null, false);
        $block['numbered'] = self::flag($block['numbered'] ?? null, false);
        $block['show_search'] = self::flag($block['show_search'] ?? null, true);

        $items = [];

        foreach (self::items($block['items'] ?? []) as $index => $item) {
            if (count($items) >= 60) {
                break;
            }

            $question = self::text($item['question'] ?? '', 300);
            $answer = is_string($item['answer'] ?? null) ? $item['answer'] : '';

            if ($question === '' && trim(strip_tags($answer)) === '') {
                continue;
            }

            $items[] = ['id' => self::id($item['id'] ?? null, 'faq-'.$index), 'question' => $question, 'answer' => $answer];
        }

        $block['items'] = $items;

        return $block;
    }

    /**
     * @param  array<int|string, mixed>  $block
     * @return array<int|string, mixed>
     */
    private static function map(array $block): array
    {
        $block['heading'] = self::text($block['heading'] ?? '', 200);
        $block['location_name'] = self::text($block['location_name'] ?? '', 200);
        $block['address'] = self::text($block['address'] ?? '', 500);
        $block['notes'] = self::text($block['notes'] ?? '', 1000);

        $block['lat'] = self::number($block['lat'] ?? null, -90, 90);
        $block['lng'] = self::number($block['lng'] ?? null, -180, 180);
        $block['map_style'] = self::choice($block['map_style'] ?? null, ['street', 'satellite', 'hybrid']);
        // Satellite imagery is only available to zoom 18.
        $block['zoom'] = min((int) (self::number($block['zoom'] ?? null, 3, 19) ?? 16), $block['map_style'] === 'street' ? 19 : 18);

        $block['height'] = self::choice($block['height'] ?? null, ['medium', 'small', 'large']);
        $block['layout'] = self::choice($block['layout'] ?? null, ['stacked', 'map_only', 'side_left', 'side_right']);
        $block['load_mode'] = self::choice($block['load_mode'] ?? null, ['click', 'immediate']);

        $block['allow_style_switch'] = self::flag($block['allow_style_switch'] ?? null, true);
        $block['show_directions'] = self::flag($block['show_directions'] ?? null, true);
        $block['show_larger_link'] = self::flag($block['show_larger_link'] ?? null, true);

        return $block;
    }

    /**
     * Uploaded items keep only the id of the private file (never a storage URL); link items keep their
     * (already scheme-checked) URL. An item with neither is dropped.
     *
     * @param  array<int|string, mixed>  $block
     * @return array<int|string, mixed>
     */
    private static function downloads(array $block): array
    {
        $block['heading'] = self::text($block['heading'] ?? '', 200);
        $block['intro'] = self::text($block['intro'] ?? '', 500);
        $block['button_label'] = self::text($block['button_label'] ?? '', 60) ?: 'Download';

        $block['layout'] = self::choice($block['layout'] ?? null, ['list', 'cards']);
        $block['sort'] = self::choice($block['sort'] ?? null, ['manual', 'newest', 'name']);
        $block['open_in'] = self::choice($block['open_in'] ?? null, ['new_tab', 'download']);

        $block['show_type'] = self::flag($block['show_type'] ?? null, true);
        $block['show_size'] = self::flag($block['show_size'] ?? null, true);
        $block['show_date'] = self::flag($block['show_date'] ?? null, false);
        $block['show_search'] = self::flag($block['show_search'] ?? null, false);
        $block['members_only'] = self::flag($block['members_only'] ?? null, false);

        $items = [];

        foreach (self::items($block['items'] ?? []) as $index => $item) {
            if (count($items) >= 100) {
                break;
            }

            $isUpload = ($item['source'] ?? null) === 'upload';
            $mediaId = $isUpload && is_numeric($item['media_id'] ?? null) ? (int) $item['media_id'] : 0;
            $url = $isUpload || ! is_string($item['url'] ?? null) ? '' : trim($item['url']);

            if (($isUpload && $mediaId < 1) || (! $isUpload && $url === '')) {
                continue;
            }

            $addedAt = is_string($item['added_at'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}/', $item['added_at']) === 1
                ? substr($item['added_at'], 0, 10)
                : '';

            $items[] = [
                'id' => self::id($item['id'] ?? null, 'file-'.$index),
                'source' => $isUpload ? 'upload' : 'link',
                'title' => self::text($item['title'] ?? '', 200),
                'description' => self::text($item['description'] ?? '', 500),
                'group' => self::text($item['group'] ?? '', 100),
                'media_id' => $mediaId ?: null,
                'url' => $url,
                'file_name' => self::text($item['file_name'] ?? '', 255),
                'mime_type' => self::text($item['mime_type'] ?? '', 100),
                'size' => max(0, (int) ($item['size'] ?? 0)),
                'added_at' => $addedAt,
            ];
        }

        $block['items'] = $items;

        return $block;
    }

    /**
     * @param  array<int|string, mixed>  $block
     * @return array<int|string, mixed>
     */
    private static function calendar(array $block): array
    {
        $block['heading'] = self::text($block['heading'] ?? '', 200);

        $block['default_view'] = self::choice($block['default_view'] ?? null, ['month', 'list']);
        $block['week_starts'] = self::choice($block['week_starts'] ?? null, ['monday', 'sunday']);
        $block['list_length'] = self::choice($block['list_length'] ?? null, [10, 5, 20]);

        $block['allow_switch'] = self::flag($block['allow_switch'] ?? null, true);
        $block['show_times'] = self::flag($block['show_times'] ?? null, true);
        $block['show_location'] = self::flag($block['show_location'] ?? null, true);
        $block['show_price'] = self::flag($block['show_price'] ?? null, false);
        $block['show_subscribe'] = self::flag($block['show_subscribe'] ?? null, true);

        return $block;
    }

    public static function text(mixed $value, int $limit): string
    {
        return is_string($value) ? mb_substr(trim(strip_tags($value)), 0, $limit) : '';
    }

    /**
     * The value if it is one of the allowed choices, otherwise the first (the default).
     *
     * @param  list<int|string>  $allowed
     */
    public static function choice(mixed $value, array $allowed): int|string
    {
        if (is_int($allowed[0]) && is_numeric($value)) {
            $value = (int) $value;
        }

        return in_array($value, $allowed, true) ? $value : $allowed[0];
    }

    public static function flag(mixed $value, bool $default): bool
    {
        return $value === null ? $default : (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default);
    }

    public static function number(mixed $value, float $min, float $max): ?float
    {
        if (! is_numeric($value) || $value < $min || $value > $max) {
            return null;
        }

        return (float) $value;
    }

    private static function seconds(mixed $value): ?int
    {
        return is_numeric($value) && $value >= 0 ? min((int) $value, 43200) : null;
    }

    private static function id(mixed $value, string $fallback): string
    {
        $id = is_string($value) ? preg_replace('/[^A-Za-z0-9_-]/', '', $value) : '';

        return $id !== '' ? mb_substr($id, 0, 64) : $fallback;
    }

    /**
     * The array items of a list field, with anything that is not itself an array dropped. Bounded so a
     * hand-made request cannot make the loop over them unreasonably long; each normaliser applies its own cap.
     *
     * @return list<array<int|string, mixed>>
     */
    private static function items(mixed $items): array
    {
        return is_array($items) ? array_slice(array_values(array_filter($items, 'is_array')), 0, 1000) : [];
    }
}
