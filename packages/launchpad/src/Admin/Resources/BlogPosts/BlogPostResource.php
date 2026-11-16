<?php

namespace Launchpad\Admin\Resources\BlogPosts;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Launchpad\Admin\StaffAccess;
use Launchpad\Models\BlogPost;

/** The website's blog: a post shows once its publish time has come; with none it is a draft */
class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static ?string $slug = 'blog';

    protected static ?string $modelLabel = 'blog post';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-newspaper';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Website';
    }

    public static function canViewAny(): bool
    {
        return config('launchpad.modules.blog') && StaffAccess::allowed('manage-site');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(200)->live(onBlur: true)
                ->afterStateUpdated(fn (Get $get, Set $set, ?string $state) => filled($get('slug')) ?: $set('slug', Str::slug((string) $state))),
            TextInput::make('slug')->label('Address')->prefix('/blog/')->required()->maxLength(120)->regex('/^[a-z0-9]+(-[a-z0-9]+)*$/')
                ->unique(ignoreRecord: true),
            Textarea::make('summary')->maxLength(300)->helperText('Shown in the list, the feed and to search engines.')->columnSpanFull(),
            // pictures go where the website can show them (needs `php artisan storage:link`, which the doctor checks)
            MarkdownEditor::make('body')->required()->helperText('Markdown. Raw HTML is removed. Pictures: PNG, JPEG, GIF or WebP.')->columnSpanFull()
                ->fileAttachmentsDisk('public')->fileAttachmentsDirectory('blog'),
            DateTimePicker::make('published_at')->label('Publish at')->helperText('Blank keeps it a draft. A time in the future publishes it then.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('published_at')->label('Published')->dateTime('j M Y H:i')->placeholder('Draft')->sortable(),
                TextColumn::make('author.name')->label('By'),
            ])
            ->recordActions([
                Action::make('view')->label('View')->url(fn (BlogPost $record) => route('blog.show', $record->slug), shouldOpenInNewTab: true)
                    ->visible(fn (BlogPost $record) => $record->published_at?->isPast() ?? false),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageBlogPosts::route('/')];
    }
}
