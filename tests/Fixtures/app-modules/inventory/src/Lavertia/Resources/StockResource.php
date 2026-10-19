<?php

declare(strict_types=1);

namespace Modules\Inventory\Lavertia\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lavertia\Forms\Components\TextInput;
use Lavertia\Panels\Resources\Resource;
use Lavertia\Support\Contracts\FieldComponent;
use Lavertia\Support\Contracts\TableColumnComponent;
use Lavertia\Tables\Components\TextColumn;
use Modules\Inventory\Models\StockItem;

final class StockResource extends Resource
{
    public static function model(): string
    {
        return StockItem::class;
    }

    public static function slug(): string
    {
        return 'stocks';
    }

    public static function title(): string
    {
        return 'Stock items';
    }

    public function query(): Builder
    {
        return StockItem::query()->where('user_id', auth()->id());
    }

    /** @return list<TableColumnComponent> */
    public function columns(): array
    {
        return [
            TextColumn::make('id')->label('ID')->sortable(),
            TextColumn::make('name')->label('Name')->searchable()->sortable(),
        ];
    }

    /** @return list<FieldComponent> */
    public function formFields(?Model $record): array
    {
        return [
            TextInput::make('name')->label('Name')->required()
                ->value((string) ($record?->getAttribute('name') ?? '')),
        ];
    }

    public function rules(?Model $record): array
    {
        return ['name' => ['required', 'string', 'max:80']];
    }

    public function createRecord(array $validated): Model
    {
        return StockItem::query()->create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
        ]);
    }

    public function updateRecord(Model $record, array $validated): void
    {
        $record->update(['name' => $validated['name']]);
    }

    public function deleteRecord(Model $record): void
    {
        $record->delete();
    }
}
