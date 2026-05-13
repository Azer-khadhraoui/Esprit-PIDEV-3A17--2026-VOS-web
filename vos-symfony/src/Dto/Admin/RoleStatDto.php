<?php

namespace App\Dto\Admin;

class RoleStatDto
{
    public string $label;
    public int $total;

    public function __construct(string $label, mixed $total)
    {
        $this->label = $label;
        $this->total = (int) $total;
    }
}
