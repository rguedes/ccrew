<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Stats extends Eloquent
{

    protected $collection = 'clan_stats_collection';

    protected $guarded = [];

}
