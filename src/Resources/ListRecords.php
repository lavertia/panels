<?php

declare(strict_types=1);

namespace Lavertia\Panels\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lavertia\Panels\Components\Action;
use Lavertia\Panels\Components\PageHeader;
use Lavertia\Panels\Components\Section;
use Lavertia\Tables\Components\Table;

/**
 * A Resource list with reusable getTabs(), scoped Eloquent table,
 * Policy authorization, and optional header actions.
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

    protected function listDescription(): ?string
    {
        return null;
    }

    protected function listSectionTitle(): string
    {
        return $this->listTitle();
    }

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /** Per-list customization; always receives a scoped query. */
    protected function modifyTableQuery(Builder $query): void {}

    protected function components(): array
    {
        $resource = static::resource();
        $header = PageHeader::make($this->listTitle());

        if ($this->listDescription() !== null) {
            $header = $header->description($this->listDescription());
        }

        $tabs = $this->resourceTabLinks();

        if ($tabs !== []) {
            $header = $header->tabs($tabs);
        }

        $actions = $this->getHeaderActions();

        if ($actions !== []) {
            $header = $header->actions($actions);
        }

        $query = $this->filteredResourceQuery();
        $this->modifyTableQuery($query);

        $table = $this->table(Table::make($this->tableName()))
            ->eloquent(
                $query,
                static fn (): bool => Gate::allows('viewAny', $resource::model()),
            );

        return [$header, Section::make($this->listSectionTitle())->components([$table])];
    }
}
