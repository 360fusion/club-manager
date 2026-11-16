<?php

namespace Launchpad\Admin\Resources\ContactMessages;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Launchpad\Admin\AdminLog;
use Launchpad\Admin\StaffAccess;
use Launchpad\Models\ContactMessage;

/** The messages from the contact form. Answer them by email (Reply goes to the sender); mark them handled here. */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $slug = 'contact-messages';

    protected static ?string $modelLabel = 'message';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-inbox';
    }

    public static function canViewAny(): bool
    {
        return StaffAccess::allowed('manage-messages');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Website';
    }

    public static function getNavigationBadge(): ?string
    {
        $new = ContactMessage::where('status', 'new')->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextEntry::make('reference'),
                TextEntry::make('created_at')->label('Sent')->dateTime('j M Y H:i'),
                TextEntry::make('name'),
                TextEntry::make('email')->copyable(),
                TextEntry::make('topic')->placeholder('none'),
                TextEntry::make('status')->badge(),
            ])->columns(3),
            Section::make('Message')->schema([TextEntry::make('message')->hiddenLabel()->prose()]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Sent')->dateTime('j M Y H:i')->sortable(),
                TextColumn::make('reference')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('topic')->placeholder(''),
                TextColumn::make('status')->badge()->color(fn (string $state): string => $state === 'new' ? 'warning' : 'success'),
            ])
            ->filters([SelectFilter::make('status')->options(['new' => 'New', 'handled' => 'Handled'])->default('new')])
            ->recordActions([
                ViewAction::make(),
                Action::make('handled')->label('Mark handled')->visible(fn (ContactMessage $record): bool => $record->status === 'new')
                    ->action(fn (ContactMessage $record) => self::setStatus($record, 'handled')),
                Action::make('reopen')->label('Reopen')->visible(fn (ContactMessage $record): bool => $record->status === 'handled')
                    ->action(fn (ContactMessage $record) => self::setStatus($record, 'new')),
            ]);
    }

    public static function setStatus(ContactMessage $message, string $status): void
    {
        $actor = AdminLog::actor();
        $message->update(['status' => $status, 'handled_by' => $status === 'handled' ? $actor?->getKey() : null, 'handled_at' => $status === 'handled' ? now() : null]);
        AdminLog::record('contact_message.updated', $message, "{$message->reference} marked {$status}", actor: $actor);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageContactMessages::route('/')];
    }
}
