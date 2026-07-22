<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * IdCardResource
 *
 * Transforms a student's ID card data into the JSON structure
 * expected by the GET /api/students/{id}/id-card endpoint.
 *
 * The underlying resource expects an associative array (returned by
 * IdCardService::getCardData()) with the following keys:
 *   - student_code
 *   - full_name
 *   - photo
 *   - batch
 *   - qr_code
 *   - template
 *
 * @mixin array<string, mixed>
 */
class IdCardResource extends JsonResource
{
    /**
     * Transform the card data into the standard API envelope.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'student_code' => $this->resource['student_code'],
            'full_name'    => $this->resource['full_name'],
            'photo'        => $this->resource['photo'],
            'batch'        => $this->resource['batch'],
            'qr_code'      => $this->resource['qr_code'],
            'template'     => $this->resource['template'] ?? 'default',
        ];
    }
}
