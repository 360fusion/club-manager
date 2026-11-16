<?php

namespace Launchpad\Admin\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Launchpad\Admin\StaffAccess;
use Launchpad\Launchpad;

class LatestSignups extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Latest sign-ups';

    public static function canView(): bool
    {
        return StaffAccess::allowed('view-accounts');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Launchpad::userModel()::query()->latest()->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('email'),
                TextColumn::make('created_at')->label('Joined')->since(),
            ]);
    }
}
