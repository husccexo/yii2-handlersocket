<?php

namespace husccexo\yii\HandlerSocket;

use Yii;
use yii\base\InvalidConfigException;
use yii\web\CacheSession;

class HSSession extends CacheSession
{
    /**
     * @var string
     */
    public $group = 'session';


    /**
     * Initializes the application component.
     */
    public function init()
    {
        parent::init();

        if (!$this->cache instanceof HSCache) {
            throw new InvalidConfigException('Cache component for session must be instance of '.HSCache::className());
        }

        $this->cache = clone $this->cache;
        $this->cache->group = $this->group;
    }
}
