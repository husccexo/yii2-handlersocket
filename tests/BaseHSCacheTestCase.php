<?php
namespace yiiunit\extensions\hs;

use Yii;
use yiiunit\framework\caching\CacheTestCase;
use husccexo\yii\HandlerSocket\HSCache as Cache;

/**
 * Class for testing MySQL HandlerSocket cache backend
 * @group hs
 * @group caching
 */
class BaseHSCacheTestCase extends CacheTestCase
{
    private $_cacheInstance = null;
    protected $dbParam;

    /**
     * @return Cache
     */
    protected function getCacheInstance()
    {
        $databases = self::getParam('databases');

        $params = isset($databases[$this->dbParam]) ? $databases[$this->dbParam] : null;
        if ($params === null) {
            $this->markTestSkipped('No HandlerSocket server connection configured.');
        }

        if ($this->_cacheInstance === null) {
            $this->_cacheInstance = new Cache(array_merge($databases['hsServer'], $params));
        }

        return $this->_cacheInstance;
    }

    /**
     * @group expireMilliseconds
     */
    public function testExpireMilliseconds()
    {
        $cache = $this->getCacheInstance();

        $this->assertTrue($cache->set('expire_test_ms', 'expire_test_ms', 1));
        usleep(100000);
        $this->assertEquals('expire_test_ms', $cache->get('expire_test_ms'));
        usleep(900000);
        $this->assertFalse($cache->get('expire_test_ms'));
    }

    public function testExpireAddMilliseconds()
    {
        $cache = $this->getCacheInstance();

        $this->assertTrue($cache->add('expire_testa_ms', 'expire_testa_ms', 1));
        usleep(100000);
        $this->assertEquals('expire_testa_ms', $cache->get('expire_testa_ms'));
        usleep(900000);
        $this->assertFalse($cache->get('expire_testa_ms'));
    }

    /**
     * Store a value that is 2 times buffer size big
     * https://github.com/yiisoft/yii2/issues/743
     */
    public function testLargeData()
    {
        $cache = $this->getCacheInstance();

        $data = str_repeat('XX', 8192); // http://www.php.net/manual/en/function.fread.php
        $key = 'bigdata1';

        $this->assertFalse($cache->get($key));
        $cache->set($key, $data);
        $this->assertTrue($cache->get($key) === $data);

        // try with multibyte string
        $data = str_repeat('ЖЫ', 8192); // http://www.php.net/manual/en/function.fread.php
        $key = 'bigdata2';

        $this->assertFalse($cache->get($key));
        $cache->set($key, $data);
        $this->assertTrue($cache->get($key) === $data);
    }

    /**
     * Store a megabyte and see how it goes
     * https://github.com/yiisoft/yii2/issues/6547
     *
     * @group reallyLargeData
     */
    public function testReallyLargeData()
    {
        $cache = $this->getCacheInstance();

        $keys = [];
        for($i = 1; $i < 16; $i++) {
            $key = 'realbigdata' . $i;
            $data = str_repeat('X', 100 * 1024); // 100 KB
            $keys[$key] = $data;

//            $this->assertTrue($cache->get($key) === false); // do not display 100KB in terminal if this fails :)
            $cache->set($key, $data);
        }
        $values = $cache->multiGet(array_keys($keys));
        foreach ($keys as $key => $value) {
            $this->assertArrayHasKey($key, $values);
            $this->assertTrue($values[$key] === $value);
        }
    }

    public function testMultiByteGetAndSet()
    {
        $cache = $this->getCacheInstance();

        $data = ['abc' => 'ежик', 2 => 'def'];
        $key = 'data1';

        $this->assertFalse($cache->get($key));
        $cache->set($key, $data);
        $this->assertTrue($cache->get($key) === $data);
    }
}
