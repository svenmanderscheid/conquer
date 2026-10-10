<?php
declare(strict_types=1);
namespace Conquer\Game\Notification;

/** Sends generic opt-in notifications only. No player/world/event IDs leave the server. */
final class PushTransport
{
    private ?\Minishlink\WebPush\WebPush $web = null;
    private ?array $accessToken = null;
    private \GuzzleHttp\Client $client;

    public function __construct(private readonly array $config)
    {
        PushConfig::dependencies();
        $this->client = new \GuzzleHttp\Client(['timeout'=>8,'connect_timeout'=>3,'allow_redirects'=>false,'http_errors'=>false]);
    }

    public function __invoke(array $subscription, array $payload): string
    {
        // Drop metadata fields even if a future caller accidentally adds them.
        $payload = array_intersect_key($payload,array_flip(['title','body','tag','url']));
        if ($subscription['platform'] === 'android') return $this->android($subscription,$payload);
        if (!PushConfig::configured($this->config)) return 'unavailable';
        PushService::validateEndpoint($subscription['endpoint']);
        if ($this->web === null) $this->web = new \Minishlink\WebPush\WebPush([
            'VAPID'=>['subject'=>$this->config['subject'],'publicKey'=>$this->config['public_key'],'privateKey'=>$this->config['private_key']],
        ], ['TTL'=>3600,'urgency'=>'normal'], $this->client);
        $report = $this->web->sendOneNotification(\Minishlink\WebPush\Subscription::create([
            'endpoint'=>$subscription['endpoint'],'publicKey'=>$subscription['public_key'],
            'authToken'=>$subscription['auth_token'],'contentEncoding'=>'aes128gcm',
        ]),json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        $status = $report->getResponse()?->getStatusCode() ?? 0;
        if ($report->isSuccess() && $status >= 200 && $status < 300) return 'sent';
        if ($report->isSubscriptionExpired()) return 'expired';
        return $status === 0 || $status === 429 || $status >= 500 || in_array($status,[401,403],true) ? 'retry' : 'failed';
    }

    private function android(array $subscription, array $payload): string
    {
        $credentials = PushConfig::firebaseCredentials($this->config);
        if ($credentials === null) return 'unavailable';
        if (!$this->accessToken || (int)($this->accessToken['expires_at'] ?? 0) < time()+60) {
            $auth = new \Google\Auth\Credentials\ServiceAccountCredentials('https://www.googleapis.com/auth/firebase.messaging',$credentials);
            $token = $auth->fetchAuthToken(\Google\Auth\HttpHandler\HttpHandlerFactory::build($this->client));
            if (empty($token['access_token'])) return 'retry';
            $this->accessToken = ['access_token'=>$token['access_token'],'expires_at'=>time()+(int)($token['expires_in'] ?? 3600)];
        }
        $response = $this->client->post('https://fcm.googleapis.com/v1/projects/'.$this->config['firebase_project_id'].'/messages:send',[
            'headers'=>['Authorization'=>'Bearer '.$this->accessToken['access_token']],
            'json'=>['message'=>[
                'token'=>substr($subscription['endpoint'],4),
                'notification'=>['title'=>$payload['title'],'body'=>$payload['body']],
                'data'=>['url'=>'city#city','tag'=>$payload['tag']],
                'android'=>['ttl'=>'3600s','priority'=>'normal','notification'=>['tag'=>$payload['tag'],'channel_id'=>'uok_game']],
            ]],
        ]);
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) return 'sent';
        $body = json_decode((string)$response->getBody(),true);
        foreach ($body['error']['details'] ?? [] as $detail) if (($detail['errorCode'] ?? '') === 'UNREGISTERED') return 'expired';
        if ($status === 401) $this->accessToken = null;
        return $status === 429 || $status >= 500 || in_array($status,[401,403],true) ? 'retry' : 'failed';
    }
}
