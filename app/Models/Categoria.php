<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    protected $table = 'categorias';

    protected $primaryKey = 'id_categoria';

    public $timestamps = false;

    protected $appends = ['codigo'];

    protected $fillable = ['nombre', 'descripcion', 'activa'];

    public function getCodigoAttribute(): string
    {
        return 'CAT-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'id_categoria', 'id_categoria');
    }
}
