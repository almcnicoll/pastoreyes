<?php

namespace App\Livewire;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class MailSettings extends Component
{
    public string $host = '';
    public int $port = 587;
    public string $encryption = 'starttls'; // 'starttls' or 'ssl'
    public string $username = '';
    public string $password = '';
    public string $fromAddress = '';
    public string $fromName = '';

    public bool $hasPassword = false;
    public ?string $testResult = null;

    public function mount(): void
    {
        abort_if(!auth()->user()->is_admin, 403);

        // Show saved values, falling back to what .env currently provides
        $mail = AppSetting::mail() ?? [];

        $this->host        = $mail['host'] ?? (string) config('mail.mailers.smtp.host');
        $this->port        = (int) ($mail['port'] ?? config('mail.mailers.smtp.port', 587));
        $this->encryption  = $mail['encryption'] ?? 'starttls';
        $this->username    = $mail['username'] ?? (string) config('mail.mailers.smtp.username');
        $this->fromAddress = $mail['from_address'] ?? (string) config('mail.from.address');
        $this->fromName    = $mail['from_name'] ?? (string) config('mail.from.name');
        $this->hasPassword = !empty($mail['password']);
    }

    public function save(): void
    {
        abort_if(!auth()->user()->is_admin, 403);

        $this->validate([
            'host'        => 'required|string|max:255',
            'port'        => 'required|integer|min:1|max:65535',
            'encryption'  => 'required|in:starttls,ssl',
            'username'    => 'nullable|string|max:255',
            'password'    => 'nullable|string|max:255',
            'fromAddress' => 'required|email',
            'fromName'    => 'nullable|string|max:255',
        ]);

        // Blank password field means "keep the saved one"
        $password = $this->password !== '' ? $this->password : (AppSetting::mail()['password'] ?? null);

        AppSetting::saveMail([
            'host'         => $this->host,
            'port'         => $this->port,
            'encryption'   => $this->encryption,
            'username'     => $this->username,
            'password'     => $password,
            'from_address' => $this->fromAddress,
            'from_name'    => $this->fromName,
        ]);

        $this->password    = '';
        $this->hasPassword = !empty($password);
        $this->dispatch('notify', message: 'Mail settings saved.');
    }

    public function sendTest(): void
    {
        abort_if(!auth()->user()->is_admin, 403);

        // Settings are applied at boot, so apply the form values for this request to test them unsaved
        config([
            'mail.default'               => 'smtp',
            'mail.mailers.smtp.host'     => $this->host,
            'mail.mailers.smtp.port'     => $this->port,
            'mail.mailers.smtp.scheme'   => $this->encryption === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => $this->username ?: null,
            'mail.mailers.smtp.password' => $this->password !== '' ? $this->password : (AppSetting::mail()['password'] ?? null),
            'mail.from.address'          => $this->fromAddress,
            'mail.from.name'             => $this->fromName,
        ]);
        Mail::purge('smtp');

        try {
            Mail::raw('This is a test email from ' . config('app.name') . '. Your mail settings work.', function ($m) {
                $m->to(auth()->user()->email)->subject('Test email');
            });
            $this->testResult = 'Test email sent to ' . auth()->user()->email . '.';
        } catch (\Throwable $e) {
            $this->testResult = 'Failed: ' . $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.mail-settings');
    }
}
