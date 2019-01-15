<?php

/**
 * @author simon <crcms@crcms.cn>
 * @datetime 2018/6/25 6:47
 *
 * @link http://crcms.cn/
 *
 * @copyright Copyright &copy; 2018 Rights Reserved CRCMS
 */

namespace CrCms\Microservice\Client\Selectors;

use CrCms\Microservice\Client\Contracts\SelectorContract;
use CrCms\Microservice\Client\Contracts\ServiceDiscoverContract;

/**
 * Class RandSelector.
 */
class RandSelector implements SelectorContract
{
    /**
     * @var ServiceDiscoverContract
     */
    protected $discover;

    /**
     * RandSelector constructor.
     *
     * @param ServiceDiscoverContract $discover
     */
    public function __construct(ServiceDiscoverContract $discover)
    {
        $this->discover = $discover;
    }

    /**
     * @param string $service
     *
     * @return array
     */
    public function select(string $service): array
    {
        $services = $this->discover->services($service);

        return $services[array_rand($services)];
    }
}
