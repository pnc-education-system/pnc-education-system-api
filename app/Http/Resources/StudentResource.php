<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id_no' => $this->student_id_no,
            'full_name' => $this->full_name,
            'gender' => $this->gender,
            'dob' => $this->dob ? $this->dob->format('Y-m-d') : null,
            'phone' => $this->phone,
            'email' => $this->email,
            'province' => $this->province,
            'high_school' => $this->high_school,
            'selection_batch_id' => $this->selection_batch_id,
            'selection_batch' => $this->whenLoaded('selectionBatch', fn() => [
                'id' => $this->selectionBatch->id,
                'name' => $this->selectionBatch->name,
            ]),
            'selection_batch_name' => $this->whenLoaded('selectionBatch', fn() => $this->selectionBatch->name),
            'enrollment_status' => $this->enrollment_status,
            'status' => $this->enrollment_status,
            'photo_path' => $this->photo_path,
            'intake_year' => $this->intake_year,
            'enrolled_at' => $this->enrolled_at ? $this->enrolled_at->format('Y-m-d') : null,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Linked records
            'records' => $this->whenLoaded('records', fn() => $this->records->map(fn($record) => [
                'id'          => $record->id,
                'category'    => $record->category,
                'title'       => $record->title,
                'description' => $record->description,
                'record_date' => $record->record_date?->format('Y-m-d'),
                'created_by'  => $record->created_by,
                'created_at'  => $record->created_at?->format('Y-m-d H:i:s'),
                'attachments' => $record->attachments->map(fn($att) => [
                    'id'        => $att->id,
                    'file_path' => $att->file_path,
                    'file_type' => $att->file_type,
                    'file_size' => $att->file_size,
                ]),
            ])),

            // Linked evaluations
            'evaluations' => $this->whenLoaded('evaluations', fn() => $this->evaluations->map(fn($eval) => [
                'id'                => $eval->id,
                'evaluation_form_id'=> $eval->evaluation_form_id,
                'evaluation_period' => $eval->evaluation_period,
                'total_score'       => $eval->total_score,
                'status'            => $eval->status,
                'submitted_at'      => $eval->submitted_at?->format('Y-m-d H:i:s'),
                'evaluation_form'   => [
                    'id'   => $eval->evaluationForm->id,
                    'name' => $eval->evaluationForm->name,
                ],
                'answers'           => $eval->answers->map(fn($ans) => [
                    'id'          => $ans->id,
                    'question_id' => $ans->question_id,
                    'score'       => $ans->score,
                    'comment'     => $ans->comment,
                ]),
            ])),

            // Linked cards
            'cards' => $this->whenLoaded('cards', fn() => $this->cards->map(fn($card) => [
                'id'             => $card->id,
                'card_number'    => $card->card_number,
                'template_id'    => $card->template_id,
                'issued_at'      => $card->issued_at?->format('Y-m-d H:i:s'),
                'printed_count'  => $card->printed_count,
                'pdf_path'       => $card->pdf_path,
            ])),

            // Enrollment status history
            'enrollment_status_histories' => $this->whenLoaded('enrollmentStatusHistories', fn() =>
                $this->enrollmentStatusHistories->map(fn($history) => [
                    'id'         => $history->id,
                    'old_status' => $history->old_status,
                    'new_status' => $history->new_status,
                    'note'       => $history->note,
                    'changed_by' => $history->changed_by,
                    'created_at' => $history->created_at?->format('Y-m-d H:i:s'),
                ])
            ),
        ];
    }
}
