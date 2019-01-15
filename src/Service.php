<?php

/**
 * @author simon <crcms@crcms.cn>
 * @datetime 2018/7/2 6:14
 *
 * @link http://crcms.cn/
 *
 * @copyright Copyright &copy; 2018 Rights Reserved CRCMS
 */

namespace CrCms\Microservice\Client;

use CrCms\Microservice\Client\Contracts\SelectorContract;
use CrCms\Microservice\Client\Exceptions\ServiceException;
use CrCms\Microservice\Client\Packer\Packer;
use DomainException;
use Exception;
use GuzzleHttp\Promise\Promise;
use Illuminate\Contracts\Container\Container;

/**
 * Class Service.
 */
class Service
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var SelectorContract
     */
    protected $selector;

    /**
     * @var Packer
     */
    protected $packer;

    /**
     * @var ServiceData|null
     */
    protected $content;

    /**
     * @var int
     */
    protected $statusCode;

    /**
     * @var ServiceFactory
     */
    protected $factory;

    /**
     * @var string
     */
    protected $driver;

    /**
     * @var string
     */
    protected $connection;

    /**
     * @var Promise
     */
    protected $promise;

    /**
     * Service constructor.
     *
     * @param Container        $container
     * @param Packer           $packer
     * @param SelectorContract $selector
     * @param ServiceFactory   $factory
     */
    public function __construct(Container $container, Packer $packer, SelectorContract $selector, ServiceFactory $factory)
    {
        $this->app = $container;
        $this->factory = $factory;
        $this->selector = $selector;
        $this->packer = $packer;
        $this->driver();
        $this->connection();
    }

    /**
     * @param string $service
     * @param string $uri
     * @param array  $params
     *
     * @return mixed
     */
    public function call(string $service, $uri = '', array $params = [])
    {
        return $this->execute($service, $uri, $params);
    }

    /**
     * @param string       $service
     * @param string|array $uri
     * @param array        $params
     *
     * @return object
     */
    protected function execute(string $service, $uri = '', array $params = [])
    {
        if (is_array($uri) || empty($uri)) {
            $params = $uri ? $uri : [];
            $url = explode('.', $service);
            $service = array_shift($url);
            $uri = implode('.', $url);
        }

        $data = $this->packer->pack($params ? ['call' => $uri, 'data' => $params] : ['call' => $uri]);

        try {
            $client = $this->factory->make($this->driver)->call($this->selector->select($service), $data);
        } catch (Exception $exception) {
            throw new ServiceException($exception);
        } finally {
            /* 服务上报，事件触发 */
            $serverInfo = compact('service', 'uri', 'params');
            $callParams = isset($exception) ? ['microservice.call.failed', [$this, $exception, $serverInfo]] : ['microservice.call', [$this, $serverInfo]];
            $this->app['events']->dispatch(...$callParams);
        }

        $this->statusCode = $client->getStatusCode();
        $content = $client->getContent();
        $this->content = $content ?
            new ServiceData($this->packer->unpack($content)) :
            null;

        return $this->content;
    }

    /**
     * @return bool
     */
    public function status(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * @return ServiceData|null
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @param null|string $driver
     *
     * @return Service
     */
    public function driver(?string $driver = null): self
    {
        $driver = $driver ? $driver : $this->app->make('config')->get('microservice-client.client');

        $connections = array_keys($this->app->make('config')->get('microservice-client.clients'));
        if (!in_array($driver, $connections, true)) {
            throw new DomainException("The Driver[{$driver}] not exists");
        }

        $this->driver = $driver;

        return $this;
    }

    /**
     * @param null|string $name
     *
     * @return Service
     */
    public function connection(?string $name = null): self
    {
        $this->connection = $name ? $name : $this->app->make('config')->get('microservice-client.connection');

        return $this;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if (is_null($this->content)) {
            return;
        }

        return $this->content->data($name);
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return $this->call($name, ...$arguments);
    }
}
