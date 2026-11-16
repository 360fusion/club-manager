<?php

namespace Launchpad\Admin\Resources\Announcements;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Launchpad\Admin\StaffAccess;
use Launchpad\Billing\PlanCatalog;
use Launchpad\Models\Announcement;

/** Notices for people inside the app: shown at the top of their account pages while live, to the audience chosen. Every change is in the admin log. */
class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static ?string $slug = 'announcements';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-megaphone';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Website';
    }

    public static function canViewAny(): bool
    {
        return StaffAccess::allowed('manage-site');
    }

    public static function form(Schema $schema): Schema
    {
        $plans = collect(app(PlanCatalog::class)->all())->mapWithKeys(fn ($plan) => [$plan->key => $plan->name])->all();

        return $schema->components([
            TextInput::make('title')->required()->maxLength(160)->columnSpanFull(),
            // a banner has no pictures: the attach button would save them where the site cannot show them
            MarkdownEditor::make('body')->required()->helperText('Markdown. Raw HTML is removed.')->columnSpanFull()->disableToolbarButtons(['attachFiles']),
            Select::make('level')->options(['info' => 'Information', 'warning' => 'Warning'])->default('info')->required()->selectablePlaceholder(false),
            Select::make('audience')->options(['everyone' => 'Everyone', 'plans' => 'Teams on some plans', 'admins' => 'Team owners and admins'])->default('everyone')->required()->selectablePlaceholder(false)->live(),
            Select::make('plans')->multiple()->options($plans)->visible(fn (Get $get) => $get('audience') === 'plans')->required(fn (Get $get) => $get('audience') === 'plans'),
            Toggle::make('dismissible')->label('People can hide it')->default(true),
            DateTimePicker::make('starts_at')->label('Shows from')->helperText('Blank for now.'),
            DateTimePicker::make('ends_at')->label('Shows until')->helperText('Blank for until removed.')->after('starts_at'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('audience')->badge(),
                TextColumn::make('level')->badge()->color(fn (string $state): string => $state === 'warning' ? 'warning' : 'gray'),
                IconColumn::make('dismissible')->boolean(),
                TextColumn::make('starts_at')->label('From')->dateTime('j M Y H:i')->placeholder('now'),
                TextColumn::make('ends_at')->label('Until')->dateTime('j M Y H:i')->placeholder('removed'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAnnouncements::route('/')];
    }
}
