<?php

namespace App\Http\Controllers\Api;

class ApiController
{
    public static function respondWithSuccess($message = null, $data = null)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data
        ]);
    }

    public static function respondWithError($message, $errors, $code)
    {
        return response()->json([
            'success'     => false,
            'message'     => $message,
            'errors'      => $errors,
            'status_Code' => $code
        ], $code);
    }

    public static function formatPagination($paginator)
    {
        return [
            'total'         => $paginator->total(),
            'per_page'      => $paginator->perPage(),
            'current_page'  => $paginator->currentPage(),
            'last_page'     => $paginator->lastPage(),
            'from'          => $paginator->firstItem(),
            'to'            => $paginator->lastItem(),
        ];
    }
}
