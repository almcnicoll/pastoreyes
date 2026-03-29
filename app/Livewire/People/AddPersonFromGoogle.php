<?php

namespace App\Livewire\People;

use App\Actions\ImportPersonFromGoogle;
use App\Services\Google\GoogleContactsService;
use Illuminate\Support\Collection;
use Livewire\Component;

class AddPersonFromGoogle extends Component
{
    public bool $open = false;
    public string $search = '';
    public Collection $results;
    public bool $loading = false;
    public bool $importing = false;
    public bool $error = false;

    // Preview state
    public ?array $preview = null;
    public ?string $selectedResourceName = null;

    protected $listeners = [
        'open-add-from-google' => 'openModal',
    ];

    public function mount(): void
    {
        $this->results = collect();
    }

    public function openModal(): void
    {
        $this->reset(['search', 'loading', 'importing', 'error', 'preview', 'selectedResourceName']);
        $this->results = collect();
        $this->open    = true;
    }

    public function updatedSearch(): void
    {
        if (strlen($this->search) < 2) {
            $this->results = collect();
            return;
        }

        $this->loading = true;
        $this->error   = false;
        $this->preview = null;
        $this->selectedResourceName = null;

        try {
            $service       = new GoogleContactsService(auth()->user());
            $this->results = $service->searchContacts($this->search);
        } catch (\Exception $e) {
            $this->error = true;
        }

        $this->loading = false;
    }

    public function selectContact(string $resourceName): void
    {
        $this->loading = true;
        $this->selectedResourceName = $resourceName;

        try {
            $service = new GoogleContactsService(auth()->user());
            $contact = $service->getContact($resourceName);

            if ($contact) {
                $this->preview = $this->buildPreview($contact);
            }
        } catch (\Exception $e) {
            $this->error = true;
        }

        $this->loading = false;
    }

    /**
     * Build a human-readable preview of what will be imported.
     */
    protected function buildPreview(array $contact): array
    {
        $rawData   = $contact['rawData'] ?? [];
        $birthdays = $rawData['birthdays'] ?? [];
        $events    = $rawData['events'] ?? [];
        $addresses = $rawData['addresses'] ?? [];
        $genders   = $rawData['genders'] ?? [];

        $preview = [
            'displayName' => $contact['displayName'],
            'photoUrl'    => $contact['photoUrl'],
            'items'       => [],
        ];

        // Gender
        $gender = strtolower($genders[0]['value'] ?? '');
        if ($gender) {
            $preview['items'][] = [
                'icon'  => 'gender',
                'label' => ucfirst($gender),
            ];
        }

        // Birthday
        foreach ($birthdays as $birthday) {
            $date = $birthday['date'] ?? null;
            if ($date) {
                $yearUnknown = !isset($date['year']) || $date['year'] === 0;
                $formatted   = $yearUnknown
                    ? sprintf('%d %s', $date['day'] ?? '?', \Carbon\Carbon::create(null, $date['month'] ?? 1)->format('F'))
                    : sprintf('%d %s %d', $date['day'] ?? '?', \Carbon\Carbon::create(null, $date['month'] ?? 1)->format('F'), $date['year']);

                $preview['items'][] = [
                    'icon'  => 'birthday',
                    'label' => 'Birthday: ' . $formatted . ($yearUnknown ? ' (year unknown)' : ''),
                ];
            }
        }

        // Events
        foreach ($events as $event) {
            $date = $event['date'] ?? null;
            $type = $event['formattedType'] ?? ucfirst($event['type'] ?? 'Event');
            if ($date) {
                $formatted = sprintf(
                    '%d %s%s',
                    $date['day'] ?? '?',
                    \Carbon\Carbon::create(null, $date['month'] ?? 1)->format('F'),
                    isset($date['year']) && $date['year'] ? ' ' . $date['year'] : ''
                );
                $preview['items'][] = [
                    'icon'  => 'event',
                    'label' => $type . ': ' . $formatted,
                ];
            }
        }

        // Addresses
        foreach ($addresses as $address) {
            $parts = array_filter([
                $address['streetAddress'] ?? null,
                $address['city'] ?? null,
                $address['postalCode'] ?? null,
            ]);
            if ($parts) {
                $preview['items'][] = [
                    'icon'  => 'address',
                    'label' => 'Address: ' . implode(', ', $parts),
                ];
            }
        }

        return $preview;
    }

    public function import(): void
    {
        if (!$this->selectedResourceName) {
            return;
        }

        $this->importing = true;

        try {
            $person = (new ImportPersonFromGoogle())->execute($this->selectedResourceName);

            $this->open = false;
            $this->dispatch('notify', message: 'Contact imported successfully.');
            $this->redirect(route('people.show', $person), navigate: true);
        } catch (\Exception $e) {
            $this->error    = true;
            $this->importing = false;
            $this->dispatch('notify', message: 'Import failed: ' . $e->getMessage());
        }
    }

    public function back(): void
    {
        $this->preview              = null;
        $this->selectedResourceName = null;
    }

    public function closeModal(): void
    {
        $this->open = false;
        $this->reset(['search', 'loading', 'importing', 'error', 'preview', 'selectedResourceName']);
        $this->results = collect();
    }

    public function render()
    {
        return view('livewire.people.add-person-from-google');
    }
}
