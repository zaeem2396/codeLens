<?php

declare(strict_types=1);

namespace CodeLens\Core\Usage\Resolvers;

use CodeLens\Core\Index\SymbolRegistry;
use CodeLens\Core\Usage\CallReference;
use CodeLens\Core\Usage\CallType;

/**
 * Resolves framework-specific patterns.
 *
 * Laravel:
 * - Facade resolution (Cache::get() -> Illuminate\Cache\Repository::get())
 * - Service container (app(ServiceClass::class))
 * - Route definitions
 *
 * Symfony:
 * - Service autowiring
 * - Event dispatcher
 */
final class FrameworkResolver implements ResolverInterface
{
    /**
     * Laravel facade mappings.
     *
     * @var array<string, string>
     */
    private const LARAVEL_FACADES = [
        'App' => 'Illuminate\Foundation\Application',
        'Artisan' => 'Illuminate\Contracts\Console\Kernel',
        'Auth' => 'Illuminate\Auth\AuthManager',
        'Blade' => 'Illuminate\View\Compilers\BladeCompiler',
        'Broadcast' => 'Illuminate\Contracts\Broadcasting\Factory',
        'Bus' => 'Illuminate\Contracts\Bus\Dispatcher',
        'Cache' => 'Illuminate\Cache\CacheManager',
        'Config' => 'Illuminate\Config\Repository',
        'Cookie' => 'Illuminate\Cookie\CookieJar',
        'Crypt' => 'Illuminate\Encryption\Encrypter',
        'DB' => 'Illuminate\Database\DatabaseManager',
        'Event' => 'Illuminate\Events\Dispatcher',
        'File' => 'Illuminate\Filesystem\Filesystem',
        'Gate' => 'Illuminate\Contracts\Auth\Access\Gate',
        'Hash' => 'Illuminate\Contracts\Hashing\Hasher',
        'Http' => 'Illuminate\Http\Client\Factory',
        'Lang' => 'Illuminate\Translation\Translator',
        'Log' => 'Illuminate\Log\LogManager',
        'Mail' => 'Illuminate\Mail\Mailer',
        'Notification' => 'Illuminate\Notifications\ChannelManager',
        'Password' => 'Illuminate\Auth\Passwords\PasswordBrokerManager',
        'Queue' => 'Illuminate\Queue\QueueManager',
        'RateLimiter' => 'Illuminate\Cache\RateLimiter',
        'Redirect' => 'Illuminate\Routing\Redirector',
        'Redis' => 'Illuminate\Redis\RedisManager',
        'Request' => 'Illuminate\Http\Request',
        'Response' => 'Illuminate\Contracts\Routing\ResponseFactory',
        'Route' => 'Illuminate\Routing\Router',
        'Schema' => 'Illuminate\Database\Schema\Builder',
        'Session' => 'Illuminate\Session\SessionManager',
        'Storage' => 'Illuminate\Filesystem\FilesystemManager',
        'URL' => 'Illuminate\Routing\UrlGenerator',
        'Validator' => 'Illuminate\Validation\Factory',
        'View' => 'Illuminate\View\Factory',
    ];

    /**
     * Laravel helper functions that resolve to classes.
     *
     * @var array<string, string>
     */
    private const LARAVEL_HELPERS = [
        'app' => 'Illuminate\Foundation\Application',
        'auth' => 'Illuminate\Auth\AuthManager',
        'cache' => 'Illuminate\Cache\CacheManager',
        'config' => 'Illuminate\Config\Repository',
        'cookie' => 'Illuminate\Cookie\CookieJar',
        'event' => 'Illuminate\Events\Dispatcher',
        'logger' => 'Illuminate\Log\LogManager',
        'redirect' => 'Illuminate\Routing\Redirector',
        'request' => 'Illuminate\Http\Request',
        'response' => 'Illuminate\Contracts\Routing\ResponseFactory',
        'session' => 'Illuminate\Session\SessionManager',
        'url' => 'Illuminate\Routing\UrlGenerator',
        'validator' => 'Illuminate\Validation\Factory',
        'view' => 'Illuminate\View\Factory',
    ];

    /**
     * Symfony common service patterns.
     *
     * @var array<string, string>
     */
    private const SYMFONY_PATTERNS = [
        'EntityManagerInterface' => 'Doctrine\ORM\EntityManager',
        'LoggerInterface' => 'Psr\Log\LoggerInterface',
        'RequestStack' => 'Symfony\Component\HttpFoundation\RequestStack',
        'RouterInterface' => 'Symfony\Component\Routing\RouterInterface',
        'EventDispatcherInterface' => 'Symfony\Component\EventDispatcher\EventDispatcherInterface',
        'CacheInterface' => 'Symfony\Contracts\Cache\CacheInterface',
        'MailerInterface' => 'Symfony\Component\Mailer\MailerInterface',
        'MessageBusInterface' => 'Symfony\Component\Messenger\MessageBusInterface',
        'SerializerInterface' => 'Symfony\Component\Serializer\SerializerInterface',
        'ValidatorInterface' => 'Symfony\Component\Validator\Validator\ValidatorInterface',
    ];

    private string $framework = 'auto';

    public function __construct(string $framework = 'auto')
    {
        $this->framework = $framework;
    }

    public function getId(): string
    {
        return 'framework';
    }

