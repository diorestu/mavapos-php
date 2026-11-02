<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\User;
use App\Services\CommercialDemoDataService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SeedCommercialDemo extends Command
{
    protected $signature = 'demo:seed-commercial {owner : Owner ID} {--branch= : Branch ID belonging to owner}';

    protected $description = 'Add marked commercial demo history and pie product photographs to one local business';

    public function handle(CommercialDemoDataService $service): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Data demo hanya dapat dibuat pada lingkungan local/testing.');

            return self::FAILURE;
        }
        $owner = User::query()->whereKey($this->argument('owner'))->where('role', 'owner')->first();
        $branch = $owner ? Branch::withoutGlobalScopes()->whereKey($this->option('branch'))->where('user_id', $owner->id)->where('is_active', true)->first() : null;
        if (! $owner || ! $branch) {
            $this->error('Owner dan cabang aktif harus berasal dari usaha yang sama.');

            return self::FAILURE;
        }
        try {
            $result = $service->seed($owner, $branch);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Data demo untuk '.$owner->name.' / '.$branch->name.' berhasil disiapkan.');
        $this->line($result['sales'].' transaksi baru; '.$result['products'].' produk katalog; '.$result['customers'].' pelanggan demo.');

        return self::SUCCESS;
    }
}
