<?php
namespace yiiunit\extensions\hs;

/**
 * Class for testing MySQL HandlerSocket cache backend
 * @group hs
 * @group caching
 */
class HSCacheMultiTableTest extends BaseHSCacheTestCase
{
    protected $dbParam = 'hsModeMultiTable';
}
