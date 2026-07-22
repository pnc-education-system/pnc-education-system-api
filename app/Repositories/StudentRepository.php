<?php

namespace App\Repositories;

use App\Models\Student;

/**
 * StudentRepository
 *
 * Encapsulates data-access logic for the Student model.
 * Controllers and Services should depend on this repository
 * rather than querying the model directly, keeping persistence
 * concerns isolated in one place.
 */
class StudentRepository
{
    /**
     * Find a student by primary key, loading the selection batch relationship.
     *
     * @param  int  $id
     * @return Student|null
     */
    public function findById(int $id): ?Student
    {
        return Student::with('selectionBatch')->find($id);
    }
}
