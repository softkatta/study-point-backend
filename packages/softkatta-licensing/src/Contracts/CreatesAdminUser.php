<?php

namespace SoftKatta\Licensing\Contracts;

interface CreatesAdminUser
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     * @return object{id: mixed, name: string, email: string}
     */
    public function create(array $data): object;
}
