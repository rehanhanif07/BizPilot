<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'cache' => $this->check(fn () => Cache::put('health-check', true, 10)),
        ];

        $isHealthy = ! in_array('failing', $checks, true);

        return $isHealthy
            ? ApiResponse::success('BizPilot API is running.', $checks)
            : ApiResponse::error('BizPilot API is degraded.', $checks, 503);
    }

    /**
     * @param  callable(): mixed  $probe
     * @return 'ok'|'failing'
     */
    private function check(callable $probe): string
    {
        try {
            $probe();

            return 'ok';
        } catch (Throwable $exception) {
            Log::error('Health check failed.', ['exception' => $exception]);

            return 'failing';
        }
    }
}
