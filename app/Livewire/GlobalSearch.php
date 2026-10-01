<?php

namespace App\Livewire;

use App\Models\Person;
use Livewire\Component;

/**
 * Site-wide search shown in the top navigation. People only for now.
 */
class GlobalSearch extends Component
{
    public string $search = '';

    /** Names are encrypted, so matching happens in PHP across every name a person has. */
    protected function results()
    {
        $terms = preg_split('/\s+/', strtolower(trim($this->search)), -1, PREG_SPLIT_NO_EMPTY);
        if (!$terms) {
            return collect();
        }

        return Person::where('user_id', auth()->id())
            ->with(['primaryName', 'names'])
            ->get()
            ->filter(function ($person) use ($terms) {
                $haystack = strtolower($person->display_name . ' ' . $person->names
                    ->map(fn($n) => implode(' ', [$n->first_name, $n->middle_names, $n->last_name, $n->preferred_name]))
                    ->implode(' '));

                foreach ($terms as $term) {
                    if (!str_contains($haystack, $term)) {
                        return false;
                    }
                }
                return true;
            })
            ->sortBy(fn($p) => strtolower($p->display_name))
            ->take(8)
            ->values();
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
