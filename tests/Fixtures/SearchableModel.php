<?php

declare(strict_types=1);

namespace Zing\LaravelScout\OpenSearch\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class SearchableModel extends Model
{
    use Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['id'];

    public function searchableAs()
    {
        return 'table';
    }

    public function scoutMetadata()
    {
        return [];
    }
}
