<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\Response;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Response as ResponseFacade;

trait ApiResponse
{
    protected function error(string $message, int $code, $errors = [])
    {
        $response = ['status' => 'error', 'message' => $message];

        if ($errors instanceof MessageBag) {
            $errors = $errors->toArray();
        }

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}

