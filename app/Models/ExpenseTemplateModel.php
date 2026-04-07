<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseTemplateModel extends Model
{
    protected $table = 'expense_templates';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'code',
        'workflow_id',
        'status',
        'created_by',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'workflow_id' => 'integer',
        'status' => 'integer',
        'created_by' => 'integer',
        'created_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'created_by', 'id');
    }
}
