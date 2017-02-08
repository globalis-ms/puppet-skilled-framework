<?php
namespace Globalis\PuppetSkilled\Queue;

abstract class Queueable
{
    /**
     * The number of seconds before the job should be made available.
     *
     * @var \DateTime|int|null
     */
    public $delay;

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public $queue;

    /**
     * Set the desired queue for the job.
     *
     * @param  string|null  $queue
     * @return $this
     */
    public function onQueue($queue)
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Set the desired delay for the job.
     *
     * @param  \DateTime|int|null  $delay
     * @return $this
     */
    public function delay($delay)
    {
        $this->delay = $delay;
        return $this;
    }
}
