<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\OmniChannel\MessageResource\Pages;
use App\Models\Tenants\OmniChannel\Message;
use Filament\Resources\Resource;
use App\Traits\HasTranslatableResource;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Grouping\Group;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Tenants\MessageService;

class MessageResource extends Resource
{
    use HasTranslatableResource;
    
    protected static ?string $model = Message::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Omni Channel';
    protected static ?string $navigationLabel = 'Live Chat';
    protected static ?string $pluralLabel = 'Live Chats';
    protected static ?string $modelLabel = 'Live Chats';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_number')
                            ->label('WhatsApp Number')
                            ->disabled(),
                        Forms\Components\TextInput::make('customer_name')
                            ->label('Customer Name')
                            ->disabled(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'queued' => 'Queued',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                            ])
                            ->disabled(),
                    ])->columnSpan(1),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Textarea::make('message')
                            ->label('Reply Message')
                            ->required()
                            ->rows(3)
                            ->columnSpan('full'),
                        Forms\Components\Select::make('message_type')
                            ->options([
                                'text' => 'Text',
                                'image' => 'Image',
                                'file' => 'File',
                            ])
                            ->default('text')
                            ->required()
                            ->reactive(),
                        Forms\Components\FileUpload::make('media_url')
                            ->visible(fn ($get) => in_array($get('message_type'), ['image', 'file']))
                            ->directory('whatsapp-media'),
                        Forms\Components\Actions::make([
                            FormAction::make('send')
                                ->label('Send Reply')
                                ->color('primary')
                                ->icon('heroicon-o-paper-airplane')
                                ->form([
                                    Forms\Components\Textarea::make('message')
                                        ->label('Reply Message')
                                        ->required()
                                        ->rows(3),
                                    Forms\Components\Select::make('message_type')
                                        ->options([
                                            'text' => 'Text',
                                            'image' => 'Image',
                                            'file' => 'File',
                                        ])
                                        ->default('text')
                                        ->required(),
                                    Forms\Components\FileUpload::make('media_url')
                                        ->visible(fn ($get) => in_array($get('message_type'), ['image', 'file']))
                                        ->directory('whatsapp-media'),
                                ])
                                ->action(function (array $data, Message $record) {
                                    // Create reply message
                                    $reply = new Message([
                                        'whatsapp_number' => $record->whatsapp_number,
                                        'customer_name' => $record->customer_name,
                                        'message' => $data['message'],
                                        'message_type' => $data['message_type'],
                                        'media_url' => $data['media_url'] ?? null,
                                        'direction' => 'outbound',
                                        'status' => $record->status,
                                        'assigned_to' => auth()->id(),
                                    ]);

                                    // Append signature
                                    $reply->appendAgentSignature();

                                    // Save to database
                                    $reply->save();

                                    // Notify success
                                    Notification::make()
                                        ->success()
                                        ->title('Message sent successfully')
                                        ->send();
                                })
                        ])->columnSpan('full'),
                    ])->columnSpan(2),
                Forms\Components\Section::make('Chat History')
                    ->schema([
                        Forms\Components\View::make('filament.tenant.resources.message.chat-history')
                    ])->columnSpan('full'),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->where(function ($q) {
                    // Get first messages of new conversations
                    $q->whereIn('id', function ($subquery) {
                        $subquery->selectRaw('MIN(id)')
                            ->from('omni_channel_messages')
                            ->where('direction', 'inbound')
                            ->groupBy('whatsapp_number');
                    });
                    
                    // Include messages from completed conversations
                    $q->orWhere(function ($q2) {
                        $q2->where('direction', 'inbound')
                           ->whereExists(function ($subquery) {
                                $subquery->from('omni_channel_messages as last_chat')
                                    ->whereColumn('omni_channel_messages.whatsapp_number', 'last_chat.whatsapp_number')
                                    ->where('status', 'completed')
                                    ->whereNotExists(function ($query) {
                                        $query->from('omni_channel_messages as in_progress')
                                            ->whereColumn('last_chat.whatsapp_number', 'in_progress.whatsapp_number')
                                            ->where('status', 'in_progress');
                                    });
                            });
                    });
                })
                ->orderBy('created_at', 'desc');
            })
            ->groups([
                Group::make('whatsapp_number')
                    ->label('WhatsApp Conversations')
                    ->collapsible(),
            ])
            ->defaultGroup('whatsapp_number')
            ->columns([
                Tables\Columns\TextColumn::make('whatsapp_number')
                    ->label('WhatsApp')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'danger' => 'queued',
                        'warning' => 'in_progress',
                        'success' => 'completed',
                    ]),
                Tables\Columns\TextColumn::make('message')
                    ->limit(50),
                Tables\Columns\IconColumn::make('direction')
                    ->icon(fn (string $state): string => match ($state) {
                        'inbound' => 'heroicon-o-arrow-left',
                        'outbound' => 'heroicon-o-arrow-right',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'inbound' => 'success',
                        'outbound' => 'info',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'queued' => 'Queued',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                    ]),
                Tables\Filters\SelectFilter::make('direction')
                    ->options([
                        'inbound' => 'Inbound',
                        'outbound' => 'Outbound',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Open Chat'),
                Tables\Actions\Action::make('complete')
                    ->label('Complete Chat')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Message $record) => $record->status === 'in_progress')
                    ->action(function (Message $record) {
                        app(MessageService::class)->completeChat($record);
                        
                        Notification::make()
                            ->success()
                            ->title('Chat completed successfully')
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('5s');
    }

    public static function getFormActions(): array
    {
        return [
            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('complete')
                    ->label('Complete Chat')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Forms\Get $get) => $get('status') === 'in_progress')
                    ->action(function (Message $record) {
                        app(MessageService::class)->completeChat($record);
                        
                        Notification::make()
                            ->success()
                            ->title('Chat completed successfully')
                            ->send();
                    }),
            ])->columnSpan('full'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMessages::route('/'),
            'edit' => Pages\EditMessage::route('/{record}/edit'),
        ];
    }
}
