<?php

declare(strict_types=1);

namespace Lavertia\Panels\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lavertia\Support\Contracts\FieldComponent;
use Lavertia\Support\Contracts\TableColumnComponent;

/**
 * Define a CRUD surface for an existing Eloquent model.
 *
 * Every query must be scoped to the authenticated user's organization/tenant;
 * records are NEVER retrieved by unscoped implicit route model binding.
 */
abstract class Resource
{
    /** @return class-string<Model> */
    abstract public static function model(): string;

    public static function slug(): string
    {
        return Str::kebab(Str::beforeLast(class_basename(static::class), 'Resource'));
    }

    public static function title(): string
    {
        return Str::headline(Str::beforeLast(class_basename(static::class), 'Resource'));
    }

    public static function navigationGroup(): ?string
    {
        return 'Resources';
    }

    /** Return a Policy/tenant-scoped query for list and record operations. */
    abstract public function query(): Builder;

    /** @return list<TableColumnComponent> */
    abstract public function columns(): array;

    /** @return list<FieldComponent> */
    abstract public function formFields(?Model $record): array;

    /** @return array<string, mixed> Laravel validation rules */
    abstract public function rules(?Model $record): array;

    /**
     * The owner MUST assign tenant/organization fields from server context;
     * validated form fields must never determine tenancy.
     *
     * @param array<string, mixed> $validated
     */
    abstract public function createRecord(array $validated): Model;

    /** @param array<string, mixed> $validated */
    abstract public function updateRecord(Model $record, array $validated): void;

    abstract public function deleteRecord(Model $record): void;

    /** @return Builder<Model> */
    final public function scopedQuery(): Builder
    {
        $query = $this->query();
        $model = static::model();

        if (! is_subclass_of($model, Model::class) || ! is_a($query->getModel(), $model)) {
            throw new InvalidArgumentException('Resource query must use its declared Eloquent model.');
        }

        return $query;
    }

    final public function findRecord(string $key): Model
    {
        // Nonexistent and out-of-scope IDs intentionally have the same 404.
        return $this->scopedQuery()->whereKey($key)->firstOrFail();
    }

    /** @return list<TableColumnComponent> */
    final public function tableColumns(): array
    {
        $columns = $this->columns();

        if (! array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException('Resource columns must be a nonempty list.');
        }

        foreach ($columns as $column) {
            if (! $column instanceof TableColumnComponent) {
                throw new InvalidArgumentException('Resource columns must implement TableColumnComponent.');
            }
        }

        return $columns;
    }

    /** @return list<FieldComponent> */
    final public function fields(?Model $record): array
    {
        $fields = $this->formFields($record);

        if (! array_is_list($fields) || $fields === []) {
            throw new InvalidArgumentException('Resource form fields must be a nonempty list.');
        }

        foreach ($fields as $field) {
            if (! $field instanceof FieldComponent) {
                throw new InvalidArgumentException('Resource form fields must implement FieldComponent.');
            }
        }

        return $fields;
    }
}
