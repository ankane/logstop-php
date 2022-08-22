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
    }

    public function testPhone()
    {
        $this->assertFiltered('555-555-5555');
        $this->assertFiltered('555 555 5555');
        $this->assertFiltered('555.555.5555');
        $this->assertNotFiltered('5555555555');
    }

    public function testCreditCard()
    {
        $this->assertFiltered('4242-4242-4242-4242');
        $this->assertFiltered('4242 4242 4242 4242');
        $this->assertFiltered('4242424242424242');
        $this->assertNotFiltered('0242424242424242');
        $this->assertNotFiltered('55555555-5555-5555-5555-555555555555'); // uuid
    }

    public function testSsn()
    {
        $this->assertFiltered('123-45-6789');
        $this->assertFiltered('123 45 6789');
        $this->assertNotFiltered('123456789');
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

    private function assertFiltered($message, $expected = '[FILTERED]', $ip = false)
    {
        $stream = fopen('php://memory', 'r+');
        $logger = $this->createLogger($stream, new LineFormatter('%message%'));
        $logger->pushProcessor(new Logstop\Processor(ip: $ip));

        $logger->info("begin $message end");
        $this->assertEquals("begin $expected end", $this->readStream($stream));

        $this->clearStream($stream);
        $quotedMessage = urlencode($message);
        $logger->info("begin $quotedMessage end");
        $this->assertEquals("begin $expected end", urldecode($this->readStream($stream)));
    }

    private function assertNotFiltered($message, $expected = '[FILTERED]', $ip = false)
    {
        $this->assertFiltered($message, expected: $message, ip: $ip);
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
