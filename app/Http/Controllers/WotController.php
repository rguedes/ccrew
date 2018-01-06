<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use RiftBit\WoTAPI\Api as WoTAPI;
use RiftBit\WoTAPI\lib\tools\Request;
use Illuminate\Support\Facades\Cache;

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
        //dd($clanInfo);
        $clan = array_first($clanInfo['data']);
        $clan['members'] = collect($clan['members'])->sortBy('account_name')->toArray();
        return view("clan.detail")->with("clan", $clan);
    }

    public function stats($userId, $tier=10){

        $range = ["bad","danger","warning","success","good","unicorn"];

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
                if($tank['all']['battles'] < 50)
                    $class = "grey";

                return array_merge($tank, ["class"=>$class], ["tank"=>$tanksList[$tank['tank_id']]]);
            }, array_first($personalTanksList['data']));

            return view("tanks.list")->with('tanks', $list)->with("user", $user);
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

}
