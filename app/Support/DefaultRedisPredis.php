<?php

namespace App\Support;

use Predis\Client as RedisClient;

final class DefaultRedisPredis
{
    public static function client(): RedisClient
    {
        $config = [
            'scheme' => 'tcp',
            'host' => config('database.redis.default.host', '127.0.0.1'),
            'port' => (int) config('database.redis.default.port', 6379),
        ];

        $password = config('database.redis.default.password');
        if (filled($password)) {
            $config['password'] = $password;
        }

        return new RedisClient($config);
    }
}
