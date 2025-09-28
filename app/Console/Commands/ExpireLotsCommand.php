<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReagentLot;

class ExpireLotsCommand extends Command
{
    protected $signature = 'lots:expire';
    protected $description = 'Marque EXPIRED les lots dont la date est passée';

    public function handle(): int
    {
        $n = ReagentLot::where('status','ACTIVE')
            ->whereNotNull('expiry_date')
            ->where('expiry_date','<', today())
            ->update(['status'=>'EXPIRED']);

        $this->info("Lots expirés: $n");
        return self::SUCCESS;
    }
}
