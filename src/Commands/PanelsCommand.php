<?php

namespace Lavertia\Panels\Commands;

use Illuminate\Console\Command;

class PanelsCommand extends Command
{
    public $signature = 'panels';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
