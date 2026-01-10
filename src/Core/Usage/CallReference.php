<?php

declare(strict_types=1);

namespace CodeLens\Core\Usage;

/**
 * Represents a single call relationship between two code elements.
 *
 * Tracks who is calling what, where, and with what confidence.
 */
final class CallReference
{
    public function __construct(
        public readonly string $callerFqn,
        public readonly string $calleeFqn,
        public readonly CallType $callType,
        public readonly string $file,
        public readonly int $line,
        public readonly float $confidence = 1.0,
        public readonly array $context = [],
    ) {
    }

    /**
     * Get the caller class FQN (without method).
     */
    public function getCallerClass(): ?string
    {
        if (str_contains($this->callerFqn, '::')) {
            return explode('::', $this->callerFqn)[0];
        }

        return null;
    }

    /**
     * Get the caller method name.
     */
    public function getCallerMethod(): ?string
    {
        if (str_contains($this->callerFqn, '::')) {
            return explode('::', $this->callerFqn)[1];
        }

        return null;
    }

    /**
     * Get the callee class FQN (without method).
     */
    public function getCalleeClass(): ?string
    {
        if (str_contains($this->calleeFqn, '::')) {
            return explode('::', $this->calleeFqn)[0];
        }

        // For new instances, the callee is the class itself
        if ($this->callType === CallType::NewInstance) {
            return $this->calleeFqn;
        }

        return null;
    }

    /**
     * Get the callee method name.
     */
    public function getCalleeMethod(): ?string
    {
        if (str_contains($this->calleeFqn, '::')) {
            return explode('::', $this->calleeFqn)[1];
        }

        // For new instances, the method is __construct
        if ($this->callType === CallType::NewInstance) {
            return '__construct';
        }

        return null;
    }

    /**
     * Check if this is a high-confidence reference.
     */
    public function isHighConfidence(): bool
    {
        return $this->confidence >= 0.9;
    }

    /**
     * Check if this is a self-reference (calling own method).
     */
    public function isSelfReference(): bool
    {
        return $this->getCallerClass() === $this->getCalleeClass();
    }

    /**
     * Convert to array for storage.
     */
    public function toArray(): array
    {
        return [
            'caller_fqn' => $this->callerFqn,
            'callee_fqn' => $this->calleeFqn,
            'call_type' => $this->callType->value,
            'file' => $this->file,
            'line' => $this->line,
            'confidence' => $this->confidence,
            'context' => $this->context,
        ];
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            callerFqn: $data['caller_fqn'],
            calleeFqn: $data['callee_fqn'],
            callType: CallType::from($data['call_type']),
            file: $data['file'],
            line: $data['line'],
            confidence: $data['confidence'] ?? 1.0,
            context: $data['context'] ?? [],
        );
    }
}
