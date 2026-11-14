<p>These people have active prayer needs:</p>

<ul>
@foreach($people as $person)
    <li>
        <a href="{{ route('people.show', ['person' => $person->id, 'tab' => 'goals_prayer']) }}">{{ $person->display_name }}</a>
        — {{ $person->outstanding }} active {{ \Illuminate\Support\Str::plural('prayer need', $person->outstanding) }}
    </li>
@endforeach
</ul>

<p style="color:#6b7280;font-size:12px">Sent by {{ config('app.name') }}. Change when these emails arrive under Settings &rarr; Prayer Emails.</p>
