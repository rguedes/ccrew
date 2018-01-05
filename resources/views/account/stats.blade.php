<div class="stats">
    <label>Battles</label>
    <span>{{$tank['all']['battles']}}</span>
    @if($tank['all']['battles']>0)
    <br/>
    <label>Avg Damage</label>
    <span>{{ round($tank['all']['damage_dealt'] / $tank['all']['battles']) }}</span>
    <br/>
    <label>Avg Spotted</label>
    <span>{{ number_format($tank['all']['spotted'] / $tank['all']['battles'],2) }}</span>
    <br/>
    <label>WR</label>
    <span>{{ number_format(($tank['all']['wins'] / $tank['all']['battles'])*100, 2) }}%</span>
    @endif
</div>