<?php
// vendor/bin/phpunit tests/HelixTest.php
define('TWITCHBOT_START', microtime(true));
ini_set('display_errors', 1);
error_reporting(E_ALL);

set_time_limit(0);
ignore_user_abort(1);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1'); // Unlimited memory usage
define('MAIN_INCLUDED', 1); // Token and SQL credential files may be protected locally and require this to be defined to access

 //if (! $token_included = require getcwd() . '/token.php') // $token
    //throw new \Exception('Token file not found. Create a file named token.php in the root directory with the bot token.');
if (! $autoloader = require file_exists(__DIR__.'/../vendor/autoload.php') ? __DIR__.'/../vendor/autoload.php' : __DIR__.'/../../../autoload.php')
throw new \Exception('Composer autoloader not found. Run `composer install` and try again.');
function loadEnv(string $filePath = __DIR__ . '/../.env'): void
{
    if (! file_exists($filePath)) throw new \Exception("The .env file does not exist.");

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $trimmedLines = array_map('trim', $lines);
    $filteredLines = array_filter($trimmedLines, fn($line) => $line && ! str_starts_with($line, '#'));

    array_walk($filteredLines, function($line) {
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if (! array_key_exists($name, $_ENV)) putenv(sprintf('%s=%s', $name, $value));
    });
}
loadEnv(getcwd() . '/.env');

use PHPUnit\Framework\TestCase;
use React\Promise\PromiseInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use Twitch\Twitch;
use Twitch\Helix;
use Twitch\Exception\QueryException;
use Twitch\Exception\RateLimitException;


class HelixTest extends TestCase
{
    /**
     * @var Twitch|\PHPUnit\Framework\MockObject\MockObject
     */
    private $twitchMock;
    private $loopMock;
    private $helixMock;
    private $oauthToken;

    protected function setUp(): void
    {
        // Load the .env file using loadEnv function
        loadEnv(__DIR__ . '\..\.env');

        // Get the OAuth token from the .env file
        $this->oauthToken = getenv('secret');

        $this->twitchMock = $this->createMock(Twitch::class);
        $this->loopMock = $this->createMock(LoopInterface::class);
        $this->twitchMock->method('getLoop')->willReturn($this->loopMock);

        // Pass the twitchMock by reference
        $twitchMockRef = &$this->twitchMock;

        $this->helixMock = $this->getMockBuilder(Helix::class)
            ->setConstructorArgs([&$twitchMockRef])
            ->onlyMethods(['query'])
            ->getMock();
    }

    public function testQueryWithRateLimitHandlingRetriesAfterWaitTime(): void
    {
        $url = 'api.twitch.tv/helix/users?login=valgorithms';
        $method = 'GET';
        $data = null;
        $resetTime = time() + 5; // 5 seconds from now

        // Mock the query method to simulate a rate limit error and a successful response
        $this->helixMock->expects($this->exactly(5))
            ->method('query')
            ->withConsecutive(
                [$url, $method, $data],
                [$url, $method, $data]
            )
            ->willReturnOnConsecutiveCalls(
                $this->createRejectedPromise(429, ['Ratelimit-Reset' => $resetTime]),
                $this->createResolvedPromise('{"data":[{"id":"29034572","login":"valgorithms","display_name":"Valgorithms","type":"","broadcaster_type":"affiliate","description":"I\'m a teacher, programmer, and patient care advocate. I make things that make other things work. My primary focus is streaming games that my community enjoys playing.","profile_image_url":"https://static-cdn.jtvnw.net/jtv_user_pictures/f34c0861-ceef-45e4-a441-4b11944780b0-profile_image-300x300.png","offline_image_url":"https://static-cdn.jtvnw.net/jtv_user_pictures/3553a8d3-4f03-4bb0-a7c9-38be8022aa9e-channel_offline_image-1920x1080.jpeg","view_count":0,"email":"valzargaming@gmail.com","created_at":"2012-03-15T22:32:11Z"}]}'),
                $this->createResolvedPromise('{"data":[{"id":"29034572","login":"valgorithms","display_name":"Valgorithms","type":"","broadcaster_type":"affiliate","description":"I\'m a teacher, programmer, and patient care advocate. I make things that make other things work. My primary focus is streaming games that my community enjoys playing.","profile_image_url":"https://static-cdn.jtvnw.net/jtv_user_pictures/f34c0861-ceef-45e4-a441-4b11944780b0-profile_image-300x300.png","offline_image_url":"https://static-cdn.jtvnw.net/jtv_user_pictures/3553a8d3-4f03-4bb0-a7c9-38be8022aa9e-channel_offline_image-1920x1080.jpeg","view_count":0,"email":"valzargaming@gmail.com","created_at":"2012-03-15T22:32:11Z"}]}'),
                $this->createResolvedPromise('{"data":[{"id":"29034572","login":"valgorithms","display_name":"Valgorithms","type":"","broadcaster_type":"affiliate","description":"I\'m a teacher, programmer, and patient care advocate. I make things that make other things work. My primary focus is streaming games that my community enjoys playing.","profile_image_url":"https://static-cdn.jtvnw.net/jtv_user_pictures/f34c0861-ceef-45e4-a441-4b11944780b0-profile_image-300x300.png","offline_image_url":"https://static-cdn.jtvnw.net/jtv_user_pictures/3553a8d3-4f03-4bb0-a7c9-38be8022aa9e-channel_offline_image-1920x1080.jpeg","view_count":0,"email":"valzargaming@gmail.com","created_at":"2012-03-15T22:32:11Z"}]}'),
                $this->createResolvedPromise('{"data":[{"id":"29034572","login":"valgorithms","display_name":"Valgorithms","type":"","broadcaster_type":"affiliate","description":"I\'m a teacher, programmer, and patient care advocate. I make things that make other things work. My primary focus is streaming games that my community enjoys playing.","profile_image_url":"https://static-cdn.jtvnw.net/jtv_user_pictures/f34c0861-ceef-45e4-a441-4b11944780b0-profile_image-300x300.png","offline_image_url":"https://static-cdn.jtvnw.net/jtv_user_pictures/3553a8d3-4f03-4bb0-a7c9-38be8022aa9e-channel_offline_image-1920x1080.jpeg","view_count":0,"email":"valzargaming@gmail.com","created_at":"2012-03-15T22:32:11Z"}]}'),
            );

        // Mock the addTimer method to immediately invoke the callback
        $this->loopMock->expects($this->once())
            ->method('addTimer')
            ->with($this->equalTo(5), $this->callback(function ($callback) {
                $callback();
                return true;
            }))
            ->willReturn($this->createMock(TimerInterface::class));

        // Call the method and verify the result
        $promise = $this->helixMock->queryWithRateLimitHandling($url, $method, $data);
        $result = \React\Async\await($promise);

        // Debugging output
        var_dump($result);

        // Decode the JSON response
        $decodedResult = json_decode($result, true);

        // Assert that the "data" field exists in the response
        $this->assertArrayHasKey('data', $decodedResult);
    }

    private function createRejectedPromise(int $status, array $headers): PromiseInterface
    {
        $response = (object)[
            'status' => $status,
            'headers' => $headers,
        ];
        return new \React\Promise\RejectedPromise(new QueryException('Rate limit exceeded', $status, null, $response));
    }

    private function createResolvedPromise($value): PromiseInterface
    {
        return new \React\Promise\FulfilledPromise($value);
    }
}