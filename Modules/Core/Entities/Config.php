<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    protected $table = 'core_config';

    protected $fillable = ['key', 'value'];
}
