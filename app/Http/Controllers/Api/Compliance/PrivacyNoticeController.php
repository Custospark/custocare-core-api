<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Compliance;

use App\Http\Controllers\Controller;
use App\Services\Compliance\PrivacyNotice;
use Illuminate\Http\JsonResponse;

class PrivacyNoticeController extends Controller
{
    /**
     * Public: current privacy notice (versioned, 9 mandatory items).
     * No auth: the notice must be readable before registration/consent.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Privacy notice retrieved.',
            'data'    => PrivacyNotice::document(),
        ]);
    }
}
