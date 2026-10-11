<?php

declare(strict_types=1);

namespace Ksfraser\FA\Mail;

/**
 * Outbound sender options, in the shape consumers of
 * ksf_mail_sender_selector expect.
 *
 * This is a plain value object implementing the consumer-owned contract
 * (options() and selected()). It intentionally does NOT extend the calendar's
 * OutboundSenderSelector: making the responder depend on the consumer's class
 * would reintroduce the coupling the hook exists to avoid. The consumer validates
 * the shape through OutboundSenderSelector::satisfies().
 *
 * Options are plain arrays with value/label keys so that alternative responders
 * can supply their own option representation.
 *
 * @package Ksfraser\FA\Mail
 * @since 1.0.0
 */
final class SenderSelector
{
    /** @var array<int, array{value: string, label: string}> */
    private $options;

    /** @var string */
    private $selected;

    /**
     * @param array<int, array{value: string, label: string}> $options
     * @param string                                          $selected
     */
    public function __construct(array $options, string $selected = '')
    {
        $this->options = array_values($options);
        $this->selected = $selected;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * @return string
     */
    public function selected(): string
    {
        return $this->selected;
    }
}
