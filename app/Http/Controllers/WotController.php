<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use RiftBit\WoTAPI\Api as WoTAPI;
use RiftBit\WoTAPI\lib\tools\Request;
use Illuminate\Support\Facades\Cache;
use App\Stats;

class WotController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    private $api;

    public function __construct()
    {
        $this->api = new WoTAPI(env("WGAPI"),"eu","en");
    }

    public function index(){
        $clanInfo = $this->api->clanInfo("500150211");
        $clan = array_first($clanInfo['data']);
        $clan['members'] = collect($clan['members'])->sortBy('account_name')->toArray();
        return view("clan.detail")->with("clan", $clan);
    }

    public function stats($userId, $tier=10){

        $range = ["bad","danger","warning","success","good","unicorn"];
        $wn8 = new \App\Wn8($this->api, $userId, true);

        //$userId = 508431014;
        #dd($api->accountInfo("nickname, statistics.all","","508431014"));
        $expected_tanks = Cache::get('expected_tanks', null);
        if(is_null($expected_tanks)){
            $expected_tanks = json_decode(file_get_contents(resource_path('assets/wn8exp.json')), true);
            Cache::put('expected_tanks', $expected_tanks, 60*24);
        }
        $user = Cache::get('user_'.$userId, null);
        if(is_null($user)){
            $_user = $this->api->accountInfo($userId,"nickname, statistics.all, clan_id");
            $user = array_first($_user['data']);
            array_set($user, "account_id", $userId);
            Cache::put('user_'.$userId, $user, 60*24);
        }

        $tanksList = $this->api->encyclopediaVehicles("images, tag, tank_id, type, short_name, name, tier, nation", $tier);
        if($tanksList['status'] == "ok") {
            $tanksList = $tanksList['data'];
            $tanksIds = join(",",array_keys($tanksList));
            $personalTanksList = $this->api->tanksStats($userId, $tanksIds);
            if(count(array_first($personalTanksList['data'])) == 0){
                return "not_available_tank_tier_".$tier;
            }
            $list = array_map(function($tank) use ($tanksList, $expected_tanks,$range){

                $exp = array_first(array_filter($expected_tanks['data'], function($value) use ($tank){
                    return $tank['tank_id'] == $value['IDNum'];
                }));
                $expDamage = $exp['expDamage'];

                if($tank['all']['battles'] > 0) {
                    // Calculate WN8
                    $rDAMAGE = $tank['all']['damage_dealt'] / ($exp['expDamage'] * $tank['all']['battles']);
                    $rSPOT = $tank['all']['spotted'] / ($exp['expSpot'] * $tank['all']['battles']);
                    $rFRAG = $tank['all']['frags'] / ($exp['expFrag'] * $tank['all']['battles']);
                    $rDEF = $tank['all']['dropped_capture_points'] / ($exp['expDef'] * $tank['all']['battles']);
                    $rWIN = $tank['all']['wins'] / ($exp['expWinRate'] * $tank['all']['battles']);

                    $rWINc = max(0, ($rWIN - 0.71) / (1 - 0.71));
                    $rDAMAGEc = max(0, ($rDAMAGE - 0.22) / (1 - 0.22));
                    $rFRAGc = max(0, min($rDAMAGEc + 0.2, ($rFRAG - 0.12) / (1 - 0.12)));
                    $rSPOTc = max(0, min($rDAMAGEc + 0.1, ($rSPOT - 0.38) / (1 - 0.38)));
                    $rDEFc = max(0, min($rDAMAGEc + 0.1, ($rDEF - 0.10) / (1 - 0.10)));

                    $wn8 = round(980 * $rDAMAGEc + 210 * $rDAMAGEc * $rFRAGc + 155 * $rFRAGc * $rSPOTc + 75 * $rDEFc * $rFRAGc + 145 * MIN(1.8, $rWINc), 0);
                }else{
                    $wn8 = 0;
                }

                $damage = $tank['all']['battles'] != 0 ? round($tank['all']['damage_dealt'] / $tank['all']['battles']) : 0;
                $winrate = $tank['all']['battles'] != 0 ? round($tank['all']['wins'] / $tank['all']['battles'] *100 ) : 0;
                switch ($damage){
                    case $damage < ($expDamage - ($expDamage*0.5)):
                        $class = 0;
                        break;
                    case $damage >= ($expDamage - ($expDamage*0.5)) && $damage < ($expDamage - ($expDamage*0.15)):
                        $class = 1;
                        break;
                    case $damage >= ($expDamage - ($expDamage*0.15)) && $damage < $expDamage:
                        $class = 2;
                        break;
                    case $damage >= $expDamage && $damage < ($expDamage + ($expDamage*0.15)):
                        $class = 3;
                        break;
                    case $damage >= ($expDamage + ($expDamage*0.15)) && $damage < ($expDamage + ($expDamage*0.5)):
                        $class = 4;
                        break;
                    case $damage >= ($expDamage + ($expDamage*0.5)):
                        $class = 5;
                        break;
                    default:
                        $class = "";
                        break;
                }

                if($class == 3 && $winrate < 50 || ($class == 4 && $winrate < 55) || ($class == 5 && $winrate < 60)){
                    $class-=1;
                }
                $class = $range[$class];
                /*if($tank['all']['battles'] < 50)
                    $class = "grey";*/

                return array_merge($tank, ["class"=>$class], ["tank"=>$tanksList[$tank['tank_id']]], ["wn8"=>$wn8] );
            }, array_first($personalTanksList['data']));

            return view("tanks.list")->with('tanks', $list)->with("user", $user)->with("wn8", $wn8->getWn8());
        }else
            return "fail";
    }

    public function settings(){
        $tanksList = $this->api->encyclopediaVehicles("images, tag, tank_id, type, short_name, name, tier, nation", 10);
        if($tanksList['status'] == "ok") {
            $tanksList = $tanksList['data'];
            return view("tanks.list")->with('tanks', $tanksList);
        }else{
            return "fail";
        }
    }


    public function get_daily_stats(){

        $clanId = 500150211;
        $lastUpdate = Cache::get("daily_stats_".$clanId, null);
        if(!is_null($lastUpdate)){
            return Stats::where("clan_id", $clanId)->where("date", date("Y-m-d 00:00:00"))->get();
        }
        $clanInfo = $this->api->clanInfo($clanId);
        $clan = array_first($clanInfo['data']);

        $membersInfo = $this->api->accountInfo(join(", ", collect($clan['members'])->pluck("account_id")->toArray()  ));
        $membersTanks = $this->api->accountTanks(join(", ", collect($clan['members'])->pluck("account_id")->toArray()  ));

        collect($membersInfo['data'])
          ->each(function ($member) use ($membersTanks) {
              if($member['last_battle_time'] < (time() - (24 * 60 * 60) ))
                  return ["clan_id"=>$member['clan_id'],"account_id"=>$member['account_id'], "date"=>date("Y-m-d 00:00:00"), "info"=>$member, "tanks"=>[]];
            $tanksStats = $this->api->tanksStats($member['account_id'])['data'][$member['account_id']];
            $tanksStats = collect($membersTanks['data'][$member['account_id']])->map(function($tank) use ($tanksStats){
                $stats = collect($tanksStats)->where("tank_id", $tank['tank_id'])->first();
                return ['data'=>$tank, "stats"=>$stats];
            })->toArray();
            $data = ["clan_id"=>$member['clan_id'],"account_id"=>$member['account_id'], "date"=>date("Y-m-d 00:00:00"), "info"=>$member, "tanks"=>$tanksStats];
            Stats::create($data);
        });
        Cache::put("daily_stats_".$clanId, time(), 60*24);
    }

}
