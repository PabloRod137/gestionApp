<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalRevision extends Model
{
    public $timestamps = false;

    protected $fillable = ['proposal_id', 'version', 'data', 'user_id', 'created_at'];

    protected $casts = ['data' => 'array', 'created_at' => 'datetime'];
}
