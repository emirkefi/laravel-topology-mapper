<?php

namespace EmirKefi\TopologyMapper\Facades;

use EmirKefi\TopologyMapper\Managers\TopologyManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array getEnrichedGraph()
 * @method static array runStaticScan()
 * @method static void clear()
 * @method static \EmirKefi\TopologyMapper\Contracts\StorageDriverInterface getStorage()
 *
 * @see \EmirKefi\TopologyMapper\Managers\TopologyManager
 */
class Topology extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TopologyManager::class;
    }
}
