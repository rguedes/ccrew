<span>{{ $clan['tag'] }}</span>
<span>{{ $clan['name'] }}</span>

<ul>
    @foreach($clan['members'] as $member)
        <li>
            @include('clan.member', ['data' => $member])
        </li>
    @endforeach
</ul>