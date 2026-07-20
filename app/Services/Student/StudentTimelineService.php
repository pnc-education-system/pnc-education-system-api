<?php

namespace App\Services\Student;

use App\Models\EnrollmentStatusHistory;
use App\Models\RecordAttachment;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\StudentRecord;
use Illuminate\Support\Collection;

class StudentTimelineService
{
    /**
     * Get student timeline entries
     */
    public function getTimeline(Student $student, ?string $search = null, ?string $category = null): Collection
    {
        $timeline = collect();

        // Add student creation event as baseline
        $timeline->push($this->getStudentCreatedEvent($student));

        // Collect enrollment status history
        $timeline = $timeline->merge($this->getEnrollmentHistory($student));

        // Collect student records
        $timeline = $timeline->merge($this->getStudentRecords($student));

        // Collect ID card records
        $timeline = $timeline->merge($this->getIdCardRecords($student));

        // Collect document/attachment records
        $timeline = $timeline->merge($this->getDocumentRecords($student));

        // Apply search filter
        if ($search) {
            $timeline = $this->applySearch($timeline, $search);
        }

        // Apply category filter
        if ($category) {
            $timeline = $this->applyCategoryFilter($timeline, $category);
        }

        // Sort by date descending (newest first)
        return $timeline->sortByDesc('created_at')->values();
    }

    /**
     * Get student creation event as the baseline timeline entry
     */
    protected function getStudentCreatedEvent(Student $student): array
    {
        return [
            'id' => 'created-' . $student->id,
            'category' => 'Status',
            'title' => 'Student Created',
            'description' => 'Student profile created',
            'created_at' => $student->created_at,
        ];
    }

    /**
     * Get enrollment status history entries
     */
    protected function getEnrollmentHistory(Student $student): Collection
    {
        return EnrollmentStatusHistory::where('student_id', $student->id)
            ->get()
            ->map(function ($history) {
                return [
                    'id' => 'enrollment-' . $history->id,
                    'category' => 'Enrollment',
                    'title' => 'Status Changed',
                    'description' => "Student status changed from {$history->old_status} to {$history->new_status}" . 
                        ($history->note ? ". Note: {$history->note}" : ''),
                    'created_at' => $history->created_at,
                ];
            });
    }

    /**
     * Get student record entries
     */
    protected function getStudentRecords(Student $student): Collection
    {
        return StudentRecord::where('student_id', $student->id)
            ->get()
            ->map(function ($record) {
                return [
                    'id' => 'record-' . $record->id,
                    'category' => $record->record_type,
                    'title' => $record->record_type,
                    'description' => $record->details,
                    'created_at' => $record->created_at,
                ];
            });
    }

    /**
     * Get ID card records
     */
    protected function getIdCardRecords(Student $student): Collection
    {
        return StudentCard::where('student_id', $student->id)
            ->get()
            ->map(function ($card) {
                return [
                    'id' => 'card-' . $card->id,
                    'category' => 'ID Card',
                    'title' => 'ID Card Issued',
                    'description' => "ID card #{$card->card_number} issued" . 
                        ($card->issued_at ? " on {$card->issued_at->format('Y-m-d')}" : ''),
                    'created_at' => $card->created_at,
                ];
            });
    }

    /**
     * Get document/attachment records
     */
    protected function getDocumentRecords(Student $student): Collection
    {
        return RecordAttachment::whereHas('studentRecord', function ($query) use ($student) {
            $query->where('student_id', $student->id);
        })
        ->with('studentRecord')
        ->get()
        ->map(function ($attachment) {
            return [
                'id' => 'document-' . $attachment->id,
                'category' => 'Document',
                'title' => 'Document Uploaded',
                'description' => ($attachment->file_type ?? 'File') . ' uploaded for: ' . ($attachment->studentRecord->details ?? 'record'),
                'created_at' => $attachment->created_at,
            ];
        });
    }

    /**
     * Apply search filter to timeline
     */
    protected function applySearch(Collection $timeline, string $search): Collection
    {
        $searchLower = strtolower($search);

        return $timeline->filter(function ($entry) use ($searchLower) {
            return str_contains(strtolower($entry['title']), $searchLower) ||
                   str_contains(strtolower($entry['description']), $searchLower) ||
                   str_contains(strtolower($entry['category']), $searchLower);
        });
    }

    /**
     * Apply category filter to timeline
     */
    protected function applyCategoryFilter(Collection $timeline, string $category): Collection
    {
        return $timeline->filter(function ($entry) use ($category) {
            return strcasecmp($entry['category'], $category) === 0;
        });
    }
}
