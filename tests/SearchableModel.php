<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder query()
 */
class SearchableModel extends Model
{
    use Searchable;
    use SoftDeletes;

    public function searchableAs(): string
    {
        return 'app.table';
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->getScoutKey(),
        ];
    }

    /**
     * @var string[]
     */
    protected $fillable = ['name'];
}
