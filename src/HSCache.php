<?php

namespace husccexo\yii\HandlerSocket;

use Yii;
use yii\base\InvalidConfigException;
use yii\caching\Cache;
use HSCore\HandlerSocket;

class HSCache extends Cache {
    /** @var HandlerSocket $hs */
    private static $hs;

    public $host = 'localhost';
    public $portRead = 9998;
    public $portWrite = 9999;
    public $secret;

    public $db;
    public $table = 'cache';
    public $type = 'yii';


    public $disabled = false;

    public $debug = false;

    public $manyLimit = 99999;


    /**
     * @var integer the probability (parts per million) that garbage collection (GC) should be performed
     * when storing a piece of data in the cache. Defaults to 100, meaning 0.01% chance.
     * This number should be between 0 and 1000000. A value 0 meaning no GC will be performed at all.
     */
    public $gcProbability = 100;


    /**
     * Initializes the DbCache component.
     * This method will initialize the [[db]] property to make sure it refers to a valid DB connection.
     * @throws InvalidConfigException if [[db]] is invalid.
     */
    public function init()
    {
        parent::init();

        if (!self::$hs) {
            self::$hs = new HandlerSocket(
                $this->host, $this->portRead, $this->secret,
                $this->host, $this->portWrite, $this->secret,
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
        if ($this->disabled) {
            return false;
        }

        $key = $this->buildKey($key);

        $params = [
            self::$hs->openReadIndex($this->db, $this->table, null, ['expire']),
            HandlerSocket::OP_EQUAL,
            2,
            $this->type, $key
        ];
        $res = self::$hs->readRequest($params);

        return $res && ($res[0][0] == 0 || $res[0][0] > time());
    }

    /**
     * Retrieves a value from cache with a specified key.
     * This is the implementation of the method declared in the parent class.
     * @param string $key a unique key identifying the cached value
     * @return string|boolean the value stored in cache, false if the value is not in the cache or expired.
     */
    protected function getValue($key)
    {
        if ($this->disabled) {
            return false;
        }

        $params = [
            self::$hs->openReadIndex($this->db, $this->table, null, ['expire', 'data']),
            HandlerSocket::OP_EQUAL,
            2,
            $this->type, $key
        ];
        $res = self::$hs->readRequest($params);

        if ($res && ($res[0][0] == 0 || $res[0][0] > time())) {
            return $res[0][1];
        }

        return false;
    }

    /**
     * Retrieves multiple values from cache with the specified keys.
     * @param array $keys a list of keys identifying the cached values
     * @return array a list of cached values indexed by the keys
     */
    protected function getValues($keys)
    {
        if ($this->disabled || empty($keys)) {
            return [];
        }

        $results = [];
        foreach ($keys as $key) {
            $results[$key] = false;
        }

        $ivlen = count($keys);

        $params = array_merge([
            self::$hs->openReadIndex($this->db, $this->table, null, ['key', 'expire', 'data']),
            HandlerSocket::OP_EQUAL,
            2,
            $this->type, '',
            $ivlen, 0,
            '@', 1, $ivlen
        ], $keys);

        foreach (self::$hs->readRequest($params) as $row) {
            if ($row[1] == 0 || $row[1] > time()) {
                $results[$row[0]] = $row[2];
            }
        }

        return $results;
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
        if ($this->disabled) {
            return false;
        }

        if (!$this->_exists($key)) {
            return $this->addValue($key, $value, $duration);
        }

        $this->gc();

        $params = [
            self::$hs->openWriteIndex($this->db, $this->table, null, ['expire', 'data']),
            HandlerSocket::OP_EQUAL,
            2,
            $this->type, $key,
            1, 0,
            HandlerSocket::COMMAND_UPDATE,
            $duration > 0 ? $duration + time() : 0, $value
        ];
        return (bool)self::$hs->writeRequest($params)[0][0];
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
        if ($this->disabled) {
            return false;
        }

        $this->gc();

        try {
            $params = [
                self::$hs->openWriteIndex($this->db, $this->table, null, ['type', 'key', 'expire', 'data']),
                HandlerSocket::COMMAND_INCREMENT,
                4,
                $this->type, $key, $duration > 0 ? $duration + time() : 0, $value
            ];
            self::$hs->writeRequest($params);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Deletes a value with the specified key from cache
     * This is the implementation of the method declared in the parent class.
     * @param string $key the key of the value to be deleted
     * @return boolean if no error happens during deletion
     */
    protected function deleteValue($key)
    {
        if ($this->disabled) {
            return false;
        }

        $params = [
            self::$hs->openWriteIndex($this->db, $this->table),
            HandlerSocket::OP_EQUAL,
            2,
            $this->type, $key,
            HandlerSocket::COMMAND_DELETE
        ];
        self::$hs->writeRequest($params);

        return true;
    }

    /**
     * Removes the expired data values.
     * @param boolean $force whether to enforce the garbage collection regardless of [[gcProbability]].
     * Defaults to false, meaning the actual deletion happens with the probability as specified by [[gcProbability]].
     */
    public function gc($force = false)
    {
        if ($this->disabled) {
            return;
        }

        if ($force || mt_rand(0, 1000000) < $this->gcProbability) {
            $params = [
                self::$hs->openWriteIndex($this->db, $this->table, 'expire', [], ['type', 'expire']),
                HandlerSocket::OP_LESS,
                1,
                time(),
                $this->manyLimit, 0,
                'F', HandlerSocket::OP_EQUAL, 0, $this->type,
                'F', HandlerSocket::OP_MORE, 1, 0,
                HandlerSocket::COMMAND_DELETE
            ];

            do {
                $res = (int)self::$hs->writeRequest($params)[0][0];
            } while ($res == $this->manyLimit);
        }
    }

    /**
     * Deletes all values from cache.
     * This is the implementation of the method declared in the parent class.
     * @return boolean whether the flush operation was successful.
     */
    protected function flushValues()
    {
        if ($this->disabled) {
            return false;
        }

        $params = [
            self::$hs->openWriteIndex($this->db, $this->table),
            HandlerSocket::OP_EQUAL,
            1,
            $this->type,
            $this->manyLimit, 0,
            HandlerSocket::COMMAND_DELETE
        ];

        do {
            $res = (int)self::$hs->writeRequest($params)[0][0];
        } while ($res == $this->manyLimit);

        return true;
    }


    private function _exists($key)
    {
        if ($this->disabled) {
            return false;
        }

        $params = [
            self::$hs->openReadIndex($this->db, $this->table, null, ['type']),
            HandlerSocket::OP_EQUAL,
            2,
            $this->type, $key
        ];
        $res = self::$hs->readRequest($params);

        return !empty($res);
    }
}