<?php

namespace App\Http\Controllers;

use FontLib\Table\Type\head;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use RiftBit\WoTAPI\Api as WoTAPI;
use RiftBit\WoTAPI\lib\tools\Request;
use Illuminate\Support\Facades\Cache;
use App\Stats;
use GuzzleHttp\Client;
use PDF;
use Excel;

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

    public function getStatsWotzilla(){
        $clans = ['GOP', 'ELCC', 'CCREW', 'RYNO', 'OE-PT', 'KINAS', 'TI7AN', 'UP-EU', 'S-D-G', 'DT-PT'];
        $output = [];
        foreach ($clans as $clan){
            $stats = resource_path('assets/stats/'.$clan.".csv");
            Excel::load($stats, function($reader) use (&$output, $clan) {
                $data = $reader->get(['nickname', 'wn8', 'battles_3', 'wn11', 'last_battle']);
                $data = $data->map(function ($data) use ($clan){
                    return array_merge($data->toArray(), ['clan_id'=>$clan]);
                });
                $output = array_merge($output, $data->toArray());
            });
        }

        $output = collect($output)
            ->reject(function($data){
                return $data['wn11'] < 2000 || $data['battles_3'] < 150;
            })
            ->sortByDesc(function($data){
                return (int)$data['wn11'];
            })
            ->values()
        ;
        $countByClan = $output->groupBy('clan_id')->map(function($clan, $key){
            return $clan->count();
        })->sort()->reverse();

        $countByWn8 = $output->groupBy(function($data){
            if($data['wn11'] > 2900){
                return "super_unicorn";
            }elseif ($data['wn11'] > 2450){
                return "unicorn";
            }
            return "great";
        })->map(function($clan, $key){
            return $clan->count();
        })->sort();
        $pdf = PDF::loadView('stats.wotzilla', compact('output', 'countByClan', 'countByWn8'));
        return $pdf->download('stats.pdf');
    }

    public function get30D(){

        $expected_tanks = Cache::get('expected_tanks', null);
        if(is_null($expected_tanks)){
            $expected_tanks = json_decode(file_get_contents(resource_path('assets/wn8exp.json')), true);
            Cache::put('expected_tanks', $expected_tanks, 60*24);
        }
        //First 100
        $tanksListRequest = $this->api->encyclopediaVehicles("images, tag, tank_id, type, short_name, name, tier, nation", "8,10");
        $tanksList = $tanksListRequest['data'];
        //Get next pages
        if($tanksListRequest['meta']['page_total']>1){
            for($i=2; $i<=$tanksListRequest['meta']['page_total']; $i++){
                $tanksListRequest = $this->api->encyclopediaVehicles("images, tag, tank_id, type, short_name, name, tier, nation", "8,10", "", "",  "", $i);
                $tanksList = array_merge($tanksList, $tanksListRequest['data']);
            }
        }

        $stats = Stats::whereBetween("date", ["2018-01-01 00:00:00", "2018-01-31 00:00:00"])->orderBy("date")->get();

        $diff = [];
        foreach ($stats->groupBy('account_id') as $key => $data){
            $first = $data->first()->toArray();
            $last = $data->last()->toArray();
            $tanks = [];
            foreach ($data as $id => $day){
                //No battles go Next day
                if($day['info']['statistics']['all']['battles']==0){
                    continue;
                }

                foreach ($day['tanks'] as $tank){

                    $tankInfo = array_first(array_filter($tanksList, function($value) use ($tank){
                        return $tank['data']['tank_id'] == $value['tank_id'];
                    }), null);
                    if(is_null($tankInfo))
                        continue;


                    if(!isset($tanks[$tank['data']['tank_id']])){
                        $tanks[$tank['data']['tank_id']] =
                            array(
                                "battles" => array(
                                    "total"=>0,
                                    "init" => $tank['stats']['all']['battles']
                                ),
                                "damage_dealt" => array(
                                    "total"=>0,
                                    "init" => $tank['stats']['all']['damage_dealt']
                                ),
                                "spotted" => array(
                                    "total"=>0,
                                    "init" => $tank['stats']['all']['spotted']
                                ),
                                "frags" => array(
                                    "total"=>0,
                                    "init" => $tank['stats']['all']['frags']
                                ),
                                "dropped_capture_points" => array(
                                    "total"=>0,
                                    "init" => $tank['stats']['all']['dropped_capture_points']
                                ),
                                "wins" => array(
                                    "total"=>0,
                                    "init" => $tank['stats']['all']['wins']
                                ),
                                "tank_info"=>$tankInfo
                            );
                    }else{
                        $tanks[$tank['data']['tank_id']]['battles']['total'] = $tank['stats']['all']['battles']-$tanks[$tank['data']['tank_id']]['battles']['init'];
                        $tanks[$tank['data']['tank_id']]['damage_dealt']['total'] = $tank['stats']['all']['damage_dealt']-$tanks[$tank['data']['tank_id']]['damage_dealt']['init'];
                        $tanks[$tank['data']['tank_id']]['spotted']['total'] = $tank['stats']['all']['spotted']-$tanks[$tank['data']['tank_id']]['spotted']['init'];
                        $tanks[$tank['data']['tank_id']]['frags']['total'] = $tank['stats']['all']['frags']-$tanks[$tank['data']['tank_id']]['frags']['init'];
                        $tanks[$tank['data']['tank_id']]['wins']['total'] = $tank['stats']['all']['wins']-$tanks[$tank['data']['tank_id']]['wins']['init'];
                    }
                }
            }
            //Calculate WN8
            $tanks = collect($tanks)->map(function($stats, $tankId) use ($expected_tanks){
                if($stats['battles']['total'] == 0){
                    return array_merge($stats, ["wn8"=>0, "tank_id"=>$tankId]);
                }

                $exp = array_first(array_filter($expected_tanks['data'], function($value) use ($tankId){
                    return $tankId == $value['IDNum'];
                }));

                // Calculate WN8
                $rDAMAGE = $stats['damage_dealt']['total'] / ($exp['expDamage'] * $stats['battles']['total']);
                $rSPOT = $stats['spotted']['total'] / ($exp['expSpot'] * $stats['battles']['total']);
                $rFRAG = $stats['frags']['total'] / ($exp['expFrag'] * $stats['battles']['total']);
                $rDEF = $stats['dropped_capture_points']['total'] / ($exp['expDef'] * $stats['battles']['total']);
                $rWIN = $stats['wins']['total'] / ($exp['expWinRate'] * $stats['battles']['total']);

                $rWINc = max(0, ($rWIN - 0.71) / (1 - 0.71));
                $rDAMAGEc = max(0, ($rDAMAGE - 0.22) / (1 - 0.22));
                $rFRAGc = max(0, min($rDAMAGEc + 0.2, ($rFRAG - 0.12) / (1 - 0.12)));
                $rSPOTc = max(0, min($rDAMAGEc + 0.1, ($rSPOT - 0.38) / (1 - 0.38)));
                $rDEFc = max(0, min($rDAMAGEc + 0.1, ($rDEF - 0.10) / (1 - 0.10)));

                $wn8 = round(980 * $rDAMAGEc + 210 * $rDAMAGEc * $rFRAGc + 155 * $rFRAGc * $rSPOTc + 75 * $rDEFc * $rFRAGc + 145 * MIN(1.8, $rWINc), 0);
                return array_merge($stats, ["wn8"=>$wn8, "tank_id"=>$tankId]);
            })->reject(function($value){
                return $value["wn8"]==0;
            })->values();
            $diff[] = array(
                "battles"=>$last['info']['statistics']['all']['battles']-$first['info']['statistics']['all']['battles'],
                "damage_dealt"=>$last['info']['statistics']['all']['damage_dealt']-$first['info']['statistics']['all']['damage_dealt'],
                "frags"=>$last['info']['statistics']['all']['frags']-$first['info']['statistics']['all']['frags'],
                "spotted"=>$last['info']['statistics']['all']['spotted']-$first['info']['statistics']['all']['spotted'],
                "account_id" =>$key,
                "name"=> $first['info']['nickname'],
                "tanks" => $tanks
            );
        }
        $diff = collect($diff)->reject(function($data){
            return $data['battles'] == 0;
        });

        /*$pdf = PDF::loadView('stats.30d', compact('diff'));
        return $pdf->download('stats30d.pdf');*/
        return view('stats.30d', compact('diff'));
    }


    public function getStats(){
        return "Disabled";
        //GOP, ELCC, CCREW, RYNO, OE-PT, KINAS, TI7AN, UP-EU, S-D-G, DT-PT
        $clans = [500150211, 500002273, 500017055, 500065961, 500043734, 500045079, 500072694, 500024010, 500071578, 500041925];
        $output = [];
        $clanInfo = $this->api->clanInfo(join(",", $clans));
        foreach ($clanInfo['data'] as $clanId => $clan){
            $client = new Client();

            $data = Cache::get("clan_stats_".$clanId, null);
            if(is_null($data)){
                $data = collect($clan['members'])->map(function($data) use ($client, $clan){
                    $res = $client->get('https://stats.tanks.gg/api/ranges/eu/'.$data['account_name']);
                    if($res->getStatusCode() == 200){
                        return array_merge( ["clan_id"=> $clan['tag']], json_decode($res->getBody(),1) );
                    }
                    return [];
                })->toArray();
                Cache::put("clan_stats_".$clanId, $data, 60*24);
                sleep(5);
            }
            $output = array_merge($output,$data);
        }

        $output = collect($output)
            ->reject(function($data){
                return $data['intervals']['30']['wn8'] < 2000 || $data['intervals']['30']['battles'] < 150;
            })
            ->sortByDesc(function($data){
                return (int)$data['intervals']['30']['wn8'];
            })
           // ->map(function($data){return collect($data)->only("clan_id", "name", "intervals"); })
            ->values()
        ;
        $countByClan = $output->groupBy('clan_id')->map(function($clan, $key){
            return $clan->count();
        })->sort()->reverse();

        $countByWn8 = $output->groupBy(function($data){
            if($data['intervals']['30']['wn8'] > 2900){
                return "super_unicorn";
            }elseif ($data['intervals']['30']['wn8'] > 2450){
                return "unicorn";
            }
            return "great";
        })->map(function($clan, $key){
            return $clan->count();
        })->sort();

        //return view("stats.wn8")->with("output", $output)->with("countByClan", $countByClan)->with("countByWn8", $countByWn8);
        $pdf = PDF::loadView('stats.wn8', compact('output', 'countByClan', 'countByWn8'));
        return $pdf->download('stats.pdf');

    }

}
