<?php

namespace App\Providers;

use App\Models\AppSetting;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->applyMailSettings();
    }

    /**
     * Admin-configured SMTP settings (Settings > Mail) override the .env mail config.
     */
    protected function applyMailSettings(): void
    {
        try {
            $mail = AppSetting::mail();
        } catch (\Throwable) {
            return; // table not migrated yet, or no database available (e.g. during install)
        }

        if (!$mail || empty($mail['host'])) {
            return;
        }

        config([
            'mail.default'                 => 'smtp',
            'mail.mailers.smtp.host'       => $mail['host'],
            'mail.mailers.smtp.port'       => (int) ($mail['port'] ?? 587),
            'mail.mailers.smtp.scheme'     => ($mail['encryption'] ?? 'starttls') === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username'   => $mail['username'] ?: null,
            'mail.mailers.smtp.password'   => $mail['password'] ?? null,
            'mail.from.address'            => $mail['from_address'] ?: config('mail.from.address'),
            'mail.from.name'               => $mail['from_name'] ?: config('mail.from.name'),
        ]);
    }
}
