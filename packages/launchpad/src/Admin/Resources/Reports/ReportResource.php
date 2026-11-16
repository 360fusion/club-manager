<?php

namespace Launchpad\Admin\Resources\Reports;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
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
use Launchpad\Models\Report;
use Launchpad\Moderation\Reports;

/** What people reported, open ones first. Staff mark each acted on (they dealt with the content in the app) or dismissed, with a note. */
class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static ?string $slug = 'reports';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-flag';
    }

    public static function canViewAny(): bool
    {
        return config('launchpad.modules.moderation') && StaffAccess::allowed('moderate');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Website';
    }

    public static function getNavigationBadge(): ?string
    {
        $open = Report::where('status', 'open')->count();

        return $open > 0 ? (string) $open : null;
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

    private static function subjectLabel(Report $record): string
    {
        return $record->subject()?->reportLabel() ?? "{$record->subject_type} #{$record->subject_id} (gone)";
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextEntry::make('subject')->label('Reported')->getStateUsing(fn (Report $record) => self::subjectLabel($record))
                    ->url(fn (Report $record) => $record->subject()?->reportUrl(), shouldOpenInNewTab: true),
                TextEntry::make('reason')->formatStateUsing(fn (string $state) => Reports::reasons()[$state] ?? $state),
                TextEntry::make('reporter.email')->label('By')->placeholder('An account since removed'),
                TextEntry::make('created_at')->label('When')->dateTime('j M Y H:i'),
                TextEntry::make('others')->label('Open reports of it')->getStateUsing(fn (Report $record) => Report::where(['subject_type' => $record->subject_type, 'subject_id' => $record->subject_id, 'status' => 'open'])->count()),
                TextEntry::make('status')->badge(),
                TextEntry::make('details')->placeholder('None given')->columnSpanFull(),
                TextEntry::make('staff_note')->label('Staff note')->placeholder('None')->columnSpanFull(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        $handle = fn (string $status, string $label) => Action::make($status === 'actioned' ? 'action' : 'dismiss')->label($label)
            ->visible(fn (Report $record) => $record->status === 'open')
            ->schema([Textarea::make('staff_note')->label('Note (for staff)')->maxLength(2000)->required($status === 'actioned')])
            ->action(function (Report $record, array $data) use ($status) {
                $record->update(['status' => $status, 'staff_note' => $data['staff_note'] ?? null, 'handled_by' => AdminLog::actor()?->getKey(), 'handled_at' => now()]);
                AdminLog::record("report.{$status}", $record, ucfirst($status).' a report of '.self::subjectLabel($record));
            });

        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('reporter'))
            ->columns([
                TextColumn::make('created_at')->label('When')->since()->sortable(),
                TextColumn::make('subject')->label('Reported')->getStateUsing(fn (Report $record) => self::subjectLabel($record))->limit(60),
                TextColumn::make('reason')->badge()->formatStateUsing(fn (string $state) => Reports::reasons()[$state] ?? $state),
                TextColumn::make('reporter.email')->label('By'),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'open' ? 'danger' : 'gray'),
            ])
            ->filters([SelectFilter::make('status')->options(['open' => 'Open', 'actioned' => 'Acted on', 'dismissed' => 'Dismissed'])->default('open')])
            ->recordActions([ViewAction::make(), $handle('actioned', 'Acted on'), $handle('dismissed', 'Dismiss')]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageReports::route('/')];
    }
}
