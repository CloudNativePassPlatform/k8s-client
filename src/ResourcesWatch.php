<?php

namespace CNPP\K8sClient;

use CNPP\K8sClient\Exception\ExitWatchException;
use GuzzleHttp\Exception\ClientException;
use Swoole\Coroutine\Http2\Client;
use Swoole\Http2\Request;
use Swoole\Http2\Response;

/**
 *
 */
class ResourcesWatch
{
    /**
     * @var Client
     */
    protected $client;
    /**
     * @var string
     */
    protected $domain;
    /**
     * @var int
     */
    protected $port;
    /**
     * @var string
     */
    protected $token;

    /**
     * ResourcesWatch constructor.
     * @param string $domain
     * @param int $port
     * @param string $token
     */
    public function __construct(string $domain, int $port, string $token)
    {
        $this->domain = $domain;
        $this->port = $port;
        $this->token = $token;
        $this->client = new Client($domain, $port, true);
        $this->client->set([
            'timeout' => -1,
            'ssl_host_name' => $domain
        ]);
        $this->client->connect();
    }

    /**
     * 监听资源
     * @param string $api
     * @param \Closure $callback
     * @param array $param
     * @param string|null $resourceVersion
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Throwable
     */
    public function watch(string $api, $callback = null, array $param = [],string $resourceVersion=null)
    {
        if($resourceVersion ===null){
            // 创建ConfigMap
            $now_version = json_decode((new KubeApi($this->domain,$this->port,$this->token))->request()->get($api, [
                'query' => http_build_query(array_merge([
                    'limit' => 15
                ],$param))
            ])->getBody()->getContents(),true);
            $resourceVersion = $now_version['metadata']['resourceVersion'];
        }
        $request = new Request();
        $request->method = 'GET';
        $request->pipeline = false;
        $param['watch'] = 'true';
        $param['resourceVersion'] = $resourceVersion;
        $request->path = "{$api}?" . http_build_query($param);
        $request->headers = [
            'host' => $this->client->host,
            'authorization' => 'Bearer ' .$this->token,
        ];
        $this->client->send($request);
        while (true) {
            usleep(10000);
            if(($response = $this->client->read()) instanceof Response && strlen(strval($response->data))>=1){
                try{
                    $callback($response);
                }catch (\Throwable $throwable){
                    if(get_class($throwable) == ExitWatchException::class){
                        break;
                    }
                    throw $throwable;
                }
            }
        }
    }
}