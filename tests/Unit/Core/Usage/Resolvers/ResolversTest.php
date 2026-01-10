<?php

declare(strict_types=1);

namespace CodeLens\Tests\Unit\Core\Usage\Resolvers;

use CodeLens\Core\Index\SymbolRegistry;
use CodeLens\Core\Index\Symbols\ClassSymbol;
use CodeLens\Core\Index\Symbols\InterfaceSymbol;
use CodeLens\Core\Index\Symbols\MethodSymbol;
use CodeLens\Core\Usage\CallReference;
use CodeLens\Core\Usage\CallType;
use CodeLens\Core\Usage\Resolvers\ClosureResolver;
use CodeLens\Core\Usage\Resolvers\ConstructorResolver;
use CodeLens\Core\Usage\Resolvers\DirectCallResolver;
use CodeLens\Core\Usage\Resolvers\FrameworkResolver;
use CodeLens\Core\Usage\Resolvers\InterfaceResolver;
use PHPUnit\Framework\TestCase;

class ResolversTest extends TestCase
{
    // ============================================
    // DirectCallResolver Tests
    // ============================================

    public function testDirectCallResolverCanResolveMethodCall(): void
    {
        $resolver = new DirectCallResolver();
        $ref = new CallReference(
            'A::m',
            'B::m',
            CallType::MethodCall,
            '/test.php',
            10
        );

        $this->assertTrue($resolver->canResolve($ref));
    }

    public function testDirectCallResolverCanResolveStaticCall(): void
    {
        $resolver = new DirectCallResolver();
        $ref = new CallReference(
            'A::m',
            'B::m',
            CallType::StaticCall,
            '/test.php',
            10
        );

        $this->assertTrue($resolver->canResolve($ref));
    }

    public function testDirectCallResolverCannotResolveFunctionCall(): void
    {
        $resolver = new DirectCallResolver();
        $ref = new CallReference(
            'A::m',
            'some_function',
            CallType::FunctionCall,
            '/test.php',
            10
        );

        $this->assertFalse($resolver->canResolve($ref));
    }

    public function testDirectCallResolverResolvesUnknownWithLowConfidence(): void
    {
        $resolver = new DirectCallResolver();
        $registry = new SymbolRegistry();

        $ref = new CallReference(
            'A::m',
            '(unresolved)::method',
            CallType::MethodCall,
            '/test.php',
            10
        );

        $resolved = $resolver->resolve($ref, $registry);

        $this->assertSame(0.3, $resolved->confidence);
    }

    public function testDirectCallResolverResolvesNewInstanceWithClassInRegistry(): void
    {
        $resolver = new DirectCallResolver();
        $registry = new SymbolRegistry();

        $class = new ClassSymbol(
            name: 'UserService',
            fqn: 'App\\Service\\UserService',
            file: '/test.php',
            lineStart: 1,
            lineEnd: 50
        );
        $registry->register($class);

        $ref = new CallReference(
            'A::m',
            'App\\Service\\UserService',
            CallType::NewInstance,
            '/test.php',
            10,
            0.5 // Lower initial confidence
        );

        $resolved = $resolver->resolve($ref, $registry);

        $this->assertSame(1.0, $resolved->confidence);
    }

    public function testDirectCallResolverResolvesExternalNewInstance(): void
    {
        $resolver = new DirectCallResolver();
        $registry = new SymbolRegistry();

        $ref = new CallReference(
            'A::m',
            'Vendor\\Package\\SomeClass',
            CallType::NewInstance,
            '/test.php',
            10,
            0.5
        );

        $resolved = $resolver->resolve($ref, $registry);

        $this->assertSame(0.8, $resolved->confidence);
        $this->assertTrue($resolved->context['external'] ?? false);
    }

    // ============================================
    // ConstructorResolver Tests
    // ============================================

    public function testConstructorResolverCanResolveNewInstance(): void
    {
        $resolver = new ConstructorResolver();
        $ref = new CallReference(
            'A::m',
            'SomeClass',
            CallType::NewInstance,
            '/test.php',
            10
        );

        $this->assertTrue($resolver->canResolve($ref));
    }

    public function testConstructorResolverCannotResolveMethodCall(): void
    {
        $resolver = new ConstructorResolver();
        $ref = new CallReference(
            'A::m',
            'B::m',
            CallType::MethodCall,
            '/test.php',
            10
        );

        $this->assertFalse($resolver->canResolve($ref));
    }

