<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMetadataRequest;
use App\Models\Item;
use App\Models\Metadata;
use App\Services\MetadataService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EditItemController extends Controller
{
    public function edit(Item $item): Response
    {
        $metadata = $item->metadata;

        $values = [];
        foreach (array_keys(Metadata::FIELD_LABELS) as $field) {
            $values[$field] = $metadata?->{$field};
        }

        return Inertia::render('EditItem', [
            'item' => [
                'id' => $item->id,
                'title' => $metadata?->primaryTitle() ?? "Item #{$item->id}",
                'category' => $metadata?->category ?? 'unsupported',
                'values' => $values,
                'images' => $item->images()->orderBy('id')->get()->map(fn ($image) => ['id' => $image->id, 'original_filename' => $image->original_filename, 'version' => $image->versionTag()])->all(),
            ],
            'categoryFields' => Metadata::CATEGORY_FIELDS,
            'fieldLabels' => Metadata::FIELD_LABELS,
        ]);
    }

    public function update(UpdateMetadataRequest $request, Item $item, MetadataService $service): RedirectResponse
    {
        $service->update(
            $item,
            $request->user(),
            $request->validated('category'),
            $request->safe()->except('category'),
        );

        return redirect()
            ->route('items.show', $item)
            ->with('status', 'Changes saved.');
    }
}
