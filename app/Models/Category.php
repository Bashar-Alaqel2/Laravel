<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Category extends Model {
    protected $table = 'categories';
    protected $primaryKey = 'category_id';
    public $timestamps = false;
    protected $fillable = ['category_name', 'price', 'max_duration', 'max_size', 'discount_type', 'discount_value'];

    public function advertisements() {
        return $this->hasMany(Advertisement::class, 'category_id', 'category_id');
    }
}
