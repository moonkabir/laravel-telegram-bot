<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class TimeoutServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Set max execution time
        $timeout = config('app.execution_timeout', 300);
        set_time_limit($timeout);

        // Set memory limit
        $memoryLimit = config('app.memory_limit', '1024M');
        ini_set('memory_limit', $memoryLimit);

        // Increase upload limits
        ini_set('upload_max_filesize', '100M');
        ini_set('post_max_size', '100M');
        ini_set('max_input_time', 600);
        ini_set('max_execution_time', 300);
    }
}
