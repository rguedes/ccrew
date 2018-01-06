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
        return view("clan.detail")->with("clan", array_first($clanInfo['data']));
    }

    public function stats($userId, $tier=10){
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
            $list = array_map(function($tank) use ($tanksList, $expected_tanks){

                $exp = array_first(array_filter($expected_tanks['data'], function($value) use ($tank){
                    return $tank['tank_id'] == $value['IDNum'];
                }));
                $expDamage = $exp['expDamage'];

                $damage = $tank['all']['battles'] != 0 ? round($tank['all']['damage_dealt'] / $tank['all']['battles']) : 0;
                switch ($damage){
                    case $damage < $expDamage:
                        $class = "danger";
                        break;
                    case $damage >= $expDamage && $damage < ($expDamage + ($expDamage*0.2)):
                        $class = "warning";
                        break;
                    case $damage >= ($expDamage + ($expDamage*0.2)) && $damage < ($expDamage + ($expDamage*0.5)):
                        $class = "success";
                        break;
                    case $damage >= ($expDamage + ($expDamage*0.5)):
                        $class = "unicorn";
                        break;
                    default:
                        $class = "";
                        break;
                }
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
