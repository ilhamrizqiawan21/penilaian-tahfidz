<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneExpiredReceipts extends Command
{
    protected $signature = 'tahfidz:prune-receipts';

    protected $description = 'Hapus mutation receipts yang sudah kedaluwarsa';

    public function handle(): int
    {
        $deleted = DB::table('mutation_receipts')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();

        $this->info("Berhasil menghapus {$deleted} mutation receipt yang kedaluwarsa.");

        return self::SUCCESS;
    }
}
