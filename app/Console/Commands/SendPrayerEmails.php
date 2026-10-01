<?php

namespace App\Console\Commands;

use App\Mail\PrayerReminder;
use App\Models\User;
use App\Services\PrayerEmailScheduler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class SendPrayerEmails extends Command
{
    protected $signature = 'pastoreyes:send-prayer-emails
                            {--user= : Only process a specific user by ID}
                            {--force : Send today\'s email now, ignoring the configured time and any earlier send today}';

    protected $description = 'Email each user the people with outstanding prayer requests who are due today';

    public function handle(PrayerEmailScheduler $scheduler): int
    {
        $users = User::where('is_active', true)
            ->where('settings->prayer_email->enabled', true)
            ->when($this->option('user'), fn($q, $id) => $q->where('id', $id))
            ->get();

        foreach ($users as $user) {
            try {
                $config = $user->settings['prayer_email'];
                $now    = now();

                $day = $this->option('force')
                    ? $now->copy()->setTimezone($config['timezone'] ?? 'Europe/London')->isoWeekday()
                    : PrayerEmailScheduler::dueDay($config, $now);

                if ($day === null) {
                    continue;
                }

                // Lets the encrypted names/titles decrypt without a lookup per attribute
                Auth::setUser($user);

                $scheduler->rebalance($user);
                $people = $scheduler->peopleForDay($user, $day);

                if ($people->isNotEmpty()) {
                    Mail::to($user->email)->send(new PrayerReminder($people));
                    $this->line("User {$user->id}: emailed {$people->count()} people.");
                }

                // Record the send even when nobody is due, so the empty day isn't re-checked all window
                $settings = $user->settings;
                $settings['prayer_email']['last_sent_on'] = $now->copy()
                    ->setTimezone($config['timezone'] ?? 'Europe/London')->toDateString();
                $user->update(['settings' => $settings]);
            } catch (\Throwable $e) {
                $this->error("User {$user->id}: failed — " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
