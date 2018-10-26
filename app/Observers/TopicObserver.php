<?php

namespace App\Observers;

use App\Handles\SlugTranslateHandler;
use App\Jobs\TranslateSlug;
use App\Models\Reply;
use App\Models\Topic;
use App\Notifications\TopicReplied;

// creating, created, updating, updated, saving,
// saved,  deleting, deleted, restoring, restored

class TopicObserver
{
    public function updating(Topic $topic)
    {
        $topic->body    = clean($topic->body, 'user_topic_body');

        $topic->excerpt = make_excerpt($topic->body);
    }

    public function saving(Topic $topic)
    {
        // XSS 过滤
        $topic->body = clean($topic->body, 'user_topic_body');

        // 生成话题摘录
        $topic->excerpt = make_excerpt($topic->body);


    }

    public function saved(Topic $topic)
    {
        // 如 slug 字段无内容，即使用翻译器对 title 进行翻译
        if (!$topic->slug) {
            // 推送任务到队列
            dispatch(new TranslateSlug($topic));
        }
    }

    public function created(Reply $reply)
    {
        $topic = $reply->topic;
        $topic->increment('reply_count', 1);

        // 通知作者话题被回复了
        $topic->user->notify(new TopicReplied($reply));
    }
}