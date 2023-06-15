<?php

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

    public function searchableAs(): string
    {
        return 'searchable-model';
    }

    /**
     * @return array{id: mixed}
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->getScoutKey(),
            'name' => $this->name,
            'is_visible' => $this->is_visible,
        ];
    }

    /**
     * @var string[]
     */
    protected $fillable = ['name', 'is_visible'];
}
