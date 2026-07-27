<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Item;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CaptureService
{
    /**
     * Store an uploaded photograph. The first photograph creates the item
     * (PROJECT.md rule 2); later photographs attach to the same item.
     */
    public function storeImage(User $user, ?Item $item, UploadedFile $photo, ?int $batchId = null, ?int $collectionId = null): Item
    {
        return DB::transaction(function () use ($user, $item, $photo, $batchId, $collectionId) {
            $item ??= Item::create(['user_id' => $user->id, 'batch_id' => $batchId, 'collection_id' => $collectionId]);

            $path = $photo->store("original/{$item->id}", 'local');

            $item->images()->create([
                'path' => $path,
                'original_filename' => $photo->getClientOriginalName(),
                'mime_type' => $photo->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $photo->getSize() ?: 0,
            ]);

            return $item;
        });
    }

    /**
     * Delete one photograph. Deleting the final remaining photograph deletes
     * the item itself (PROJECT.md rule 3).
     *
     * @return bool true when the item was deleted along with the image
     */
    public function deleteImage(Image $image): bool
    {
        $item = $image->item;

        Storage::disk('local')->delete($image->path);
        $image->delete();

        if ($item->images()->count() === 0) {
            $this->deleteItem($item);

            return true;
        }

        return false;
    }

    /**
     * Delete an item together with all of its image files.
     */
    public function deleteItem(Item $item): void
    {
        foreach ($item->images as $image) {
            Storage::disk('local')->delete($image->path);
        }

        Storage::disk('local')->deleteDirectory("original/{$item->id}");

        $item->delete();
    }
}