    public function getName(): string
    {
        return 'Framework Resolver';
    }

    public function getPriority(): int
    {
        return 50; // Lower priority - runs after direct resolution
    }

    public function canResolve(CallReference $reference): bool
    {
        return in_array($reference->callType, [
            CallType::StaticCall,
            CallType::FunctionCall,
            CallType::MethodCall,
        ], true);
    }

    public function resolve(CallReference $reference, SymbolRegistry $registry): CallReference
    {
        // Try Laravel resolution
        if ($this->framework === 'auto' || $this->framework === 'laravel') {
            $resolved = $this->resolveLaravel($reference);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        // Try Symfony resolution
        if ($this->framework === 'auto' || $this->framework === 'symfony') {
            $resolved = $this->resolveSymfony($reference, $registry);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return $reference;
    }

    /**
     * Resolve Laravel-specific patterns.
     */
    private function resolveLaravel(CallReference $reference): ?CallReference
    {
        // Handle static Facade calls
        if ($reference->callType === CallType::StaticCall) {
            return $this->resolveFacade($reference);
        }

        // Handle helper function calls
        if ($reference->callType === CallType::FunctionCall) {
            return $this->resolveHelper($reference);
        }

        return null;
    }

    /**
     * Resolve Laravel Facade calls.
     */
    private function resolveFacade(CallReference $reference): ?CallReference
    {
        $calleeFqn = $reference->calleeFqn;

        if (! str_contains($calleeFqn, '::')) {
            return null;
        }

        [$className, $methodName] = explode('::', $calleeFqn, 2);

        // Get just the class name without namespace
        $shortName = class_basename($className);

        // Check if it's a known facade
        if (isset(self::LARAVEL_FACADES[$shortName])) {
            $realClass = self::LARAVEL_FACADES[$shortName];

            return new CallReference(
                callerFqn: $reference->callerFqn,
                calleeFqn: $realClass . '::' . $methodName,
                callType: $reference->callType,
                file: $reference->file,
                line: $reference->line,
                confidence: 0.8, // Good confidence for facade resolution
                context: array_merge($reference->context, [
                    'resolved_from' => 'laravel_facade',
                    'facade' => $shortName,
                    'real_class' => $realClass,
                ]),
            );
        }

        // Check for Facades namespace pattern
        if (str_contains($className, 'Facades\\') || str_ends_with($className, 'Facade')) {
            return new CallReference(
                callerFqn: $reference->callerFqn,
                calleeFqn: $calleeFqn,
                callType: $reference->callType,
                file: $reference->file,
                line: $reference->line,
                confidence: 0.6, // Lower confidence - unknown facade
                context: array_merge($reference->context, [
                    'is_facade' => true,
                    'facade_class' => $className,
                ]),
            );
        }

        return null;
    }

    /**
     * Resolve Laravel helper function calls.
     */
    private function resolveHelper(CallReference $reference): ?CallReference
    {
        $functionName = strtolower($reference->calleeFqn);

        if (isset(self::LARAVEL_HELPERS[$functionName])) {
            $resolvedClass = self::LARAVEL_HELPERS[$functionName];

            return new CallReference(
                callerFqn: $reference->callerFqn,
                calleeFqn: $resolvedClass,
                callType: CallType::NewInstance, // Helpers return instances
                file: $reference->file,
                line: $reference->line,
                confidence: 0.7,
                context: array_merge($reference->context, [
                    'resolved_from' => 'laravel_helper',
                    'helper' => $functionName,
                    'resolved_class' => $resolvedClass,
                ]),
            );
        }

        return null;
    }

    /**
     * Resolve Symfony-specific patterns.
     */
    private function resolveSymfony(CallReference $reference, SymbolRegistry $registry): ?CallReference
    {
        // Check for common Symfony interface patterns
        $calleeFqn = $reference->calleeFqn;

        if (str_contains($calleeFqn, '::')) {
            [$className, $methodName] = explode('::', $calleeFqn, 2);

            // Check Symfony patterns
            foreach (self::SYMFONY_PATTERNS as $interfaceSuffix => $implementation) {
                if (str_ends_with($className, $interfaceSuffix)) {
                    return new CallReference(
                        callerFqn: $reference->callerFqn,
                        calleeFqn: $calleeFqn,
                        callType: CallType::Interface,
                        file: $reference->file,
                        line: $reference->line,
                        confidence: 0.7,
                        context: array_merge($reference->context, [
                            'symfony_service' => true,
                            'interface' => $className,
                            'likely_implementation' => $implementation,
                        ]),
                    );
                }
            }
        }

        return null;
    }

    /**
     * Set the framework to resolve for.
     */
    public function setFramework(string $framework): void
    {
        $this->framework = $framework;
    }

    /**
     * Get the current framework setting.
     */
    public function getFramework(): string
    {
        return $this->framework;
    }

    /**
     * Get all known Laravel facades.
     *
     * @return array<string, string>
     */
    public function getLaravelFacades(): array
    {
        return self::LARAVEL_FACADES;
    }

    /**
     * Get all known Laravel helpers.
     *
     * @return array<string, string>
     */
    public function getLaravelHelpers(): array
    {
        return self::LARAVEL_HELPERS;
    }
}

/**
 * Get class basename - standalone helper.
 */
function class_basename(string $class): string
{
    $parts = explode('\\', $class);

    return end($parts);
}

