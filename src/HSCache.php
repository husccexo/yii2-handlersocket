<?php

namespace husccexo\yii\HandlerSocket;

use Yii;
use yii\base\InvalidConfigException;
use yii\caching\Cache;
use HSLib\CacheMultiType;

class HSCache extends Cache
{
    /**
     * @var \HSLib\AdvancedCacheInterface
     */
    private static $hs;

    public $host = 'localhost';
    public $portRead = 9998;
    public $portWrite = 9999;
    public $secret;

    public $db;
    public $table = 'cache';
    public $type = 'yii';

    public $debug = false;

    /**
     * @var bool
     * @deprecated
     */
    public $disabled = false;
    /**
     * @var int
     * @deprecated
     */
    public $manyLimit = 99999;


    /**
     * @var integer the probability (parts per million) that garbage collection (GC) should be performed
     * when storing a piece of data in the cache. Defaults to 100, meaning 0.01% chance.
     * This number should be between 0 and 1000000. A value 0 meaning no GC will be performed at all.
     */
    public $gcProbability = 100;


    /**
     * Initializes the HSCache component.
     * This method will initialize the [[db]] property to make sure it refers to a valid DB connection.
     * @throws InvalidConfigException if [[db]] is invalid.
     */
    public function init()
    {
        parent::init();

        if (!self::$hs) {
            self::$hs = new CacheMultiType(
                $this->host . ':' . $this->portRead, $this->secret,
                $this->host . ':' . $this->portWrite, $this->secret,
                $this->db, $this->table,
                $this->debug
            );
        }
    }

    /**
     * Checks whether a specified key exists in the cache.
     * This can be faster than getting the value from the cache if the data is big.
     * Note that this method does not check whether the dependency associated
     * with the cached data, if there is any, has changed. So a call to [[get]]
     * may return false while exists returns true.
     * @param mixed $key a key identifying the cached value. This can be a simple string or
     * a complex data structure consisting of factors representing the key.
     * @return boolean true if a value exists in cache, false if the value is not in the cache or expired.
     */
    public function exists($key)
    {
        return self::$hs->valid($this->type, $this->buildKey($key));
    }

    /**
     * Retrieves a value from cache with a specified key.
     * This is the implementation of the method declared in the parent class.
     * @param string $key a unique key identifying the cached value
     * @return string|boolean the value stored in cache, false if the value is not in the cache or expired.
     */
    protected function getValue($key)
    {
        $res = self::$hs->get($this->type, $key);
        return ($res === null) ? false : $res;
    }

    /**
     * Retrieves multiple values from cache with the specified keys.
     * @param array $keys a list of keys identifying the cached values
     * @return array a list of cached values indexed by the keys
     */
    protected function getValues($keys)
    {
        return array_map(
            function ($res) {
                return ($res === null) ? false : $res;
            },
            self::$hs->getMany($this->type, $keys)
        );
    }

    /**
     * Stores a value identified by a key in cache.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param string $value the value to be cached
     * @param integer $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return boolean true if the value is successfully stored into cache, false otherwise
     */
    protected function setValue($key, $value, $duration)
    {
        $this->gc();
        return self::$hs->set($this->type, $key, $value, $duration);
    }

    /**
     * Stores a value identified by a key into cache if the cache does not contain this key.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param string $value the value to be cached
     * @param integer $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return boolean true if the value is successfully stored into cache, false otherwise
     */
    protected function addValue($key, $value, $duration)
    {
        $this->gc();
        return self::$hs->add($this->type, $key, $value, $duration);
    }

    /**
     * Deletes a value with the specified key from cache
     * This is the implementation of the method declared in the parent class.
     * @param string $key the key of the value to be deleted
     * @return boolean if no error happens during deletion
     */
    protected function deleteValue($key)
    {
        return self::$hs->delete($this->type, $key);
    }

    /**
     * Removes the expired data values.
     * @param boolean $force whether to enforce the garbage collection regardless of [[gcProbability]].
     * Defaults to false, meaning the actual deletion happens with the probability as specified by [[gcProbability]].
     */
    public function gc($force = false)
    {
        if ($force || mt_rand(0, 1000000) < $this->gcProbability) {
            self::$hs->gc($this->type);
        }
    }

    /**
     * Deletes all values from cache.
     * This is the implementation of the method declared in the parent class.
     * @return boolean whether the flush operation was successful.
     */
    protected function flushValues()
    {
        return self::$hs->flush($this->type);
    }
}
