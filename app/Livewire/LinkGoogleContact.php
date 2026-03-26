<?php

namespace App\Livewire;

use App\Models\Person;
use App\Services\Google\GoogleContactsService;
use Illuminate\Support\Collection;
use Livewire\Component;

class LinkGoogleContact extends Component
{
    public bool $open = false;
    public ?int $personId = null;
    public string $search = '';
    public Collection $results;
    public bool $loading = false;
    public bool $error = false;

    protected $listeners = [
        'open-link-google-contact' => 'openModal',
    ];

    public function mount(): void
    {
        $this->results = collect();
    }

    public function openModal(int $personId): void
    {
        $this->personId = $personId;
        $this->search   = '';
        $this->results  = collect();
        $this->open     = true;
    }

    public function updatedSearch(): void
    {
        if (strlen($this->search) < 2) {
            $this->results = collect();
            return;
        }

        $this->loading = true;
        $this->error   = false;

        try {
            $service       = new GoogleContactsService(auth()->user());
            $this->results = $service->searchContacts($this->search);
        } catch (\Exception $e) {
            $this->error = true;
        }

        $this->loading = false;
    }

    public function linkContact(string $resourceName): void
    {
        $person = Person::findOrFail($this->personId);
        abort_if($person->user_id !== auth()->id(), 403);

        $person->google_contact_id = $resourceName;
        $person->save();

        $this->open = false;
        $this->dispatch('notify', message: 'Google Contact linked.');
        $this->dispatch('google-contact-linked');
    }

    public function closeModal(): void
    {
        $this->open     = false;
        $this->search   = '';
        $this->results  = collect();
        $this->personId = null;
    }

    public function render()
    {
        return view('livewire.link-google-contact');
    }
}
