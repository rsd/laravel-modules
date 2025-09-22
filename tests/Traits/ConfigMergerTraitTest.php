<?php

namespace Nwidart\Modules\Tests\Traits;

use Illuminate\Contracts\Foundation\CachesConfiguration;
use Mockery;
use Nwidart\Modules\Tests\BaseTestCase;

class ConfigMergerTraitTest extends BaseTestCase
{
    protected $configMerger;
    protected $tempConfigPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configMerger = new UseConfigMergerTrait();
        $this->configMerger->app = $this->app;
        $this->tempConfigPath = sys_get_temp_dir() . '/test_config_' . uniqid() . '.php';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
        
        // Clean up temporary config file if it exists
        if (file_exists($this->tempConfigPath)) {
            unlink($this->tempConfigPath);
        }
    }

    /**
     * Test that the trait method exists and is callable
     */
    public function test_merge_config_defaults_from_method_exists()
    {
        $this->assertTrue(method_exists($this->configMerger, 'callMergeConfigDefaultsFrom'));
    }

    /**
     * Create a temporary config file with the given array data
     */
    private function createTempConfigFile(array $config): string
    {
        $configContent = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($this->tempConfigPath, $configContent);
        return $this->tempConfigPath;
    }

    /**
     * Get database config array for testing (simulates modules/Blog/config/database.php)
     */
    private function getDatabaseConfigArray(): array
    {
        return [
            'default' => 'sqlite',
            'connections' => [
                'mysql' => [
                    'driver' => 'mysql',
                    'host' => 'module.db.host',
                    'port' => 3307,
                    'username' => 'module_user',
                    'password' => 'module_secret',
                    'database' => 'module_database',
                ],
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => '/storage/app/module.sqlite',
                ],
                'redis' => [
                    'host' => '127.0.0.1',
                    'port' => 6379,
                ],
            ],
            'migrations' => [
                'table' => 'module_migrations',
            ],
        ];
    }

    /**
     * Get cache config array for testing (simulates modules/Blog/config/cache.php)
     */
    private function getCacheConfigArray(): array
    {
        return [
            'default' => 'redis',
            'stores' => [
                'file' => [
                    'driver' => 'file',
                    'path' => '/storage/framework/cache/module',
                ],
                'redis' => [
                    'driver' => 'redis',
                    'connection' => 'cache',
                ],
                'module_cache' => [
                    'driver' => 'array',
                ],
            ],
            'prefix' => 'module_cache',
        ];
    }

    /**
     * Get filesystems config array for testing (simulates modules/Blog/config/filesystems.php)
     */
    private function getFilesystemsConfigArray(): array
    {
        return [
            'default' => 'module_disk',
            'disks' => [
                'local' => [
                    'driver' => 'local',
                    'root' => '/storage/app/module',
                ],
                'module_disk' => [
                    'driver' => 's3',
                    'bucket' => 'module-bucket',
                    'region' => 'us-east-1',
                ],
            ],
        ];
    }

    /**
     * Get empty config array for testing
     */
    private function getEmptyConfigArray(): array
    {
        return [];
    }

    public function test_deep_merging_database_config_with_existing_precedence_default()
    {
        // Arrange: Set existing Laravel config/database.php
        config(['database' => [
            'default' => 'mysql',
            'connections' => [
                'mysql' => [
                    'driver' => 'mysql',
                    'host' => 'production.example.com',
                    'port' => 3306,
                    'username' => 'root',
                    'password' => 'production_secret',
                    'timeout' => 30,
                ],
            ],
            'migrations' => [
                'table' => 'migrations',
            ],
        ]]);

        // Act: Merge module's config/database.php (deep=true, existingPrecedence=true by default)
        $configPath = $this->createTempConfigFile($this->getDatabaseConfigArray());
        $this->configMerger->callMergeConfigDefaultsFrom($configPath, 'database');

        // Assert: Existing config should take precedence in nested arrays
        $result = config('database');

        // Default connection: existing should win
        $this->assertEquals('mysql', $result['default']); // existing wins over 'sqlite'

        // MySQL connection: existing values should override module ones
        $this->assertEquals('production.example.com', $result['connections']['mysql']['host']); // existing wins
        $this->assertEquals(3306, $result['connections']['mysql']['port']); // existing wins  
        $this->assertEquals('root', $result['connections']['mysql']['username']); // existing wins
        $this->assertEquals('production_secret', $result['connections']['mysql']['password']); // existing wins
        $this->assertEquals(30, $result['connections']['mysql']['timeout']); // existing only
        $this->assertEquals('module_database', $result['connections']['mysql']['database']); // module adds

        // New connections should be added from module config
        $this->assertArrayHasKey('sqlite', $result['connections']); // module adds
        $this->assertEquals('sqlite', $result['connections']['sqlite']['driver']);
        $this->assertArrayHasKey('redis', $result['connections']); // module adds
        $this->assertEquals('127.0.0.1', $result['connections']['redis']['host']);

        // Migrations: existing should take precedence
        $this->assertEquals('migrations', $result['migrations']['table']); // existing wins over 'module_migrations'
    }

    public function test_deep_merging_database_config_without_existing_precedence()
    {
        // Arrange: Set existing Laravel config/database.php
        config(['database' => [
            'default' => 'mysql',
            'connections' => [
                'mysql' => [
                    'driver' => 'mysql',
                    'host' => 'production.example.com',
                    'port' => 3306,
                    'username' => 'root',
                    'password' => 'production_secret',
                    'timeout' => 30,
                ],
            ],
            'migrations' => [
                'table' => 'migrations',
            ],
        ]]);

        // Act: Merge module config with module precedence (deep=true, existingPrecedence=false)
        $configPath = $this->createTempConfigFile($this->getDatabaseConfigArray());
        $this->configMerger->callMergeConfigDefaultsFrom(
            $configPath,
            'database',
            false,  // existingPrecedence = false (module wins)
            true    // deep = true
        );

        // Assert: Module config should take precedence over existing
        $result = config('database');

        // Default connection: module should win
        $this->assertEquals('sqlite', $result['default']); // module wins

        // MySQL connection: module values should override existing ones  
        $this->assertEquals('module.db.host', $result['connections']['mysql']['host']); // module wins
        $this->assertEquals(3307, $result['connections']['mysql']['port']); // module wins
        $this->assertEquals('module_user', $result['connections']['mysql']['username']); // module wins
        $this->assertEquals('module_secret', $result['connections']['mysql']['password']); // module wins
        $this->assertEquals(30, $result['connections']['mysql']['timeout']); // existing preserved
        $this->assertEquals('module_database', $result['connections']['mysql']['database']); // module adds

        // Migrations: module should take precedence
        $this->assertEquals('module_migrations', $result['migrations']['table']); // module wins
    }

    public function test_shallow_merging_cache_config_with_existing_precedence()
    {
        // Arrange: Set existing Laravel config/cache.php
        config(['cache' => [
            'default' => 'file',
            'stores' => [
                'file' => [
                    'driver' => 'file',
                    'path' => '/storage/framework/cache/data',
                ],
                'redis' => [
                    'driver' => 'redis',
                    'connection' => 'default',
                ],
            ],
            'prefix' => 'laravel_cache',
        ]]);

        // Act: Merge module config with shallow merging (shallow=true, existingPrecedence=true)
        $configPath = $this->createTempConfigFile($this->getCacheConfigArray());
        $this->configMerger->callMergeConfigDefaultsFrom(
            $configPath,
            'cache',
            true,   // existingPrecedence = true (existing wins)
            false   // deep = false (shallow merging)
        );

        // Assert: With shallow merging, existing top-level keys should be preserved completely
        $result = config('cache');

        $this->assertEquals('file', $result['default']); // existing wins over 'redis'
        $this->assertEquals('laravel_cache', $result['prefix']); // existing wins over 'module_cache'

        // For arrays in shallow merge, existing should win completely (no deep merging)
        $this->assertArrayHasKey('file', $result['stores']);
        $this->assertArrayHasKey('redis', $result['stores']);
        $this->assertArrayNotHasKey('module_cache', $result['stores']); // module store not added
        $this->assertEquals('file', $result['stores']['file']['driver']); // existing structure preserved
        $this->assertEquals('/storage/framework/cache/data', $result['stores']['file']['path']); // existing path preserved
    }

    public function test_shallow_merging_cache_config_without_existing_precedence()
    {
        // Arrange: Set existing Laravel config/cache.php
        config(['cache' => [
            'default' => 'file',
            'stores' => [
                'file' => [
                    'driver' => 'file',
                    'path' => '/storage/framework/cache/data',
                ],
                'redis' => [
                    'driver' => 'redis',
                    'connection' => 'default',
                ],
            ],
            'prefix' => 'laravel_cache',
        ]]);

        // Act: Merge module config with shallow merging (shallow=true, existingPrecedence=false)
        $configPath = $this->createTempConfigFile($this->getCacheConfigArray());
        $this->configMerger->callMergeConfigDefaultsFrom(
            $configPath,
            'cache',
            false,  // existingPrecedence = false (module wins)
            false   // deep = false (shallow merging)
        );

        // Assert: With shallow merging and module precedence, module values should win
        $result = config('cache');

        $this->assertEquals('redis', $result['default']); // module wins
        $this->assertEquals('module_cache', $result['prefix']); // module wins

        // For arrays in shallow merge, module should win completely
        $this->assertArrayHasKey('file', $result['stores']);
        $this->assertArrayHasKey('redis', $result['stores']);
        $this->assertArrayHasKey('module_cache', $result['stores']); // module store added
        $this->assertEquals('/storage/framework/cache/module', $result['stores']['file']['path']); // module path
        $this->assertEquals('cache', $result['stores']['redis']['connection']); // module connection
        $this->assertEquals('array', $result['stores']['module_cache']['driver']); // module-only store
    }

    public function test_merging_filesystems_config_with_empty_existing_config()
    {
        // Arrange: Start with empty filesystems config
        config(['filesystems' => []]);

        // Act: Merge module's filesystems config
        $configPath = $this->createTempConfigFile($this->getFilesystemsConfigArray());
        $this->configMerger->callMergeConfigDefaultsFrom($configPath, 'filesystems');

        // Assert: Should get all values from module config
        $result = config('filesystems');

        $this->assertEquals('module_disk', $result['default']);
        $this->assertEquals('local', $result['disks']['local']['driver']);
        $this->assertEquals('/storage/app/module', $result['disks']['local']['root']);
        $this->assertEquals('s3', $result['disks']['module_disk']['driver']);
        $this->assertEquals('module-bucket', $result['disks']['module_disk']['bucket']);
    }

    public function test_merging_database_config_with_empty_module_config()
    {
        // Arrange: Set existing Laravel database config
        config(['database' => [
            'default' => 'mysql',
            'connections' => [
                'mysql' => [
                    'driver' => 'mysql',
                    'host' => 'localhost',
                    'username' => 'root',
                ],
            ],
        ]]);

        // Act: Merge with empty module config file
        $configPath = $this->createTempConfigFile($this->getEmptyConfigArray());
        $this->configMerger->callMergeConfigDefaultsFrom($configPath, 'database');

        // Assert: Existing config should be preserved
        $result = config('database');

        $this->assertEquals('mysql', $result['default']);
        $this->assertEquals('localhost', $result['connections']['mysql']['host']);
        $this->assertEquals('root', $result['connections']['mysql']['username']);
    }

    public function test_cached_configuration_is_not_modified()
    {
        // Arrange: Mock the application to simulate cached configuration
        $mockApp = Mockery::mock(CachesConfiguration::class);
        $mockApp->shouldReceive('configurationIsCached')->once()->andReturn(true);
        $mockApp->shouldNotReceive('make'); // Should not try to get config service

        $configMerger = new UseConfigMergerTrait();
        $configMerger->app = $mockApp;

        // Set existing config
        config(['test_cached' => ['existing' => 'value']]);

        // Act: Attempt to merge config defaults
        $configPath = $this->createTempConfigFile($this->getDatabaseConfigArray());
        $configMerger->callMergeConfigDefaultsFrom($configPath, 'test_cached');

        // Assert: Config should remain unchanged when cached
        $result = config('test_cached');
        $this->assertEquals(['existing' => 'value'], $result);
    }

    public function test_non_cached_configuration_is_modified()
    {
        // Arrange: Mock the application to simulate non-cached configuration
        $mockApp = Mockery::mock(CachesConfiguration::class);
        $mockApp->shouldReceive('configurationIsCached')->once()->andReturn(false);
        $mockApp->shouldReceive('make')->with('config')->once()->andReturn($this->app['config']);

        $configMerger = new UseConfigMergerTrait();
        $configMerger->app = $mockApp;

        // Set existing config
        config(['test_non_cached' => ['existing' => 'value']]);

        // Act: Merge config defaults
        $configPath = $this->createTempConfigFile($this->getDatabaseConfigArray());
        $configMerger->callMergeConfigDefaultsFrom($configPath, 'test_non_cached');

        // Assert: Config should be modified when not cached
        $result = config('test_non_cached');
        $this->assertArrayHasKey('connections', $result); // New config should be merged
        $this->assertEquals('value', $result['existing']); // Existing should be preserved
    }

    public function test_app_without_cache_configuration_interface()
    {
        // Arrange: Mock app that doesn't implement CachesConfiguration
        $mockApp = Mockery::mock();
        $mockApp->shouldReceive('make')->with('config')->once()->andReturn($this->app['config']);

        $configMerger = new UseConfigMergerTrait();
        $configMerger->app = $mockApp;

        // Set existing config
        config(['test_no_cache_interface' => ['existing' => 'value']]);

        // Act: Merge config defaults
        $configPath = $this->createTempConfigFile($this->getDatabaseConfigArray());
        $configMerger->callMergeConfigDefaultsFrom($configPath, 'test_no_cache_interface');

        // Assert: Config should be modified (cache check is bypassed)
        $result = config('test_no_cache_interface');
        $this->assertArrayHasKey('connections', $result); // New config should be merged
        $this->assertEquals('value', $result['existing']); // Existing should be preserved
    }

    public function test_merging_complex_mail_config_with_nested_arrays()
    {
        // Arrange: Complex existing Laravel config/mail.php
        config(['mail' => [
            'default' => 'smtp',
            'mailers' => [
                'smtp' => [
                    'transport' => 'smtp',
                    'host' => 'production.smtp.com',
                    'port' => 587,
                    'encryption' => 'tls',
                    'username' => 'prod@example.com',
                    'timeout' => 60,
                ],
                'mailgun' => [
                    'transport' => 'mailgun',
                    'domain' => 'production.mailgun.org',
                ],
            ],
            'from' => [
                'address' => 'noreply@production.com',
                'name' => 'Production App',
            ],
        ]]);

        // Module's mail config with overlapping structure
        $moduleMailConfig = [
            'default' => 'ses',  // Module wants different default
            'mailers' => [
                'smtp' => [
                    'transport' => 'smtp',
                    'host' => 'module.smtp.com',      // Different host
                    'port' => 465,                    // Different port
                    'encryption' => 'ssl',            // Different encryption
                    'username' => 'module@example.com', // Different username
                    'password' => 'module-password',  // New field
                ],
                'ses' => [                           // New mailer
                    'transport' => 'ses',
                    'key' => 'module-key',
                    'secret' => 'module-secret',
                    'region' => 'us-west-2',
                ],
            ],
            'from' => [
                'address' => 'noreply@module.com',   // Different from address
                'name' => 'Module App',              // Different from name
            ],
        ];

        // Act: Deep merge with existing precedence (default behavior)
        $configPath = $this->createTempConfigFile($moduleMailConfig);
        $this->configMerger->callMergeConfigDefaultsFrom($configPath, 'mail');

        // Assert: Complex nested merging behavior
        $result = config('mail');

        // Default mailer: existing should win
        $this->assertEquals('smtp', $result['default']); // existing wins over 'ses'

        // SMTP mailer: existing values should take precedence
        $this->assertEquals('smtp', $result['mailers']['smtp']['transport']); // same in both
        $this->assertEquals('production.smtp.com', $result['mailers']['smtp']['host']); // existing wins
        $this->assertEquals(587, $result['mailers']['smtp']['port']); // existing wins
        $this->assertEquals('tls', $result['mailers']['smtp']['encryption']); // existing wins
        $this->assertEquals('prod@example.com', $result['mailers']['smtp']['username']); // existing wins
        $this->assertEquals(60, $result['mailers']['smtp']['timeout']); // existing only
        $this->assertEquals('module-password', $result['mailers']['smtp']['password']); // module adds

        // Existing mailgun mailer should be preserved
        $this->assertEquals('mailgun', $result['mailers']['mailgun']['transport']);
        $this->assertEquals('production.mailgun.org', $result['mailers']['mailgun']['domain']);

        // New SES mailer should be added from module
        $this->assertArrayHasKey('ses', $result['mailers']);
        $this->assertEquals('ses', $result['mailers']['ses']['transport']);
        $this->assertEquals('module-key', $result['mailers']['ses']['key']);
        $this->assertEquals('us-west-2', $result['mailers']['ses']['region']);

        // From config: existing should take precedence
        $this->assertEquals('noreply@production.com', $result['from']['address']); // existing wins
        $this->assertEquals('Production App', $result['from']['name']); // existing wins
    }
}
