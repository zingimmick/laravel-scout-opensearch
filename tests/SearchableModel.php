<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * @property string $name
 * @property int $is_visible
 *
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder query()
 */
class SearchableModel extends Model
{
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

    protected $fillable = ['name', 'is_visible'];
}