    public function testConstructorResolverExtractsDependencies(): void
    {
        $resolver = new ConstructorResolver();
        $registry = new SymbolRegistry();

        $constructor = new MethodSymbol(
            name: '__construct',
            parentFqn: 'App\\Service',
            file: '/test.php',
            lineStart: 10,
            lineEnd: 15,
            parameters: [
                ['name' => '$userRepo', 'type' => 'App\\Repository\\UserRepository'],
                ['name' => '$logger', 'type' => 'Psr\\Log\\LoggerInterface'],
                ['name' => '$debug', 'type' => 'bool'],
            ]
        );

        $class = new ClassSymbol(
            name: 'Service',
            fqn: 'App\\Service',
            file: '/test.php',
            lineStart: 1,
            lineEnd: 50,
            methods: [$constructor]
        );
        $registry->register($class);

        $ref = new CallReference(
            'A::m',
            'App\\Service',
            CallType::NewInstance,
            '/test.php',
            10
        );

        $resolved = $resolver->resolve($ref, $registry);

        $this->assertArrayHasKey('dependencies', $resolved->context);
        $this->assertCount(2, $resolved->context['dependencies']); // Only class types
    }

    // ============================================
    // InterfaceResolver Tests
    // ============================================

    public function testInterfaceResolverCanResolveMethodCall(): void
    {
        $resolver = new InterfaceResolver();
        $ref = new CallReference(
            'A::m',
            'B::m',
            CallType::MethodCall,
            '/test.php',
            10
        );

        $this->assertTrue($resolver->canResolve($ref));
    }

    public function testInterfaceResolverCannotResolveNewInstance(): void
    {
        $resolver = new InterfaceResolver();
        $ref = new CallReference(
            'A::m',
            'SomeClass',
            CallType::NewInstance,
            '/test.php',
            10
        );

        $this->assertFalse($resolver->canResolve($ref));
    }

    public function testInterfaceResolverFindsImplementations(): void
    {
        $resolver = new InterfaceResolver();
        $registry = new SymbolRegistry();

        // Register interface
        $interface = new InterfaceSymbol(
            name: 'UserRepositoryInterface',
            fqn: 'App\\Repository\\UserRepositoryInterface',
            file: '/test.php',
            lineStart: 1,
            lineEnd: 10
        );
        $registry->register($interface);

        // Register implementation
        $class = new ClassSymbol(
            name: 'EloquentUserRepository',
            fqn: 'App\\Repository\\EloquentUserRepository',
            file: '/test.php',
            lineStart: 15,
            lineEnd: 100,
            implements: ['App\\Repository\\UserRepositoryInterface']
        );
        $registry->register($class);

        $ref = new CallReference(
            'A::m',
            'App\\Repository\\UserRepositoryInterface::findById',
            CallType::MethodCall,
            '/test.php',
            10
        );

        $resolved = $resolver->resolve($ref, $registry);

        $this->assertSame(CallType::Interface, $resolved->callType);
        $this->assertTrue($resolved->context['is_interface'] ?? false);
        $this->assertContains(
            'App\\Repository\\EloquentUserRepository',
            $resolved->context['implementations']
        );
    }

    public function testInterfaceResolverBuildsImplementationMap(): void
    {
        $resolver = new InterfaceResolver();
        $registry = new SymbolRegistry();

        // Register interface
        $interface = new InterfaceSymbol(
            name: 'RepositoryInterface',
            fqn: 'App\\RepositoryInterface',
            file: '/test.php',
            lineStart: 1,
            lineEnd: 10
        );
        $registry->register($interface);

        // Register implementations
        $class1 = new ClassSymbol(
            name: 'UserRepo',
            fqn: 'App\\UserRepo',
            file: '/test.php',
            lineStart: 15,
            lineEnd: 50,
            implements: ['App\\RepositoryInterface']
        );
        $class2 = new ClassSymbol(
            name: 'PostRepo',
            fqn: 'App\\PostRepo',
            file: '/test.php',
            lineStart: 55,
            lineEnd: 100,
            implements: ['App\\RepositoryInterface']
        );
        $registry->register($class1);
        $registry->register($class2);

        $map = $resolver->buildImplementationMap($registry);

        $this->assertArrayHasKey('App\\RepositoryInterface', $map);
        $this->assertCount(2, $map['App\\RepositoryInterface']);
    }

    // ============================================
    // ClosureResolver Tests
    // ============================================

    public function testClosureResolverCanResolveClosure(): void
    {
        $resolver = new ClosureResolver();
        $ref = new CallReference(
            'A::m',
            '(closure)',
            CallType::Closure,
            '/test.php',
            10
        );

        $this->assertTrue($resolver->canResolve($ref));
    }

    public function testClosureResolverCannotResolveMethodCall(): void
    {
        $resolver = new ClosureResolver();
        $ref = new CallReference(
            'A::m',
            'B::m',
            CallType::MethodCall,
            '/test.php',
            10
        );

        $this->assertFalse($resolver->canResolve($ref));
    }

