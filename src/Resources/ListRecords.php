<?php

declare(strict_types=1);

namespace Lavertia\Panels\Resources;

use Illuminate\Support\Facades\Gate;
use Lavertia\Panels\Components\PageHeader;
use Lavertia\Tables\Components\Table;

/**
 * A resource list with optional getTabs(), typed Eloquent table and safe
 * default query/Policy. Override table() to configure columns, not authority.
 */
abstract class ListRecords extends ResourcePage
{
    use HasResourceTabs;

    abstract protected function table(Table $table): Table;

    protected function tableName(): string
    {
        return 'records';
    }

    protected function listTitle(): string
    {
        return static::title();
    }

    protected function components(): array
    {
        $resource = static::resource();
        $header = PageHeader::make($this->listTitle());
        $tabs = $this->resourceTabLinks();

        if ($tabs !== []) {
            $header = $header->tabs($tabs);
        }

        $table = $this->table(Table::make($this->tableName()))
            ->eloquent(
                $this->filteredResourceQuery(),
                static fn (): bool => Gate::allows('viewAny', $resource::model()),
            );

        return [$header, $table];
    }
}
