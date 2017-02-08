<?php
namespace Globalis\PuppetSkilled\Tests\Queue;

use Mockery as m;
use ReflectionClass;
use stdClass;

class DatabaseQueueTest extends \PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    protected function getQueryBuilderMock()
    {
        $query = m::mock('StdClass');
        $query->shouldReceive('from')->andReturn(m::self());
        return $query;
    }

    public function testPushProperlyPushesJobOntoDatabase()
    {
        $query = $this->getQueryBuilderMock();
        $query->shouldReceive('insertGetId')->once()->andReturnUsing(function ($array) {
            $this->assertEquals('default', $array['queue']);
            $this->assertEquals(json_encode(['job' => 'foo', 'data' => ['data']]), $array['payload']);
            $this->assertEquals(0, $array['attempts']);
            $this->assertNull($array['reserved_at']);
            $this->assertInternalType('int', $array['available_at']);
        });

        $queue = $this->getMockBuilder('\Globalis\PuppetSkilled\Queue\DatabaseQueue')
            ->setMethods(['getTime', 'getQueryBuilder'])
            ->setConstructorArgs([m::mock('CI_DB_driver'), 'table', 'default'])
            ->getMock();
        $queue->expects($this->any())->method('getQueryBuilder')
            ->will($this->returnValue($query));
        $queue->expects($this->any())->method('getTime')->will($this->returnValue('time'));

        $queue->push('foo', ['data']);
    }

    public function testDelayedPushProperlyPushesJobOntoDatabase()
    {
        $query =$this->getQueryBuilderMock();
        $query->shouldReceive('insertGetId')->once()->andReturnUsing(function ($array) {
            $this->assertEquals('default', $array['queue']);
            $this->assertEquals(json_encode(['job' => 'foo', 'data' => ['data']]), $array['payload']);
            $this->assertEquals(0, $array['attempts']);
            $this->assertNull($array['reserved_at']);
            $this->assertInternalType('int', $array['available_at']);
        });

        $queue = $this->getMockBuilder('Globalis\PuppetSkilled\Queue\DatabaseQueue')
            ->setMethods(
                ['getTime', 'getQueryBuilder']
            )
            ->setConstructorArgs([m::mock('CI_DB_driver'), 'table', 'default'])
            ->getMock();

        $queue->expects($this->any())->method('getQueryBuilder')
            ->will($this->returnValue($query));
        $queue->expects($this->any())->method('getTime')->will($this->returnValue('time'));
        $queue->later(10, 'foo', ['data']);
    }

    public function testFailureToCreatePayloadFromObject()
    {
        $this->expectException('InvalidArgumentException');

        $job = new stdClass();
        $job->invalid = "\xc3\x28";

        $queue = $this->getMockForAbstractClass('Globalis\PuppetSkilled\Queue\Queue');
        $class = new ReflectionClass('Globalis\PuppetSkilled\Queue\Queue');

        $createPayload = $class->getMethod('createPayload');
        $createPayload->setAccessible(true);
        $createPayload->invokeArgs($queue, [
            $job,
        ]);
    }

    public function testFailureToCreatePayloadFromArray()
    {
        $this->expectException('InvalidArgumentException');

        $queue = $this->getMockForAbstractClass('Globalis\PuppetSkilled\Queue\Queue');
        $class = new ReflectionClass('Globalis\PuppetSkilled\Queue\Queue');

        $createPayload = $class->getMethod('createPayload');
        $createPayload->setAccessible(true);
        $createPayload->invokeArgs($queue, [
            ["\xc3\x28"],
        ]);
    }

    public function testBulkBatchPushesOntoDatabase()
    {
        $query =$this->getQueryBuilderMock();
        $query->shouldReceive('insert')->once()->andReturnUsing(function ($records) {
            $this->assertEquals([[
                'queue' => 'queue',
                'payload' => json_encode(['job' => 'foo', 'data' => ['data']]),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => 'available',
                'created_at' => 'created',
            ], [
                'queue' => 'queue',
                'payload' => json_encode(['job' => 'bar', 'data' => ['data']]),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => 'available',
                'created_at' => 'created',
            ]], $records);
        });

        $queue = $this->getMockBuilder('Globalis\PuppetSkilled\Queue\DatabaseQueue')
            ->setMethods(['getTime', 'getAvailableAt', 'getQueryBuilder'])
            ->setConstructorArgs([m::mock('CI_DB_driver'), 'table', 'default'])
            ->getMock();

        $queue->expects($this->any())->method('getQueryBuilder')
                ->will($this->returnValue($query));
        $queue->expects($this->any())->method('getTime')->will($this->returnValue('created'));
        $queue->expects($this->any())->method('getAvailableAt')->will($this->returnValue('available'));

        $queue->bulk(['foo', 'bar'], ['data'], 'queue');
    }
}
