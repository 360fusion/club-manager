<?php

namespace Launchpad\Admin\Resources\Subscriptions;

use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Subscription;
use Launchpad\Admin\StaffAccess;
use Launchpad\Billing\PlanCatalog;
use Launchpad\Modules;

/** Who pays for what, as the app has it from Stripe. Read only: changes are made at Stripe and arrive by webhook. */
class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $slug = 'subscriptions';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-credit-card';
    }

    public static function canViewAny(): bool
    {
        return Modules::enabled('billing') && StaffAccess::allowed('view-billing');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Billing';
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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('owner'))
            ->columns([
                TextColumn::make('owner.name')->label('Team')->searchable(),
                TextColumn::make('plan')->badge()->getStateUsing(function (Subscription $record): string {
                    [$plan, $period] = app(PlanCatalog::class)->ofSubscription($record) ?? [null, null];

                    return $plan ? "{$plan->name} ({$period})" : 'Unknown price';
                }),
                TextColumn::make('stripe_status')->label('Status')->badge()->color(fn (string $state): string => match ($state) {
                    'active', 'trialing' => 'success',
                    'past_due', 'incomplete' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('quantity')->label('Seats'),
                TextColumn::make('ends_at')->label('Ends')->dateTime('j M Y')->placeholder(''),
                TextColumn::make('stripe_id')->label('Stripe')->copyable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Started')->dateTime('j M Y')->sortable(),
            ])
            ->filters([SelectFilter::make('stripe_status')->label('Status')->options(['active' => 'Active', 'past_due' => 'Past due', 'canceled' => 'Cancelled', 'incomplete' => 'Incomplete'])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSubscriptions::route('/')];
    }
}
