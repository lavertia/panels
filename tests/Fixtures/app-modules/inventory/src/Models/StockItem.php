<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

final class StockItem extends Model
{
    protected $table = 'inventory_stock_items';

    protected $fillable = ['name', 'user_id'];
}
