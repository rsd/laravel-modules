<?php

namespace Nwidart\Modules\Tests\Traits;

use Nwidart\Modules\Traits\ConfigMergerTrait;

class UseConfigMergerTrait
{
    use ConfigMergerTrait;

    public $app;

    public function callMergeConfigDefaultsFrom($path, $key, $existingPrecedence = true, $deep = true)
    {
        return $this->mergeConfigDefaultsFrom($path, $key, $existingPrecedence, $deep);
    }
}
