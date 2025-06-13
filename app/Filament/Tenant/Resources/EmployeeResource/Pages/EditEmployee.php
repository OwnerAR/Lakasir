<?php

namespace App\Filament\Tenant\Resources\EmployeeResource\Pages;

use App\Filament\Tenant\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        
        if (!empty($data['foto_url'])) {
            if (is_string($data['foto_url']) && strpos($data['foto_url'], '[') === 0) {
                try {
                    $data['foto_url'] = json_decode($data['foto_url'], true);
                } catch (\Exception $e) {
                    \Log::error("Error decoding foto_url: " . $e->getMessage());
                }
            }
        }
        
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
