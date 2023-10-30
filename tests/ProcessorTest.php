<?php

use PHPUnit\Framework\TestCase;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

final class ProcessorTest extends TestCase
{
    public function testEmail()
    {
        $this->assertFiltered('test@example.org');
        $this->assertFiltered('test123@example.org');
        $this->assertFiltered('TEST@example.org');
        $this->assertFiltered('test@sub.example.org');
        $this->assertFiltered('test@sub.sub2.example.org');
        $this->assertFiltered('test+test@example.org');
        $this->assertFiltered('test.test@example.org');
        $this->assertFiltered('test-test@example.org');
        $this->assertFiltered('test@example.us');
        $this->assertFiltered('test@example.science');
        $this->assertNotFiltered('test@example.org', email: false);
    }

    public function testPhone()
    {
        $this->assertFiltered('555-555-5555');
        $this->assertFiltered('555 555 5555');
        $this->assertFiltered('555.555.5555');
        $this->assertNotFiltered('5555555555');
        $this->assertNotFiltered('555-555-5555', phone: false);

        // use 7 digit min
        // https://stackoverflow.com/questions/14894899/what-is-the-minimum-length-of-a-valid-international-phone-number
        $this->assertNotFiltered('+123456');
        $this->assertFiltered('+1234567');
        $this->assertFiltered('+15555555555');
        $this->assertFiltered('+123456789012345');
        $this->assertNotFiltered('+1234567890123456');
    }

    public function testCreditCard()
    {
        $this->assertFiltered('4242-4242-4242-4242');
        $this->assertFiltered('4242 4242 4242 4242');
        $this->assertFiltered('4242424242424242');
        $this->assertNotFiltered('0242424242424242');
        $this->assertNotFiltered('55555555-5555-5555-5555-555555555555'); // uuid
        $this->assertNotFiltered('4242-4242-4242-4242', creditCard: false);
    }

    public function testSsn()
    {
        $this->assertFiltered('123-45-6789');
        $this->assertFiltered('123 45 6789');
        $this->assertNotFiltered('123456789');
        $this->assertNotFiltered('123-45-6789', ssn: false);
    }

    public function testIp()
    {
        $this->assertNotFiltered('127.0.0.1');
        $this->assertFiltered('127.0.0.1', ip: true);
    }

    public function testUrlPassword()
    {
        $this->assertFiltered('https://user:pass@host', expected: 'https://user:[FILTERED]@host');
        $this->assertFiltered('https://user:pass@host.com', expected: 'https://user:[FILTERED]@host.com');
        $this->assertNotFiltered('https://user:pass@host', urlPassword: false);
    }

    public function testMac()
    {
        $this->assertNotFiltered('ff:ff:ff:ff:ff:ff');
        $this->assertFiltered('ff:ff:ff:ff:ff:ff', mac: true);
        $this->assertFiltered('a1:b2:c3:d4:e5:f6', mac: true);
        $this->assertFiltered('A1:B2:C3:D4:E5:F6', mac: true);
    }

    public function testMultiple()
    {
        $this->assertFiltered('test@example.org test2@example.org 123-45-6789', expected: '[FILTERED] [FILTERED] [FILTERED]');
    }

    public function testOrder()
    {
        $this->assertFiltered('123-45-6789@example.org');
        $this->assertFiltered('127.0.0.1@example.org', ip: true);
    }

    public function testContext()
    {
        $stream = fopen('php://memory', 'r+');
        $logger = $this->createLogger($stream);
        $logger->pushProcessor(new Logstop\Processor());

        $logger->info('Hi', ['email' => 'test@example.org']);
        $contents = $this->readStream($stream);
        $this->assertStringContainsString('{"email":"[FILTERED]"}', $contents);
        $this->assertStringNotContainsString('test@example.org', $contents);
    }

    public function testContextWithMultiDimensionalArray()
    {
        $stream = fopen('php://memory', 'r+');
        $logger = $this->createLogger($stream);
        $logger->pushProcessor(new Logstop\Processor());

        $logger->info(
            'Hi',
            ['params' => ['user' => ['email' => 'test@example.org', 'name' => 'Alice']]]
        );
        $contents = $this->readStream($stream);
        $this->assertStringContainsString('"email":"[FILTERED]"', $contents);
        $this->assertStringNotContainsString('test@example.org', $contents);
    }

    public function testPsrLogMessageProcessorBefore()
    {
        $stream = fopen('php://memory', 'r+');
        $logger = $this->createLogger($stream);
        $logger->pushProcessor(new PsrLogMessageProcessor());
        $logger->pushProcessor(new Logstop\Processor());

        $logger->info('begin {email} end', ['email' => 'test@example.org']);
        $contents = $this->readStream($stream);
        $this->assertStringContainsString('begin [FILTERED] end', $contents);
        $this->assertStringContainsString('{"email":"[FILTERED]"}', $contents);
        $this->assertStringNotContainsString('test@example.org', $contents);
    }

    public function testPsrLogMessageProcessorAfter()
    {
        $stream = fopen('php://memory', 'r+');
        $logger = $this->createLogger($stream);
        $logger->pushProcessor(new Logstop\Processor());
        $logger->pushProcessor(new PsrLogMessageProcessor());

        $logger->info('begin {email} end', ['email' => 'test@example.org']);
        $contents = $this->readStream($stream);
        $this->assertStringContainsString('begin [FILTERED] end', $contents);
        $this->assertStringContainsString('{"email":"[FILTERED]"}', $contents);
        $this->assertStringNotContainsString('test@example.org', $contents);
    }

    private function assertFiltered($message, $expected = '[FILTERED]', ...$args)
    {
        $stream = fopen('php://memory', 'r+');
        $logger = $this->createLogger($stream, new LineFormatter('%message%'));
        $logger->pushProcessor(new Logstop\Processor(...$args));

        $logger->info("begin $message end");
        $this->assertEquals("begin $expected end", $this->readStream($stream));

        $this->clearStream($stream);
        $quotedMessage = urlencode($message);
        $logger->info("begin $quotedMessage end");
        $this->assertEquals("begin $expected end", urldecode($this->readStream($stream)));
    }

    private function assertNotFiltered($message, $expected = '[FILTERED]', ...$args)
    {
        $this->assertFiltered($message, ...$args, expected: $message);
    }

    private function createLogger($stream, $formatter = null)
    {
        $handler = new StreamHandler($stream, Level::Info);

        if (!is_null($formatter)) {
            $handler->setFormatter($formatter);
        }

        $logger = new Logger('test');
        $logger->pushHandler($handler);
        return $logger;
    }

    private function readStream($stream)
    {
        rewind($stream);
        return stream_get_contents($stream);
    }

    private function clearStream($stream)
    {
        ftruncate($stream, 0);
    }
}
