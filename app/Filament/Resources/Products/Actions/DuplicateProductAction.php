<?php

namespace App\Filament\Resources\Products\Actions;

use App\Models\Product;
use Filament\Actions\ReplicateAction;
use Illuminate\Support\Str;

class DuplicateProductAction
{
    public static function make(): ReplicateAction
    {
        return ReplicateAction::make()
            ->label('Duplicate')
            ->successNotificationTitle('Product duplicated')
            ->beforeReplicaSaved(function (Product $replica, Product $record): void {
                $replica->name = $record->name.' (Copy)';
                $replica->slug = self::uniqueSlug($record->slug);
                $replica->public_id = self::uniquePublicId();
                $replica->is_active = false;
            })
            ->after(function (Product $replica, Product $record): void {
                $record->loadMissing(['images', 'colors', 'sizes', 'specifications', 'collections']);

                foreach ($record->images as $image) {
                    $replica->images()->create([
                        'path' => $image->path,
                        'alt_text' => $image->alt_text,
                        'sort_order' => $image->sort_order,
                    ]);
                }

                foreach ($record->colors as $color) {
                    $replica->colors()->create([
                        'name' => $color->name,
                        'hex' => $color->hex,
                        'sort_order' => $color->sort_order,
                    ]);
                }

                foreach ($record->sizes as $size) {
                    $replica->sizes()->create([
                        'label' => $size->label,
                        'sort_order' => $size->sort_order,
                    ]);
                }

                foreach ($record->specifications as $specification) {
                    $replica->specifications()->create([
                        'label' => $specification->label,
                        'value' => $specification->value,
                        'sort_order' => $specification->sort_order,
                    ]);
                }

                $replica->collections()->sync($record->collections->modelKeys());
            });
    }

    private static function uniqueSlug(string $slug): string
    {
        $base = Str::slug($slug.'-copy') ?: 'product-copy';
        $candidate = $base;
        $suffix = 2;

        while (Product::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private static function uniquePublicId(): string
    {
        do {
            $publicId = (string) random_int(1000000000, 9999999999);
        } while (Product::withTrashed()->where('public_id', $publicId)->exists());

        return $publicId;
    }
}
