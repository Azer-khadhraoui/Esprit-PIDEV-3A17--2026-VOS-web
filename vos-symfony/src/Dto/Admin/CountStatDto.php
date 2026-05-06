<?php

namespace App\Dto\Admin;

class CountStatDto
{
    public ?string $label;
    public int|float $total;

    public function __construct(mixed $label, mixed $total)
    {
        if ($label instanceof \DateTimeInterface) {
            $this->label = $label->format('Y-m-d');
        } elseif ($label === null) {
            $this->label = null;
        } else {
            $this->label = (string) $label;
        }

        $this->total = is_float($total) ? $total : (int) $total;
    }
}
