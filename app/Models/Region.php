<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $fillable = ['name', 'slug'];

    public function constituencies(): HasMany
    {
        return $this->hasMany(Constituency::class)->orderBy('name');
    }
}
