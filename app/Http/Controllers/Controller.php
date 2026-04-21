<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
    
    /**
     * Standard success response
     */
    // protected function successResponse($data, string $message = 'Success', int $code = 200): \Illuminate\Http\JsonResponse
    // {
    //     return response()->json([
    //         'success' => true,
    //         'message' => $message,
    //         'data' => $data
    //     ], $code);
    // }
    
    /**
     * Standard error response
     */
    // protected function errorResponse(string $message, int $code = 400, $errors = null): \Illuminate\Http\JsonResponse
    // {
    //     $response = [
    //         'success' => false,
    //         'message' => $message
    //     ];
        
    //     if ($errors) {
    //         $response['errors'] = $errors;
    //     }
        
    //     return response()->json($response, $code);
    // }
}