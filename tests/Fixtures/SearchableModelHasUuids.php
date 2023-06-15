<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class SearchableModelHasUuids extends Model
{
    use HasUuids;
    use Searchable;
    use SoftDeletes;

    protected $primaryKey = 'uuid';

    /**
     * @return array{id: mixed}
     */
    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'is_visible' => $this->is_visible,
        ];
    }

    /**
     * @var string[]
     */
    protected $fillable = ['name', 'is_visible'];
}
