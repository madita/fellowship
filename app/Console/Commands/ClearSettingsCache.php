<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

class ClearSettingsCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'settings:clear-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all settings cache';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Clearing settings cache...');

        Setting::clearCache();

        $this->info('Settings cache cleared successfully!');

        return 0;
    }
}
