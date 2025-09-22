<?php

namespace Nwidart\Modules\Traits;

use Illuminate\Contracts\Foundation\CachesConfiguration;

trait ConfigMergerTrait
{
    /**
     * Merge configuration defaults from a file, allowing existing config to take precedence.
     *
     * @param  string  $path
     * @param  string  $key
     * @param  bool  $existingPrecedence  Whether existing config takes precedence over module config
     * @param  bool  $deep  Whether to use deep merging (array_replace_recursive) or shallow merging (array_merge)
     * @return void
     */
    protected function mergeConfigDefaultsFrom($path, $key, $existingPrecedence = true, $deep = true)
    {
        if (! ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached())) {
            $config = $this->app->make('config');
            $existing = $config->get($key, []);
            $new = require $path;

            if ($deep) {
                if ($existingPrecedence) {
                    $config->set($key, array_replace_recursive($new, $existing));
                } else {
                    $config->set($key, array_replace_recursive($existing, $new));
                }
            } else {
                if ($existingPrecedence) {
                    $config->set($key, array_merge($new, $existing));
                } else {
                    $config->set($key, array_merge($existing, $new));
                }
            }
        }
    }
}