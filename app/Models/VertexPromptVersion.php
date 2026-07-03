<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VertexPromptVersion extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'vertex_prompt_id',
        'version_number',
        'data',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'data'           => 'array',
            'version_number' => 'integer',
            'created_at'     => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<VertexPrompt, $this>
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(VertexPrompt::class, 'vertex_prompt_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
