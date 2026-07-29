<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function error(string $message, int $code, $errors = [])
    {
        $response = ['status' => 'error', 'message' => $message];

        if ($errors instanceof \Illuminate\Support\MessageBag) {
            $errors = $errors->toArray();
        }

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}
