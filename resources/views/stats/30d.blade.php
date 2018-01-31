<div style="display: inline-block; width: 900px">
    @foreach($diff as $data)
    <span>{{$data['name']}}</span>
    <ul>
        @foreach($data['tanks'] as $tank)
            <li style="float: left; width: 230px; height: 150px">
                (T{{$tank['tank_info']['tier']}}) => {{$tank['tank_info']['short_name']}}
                <br/> WN8: {{$tank['wn8']}}
                <br/> Battles: {{$tank['battles']['total']}}
                <br/> Damage: {{ $data['battles'] > 0 ? round($tank['damage_dealt']['total']/$tank['battles']['total'],0) : 0}}
                <br/> Frags: {{ $data['battles'] > 0 ? round($tank['frags']['total']/$tank['battles']['total'],2) : 0}}
                <br/> Wins: {{ $data['battles'] > 0 ? round( ($tank['wins']['total']/$tank['battles']['total'] * 100), 0) : 0}}%

            </li>
        @endforeach
    </ul>
        <div style="clear: both"></div>
    @endforeach
</div>
<!--
<table width="100%">
    <thead>
        <tr>
            <td>Player</td>
            <td>Battles</td>
            <td>Damage</td>
            <td>Frags</td>
            <td>Spotted</td>
        </tr>
    </thead>
    <tbody>
        @foreach($diff as $data)
            <tr>
                <td>{{$data['name']}}</td>
                <td>{{ round($data['battles'],0)}}</td>
                <td>{{ $data['battles'] > 0 ? round($data['damage_dealt']/$data['battles'],0) : 0}}</td>
                <td>{{ $data['battles'] > 0 ? round($data['frags']/$data['battles'],2) : 0}}</td>
                <td>{{ $data['battles'] > 0 ? round($data['spotted']/$data['battles'],2) : 0}}</td>
            </tr>
        @endforeach
    </tbody>
</table>-->