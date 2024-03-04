<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * @property string $name
 * @property int $is_visible
 *
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder query()
 */
class SearchableModelHasUuids extends Model
{
    use HasUuids;
    use Searchable;
    use SoftDeletes;

    protected $primaryKey = 'uuid';

    /**
     * @return array{name: string, is_visible: int}
     */
    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'is_visible' => $this->is_visible,
        ];
    }

    protected $fillable = ['name', 'is_visible'];
}
