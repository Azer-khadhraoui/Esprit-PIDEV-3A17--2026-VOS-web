<?php

namespace App\Dto\Admin;

class OffreStatDto
{
    public int $total;
    public int $active;
    public int $closed;

    public function __construct(mixed $total, mixed $active, mixed $closed)
    {
        $this->total = (int) $total;
        $this->active = (int) $active;
        $this->closed = (int) $closed;
    }
}
