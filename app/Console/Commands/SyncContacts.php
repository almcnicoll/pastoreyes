<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ContactSyncService;
use Illuminate\Console\Command;

class SyncContacts extends Command
{
    protected $signature = 'pastoreyes:sync-contacts
                            {--user= : Only sync a specific user by ID}
                            {--batch-size=20 : Number of contacts to process per user per run}
                            {--all : Process all contacts for each user in one run (ignores batch size)}';

    protected $description = 'Sync Google Contacts for all users, processing one batch per user per run';

    public function handle(): int
    {
        $batchSize  = (int) $this->option('batch-size');
        $specificId = $this->option('user');
        $processAll = $this->option('all');

        // If --all flag is set, use a very large batch to process everything
        if ($processAll) {
            $batchSize = 99999;
            $this->info('Running full sync for all contacts (--all flag set).');
        }

        // Fetch users to process
        $users = $specificId
            ? User::where('id', $specificId)->where('is_active', true)->get()
            : User::where('is_active', true)
                ->whereNotNull('google_oauth_refresh_token')
                ->get();

        if ($users->isEmpty()) {
            $this->warn('No eligible users found.');
            return self::SUCCESS;
        }

        $totalProcessed = 0;
        $totalFlagged   = 0;

        foreach ($users as $user) {
            // Skip users with no Google OAuth refresh token
            if (empty($user->google_oauth_refresh_token)) {
                $this->line("  Skipping user {$user->id} — no Google refresh token.");
                continue;
            }

            $this->line("Processing user {$user->id}...");

            try {
                $service = new ContactSyncService($user, $batchSize);
                $result  = $service->runBatch();

                $totalProcessed += $result['processed'];
                $totalFlagged   += $result['flagged'];

                if ($result['reset']) {
                    $this->line("  ✓ Reached end of contacts — cursor reset for next cycle.");
                } else {
                    $this->line("  ✓ Processed {$result['processed']} contacts, flagged {$result['flagged']} differences.");
                }

            } catch (\Exception $e) {
                $this->error("  ✗ Failed for user {$user->id}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Sync complete. Total processed: {$totalProcessed}, total flagged: {$totalFlagged}.");

        return self::SUCCESS;
    }
}