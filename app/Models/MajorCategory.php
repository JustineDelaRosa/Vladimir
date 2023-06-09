<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MajorCategory extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'major_category_name',
        'est_useful_life',
        'is_active',
    ];
    protected $casts = [
        'is_active' => 'boolean'
    ];

    // public function findDivisionIdAttribute($value)
    // {
    //     return $this->attributes['division_id'] = Division::find($value);
    // }


    public function minorCategory()
    {
        return $this->hasMany(MinorCategory::class, 'major_category_id', 'id');
    }

    public function fixedAsset()
    {
        return $this->hasMany(FixedAsset::class, 'major_category_id', 'id');
    }
}