    public function testClosureResolverAnalyzesUsedVariables(): void
    {
        $resolver = new ClosureResolver();
        $registry = new SymbolRegistry();

        $ref = new CallReference(
            'A::m',
            '(closure)',
            CallType::Closure,
            '/test.php',
            10,
            0.7,
            ['uses' => ['$this', '$userService', '$config']]
        );

        $resolved = $resolver->resolve($ref, $registry);

        $this->assertArrayHasKey('analysis', $resolved->context);
        $this->assertTrue($resolved->context['analysis']['captures_this']);
        $this->assertTrue($resolved->context['analysis']['captures_service']);
    }

    public function testClosureResolverDetectsPureClosure(): void
    {
        $resolver = new ClosureResolver();
        $registry = new SymbolRegistry();

        $ref = new CallReference(
            'A::m',
            '(closure)',
            CallType::Closure,
            '/test.php',
            10,
            0.7,
            ['uses' => []]
        );

        $resolved = $resolver->resolve($ref, $registry);

        $this->assertTrue($resolved->context['analysis']['is_pure']);
    }

    // ============================================
    // FrameworkResolver Tests
    // ============================================

    public function testFrameworkResolverCanResolveStaticCall(): void
    {
        $resolver = new FrameworkResolver();
        $ref = new CallReference(
            'A::m',
            'Cache::get',
            CallType::StaticCall,
            '/test.php',
            10
        );

        $this->assertTrue($resolver->canResolve($ref));
    }

    public function testFrameworkResolverResolvesLaravelFacade(): void
    {
        $resolver = new FrameworkResolver('laravel');
        $registry = new SymbolRegistry();

        $ref = new CallReference(
            'A::m',
            'Cache::get',
            CallType::StaticCall,
            '/test.php',
            10
        );

        $resolved = $resolver->resolve($ref, $registry);

        $this->assertStringContainsString('Illuminate\\Cache', $resolved->calleeFqn);
        $this->assertSame('laravel_facade', $resolved->context['resolved_from']);
    }

    public function testFrameworkResolverResolvesLaravelHelper(): void
    {
        $resolver = new FrameworkResolver('laravel');
        $registry = new SymbolRegistry();

        $ref = new CallReference(
            'A::m',
            'cache',
            CallType::FunctionCall,
            '/test.php',
            10
        );

        $resolved = $resolver->resolve($ref, $registry);

        $this->assertSame('laravel_helper', $resolved->context['resolved_from']);
    }

    public function testFrameworkResolverGetsFacadeList(): void
    {
        $resolver = new FrameworkResolver();

        $facades = $resolver->getLaravelFacades();

        $this->assertArrayHasKey('Cache', $facades);
        $this->assertArrayHasKey('DB', $facades);
        $this->assertArrayHasKey('Auth', $facades);
    }

    public function testFrameworkResolverGetsHelperList(): void
    {
        $resolver = new FrameworkResolver();

        $helpers = $resolver->getLaravelHelpers();

        $this->assertArrayHasKey('app', $helpers);
        $this->assertArrayHasKey('cache', $helpers);
        $this->assertArrayHasKey('config', $helpers);
    }

    public function testFrameworkResolverSetFramework(): void
    {
        $resolver = new FrameworkResolver();

        $this->assertSame('auto', $resolver->getFramework());

        $resolver->setFramework('symfony');

        $this->assertSame('symfony', $resolver->getFramework());
    }

    // ============================================
    // Resolver Priority Tests
    // ============================================

    public function testDirectCallResolverHasHighestPriority(): void
    {
        $direct = new DirectCallResolver();
        $constructor = new ConstructorResolver();
        $interface = new InterfaceResolver();
        $closure = new ClosureResolver();
        $framework = new FrameworkResolver();

        $this->assertGreaterThan($constructor->getPriority(), $direct->getPriority());
        $this->assertGreaterThan($interface->getPriority(), $constructor->getPriority());
        $this->assertGreaterThan($closure->getPriority(), $interface->getPriority());
        $this->assertGreaterThan($framework->getPriority(), $closure->getPriority());
    }

    public function testResolverIds(): void
    {
        $this->assertSame('direct_call', (new DirectCallResolver())->getId());
        $this->assertSame('constructor', (new ConstructorResolver())->getId());
        $this->assertSame('interface', (new InterfaceResolver())->getId());
        $this->assertSame('closure', (new ClosureResolver())->getId());
        $this->assertSame('framework', (new FrameworkResolver())->getId());
    }

    public function testResolverNames(): void
    {
        $this->assertSame('Direct Call Resolver', (new DirectCallResolver())->getName());
        $this->assertSame('Constructor Resolver', (new ConstructorResolver())->getName());
        $this->assertSame('Interface Resolver', (new InterfaceResolver())->getName());
        $this->assertSame('Closure Resolver', (new ClosureResolver())->getName());
        $this->assertSame('Framework Resolver', (new FrameworkResolver())->getName());
    }
}

