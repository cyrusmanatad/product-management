<?php

namespace App\Services;

class UserService
{
    public function create(array $data): never
    {
        throw new \LogicException('Staff must join through a vendor invitation.');
    }
}
