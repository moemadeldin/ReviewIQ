<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * This trait should be used by models that have 'name' and 'slug' properties.
 *
 * @property string $name
 * @property string $slug
 */
trait Sluggable
{
    protected static function bootSluggable(): void
    {
        static::creating(function (Model $model): void {
            $name = $model->getAttribute('name');
            if (is_string($name) && $name !== '') {
                /** @var string $slug */
                $slug = Str::slug($name);
                $model->setAttribute('slug', $slug);
            }
        });

        static::updating(function (Model $model): void {
            $name = $model->getAttribute('name');
            $originalName = $model->getOriginal('name');
            if (is_string($name) && $name !== '' && $name !== $originalName) {
                /** @var string $slug */
                $slug = Str::slug($name);
                $model->setAttribute('slug', $slug);
            }
        });
    }
}
