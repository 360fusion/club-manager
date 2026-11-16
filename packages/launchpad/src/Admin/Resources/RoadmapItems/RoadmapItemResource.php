<?php

namespace Launchpad\Admin\Resources\RoadmapItems;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Launchpad\Admin\AdminLog;
use Launchpad\Admin\StaffAccess;
use Launchpad\Models\RoadmapItem;

/** The public roadmap: approve what people suggest, move items along, and merge duplicates (their votes move too) */
class RoadmapItemResource extends Resource
{
    protected static ?string $model = RoadmapItem::class;

    protected static ?string $slug = 'roadmap';

    protected static ?string $modelLabel = 'roadmap item';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-map';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Website';
    }

    public static function canViewAny(): bool
    {
        return config('launchpad.modules.roadmap') && StaffAccess::allowed('manage-site');
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = RoadmapItem::where('status', 'review')->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(160)->columnSpanFull(),
            Textarea::make('description')->maxLength(2000)->columnSpanFull(),
            Select::make('status')->options(array_diff_key(RoadmapItem::STATUSES, ['merged' => 1]))->default('idea')->required()->selectablePlaceholder(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('votes', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('author'))
            ->columns([
                TextColumn::make('title')->searchable()->limit(70),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => RoadmapItem::STATUSES[$state] ?? $state),
                TextColumn::make('votes')->sortable(),
                TextColumn::make('author.email')->label('Suggested by')->placeholder('Staff'),
                TextColumn::make('created_at')->label('Added')->since()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(RoadmapItem::STATUSES)])
            ->recordActions([
                Action::make('approve')->label('Show it')->visible(fn (RoadmapItem $record) => $record->status === 'review')
                    ->action(fn (RoadmapItem $record) => $record->update(['status' => 'idea'])),
                Action::make('merge')->label('Merge into')->visible(fn (RoadmapItem $record) => $record->status !== 'merged')
                    ->modalDescription('For a duplicate: its votes move to the item you pick (once per person) and it stops showing.')
                    ->schema(fn (RoadmapItem $record) => [Select::make('into')->label('The item to keep')->required()->searchable()
                        ->options(RoadmapItem::whereKeyNot($record->id)->whereNotIn('status', ['merged', 'review'])->pluck('title', 'id'))])
                    ->action(function (RoadmapItem $record, array $data) {
                        $into = RoadmapItem::findOrFail($data['into']);
                        $record->mergeInto($into);
                        AdminLog::record('roadmap.merged', $record, "Merged “{$record->title}” into “{$into->title}”");
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageRoadmapItems::route('/')];
    }
}
