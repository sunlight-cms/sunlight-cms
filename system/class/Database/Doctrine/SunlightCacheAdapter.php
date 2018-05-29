<?php

namespace Sunlight\Database\Doctrine;

use Doctrine\Common\Cache\CacheProvider;
use Kuria\Cache\CacheInterface;

class SunlightCacheAdapter extends CacheProvider
{
    /** @var CacheInterface */
    protected $cache;

    /**
     * @param CacheInterface $cache
     */
    function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    protected function doFetch($id)
    {
        return $this->cache->get($this->processId($id));
    }

    protected function doContains($id)
    {
        return $this->cache->has($this->processId($id));
    }

    protected function doSave($id, $data, $lifeTime = 0)
    {
        return $this->cache->set($this->processId($id), $data, $lifeTime);
    }

    protected function doDelete($id)
    {
        return $this->cache->remove($this->processId($id));
    }

    protected function doFlush()
    {
        return $this->cache->clear();
    }

    protected function doGetStats()
    {
        return null;
    }

    /**
     * @param string $id
     * @return string
     */
    protected function processId($id)
    {
        return hash('sha256', $id);
    }
}
