<div>
    {{--
        Reuse the shared Timeline Livewire component, pre-filtered to this person.
        The Timeline component accepts an optional personId prop which locks it
        to a single person and pre-populates the person filter.
    --}}
    <livewire:timeline :personId="$person->id" :key="'timeline-person-'.$person->id" />
</div>
