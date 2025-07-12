<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\OmniChannel\TicketResource\Pages;
use App\Models\Tenants\OmniChannel\Ticket;
use Filament\Resources\Resource;
use App\Traits\HasTranslatableResource;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;

class TicketResource extends Resource
{
    use HasTranslatableResource;
    
    protected static ?string $model = Ticket::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'Omni Channel';
    protected static ?string $navigationLabel = 'Tickets';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable(),
                    
                Forms\Components\Select::make('agent_id')
                    ->relationship('agent', 'name')
                    ->searchable()
                    ->label('Assigned Agent'),
                    
                Forms\Components\Select::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'urgent' => 'Urgent',
                    ])
                    ->required()
                    ->default('medium'),
                    
                Forms\Components\Select::make('category')
                    ->options([
                        'general' => 'General Inquiry',
                        'technical' => 'Technical Support',
                        'billing' => 'Billing Issue',
                        'feature' => 'Feature Request',
                    ])
                    ->required()
                    ->default('general'),
                    
                Forms\Components\Select::make('status')
                    ->options([
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'waiting' => 'Waiting for Customer',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ])
                    ->required()
                    ->default('open'),
                    
                Forms\Components\Textarea::make('description')
                    ->label('Ticket Description')
                    ->required()
                    ->rows(3),
                    
                Forms\Components\DateTimePicker::make('due_date')
                    ->label('Due Date')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->label('Ticket ID'),
                    
                Tables\Columns\TextColumn::make('user.name')
                    ->sortable()
                    ->searchable()
                    ->label('Customer'),
                    
                Tables\Columns\TextColumn::make('agent.name')
                    ->sortable()
                    ->searchable()
                    ->label('Agent'),
                    
                Tables\Columns\BadgeColumn::make('priority')
                    ->colors([
                        'danger' => 'urgent',
                        'warning' => 'high',
                        'info' => 'medium',
                        'success' => 'low',
                    ]),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'danger' => 'open',
                        'warning' => 'in_progress',
                        'info' => 'waiting',
                        'success' => ['resolved', 'closed'],
                    ]),
                    
                Tables\Columns\TextColumn::make('category')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Created'),
                    
                Tables\Columns\TextColumn::make('due_date')
                    ->dateTime()
                    ->sortable()
                    ->label('Due Date'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'waiting' => 'Waiting for Customer',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ]),
                    
                SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'urgent' => 'Urgent',
                    ]),
                    
                SelectFilter::make('category')
                    ->options([
                        'general' => 'General Inquiry',
                        'technical' => 'Technical Support',
                        'billing' => 'Billing Issue',
                        'feature' => 'Feature Request',
                    ]),
                    
                SelectFilter::make('agent')
                    ->relationship('agent', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('assign')
                    ->action(function (Ticket $record, array $data): void {
                        $record->update([
                            'agent_id' => auth()->id(),
                            'status' => 'in_progress'
                        ]);
                    })
                    ->requiresConfirmation()
                    ->visible(fn (Ticket $record): bool => 
                        $record->agent_id === null && 
                        $record->status === 'open'
                    )
                    ->label('Assign to Me'),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('60s');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'agent'])
            ->when(
                !auth()->user()->hasRole('admin'),
                fn (Builder $query) => $query->where(function($q) {
                    $q->where('agent_id', auth()->id())
                      ->orWhereNull('agent_id');
                })
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'edit' => Pages\EditTicket::route('/{record}/edit'),
        ];
    }
}
