<?php
namespace Globalis\PuppetSkilled\Queue;

use Globalis\PuppetSkilled\Queue\Job\Base as Job;

class CallQueuedHandler
{

    /**
     * Handle the queued job.
     *
     * @param  \Globalis\PuppetSkilled\Queue\Job\Base  $job
     * @param  array  $data
     * @return void
     */
    public function call(Job $job, array $data)
    {
        $command = unserialize($data['command']);

        $this->dispatchNow($command);

        if (! $job->isDeletedOrReleased()) {
            $job->delete();
        }
    }

    /**
     * Call the failed method on the job instance.
     *
     * The exception that caused the failure will be passed.
     *
     * @param  array  $data
     * @param  \Exception  $e
     * @return void
     */
    public function failed(array $data, $e)
    {
        $command = unserialize($data['command']);

        if (method_exists($command, 'failed')) {
            $command->failed($e);
        }
    }

    protected function dispatchNow($command)
    {
        return $command->handle();
    }
}
