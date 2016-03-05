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
     * @var string
     * @deprecated This property is an alias for [[group]]
     */
    public $type;


    /**
     * Initializes the application component.
     */
    public function init()
    {
        parent::init();

        if (!$this->cache instanceof HSCache) {
            throw new InvalidConfigException('Cache component for session must be instance of '.HSCache::className());
        }

        if ($this->type !== null) {
            $this->group = $this->type;
        }

        $this->cache = clone $this->cache;
        $this->cache->group = $this->group;
    }
}
