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
    use SoftDeletes;

    use Searchable;

    /**
     * @return string
     */
    public function searchableAs()
    {
        return 'app.table';
    }

    /**
     * @return array
     */
    public function toSearchableArray()
    {
        return [
            'id' => $this->getScoutKey(),
        ];
    }

    protected $fillable = ['name'];
}
