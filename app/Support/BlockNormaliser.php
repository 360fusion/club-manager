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

        return match ($block['type'] ?? null) {
            'youtube' => self::youtube($block),
            'cta_banner' => self::ctaBanner($block),
            'button' => self::linkBlock($block, 'url'),
            'hero' => self::linkBlock($block, 'cta_link'),
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

        $block['style'] = self::choice($block['style'] ?? null, ['bold', 'soft', 'image']);
        $block['overlay'] = self::choice($block['overlay'] ?? null, ['medium', 'light', 'strong']);
        $block['align'] = self::choice($block['align'] ?? null, ['center', 'left']);
        $block['size'] = self::choice($block['size'] ?? null, ['normal', 'compact', 'large']);

        $block['button_new_tab'] = self::flag($block['button_new_tab'] ?? null, false);
        $block['button2_new_tab'] = self::flag($block['button2_new_tab'] ?? null, false);

        $block['button_url'] = self::link($block['button_url'] ?? '');
        $block['button2_url'] = self::link($block['button2_url'] ?? '');

        return $block;
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
