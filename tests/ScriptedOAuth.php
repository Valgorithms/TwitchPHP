<?php

namespace Twitch\Tests;

use GuzzleHttp\Psr7\Response;
use React\Http\Browser;
use React\Promise\PromiseInterface;
use Twitch\Http\OAuth;
use Twitch\Twitch;

/** OAuth HTTP responses without real tokens or network access. */
final class ScriptedOAuth extends Browser
{
    public array $validations = [];

    public array $grants = [];

    public array $calls = [];

    public function __construct(private string $clientId)
    {
    }

    public static function attach(Twitch $twitch): self
    {
        $browser = new self($twitch->getClientId());
        (new \ReflectionProperty(OAuth::class, 'browser'))->setValue($twitch->getOAuth(), $browser);

        return $browser;
    }

    public function get($url, array $headers = []): PromiseInterface
    {
        $this->calls[] = ['GET', $url, $headers];
        $info = array_shift($this->validations) ?? ['client_id' => $this->clientId, 'user_id' => '1', 'login' => 'bot', 'scopes' => ['chat:read', 'chat:edit']];

        return $this->response($info);
    }

    public function post($url, array $headers = [], $body = ''): PromiseInterface
    {
        $this->calls[] = ['POST', $url, $body];

        return $this->response(array_shift($this->grants) ?? ['access_token' => 'new-app', 'expires_in' => 3600, 'token_type' => 'bearer']);
    }

    private function response(array|\Throwable|PromiseInterface $value): PromiseInterface
    {
        if ($value instanceof PromiseInterface) {
            return $value->then(fn ($result) => $this->response($result));
        }

        return $value instanceof \Throwable ? \React\Promise\reject($value) : \React\Promise\resolve(new Response(200, [], json_encode($value)));
    }
}
