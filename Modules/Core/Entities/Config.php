<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    protected $table = 'core_config';

    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'json',
    ];

    public static function get($key, $default = null)
    {
        $config = static::where('key', $key)->first();
        return $config ? $config->value : $default;
    }

    public static function set($key, $value)
    {
        $config = static::where('key', $key)->first();
        if ($config) {
            $config->where('key', $key)->update([
                'value' => $value,
            ]);
        } else {
            static::create([
                'key' => $key,
                'value' => $value,
            ]);
        }
    }

    public static function del($key)
    {
        static::where('key', $key)->delete();
    }
}
