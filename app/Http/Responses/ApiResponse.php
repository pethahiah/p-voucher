<?php

namespace App\Http\Responses;


use Illuminate\Http\JsonResponse;
use Illuminate\Support\MessageBag;

class ApiResponse
{
    /**
     * Format a successful API response.
     *
     * @param string $message
     * @param array|null $data
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function success(string $message, $data = null, int $statusCode = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => is_array($data) ? $data : ['response' => $data]
        ], $statusCode);
    }



    /**
     * Format an error API response.
     *
     * @param string $message
     * @param array|MessageBag $errors
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function error(string $message, $errors = [], int $statusCode = 400): JsonResponse
    {
        // Convert MessageBag to array if necessary
        if ($errors instanceof MessageBag) {
            $errors = $errors->toArray();
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $statusCode);
    }
}
