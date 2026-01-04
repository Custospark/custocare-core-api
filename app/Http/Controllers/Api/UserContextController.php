<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserContextResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserContextController extends Controller
{
    public function resolve(Request $request, UserContextResolverService $resolver): JsonResponse
    {
        $user = $request->user();

        $context = $resolver->resolve($user->id);

        return response()->json([
            'success' => true,
            'data' => $context,
        ]);
    }
}
