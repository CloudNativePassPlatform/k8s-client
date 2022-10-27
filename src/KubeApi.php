<?php

namespace CNPP\K8sClient;


use GuzzleHttp\Client;

/**
 *
 */
class KubeApi
{

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    public function __construct(string $host,int $port,string $token)
    {
        $this->client = new Client([
            'verify' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'authorization' => 'Bearer ' .$token,
            ],
            'base_uri' => 'https://' . $host . ':' . $port,
            'timeout' => 5
        ]);
    }
    public function request()
    {
        return $this->client;
    }
}