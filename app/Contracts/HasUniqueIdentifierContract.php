<?php

namespace App\Contracts;

interface HasUniqueIdentifierContract
{
    public function getUID(): string;
}
