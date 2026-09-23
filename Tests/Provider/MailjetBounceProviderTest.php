<?php declare(strict_types=1);

namespace MauticPlugin\MauticBounceWebhookBundle\Tests\Provider;

use Mautic\LeadBundle\Entity\DoNotContact;
use MauticPlugin\MauticBounceWebhookBundle\Provider\MailjetBounceProvider;
use MauticPlugin\MauticBounceWebhookBundle\Provider\TransportCallbackInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class MailjetBounceProviderTest extends TestCase
{
    private TransportCallbackInterface&MockObject $transportCallback;
    private LoggerInterface&MockObject $logger;
    private MailjetBounceProvider $provider;

    protected function setUp(): void
    {
        $this->transportCallback = $this->createMock(TransportCallbackInterface::class);
        $this->logger            = $this->createMock(LoggerInterface::class);
        $this->provider          = new MailjetBounceProvider($this->transportCallback, $this->logger);
    }

    // -------------------------------------------------------------------------
    // supports()
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\DataProvider('mailjetEventTypeProvider')]
    public function testSupportsReturnsTrueForAllMailjetEventTypes(string $eventType): void
    {
        $request = $this->makeRequest([['event' => $eventType, 'email' => 'test@example.com']]);

        $this->assertTrue($this->provider->supports($request), "supports() should return true for event type '$eventType'");
    }

    public static function mailjetEventTypeProvider(): array
    {
        return [
            'bounce'  => ['bounce'],
            'blocked' => ['blocked'],
            'spam'    => ['spam'],
            'sent'    => ['sent'],
            'open'    => ['open'],
            'click'   => ['click'],
            'unsub'   => ['unsub'],
        ];
    }

    public function testSupportsReturnsFalseForUnknownEventType(): void
    {
        $request = $this->makeRequest([['event' => 'unknown_type', 'email' => 'test@example.com']]);

        $this->assertFalse($this->provider->supports($request));
    }

    public function testSupportsReturnsFalseForEmptyPayload(): void
    {
        $request = $this->makeRequest([]);

        $this->assertFalse($this->provider->supports($request));
    }

    public function testSupportsReturnsFalseForInvalidJson(): void
    {
        $request = Request::create('/', 'POST', [], [], [], [], 'not-json');

        $this->assertFalse($this->provider->supports($request));
    }

    public function testSupportsReturnsFalseForNonArrayPayload(): void
    {
        $request = Request::create('/', 'POST', [], [], [], [], '"just a string"');

        $this->assertFalse($this->provider->supports($request));
    }

    public function testSupportsReturnsFalseWhenFirstEventHasNoEventKey(): void
    {
        $request = $this->makeRequest([['email' => 'test@example.com']]);

        $this->assertFalse($this->provider->supports($request));
    }

    // -------------------------------------------------------------------------
    // process() — bounce
    // -------------------------------------------------------------------------

    public function testHardBounceAddsDncEntry(): void
    {
        $this->transportCallback
            ->expects($this->once())
            ->method('addFailureByAddress')
            ->with('bounce@example.com', 'Mailjet hard bounce: mailbox-full', DoNotContact::BOUNCED);

        $request = $this->makeRequest([[
            'event'        => 'bounce',
            'email'        => 'bounce@example.com',
            'hard_bounce'  => true,
            'error'        => 'mailbox-full',
        ]]);

        $this->provider->process($request);
    }

    public function testSoftBounceDoesNotAddDncEntry(): void
    {
        $this->transportCallback->expects($this->never())->method('addFailureByAddress');

        $request = $this->makeRequest([[
            'event'       => 'bounce',
            'email'       => 'soft@example.com',
            'hard_bounce' => false,
        ]]);

        $this->provider->process($request);
    }

    public function testHardBounceWithoutErrorFieldUsesDefaultComment(): void
    {
        $this->transportCallback
            ->expects($this->once())
            ->method('addFailureByAddress')
            ->with('bounce@example.com', 'Mailjet hard bounce: hard bounce', DoNotContact::BOUNCED);

        $request = $this->makeRequest([[
            'event'       => 'bounce',
            'email'       => 'bounce@example.com',
            'hard_bounce' => true,
        ]]);

        $this->provider->process($request);
    }

    // -------------------------------------------------------------------------
    // process() — blocked
    // -------------------------------------------------------------------------

    public function testBlockedAddsDncEntry(): void
    {
        $this->transportCallback
            ->expects($this->once())
            ->method('addFailureByAddress')
            ->with('blocked@example.com', 'Mailjet blocked: user-unknown', DoNotContact::BOUNCED);

        $request = $this->makeRequest([[
            'event' => 'blocked',
            'email' => 'blocked@example.com',
            'error' => 'user-unknown',
        ]]);

        $this->provider->process($request);
    }

    public function testBlockedWithoutErrorFieldUsesDefaultComment(): void
    {
        $this->transportCallback
            ->expects($this->once())
            ->method('addFailureByAddress')
            ->with('blocked@example.com', 'Mailjet blocked: blocked', DoNotContact::BOUNCED);

        $request = $this->makeRequest([[
            'event' => 'blocked',
            'email' => 'blocked@example.com',
        ]]);

        $this->provider->process($request);
    }

    // -------------------------------------------------------------------------
    // process() — spam
    // -------------------------------------------------------------------------

    public function testSpamAddsUnsubscribedDncEntry(): void
    {
        $this->transportCallback
            ->expects($this->once())
            ->method('addFailureByAddress')
            ->with('spam@example.com', 'Mailjet spam complaint', DoNotContact::UNSUBSCRIBED);

        $request = $this->makeRequest([[
            'event' => 'spam',
            'email' => 'spam@example.com',
        ]]);

        $this->provider->process($request);
    }

    // -------------------------------------------------------------------------
    // process() — ignored event types
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\DataProvider('ignoredEventTypeProvider')]
    public function testIgnoredEventTypesDoNotAddDncEntry(string $eventType): void
    {
        $this->transportCallback->expects($this->never())->method('addFailureByAddress');

        $request = $this->makeRequest([[
            'event' => $eventType,
            'email' => 'user@example.com',
        ]]);

        $this->provider->process($request);
    }

    public static function ignoredEventTypeProvider(): array
    {
        return [
            'sent'  => ['sent'],
            'open'  => ['open'],
            'click' => ['click'],
            'unsub' => ['unsub'],
        ];
    }

    // -------------------------------------------------------------------------
    // process() — edge cases
    // -------------------------------------------------------------------------

    public function testEventsWithEmptyEmailAreSkipped(): void
    {
        $this->transportCallback->expects($this->never())->method('addFailureByAddress');

        $request = $this->makeRequest([[
            'event'       => 'bounce',
            'email'       => '',
            'hard_bounce' => true,
        ]]);

        $this->provider->process($request);
    }

    public function testMixedBatchProcessesCorrectEvents(): void
    {
        // Only the hard bounce and blocked should trigger addFailureByAddress
        $this->transportCallback
            ->expects($this->exactly(2))
            ->method('addFailureByAddress');

        $request = $this->makeRequest([
            ['event' => 'sent',    'email' => 'sent@example.com'],
            ['event' => 'bounce',  'email' => 'hard@example.com', 'hard_bounce' => true,  'error' => 'mailbox-full'],
            ['event' => 'bounce',  'email' => 'soft@example.com', 'hard_bounce' => false],
            ['event' => 'blocked', 'email' => 'blk@example.com'],
            ['event' => 'open',    'email' => 'open@example.com'],
        ]);

        $this->provider->process($request);
    }

    public function testNonArrayItemsInBatchAreSkipped(): void
    {
        $this->transportCallback->expects($this->never())->method('addFailureByAddress');

        // Manually build a payload with a non-array item mixed in
        $content = json_encode(['not-an-array', ['event' => 'sent', 'email' => 'x@example.com']]);
        $request = Request::create('/', 'POST', [], [], [], [], $content);

        $this->provider->process($request);
    }

    public function testProcessDoesNothingForInvalidJson(): void
    {
        $this->transportCallback->expects($this->never())->method('addFailureByAddress');

        $request = Request::create('/', 'POST', [], [], [], [], 'bad-json');

        $this->provider->process($request);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeRequest(array $payload): Request
    {
        return Request::create('/', 'POST', [], [], [], [], json_encode($payload));
    }
}
