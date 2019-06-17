<?php

namespace Aha\Commands;

use Aha\Snippets\Log;
use Illuminate\Console\Command;

class JenkinsCommand extends Command
{
    protected $signature = 'jenkins';
    public $description = '用于jenkins后脚本';

    public function handle()
    {
        app()->configure('jenkins');
        $jenkins = config('jenkins', []);

        foreach ($jenkins as $raw) {
            try {
                $this->callSilent($raw['command'], $raw['arguments']);
            } catch (\Exception $e) {
                Log::addError('Jenkins构建后执行脚本[' . $raw['command'] . ']异常, 错误: ' . $e->getMessage(), $raw);
            }
        }
    }
}