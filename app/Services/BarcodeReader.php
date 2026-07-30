<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

/**
 * Reads barcodes out of an image using zbarimg (zbar-tools, installed in
 * the server image). Mechanical and fast — validation never waits on AI.
 * Returns every code found; an unreadable image returns nothing, and the
 * pipeline flags rather than fails.
 */
class BarcodeReader
{
    /**
     * @return array<int, string>
     */
    public function read(string $absolutePath): array
    {
        $result = Process::timeout(15)->run(['zbarimg', '--raw', '-q', $absolutePath]);

        if (! $result->successful() && trim($result->output()) === '') {
            return [];
        }

        return collect(explode("\n", $result->output()))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}
