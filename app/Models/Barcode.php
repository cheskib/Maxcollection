<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barcode extends Model
{
    public const TYPE_BAG = 'bag';

    public const TYPE_BOX = 'box';

    public const TYPE_DIVIDER = 'divider';

    /** Code prefix for each type: BAG-000123, BOX-000042, DIV-000087. */
    public const PREFIXES = [
        self::TYPE_BAG => 'BAG',
        self::TYPE_BOX => 'BOX',
        self::TYPE_DIVIDER => 'DIV',
    ];

    protected $fillable = ['type', 'code', 'label', 'print_run', 'printed_at', 'voided_at', 'void_reason'];

    protected function casts(): array
    {
        return [
            'printed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /** Divider display name, falling back to the code itself. */
    public function displayLabel(): string
    {
        return $this->label ?? $this->code;
    }
}
