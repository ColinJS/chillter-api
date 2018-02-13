<?php

namespace OneSignal;

use C\Event\AbstractEvent;
use OneSignal\Model\Notification;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class Client
{
    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * Client constructor
     *
     * @param Configuration $configuration
     * @param LoggerInterface $logger
     */
    public function __construct(Configuration $configuration, LoggerInterface $logger)
    {
        $this->configuration = $configuration;
        $this->logger = $logger;

        if (!$this->configuration->getKey()) {
            throw new \LogicException('REST API key must be provided.');
        }
    }

    /**
     * Request notification to OneSignal
     *
     * @param Notification $notification
     * @return Response
     * @throws \Exception
     */
    public function postNotification(Notification $notification)
    {
        $data = [
            'include_player_ids' => $notification->getRecipients(),
            'ttl' => $notification->getTtl(),
            'priority' => $notification->getPriority(),
            'contents' => [],
            'headings' => [],
            'ios_badgeType' => 'Increase',
            'ios_badgeCount' => 1,
        ];

        if ($notification->getEvent() instanceof AbstractEvent) {
            $data['data'] = $notification->getEvent()->toArray();
        }

        foreach ($notification->getContents() as $content) {
            $data['contents'][$content->getLanguageCode()] = $content->getContent();

            if ($content->getHeading()) {
                $data['headings'][$content->getLanguageCode()] = $content->getHeading();
            }
        }

        $requestUrl = $this->buildUrl('notifications');
        $startRequest = microtime(true);
        $response = $this->getClient()->post($requestUrl, [
            'json' => $data
        ]);

        $contents = $response->getBody()->getContents();
        $responseBody = \GuzzleHttp\json_decode($contents, true);

        $this->logger->debug(null, [
            'request_url' => $requestUrl,
            'request_body' => \GuzzleHttp\json_encode($data),
            'response_status' => $response->getStatusCode(),
            'response_body' => $contents,
            'response_time' => (int)((microtime(true) - $startRequest) * 1000),
        ]);

        if (array_key_exists('id', $responseBody)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        if (array_key_exists('errors', $responseBody)) {
            return new JsonResponse($responseBody, Response::HTTP_BAD_REQUEST);
        }

        throw new \Exception("Unprocessable response from OneSignal.\nResponse body: $contents");
    }

    /**
     * Get Guzzle Client
     *
     * @return \GuzzleHttp\Client
     */
    protected function getClient()
    {
        if (!$this->client instanceof \GuzzleHttp\Client) {
            $this->client = new \GuzzleHttp\Client([
                'http_errors' => false,
                'headers' => [
                    'Authorization' => "Basic {$this->configuration->getKey()}"
                ],
            ]);
        }

        return $this->client;
    }

    /**
     * Build full request URL
     *
     * @param $method
     * @param array $parameters
     * @return string
     */
    protected function buildUrl($method, array $parameters = array())
    {
        $parameters['app_id'] = 'be2f4c2c-0d4e-4ca8-a9c8-9d48673e2261';

        $url = $this->getEndpointUrl($method);

        if ($parameters) {
            $url .= '?' . http_build_query($parameters);
        }

        return $url;
    }

    /**
     * Get API method endpoint URL, for example "https://onesignal.com/api/v1/notifications"
     *
     * @param $method
     * @return string
     */
    protected function getEndpointUrl($method)
    {
        return "{$this->configuration->getProtocol()}://{$this->configuration->getHost()}/api/"
            . "v{$this->configuration->getVersion()}/$method";
    }
}
