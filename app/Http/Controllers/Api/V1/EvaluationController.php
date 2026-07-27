<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Api\V1\Concerns\AuditableLogger;
use App\Http\Resources\EvaluationTemplateResource;
use App\Models\EvaluationCategory;
use App\Models\EvaluationForm;
use App\Models\EvaluationQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EvaluationController extends Controller
{
    use ApiResponse, AuditableLogger;

    // ──────────────────────────────────────────────
    //  Evaluation Templates (EvaluationForm)
    // ──────────────────────────────────────────────

    /**
     * Retrieve all evaluation templates with their categories and questions.
     */
    public function index(): JsonResponse
    {
        $templates = EvaluationForm::with(['categories' => function ($query) {
            $query->orderBy('sort_order');
        }, 'categories.questions' => function ($query) {
            $query->orderBy('sort_order');
        }])
            ->orderBy('name')
            ->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Evaluation templates retrieved successfully',
            'data'    => EvaluationTemplateResource::collection($templates),
        ], 200);
    }

    /**
     * Retrieve a single evaluation template by ID.
     */
    public function show(int $id): JsonResponse
    {
        $template = EvaluationForm::with(['categories' => function ($query) {
            $query->orderBy('sort_order');
        }, 'categories.questions' => function ($query) {
            $query->orderBy('sort_order');
        }])->find($id);

        if (!$template) {
            return $this->error('Evaluation template not found', 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Evaluation template retrieved successfully',
            'data'    => new EvaluationTemplateResource($template),
        ], 200);
    }

    /**
     * Create a new evaluation template.
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $template = EvaluationForm::create([
            'name'        => $request->name,
            'description' => $request->description,
            'is_active'   => $request->boolean('is_active', true),
        ]);

        $this->logAudit($template, 'evaluation_template_created', $request, [], $template->toArray());

        return response()->json([
            'status'  => 'success',
            'message' => 'Evaluation template created successfully',
            'data'    => new EvaluationTemplateResource($template->load(['categories.questions' => function ($q) {
                $q->orderBy('sort_order');
            }])),
        ], 201);
    }

    /**
     * Update an existing evaluation template.
     */
    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $template = EvaluationForm::find($id);

        if (!$template) {
            return $this->error('Evaluation template not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $oldValues = $template->toArray();
        $dirty = false;

        if ($request->has('name')) {
            $template->name = $request->name;
            $dirty = true;
        }

        // Use exists() for nullable fields so they can be cleared
        if ($request->exists('description')) {
            $template->description = $request->description;
            $dirty = true;
        }

        if ($request->has('is_active')) {
            $template->is_active = $request->boolean('is_active');
            $dirty = true;
        }

        if (!$dirty) {
            return $this->error('No changes detected', 400);
        }

        $template->save();
        $this->logAudit($template, 'evaluation_template_updated', $request, $oldValues, $template->toArray());

        return response()->json([
            'status'  => 'success',
            'message' => 'Evaluation template updated successfully',
            'data'    => new EvaluationTemplateResource($template->load(['categories.questions' => function ($q) {
                $q->orderBy('sort_order');
            }])),
        ], 200);
    }

    /**
     * Delete an evaluation template.
     */
    public function destroyTemplate(Request $request, int $id): JsonResponse
    {
        $template = EvaluationForm::with('categories')->find($id);

        if (!$template) {
            return $this->error('Evaluation template not found', 404);
        }

        // Prevent deletion if evaluations are linked
        if ($template->evaluations()->count() > 0) {
            return $this->error(
                'Cannot delete template: it has ' . $template->evaluations()->count() . ' evaluation(s) linked to it.',
                400
            );
        }

        $oldValues = $template->toArray();

        // Cascade delete: batch-delete questions, then categories, then template
        $categoryIds = $template->categories->pluck('id');
        if ($categoryIds->isNotEmpty()) {
            EvaluationQuestion::whereIn('category_id', $categoryIds)->delete();
        }
        $template->categories()->delete();
        $template->delete();

        $this->logAudit($template, 'evaluation_template_deleted', $request, $oldValues, []);

        return response()->json([
            'status'  => 'success',
            'message' => 'Evaluation template deleted successfully',
        ], 200);
    }

    // ──────────────────────────────────────────────
    //  Evaluation Categories
    // ──────────────────────────────────────────────

    /**
     * Create a new category within an evaluation template.
     */
    public function storeCategory(Request $request, int $templateId): JsonResponse
    {
        $template = EvaluationForm::find($templateId);

        if (!$template) {
            return $this->error('Evaluation template not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:255',
            'sort_order' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $category = $template->categories()->create([
            'name'       => $request->name,
            'sort_order' => $request->integer('sort_order', 1),
        ]);

        $this->logAudit($category, 'evaluation_category_created', $request, [], $category->toArray());

        return response()->json([
            'status'  => 'success',
            'message' => 'Evaluation category created successfully',
            'data'    => $category,
        ], 201);
    }

    /**
     * Update an existing evaluation category.
     */
    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $category = EvaluationCategory::find($id);

        if (!$category) {
            return $this->error('Evaluation category not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name'       => 'sometimes|required|string|max:255',
            'sort_order' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $oldValues = $category->toArray();
        $dirty = false;

        foreach (['name', 'sort_order'] as $field) {
            if ($request->has($field)) {
                $category->$field = $request->$field;
                $dirty = true;
            }
        }

        if (!$dirty) {
            return $this->error('No changes detected', 400);
        }

        $category->save();
        $this->logAudit($category, 'evaluation_category_updated', $request, $oldValues, $category->toArray());

        return response()->json([
            'status'  => 'success',
            'message' => 'Evaluation category updated successfully',
            'data'    => $category,
        ], 200);
    }

    /**
     * Delete an evaluation category.
     */
    public function destroyCategory(Request $request, int $id): JsonResponse
    {
        $category = EvaluationCategory::withCount('questions')->find($id);

        if (!$category) {
            return $this->error('Evaluation category not found', 404);
        }

        $oldValues = $category->toArray();

        // Cascade delete associated questions
        $category->questions()->delete();
        $category->delete();

        $this->logAudit($category, 'evaluation_category_deleted', $request, $oldValues, []);

        return response()->json([
            'status'  => 'success',
            'message' => 'Evaluation category deleted successfully (' . $category->questions_count . ' question(s) removed)',
        ], 200);
    }

    // ──────────────────────────────────────────────
    //  Evaluation Questions
    // ──────────────────────────────────────────────

    /**
     * Create a new question within a category.
     */
    public function storeQuestion(Request $request, int $categoryId): JsonResponse
    {
        $category = EvaluationCategory::find($categoryId);

        if (!$category) {
            return $this->error('Evaluation category not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'question_text' => 'required|string',
            'score'         => 'required|numeric|min:0|max:999.99',
            'sort_order'    => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $question = $category->questions()->create([
            'template_id'   => $category->evaluation_form_id,
            'question_text' => $request->question_text,
            'score'         => $request->score,
            'sort_order'    => $request->integer('sort_order', 0),
        ]);

        $this->logAudit($question, 'evaluation_question_created', $request, [], $question->toArray());

        return response()->json([
            'status'  => 'success',
            'message' => 'Evaluation question created successfully',
            'data'    => [
                'id'         => $question->id,
                'question'   => $question->question_text,
                'max_score'  => (float) $question->score,
                'sort_order' => $question->sort_order,
            ],
        ], 201);
    }

    /**
     * Update an existing evaluation question.
     */
    public function updateQuestion(Request $request, int $id): JsonResponse
    {
        $question = EvaluationQuestion::find($id);

        if (!$question) {
            return $this->error('Evaluation question not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'question_text' => 'sometimes|required|string',
            'score'         => 'sometimes|required|numeric|min:0|max:999.99',
            'sort_order'    => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $oldValues = $question->toArray();
        $dirty = false;

        foreach (['question_text', 'score', 'sort_order'] as $field) {
            if ($request->has($field)) {
                $question->$field = $request->$field;
                $dirty = true;
            }
        }

        if (!$dirty) {
            return $this->error('No changes detected', 400);
        }

        $question->save();
        $this->logAudit($question, 'evaluation_question_updated', $request, $oldValues, $question->toArray());

        return response()->json([
            'status'  => 'success',
            'message' => 'Evaluation question updated successfully',
            'data'    => [
                'id'         => $question->id,
                'question'   => $question->question_text,
                'max_score'  => (float) $question->score,
                'sort_order' => $question->sort_order,
            ],
        ], 200);
    }

    /**
     * Delete an evaluation question.
     */
    public function destroyQuestion(Request $request, int $id): JsonResponse
    {
        $question = EvaluationQuestion::find($id);

        if (!$question) {
            return $this->error('Evaluation question not found', 404);
        }

        // Prevent deletion if answers exist
        $answersCount = $question->answers()->count();
        if ($answersCount > 0) {
            return $this->error(
                "Cannot delete question: it has {$answersCount} evaluation answer(s).",
                400
            );
        }

        $oldValues = $question->toArray();
        $question->delete();

        $this->logAudit($question, 'evaluation_question_deleted', $request, $oldValues, []);

        return response()->json([
            'status'  => 'success',
            'message' => 'Evaluation question deleted successfully',
        ], 200);
    }
}
