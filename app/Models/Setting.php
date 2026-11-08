<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ajuste local de la app (clave/valor) en el SQLite del dispositivo.
 *
 * @property string $key
 * @property string|null $value
 */
final class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = ['key', 'value'];
}
