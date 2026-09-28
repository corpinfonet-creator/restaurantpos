<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    // AQUI ESTABA EL DETALLE: Agregamos 'is_saleable' para permitir que se guarde
    protected $fillable = [
        'name', 
        'price', 
        'stock', 
        'category_id', 
        'image', 
        'is_active', 
        'is_saleable' // <--- IMPORTANTE
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function ingredients()
    {
        return $this->belongsToMany(Product::class, 'product_ingredients', 'product_id', 'ingredient_id')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }
}