<?php

namespace App\Http\Controllers\Api\V1\Evaluation;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Requests\StoreStudentEvaluationRequest;
use App\Models\Evaluation;
use App\Models\EvaluationAnswer;
use App\Models\EvaluationForm;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class StudentEvaluationController extends Controller
{
    use ApiResponse;
    /**
     * Get all evaluations for a student.
     */
    public function index(int $id)
    {
        $student = Student::find($id);
        if (!$student) {
            return $this->error('Student not found', 404);
        }

        $evaluations = Evaluation::with(['answers.question.category'])
            ->where('student_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $formIds = $evaluations->pluck('evaluation_form_id')->unique();
        $forms = EvaluationForm::with(['categories' => function ($q) {
            $q->orderBy('sort_order');
        }, 'categories.questions' => function ($q) {
            $q->orderBy('sort_order');
        }])->whereIn('id', $formIds)->get()->keyBy('id');

        $data = $evaluations->map(function ($evaluation) use ($forms) {
            $form = $forms->get($evaluation->evaluation_form_id);

            $categoryScores = [];
            if ($form) {
                $answersByQuestion = $evaluation->answers->keyBy('question_id');
                foreach ($form->categories as $category) {
                    $total = 0;
                    $questions = [];
                    foreach ($category->questions as $question) {
                        $answer = $answersByQuestion->get($question->id);
                        $score = $answer ? (float) $answer->score : 0;
                        $total += $score;
                        $questions[] = [
                            'question_id'   => $question->id,
                            'question_text' => $question->question_text,
                            'score'         => $score,
                            'max_score'     => (float) $question->score,
                        ];
                    }
                    $categoryScores[] = [
                        'category_id'   => $category->id,
                        'category_name' => $category->name,
                        'total_score'   => $total,
                        'questions'     => $questions,
                    ];
                }
            }

            return [
                'id'                 => $evaluation->id,
                'student_id'         => $evaluation->student_id,
                'evaluation_form_id' => $evaluation->evaluation_form_id,
                'evaluation_period'  => $evaluation->evaluation_period,
                'total_score'        => (float) $evaluation->total_score,
                'status'             => $evaluation->status,
                'submitted_at'       => $evaluation->submitted_at?->toIso8601String(),
                'category_scores'    => $categoryScores,
                'answers'            => $evaluation->answers->map(fn ($a) => [
                    'id'          => $a->id,
                    'question_id' => $a->question_id,
                    'score'       => (float) $a->score,
                    'comment'     => $a->comment,
                ]),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Evaluations retrieved successfully',
            'data'    => $data,
        ]);
    }

    public function store(StoreStudentEvaluationRequest $request, int $id)
    {
        $student = Student::find($id);
        if (!$student) {
            return $this->error('Student not found', 404);
        }

        $formId  = $request->integer('evaluation_form_id');
        $period  = $request->input('evaluation_period');
        $answers = $request->input('answers', []);

        $form = EvaluationForm::with(['questions', 'categories'])->find($formId);
        if (!$form) {
            return $this->error('Evaluation form not found', 404);
        }

        $formQuestionIds = $form->questions->pluck('id')->toArray();
        $submittedIds    = array_column($answers, 'question_id');
        $invalidIds      = array_diff($submittedIds, $formQuestionIds);

        if (!empty($invalidIds)) {
            return $this->error(
                'Some questions do not belong to the specified evaluation form: ' . implode(', ', $invalidIds),
                422
            );
        }

        $questionsById = $form->questions->keyBy('id');

        foreach ($answers as $answer) {
            $question = $questionsById->get($answer['question_id']);
            if ($answer['score'] > $question->score) {
                return $this->error(
                    "Score for question #{$answer['question_id']} must not exceed {$question->score}.",
                    422
                );
            }
        }

        /** @var Evaluation $evaluation */
        $evaluation = DB::transaction(function () use ($student, $form, $period, $answers, $questionsById) {
            /** @var Evaluation $evaluation */
            $evaluation = Evaluation::create([
                'student_id'         => $student->id,
                'evaluation_form_id' => $form->id,
                'evaluation_period'  => $period,
                'total_score'        => 0,
                'status'             => 'Submitted',
                'submitted_at'       => now(),
            ]);

            $totalScore = 0;

            foreach ($answers as $answer) {
                EvaluationAnswer::create([
                    'evaluation_id' => $evaluation->id,
                    'question_id'   => $answer['question_id'],
                    'score'         => $answer['score'],
                    'comment'       => $answer['comment'] ?? null,
                ]);

                $totalScore += $answer['score'];
            }

            $evaluation->update(['total_score' => $totalScore]);

            return $evaluation->fresh(['answers']);
        });
        $categoryScores = [];
        foreach ($answers as $answer) {
            $question = $questionsById->get($answer['question_id']);
            $catId    = $question->category_id;

            $categoryScores[$catId] = ($categoryScores[$catId] ?? 0) + $answer['score'];
        }

        $categoryScoreList = $form->categories->map(fn ($cat) => [
            'category_id'   => $cat->id,
            'category_name' => $cat->name,
            'total_score'   => (float) ($categoryScores[$cat->id] ?? 0),
        ])->values();

        return response()->json([
            'status'  => 'success',
            'message' => 'Evaluation submitted successfully',
            'data'    => [
                'id'                 => $evaluation->id,
                'student_id'         => $evaluation->student_id,
                'evaluation_form_id' => $evaluation->evaluation_form_id,
                'evaluation_period'  => $evaluation->evaluation_period,
                'total_score'        => (float) $evaluation->total_score,
                'status'             => $evaluation->status,
                'submitted_at'       => $evaluation->submitted_at?->toIso8601String(),
                'category_scores'    => $categoryScoreList,
                'answers'            => $evaluation->answers->map(fn ($a) => [
                    'id'          => $a->id,
                    'question_id' => $a->question_id,
                    'score'       => (float) $a->score,
                    'comment'     => $a->comment,
                ]),
            ],
        ], 201);
    }
}
