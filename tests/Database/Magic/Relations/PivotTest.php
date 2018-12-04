<?php
namespace Globalis\PuppetSkilled\Tests\Database\Magic\Relations;

use Mockery as m;
use Globalis\PuppetSkilled\Database\Magic\Relations\Pivot;

class PivotTest extends \PHPUnit\Framework\TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testPropertiesAreSetCorrectly()
    {
        $parent = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model[getConnectionName]');
        $parent->shouldReceive('getConnectionName')->once()->andReturn('connection');
        $parent->setDateFormat('Y-m-d H:i:s');
        $pivot = new Pivot(['foo' => 'bar', 'created_at' => '2015-09-12'],$parent, 'table', true);

        $this->assertEquals(['foo' => 'bar', 'created_at' => '2015-09-12 00:00:00.000000'], $pivot->getAttributes());
        $this->assertEquals('connection', $pivot->getConnectionName());
        $this->assertEquals('table', $pivot->getTable());
        $this->assertTrue($pivot->exists);
    }

    public function testMutatorsAreCalledFromConstructor()
    {
        $parent = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model[getConnectionName]');
        $parent->shouldReceive('getConnectionName')->once()->andReturn('connection');

        $pivot = new PivotTestMutatorStub(['foo' => 'bar'], $parent, 'table', true);

        $this->assertTrue($pivot->getMutatorCalled());
    }

    public function testFromRawAttributesDoesNotDoubleMutate()
    {
        $parent = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model[getConnectionName]');
        $parent->shouldReceive('getConnectionName')->once()->andReturn('connection');

        $pivot = PivotTestJsonCastStub::fromRawAttributes($parent, ['foo' => json_encode(['name' => 'Taylor'])], 'table', true);

        $this->assertEquals(['name' => 'Taylor'], $pivot->foo);
    }

    public function testPropertiesUnchangedAreNotDirty()
    {
        $parent = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model[getConnectionName]');
        $parent->shouldReceive('getConnectionName')->once()->andReturn('connection');
        $pivot = new Pivot(['foo' => 'bar', 'shimy' => 'shake'], $parent, 'table', true);

        $this->assertEquals([], $pivot->getDirty());
    }

    public function testPropertiesChangedAreDirty()
    {
        $parent = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model[getConnectionName]');
        $parent->shouldReceive('getConnectionName')->once()->andReturn('connection');
        $pivot = new Pivot(['foo' => 'bar', 'shimy' => 'shake'], $parent, 'table', true);
        $pivot->shimy = 'changed';

        $this->assertEquals(['shimy' => 'changed'], $pivot->getDirty());
    }

    public function testTimestampPropertyIsSetIfCreatedAtInAttributes()
    {
        $parent = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model[getConnectionName,getDates]');
        $parent->shouldReceive('getConnectionName')->andReturn('connection');
        $parent->shouldReceive('getDates')->andReturn([]);
        $pivot = new PivotTestDateStub(['foo' => 'bar', 'created_at' => 'foo'], $parent, 'table');
        $this->assertTrue($pivot->timestamps);

        $pivot = new PivotTestDateStub(['foo' => 'bar'], $parent, 'table');
        $this->assertFalse($pivot->timestamps);
    }

    public function testKeysCanBeSetProperly()
    {
        $parent = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model[getConnectionName]');
        $parent->shouldReceive('getConnectionName')->once()->andReturn('connection');
        $pivot = new Pivot(['foo' => 'bar'], $parent, 'table');
        $pivot->setPivotKeys('foreign', 'other');

        $this->assertEquals('foreign', $pivot->getForeignKey());
        $this->assertEquals('other', $pivot->getOtherKey());
    }

    public function testDeleteMethodDeletesModelByKeys()
    {
        $parent = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model[getConnectionName]');
        $parent->guard([]);
        $parent->shouldReceive('getConnectionName')->once()->andReturn('connection');
        $pivot = $this->getMockBuilder('\Globalis\PuppetSkilled\Database\Magic\Relations\Pivot')->setMethods(['newQuery'])->setConstructorArgs([['foo' => 'bar'], $parent, 'table'])->getMock();
        $pivot->setPivotKeys('foreign', 'other');
        $pivot->foreign = 'foreign.value';
        $pivot->other = 'other.value';
        $query = m::mock('stdClass');
        $query->shouldReceive('where')->once()->with(['foreign' => 'foreign.value', 'other' => 'other.value'])->andReturn($query);
        $query->shouldReceive('delete')->once()->andReturn(true);
        $pivot->expects($this->once())->method('newQuery')->will($this->returnValue($query));

        $this->assertTrue($pivot->delete());
    }
}

class PivotTestDateStub extends \Globalis\PuppetSkilled\Database\Magic\Relations\Pivot
{
    public function getDates()
    {
        return [];
    }
}

class PivotTestMutatorStub extends \Globalis\PuppetSkilled\Database\Magic\Relations\Pivot
{
    private $mutatorCalled = false;

    public function setFooAttribute($value)
    {
        $this->mutatorCalled = true;

        return $value;
    }

    public function getMutatorCalled()
    {
        return $this->mutatorCalled;
    }
}

class PivotTestJsonCastStub extends \Globalis\PuppetSkilled\Database\Magic\Relations\Pivot
{
    protected $casts = [
        'foo' => 'json',
    ];
}
