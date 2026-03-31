<?php

namespace App\Livewire;

use App\Actions\ExportUserData;
use App\Actions\ImportUserData;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataSettings extends Component
{
    use WithFileUploads;

    // Export
    public string $exportPassphrase = '';
    public string $exportPassphraseConfirm = '';
    public bool $exporting = false;

    // Import
    public $importFile = null;
    public string $importPassphrase = '';
    public bool $importFileValidated = false;
    public bool $showImportConfirm = false;
    public bool $replaceExisting = false;
    public bool $importing = false;
    public ?array $importSummary = null;
    public ?string $importError = null;

    public function export(): StreamedResponse
    {
        $this->validate([
            'exportPassphrase'        => 'required|min:8',
            'exportPassphraseConfirm' => 'required|same:exportPassphrase',
        ], [
            'exportPassphraseConfirm.same' => 'Passphrases do not match.',
        ]);

        $this->exporting = true;

        try {
            $encrypted = (new ExportUserData())->execute(auth()->user(), $this->exportPassphrase);
            $filename  = 'pastoreyes-export-' . now()->format('Y-m-d') . '.pastoreyes';

            $this->exportPassphrase        = '';
            $this->exportPassphraseConfirm = '';
            $this->exporting               = false;

            return response()->streamDownload(function () use ($encrypted) {
                echo $encrypted;
            }, $filename, [
                'Content-Type' => 'application/octet-stream',
            ]);

        } catch (\Exception $e) {
            $this->exporting = false;
            $this->addError('exportPassphrase', 'Export failed: ' . $e->getMessage());
        }
    }

    public function validateImportFile(): void
    {
        $this->validate([
            'importFile' => 'required|file|max:51200', // 50MB max
        ]);

        $this->importFileValidated = true;
        $this->importError         = null;
    }

    public function proceedToImportConfirm(): void
    {
        $this->validate([
            'importPassphrase' => 'required|min:1',
        ]);

        // Try decrypting to validate passphrase before showing confirm screen
        try {
            $contents = file_get_contents($this->importFile->getRealPath());
            $action   = new ImportUserData();
            // Test decryption only — don't import yet
            $reflection = new \ReflectionClass($action);
            $method     = $reflection->getMethod('decrypt');
            $method->setAccessible(true);
            $json    = $method->invoke($action, $contents, $this->importPassphrase);
            $payload = json_decode($json, true);

            if (!$payload || ($payload['app'] ?? '') !== 'PastorEyes') {
                $this->importError = 'This does not appear to be a valid PastorEyes export file.';
                return;
            }

            $this->showImportConfirm = true;

        } catch (\Exception $e) {
            $this->importError = $e->getMessage();
        }
    }

    public function import(): void
    {
        $this->importing = true;
        $this->importError = null;

        try {
            $contents = file_get_contents($this->importFile->getRealPath());

            $summary = (new ImportUserData())->execute(
                $contents,
                $this->importPassphrase,
                auth()->user(),
                $this->replaceExisting
            );

            $this->importSummary      = $summary;
            $this->showImportConfirm  = false;
            $this->importFileValidated = false;
            $this->importFile         = null;
            $this->importPassphrase   = '';
            $this->replaceExisting    = false;

            $this->dispatch('notify', message: 'Import completed successfully.');

        } catch (\Exception $e) {
            $this->importError = $e->getMessage();
        }

        $this->importing = false;
    }

    public function cancelImport(): void
    {
        $this->reset([
            'importFile', 'importPassphrase', 'importFileValidated',
            'showImportConfirm', 'replaceExisting', 'importing',
            'importSummary', 'importError',
        ]);
    }

    public function render()
    {
        return view('livewire.data-settings');
    }
}
