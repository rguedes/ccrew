WN8 30D > 2000<br />
> 150 batalhas<br />

<hr />
<table>
    <thead>
        <tr>
            <td>Clan</td>
            <td>#</td>
        </tr>
    </thead>
    <tbody>
        @foreach($countByClan as $key => $data)
            <tr>
                <td>{{ $key }}</td>
                <td>{{ $data }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<hr/>

<table>
    <thead>
        <tr>
            <td>Stats</td>
            <td>#</td>
        </tr>
    </thead>
    <tbody>
        @foreach($countByWn8 as $key => $data)
            <tr>
                <td>{{ studly_case($key) }}</td>
                <td>{{ $data }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<hr/>



<table>
    <thead>
        <tr>
            <td>Player</td>
            <td>WN8</td>
            <td>AVG Tier</td>
            <td>Battles</td>
            <td>Clan</td>
        </tr>
    </thead>
    <tbody>
        @foreach($output as $data)
            <tr>
                <td>{{$data['nickname']}}</td>
                <td>{{ round($data['wn11'], 0) }}</td>
                <td></td>
                <td>{{ round($data['battles_3'],0)}}</td>
                <td>{{ $data['clan_id']}}</td>
            </tr>
        @endforeach
    </tbody>
</table>