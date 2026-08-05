<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class QuotaExceededException extends Exception
{
    public function render($request): JsonResponse
    {
        $recommendation = \App\Models\Plan::query()
            ->where('is_free', false)
            ->where('is_active', true)
            ->orderBy('price_monthly')
            ->first();

        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'quota_exceeded',
            'upgrade' => $recommendation
                ? ['plan_slug' => $recommendation->slug, 'plan_name' => $recommendation->name]
                : null,
        ], 422);
    }
}