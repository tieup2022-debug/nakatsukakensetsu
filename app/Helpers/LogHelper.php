<?php

use Illuminate\Support\Facades\Log;

if (!function_exists('error')) {
    function error($e, $file, $method, $line)
    {
        $context = [
            'file'   => basename($file),
            'method' => $method,
            'line'   => $line,
            'trace'  => $e instanceof \Throwable ? $e->getTraceAsString() : null,
        ];

        Log::channel(config('logging.default', 'stack'))->error(
            $e->getMessage(),
            $context
        );
    }
}

