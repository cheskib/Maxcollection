<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Http\Request;

class CollectionService
{
    /**
     * Resolve the collection a request refers to: an existing id, a new
     * (or matching) name, or null for unassigned.
     */
    public function resolveFromRequest(Request $request, User $user): ?int
    {
        $newName = trim((string) $request->input('new_collection_name'));

        if ($newName !== '') {
            return Collection::firstOrCreate(['name' => $newName], ['user_id' => $user->id])->id;
        }

        $id = $request->input('collection_id');

        return $id === null || $id === '' ? null : (int) $id;
    }

    /**
     * Validation rules shared by every endpoint that accepts a collection.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'collection_id' => ['nullable', 'integer', 'exists:collections,id'],
            'new_collection_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
