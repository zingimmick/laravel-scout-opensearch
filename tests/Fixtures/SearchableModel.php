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
     * @var array<string>
     */
    protected $fillable = ['id'];

    public function searchableAs(): string
    {
        return 'table';
    }

    /**
     * @return array<string, mixed>
     */
    public function scoutMetadata()
    {
        return [];
    }
}
