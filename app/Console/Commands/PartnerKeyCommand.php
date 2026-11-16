<?php

namespace App\Console\Commands;

use App\Models\PartnerApiKey;
use App\Models\User;
use Illuminate\Console\Command;

class PartnerKeyCommand extends Command
{
    protected $signature = 'partner:key {email : Owner email} {--name=Integration} {--sandbox} {--days=90} {--revoke= : Revoke a key ID instead of issuing}';

    protected $description = 'Issue or revoke a read-only third-party API key for a merchant';

    public function handle(): int
    {
        $owner = User::where('email', $this->argument('email'))->where('role', 'owner')->first();
        if (! $owner) {
            $this->error('Owner not found.');

            return self::FAILURE;
        }
        if ($this->option('revoke') !== null) {
            $key = PartnerApiKey::where('owner_id', $owner->id)->find($this->option('revoke'));
            if (! $key) {
                $this->error('Key not found for this owner.');

                return self::FAILURE;
            }
            $key->update(['revoked_at' => now()]);
            $this->info('Key revoked.');

            return self::SUCCESS;
        }
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
        $name = trim((string) $this->option('name'));
        if (! $days || $days < 1 || $days > 365 || $name === '' || mb_strlen($name) > 120) {
            $this->error('Use --days=1..365 and a name of 1..120 characters.');

            return self::FAILURE;
        }
        [$key, $plain] = PartnerApiKey::issue($owner, $name, (bool) $this->option('sandbox'), now()->addDays($days));
        $this->info('Key ID: '.$key->id.'; copy the token now, it is only shown once:');
        $this->line($plain);

        return self::SUCCESS;
    }
}
