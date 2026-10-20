<?php

declare(strict_types=1);

namespace Modules\Inventory\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Inventory\Models\StockItem;

final class StockItemPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, StockItem $stock): bool
    {
        return (string) $stock->user_id === (string) $user->getAuthIdentifier();
    }

    public function update(Authenticatable $user, StockItem $stock): bool
    {
        return $this->view($user, $stock);
    }

    public function delete(Authenticatable $user, StockItem $stock): bool
    {
        return $this->view($user, $stock);
    }
}
