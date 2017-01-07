<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    protected $table = 'core_api_log';
    protected $fillable = ['url', 'host', 'action', 'params', 'status_code', 'data', 'start_time', 'end_time', 'cost_time'];

    protected $casts = [
        'params' => 'json',
        'data' => 'json',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }
}
