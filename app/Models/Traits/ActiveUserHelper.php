<?php
/**
 * Created by PhpStorm.
 * User: raolufeng
 * Date: 2018/11/16
 * Time: 10:36 PM
 */

namespace App\Models\Traits;


use App\Models\Reply;
use App\Models\Topic;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait ActiveUserHelper
{
    //用于存放临时用户数据
    protected $users = [];

    //配置信息
    protected $topic_weight = 4;//话题权重
    protected $reply_weight = 1;//回复权重
    protected $pass_days    = 7;//多少天内发表过内容
    protected $user_number  = 6;//取出来多少用户

    //缓存相关配置
    protected $cache_key = 'larabbs_active_users';
    protected $cache_expire_in_minutes = 65;

    public function getActiveUsers()
    {
        //尝试从缓存取出cache_key对应的数据，能取到则直接返回数据
        //否则运行匿名函数中的代码取出活跃用户数据，返回同时做缓存
        return Cache::remember($this->cache_key, $this->cache_expire_in_minutes, function () {
            return $this->calculateActiveUsers();
        });
    }

    public function calculateAndCacheActiveUsers()
    {
        //取得活跃用户
        $active_users = $this->calculateActiveUsers();
        //并加以缓存
        $this->cacheActiveUsers($active_users);
    }

    private function calculateActiveUsers()
    {
        $this->calculateTopicScore();
        $this->calculateReplyScore();

        //数组按照得分排序
        $users = array_sort($this->users, function ($user) {
            return $user['score'];
        });

        //倒叙排序，保持key不变
        $users = array_reverse($users, true);

        //获取想要数据
        $users = array_slice($users, 0, $this->user_number, true);

        $active_users = collect();

        foreach($users as $user_id => $user) {
            $user = $this->find($user_id);

            if ($user) {
                $active_users->push($user);
            }
        }

        //返回数据
        return $active_users;
    }


    private function calculateTopicScore()
    {
        // 从话题数据表里取出限定时间范围（$pass_days）内，有发表过话题的用户
        // 并且同时取出用户此段时间内发布话题的数量
        $topic_users = Topic::query()->select(DB::raw('user_id, count(*) as topic_count'))
                                     ->where('created_at', '>=', Carbon::now()->subDays($this->pass_days))
                                     ->groupBy('user_id')
                                     ->get();

        //根据话题数计算得分
        foreach ($topic_users as $value) {
            $this->users[$value->user_id]['score'] = $value->topic_count * $this->topic_weight;
        }
    }

    private function calculateReplyScore()
    {
        // 从回复数据表里取出限定时间范围（$pass_days）内，有发表过回复的用户
        // 并且同时取出用户此段时间内发布回复的数量
        $reply_users = Reply::query()->select(DB::raw('user_id, count(*) as reply_count'))
            ->where('created_at', '>=', Carbon::now()->subDays($this->pass_days))
            ->groupBy('user_id')
            ->get();
        // 根据回复数量计算得分
        foreach ($reply_users as $value) {
            $reply_score = $value->reply_count * $this->reply_weight;
            if (isset($this->users[$value->user_id])) {
                $this->users[$value->user_id]['score'] += $reply_score;
            } else {
                $this->users[$value->user_id]['score'] = $reply_score;
            }
        }
    }

    private function cacheActiveUsers($active_users)
    {
        //数据放入缓存
        Cache::put($this->cache_key, $active_users, $this->cache_expire_in_minutes);
    }

}