<?php

declare(strict_types=1);

namespace Lavertia\Panels\Components;

use InvalidArgumentException;
use Lavertia\Support\Components\AbstractComponent;
use Lavertia\Support\Contracts\Component;
use Lavertia\Support\Contracts\TableColumnComponent;
use Lavertia\Support\Contracts\TableComponent;

final class Table extends AbstractComponent implements TableComponent
{
    /** @var list<TableColumnComponent> */
    private array $columns = [];

    private function __construct(private readonly string $name)
    {
    }

    public static function make(string $name): self
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,127}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Invalid table name.');
        }

        return (new self($name))->key($name);
    }

    public function getType(): string
    {
        return 'table';
    }

    public function getTableName(): string
    {
        return $this->name;
    }

    /** @param list<TableColumnComponent> $columns */
    public function columns(array $columns): static
    {
        if (! array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException('Tables require a non-empty list of columns.');
        }

        $names = [];

        foreach ($columns as $column) {
            if (! $column instanceof TableColumnComponent) {
                throw new InvalidArgumentException('Columns must implement TableColumnComponent.');
            }

            if (isset($names[$column->getColumnName()])) {
                throw new InvalidArgumentException('Duplicate table column name.');
            }

            $names[$column->getColumnName()] = true;
        }

        $copy = clone $this;
        $copy->columns = $columns;

        return $copy;
    }

    /** @return list<Component> */
    public function getComponents(): array
    {
        return $this->columns;
    }

    /**
     * Temporary bounded snapshot only. Server-driven pagination is not implemented yet.
     *
     * @param list<array<string, scalar|null>> $rows
     */
    public function rows(array $rows): static
    {
        if (! array_is_list($rows) || count($rows) > 100) {
            throw new InvalidArgumentException('Table rows must be a list of at most 100 items.');
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException('Each table row must be an associative array.');
            }

            foreach ($row as $value) {
                if ($value !== null && ! is_scalar($value)) {
                    throw new InvalidArgumentException('Table cells must contain scalar values.');
                }
            }
        }

        return $this->withProp('rows', $rows);
    }

    /** @return array<string, mixed> */
    public function getProps(): array
    {
        return ['name' => $this->name, 'rows' => [], ...parent::getProps()];
    }
}
