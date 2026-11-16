<?php

namespace Launchpad\Admin\Pages;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Notification as Mail;
use Illuminate\Validation\ValidationException;
use Launchpad\Admin\AdminLog;
use Launchpad\Mail\Renderer;
use Launchpad\Mail\Templates;
use Launchpad\Models\EmailTemplate;
use Launchpad\Notifications\EmailTemplateSample;
use Launchpad\Support\Locales;
use Livewire\Attributes\Url;

/**
 * Every mail the app sends, with the wording it has now (the default until staff edit it). An edit is kept only if it still has the
 * variables the mail cannot work without (a link to click); it can be tried as a preview with sample values or sent to the staff member.
 */
class EmailTemplates extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'email-templates';

    protected static ?string $title = 'Email templates';

    protected string $view = 'launchpad::admin.email-templates';

    #[Url(as: 'template')]
    public ?string $key = null;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** The language being edited */
    public string $locale = '';

    public ?string $previewSubject = null;

    public ?string $previewHtml = null;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-envelope';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Website';
    }

    public function mount(): void
    {
        $this->locale = Locales::default();   // the language mail is sent in when nobody has one, even if it is not in the list
        $this->open($this->key !== null && isset(Templates::all()[$this->key]) ? $this->key : array_key_first(Templates::all()));
    }

    public function open(string $key): void
    {
        abort_unless(isset(Templates::all()[$key]), 404);

        $this->key = $key;
        $this->previewSubject = $this->previewHtml = null;
        $this->resetErrorBag();
        $this->form->fill(Templates::resolve($key, $this->locale));
    }

    public function updatedLocale(): void
    {
        abort_unless(isset(Locales::supported()[$this->locale]), 404);

        $this->open($this->key);
    }

    /** @return array<string, string> the languages to choose from, empty when the site has only one */
    public function languages(): array
    {
        return count(Locales::supported()) > 1 ? Locales::supported() : [];
    }

    /** @return list<array{key: string, label: string, edited: bool}> */
    public function rows(): array
    {
        $edited = EmailTemplate::where('locale', $this->locale)->pluck('key')->all();

        return collect(Templates::all())->map(fn ($t, $key) => ['key' => $key, 'label' => $t['label'], 'edited' => in_array($key, $edited, true)])->values()->all();
    }

    /** @return array<string, mixed> */
    public function template(): array
    {
        return Templates::get($this->key);
    }

    public function form(Schema $schema): Schema
    {
        $template = Templates::get($this->key ?? array_key_first(Templates::all()));
        $hint = collect($template['variables'])->map(fn ($what, $name) => '{'.'{'.$name.'}'.'} '.$what.(in_array($name, $template['required'], true) ? ' (required)' : ''))->implode('; ');

        return $schema->components([
            TextInput::make('subject')->required()->maxLength(255)->helperText('Variables: '.$hint.'; {'.'{site}} the site name.'),
            Textarea::make('body')->label('Body (Markdown)')->required()->rows(14)->helperText('A line with only a link, such as [Label]({'.'{url}}), becomes the button. Raw HTML is removed.'),
        ])->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        try {
            Templates::save($this->key, $state['subject'], $state['body'], null, $this->locale);
        } catch (ValidationException $e) {
            $this->addError('data.body', implode(' ', $e->errors()['body']));
            Notification::make()->danger()->title('Not saved')->body(implode("\n", $e->errors()['body']))->send();

            return;
        }

        Notification::make()->success()->title('Saved')->send();
    }

    public function resetToDefault(): void
    {
        Templates::reset($this->key, $this->locale);
        $this->open($this->key);
        Notification::make()->success()->title('Back to the default wording')->send();
    }

    public function preview(): void
    {
        $message = Renderer::message($this->key, $this->template()['sample'], $this->wording());
        $this->previewSubject = $message->subject;
        $this->previewHtml = (string) $message->render();
    }

    public function sendTest(): void
    {
        $to = AdminLog::actor()?->email;
        abort_if($to === null, 403);

        Mail::route('mail', $to)->notifyNow(new EmailTemplateSample($this->key, $this->wording()));
        Notification::make()->success()->title("Test sent to {$to}")->send();
    }

    /** @return array{subject: string, body: string} what is in the form now, saved or not */
    private function wording(): array
    {
        return ['subject' => (string) ($this->data['subject'] ?? ''), 'body' => (string) ($this->data['body'] ?? '')];
    }
}
