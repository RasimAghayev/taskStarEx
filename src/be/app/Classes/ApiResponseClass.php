<?php

namespace App\Classes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApiResponseClass
{
    public static function rollback(\Throwable $e, $message = 'Something went wrong! Process not completed')
    {
        DB::rollBack();

        return self::throw($e, $message);
    }

    public static function throw(\Throwable $e, $message = 'Something went wrong! Process not completed')
    {
        Log::error($e->getMessage(), ['exception' => $e]);

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => config('app.debug') ? $e->getMessage() : null,
        ], 500);
    }

    public static function sendResponse($result, $message = '', $code = 200)
    {
        $response = [
            'success' => true,
            'data' => $result,
        ];
        if (!empty($message)) {
            $response['message'] = $message;
        }

        return response()->json($response, $code);
    }
}
