<?php
/**
 * Created by PhpStorm.
 * User: raolufeng
 * Date: 2018/11/17
 * Time: 12:52 AM
 */

namespace App\Observers;

use App\Models\Link;
use Illuminate\Support\Facades\Cache;

class LinkObserver
{
    //保存时清空cache_key对应的缓存
    public function saved(Link $link)
    {
        Cache::forget($link->cache_key);
    }
}