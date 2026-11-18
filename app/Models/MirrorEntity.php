<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fila del espejo local: el resource de la API tal como llegó.
 *
 * @property string $entity
 * @property string $id
 * @property string $payload
 * @property string|null $updated_at
 * @property string|null $deleted_at
 * @property string $fetched_at
 */
final class MirrorEntity extends Model
{
    protected $table = 'mirror_entities';

    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['entity', 'id', 'payload', 'updated_at', 'deleted_at', 'fetched_at'];

    /** @return array<string, mixed> */
    public function data(): array
    {
        $decoded = json_decode($this->payload, true);

        return is_array($decoded) ? $decoded : [];
    }
}
