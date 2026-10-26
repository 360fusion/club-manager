<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * The Blade components that Livewire views share (resources/views/components) render their states.
 */
class BladeKitTest extends TestCase
{
    public function test_input_shows_the_validation_error_for_its_wire_model_field(): void
    {
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['form.email' => 'That address is already in use.'])));

        $html = Blade::render('<x-input wire:model="form.email" label="Email" hint="Never shown to members." />');

        $this->assertStringContainsString('for="field-form-email"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('That address is already in use.', $html);
        $this->assertStringNotContainsString('Never shown to members.', $html);
    }

    public function test_input_without_an_error_shows_its_hint(): void
    {
        view()->share('errors', new ViewErrorBag);

        $html = Blade::render('<x-textarea wire:model="notes" label="Notes" hint="Only officers see these." />');

        $this->assertStringContainsString('aria-describedby="field-notes-hint"', $html);
        $this->assertStringContainsString('Only officers see these.', $html);
        $this->assertStringNotContainsString('aria-invalid', $html);
    }

    public function test_modal_renders_only_while_shown_and_closes_through_the_named_method(): void
    {
        $this->assertSame('', trim(Blade::render('<x-modal :show="false" title="Add a visitor" close="closeVisitor">Body</x-modal>')));

        $html = Blade::render('<x-modal :show="true" title="Add a visitor" close="closeVisitor">Body</x-modal>');

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-labelledby="modal-add-a-visitor"', $html);
        $this->assertStringContainsString('x-trap.noscroll="true"', $html);
        $this->assertStringContainsString('$wire.closeVisitor()', $html);
        $this->assertStringContainsString('wire:click="closeVisitor"', $html);
    }

    public function test_tabs_mark_only_the_active_item_as_the_current_page(): void
    {
        $html = Blade::render('<x-tabs active="dues" :items="$items" />', ['items' => [
            ['key' => 'roster', 'label' => 'Roster', 'href' => '/roster'],
            ['key' => 'dues', 'label' => 'Dues', 'href' => '/dues'],
        ]]);

        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertMatchesRegularExpression('/href="\/dues"\s+aria-current="page"/', $html);
    }

    public function test_button_with_an_href_is_a_link(): void
    {
        $this->assertStringContainsString('<a href="/export"', Blade::render('<x-button href="/export" variant="secondary">Export</x-button>'));
        $this->assertStringContainsString('<button type="submit"', Blade::render('<x-button type="submit" wire:click="save">Save</x-button>'));
    }
}
