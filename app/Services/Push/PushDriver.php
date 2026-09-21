<?php

namespace App\Services\Push;

interface PushDriver
{
    public function send(string $token, string $title, string $body, array $data = []): bool;
}
