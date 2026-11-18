<?php

namespace App\Livewire;

use App\Services\PersonSearch;
use Livewire\Component;

/**
 * Site-wide search shown in the top navigation. People only for now.
 */
class GlobalSearch extends Component
{
    public string $search = '';

    protected function results()
    {
        return PersonSearch::find(auth()->id(), $this->search);
    }

    /** Enter key: go straight to the top result. */
    public function go()
    {
        $first = $this->results()->first();

        return $first ? redirect()->route('people.show', $first) : null;
    }

    public function render()
    {
        return view('livewire.global-search', ['results' => $this->results()]);
    }
}
