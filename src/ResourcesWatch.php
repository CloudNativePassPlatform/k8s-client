<?php

namespace CNPP\K8sClient;

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
     * @param string $domain
     * @param int $port
     * @param bool $sll
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

    public function watch(string $api, $callback = null, array $param = [])
    {
        // 创建ConfigMap
        $now_version = json_decode((new KubeApi($this->domain,$this->port,$this->token))->request()->get($api, [
            'query' => http_build_query([
                'limit' => 15
            ])
        ])->getBody()->getContents(),true);
        $resourceVersion = $now_version['metadata']['resourceVersion'];
        $request = new Request();
        $request->method = 'GET';
        $request->pipeline = true;
        $param['watch'] = 'true';
        $param['resourceVersion'] = $resourceVersion;
        $request->path = "{$api}?" . http_build_query($param);
        $request->headers = [
            'host' => $this->client->host,
            'authorization' => 'Bearer ' .$this->token,
        ];
        $this->client->send($request);
        go(function() use($api){
           if(!$this->client->ping()){
               $this->client->close();
               sleep(1);
               $this->client->connect();
               // 创建ConfigMap
               $now_version = json_decode((new KubeApi($this->domain,$this->port,$this->token))->request()->get($api, [
                   'query' => http_build_query([
                       'limit' => 15
                   ])
               ])->getBody()->getContents(),true);
               $resourceVersion = $now_version['metadata']['resourceVersion'];
               $request = new Request();
               $request->method = 'GET';
               $request->pipeline = true;
               $param['watch'] = 'true';
               $param['resourceVersion'] = $resourceVersion;
               $request->path = "{$api}?" . http_build_query($param);
               $request->headers = [
                   'host' => $this->client->host,
                   'authorization' => 'Bearer ' . $this->token,
               ];
               $this->client->send($request);
           }
        });
        go(function () use ($callback) {
            while (($response = $this->client->read()) instanceof Response) {
                $callback($response);
            }
        });
    }
}