<?php
namespace Globalis\PuppetSkilled\Tests\Database\Magic\Relations;

use Mockery as m;
use Globalis\PuppetSkilled\Database\Magic\Relations\MorphToMany;

class MorphToManyTest extends \PHPUnit\Framework\TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testEagerConstraintsAreProperlyAdded()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->shouldReceive('whereIn')->once()->with('taggables.taggable_id', [1, 2]);
        $relation->getQuery()->shouldReceive('where')->once()->with('taggables.taggable_type', get_class($relation->getParent()));
        $model1 = new MorphToManyModelStub;
        $model1->id = 1;
        $model2 = new MorphToManyModelStub;
        $model2->id = 2;
        $relation->addEagerConstraints([$model1, $model2]);
    }

    public function testAttachInsertsPivotTableRecord()
    {
        $relation = $this->getMockBuilder('Globalis\PuppetSkilled\Database\Magic\Relations\MorphToMany')->setMethods(['touchIfTouching'])->setConstructorArgs($this->getRelationArguments())->getMock();
        $query = m::mock('stdClass');
        $query->shouldReceive('from')->once()->with('taggables')->andReturn($query);
        $query->shouldReceive('insert')->once()->with([['taggable_id' => 1, 'taggable_type' => get_class($relation->getParent()), 'tag_id' => 2, 'foo' => 'bar']])->andReturn(true);
        $relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
        $mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
        $relation->expects($this->once())->method('touchIfTouching');

        $relation->attach(2, ['foo' => 'bar']);
    }

    public function testDetachRemovesPivotTableRecord()
    {
        $relation = $this->getMockBuilder('Globalis\PuppetSkilled\Database\Magic\Relations\MorphToMany')->setMethods(['touchIfTouching'])->setConstructorArgs($this->getRelationArguments())->getMock();
        $query = m::mock('stdClass');
        $query->shouldReceive('from')->once()->with('taggables')->andReturn($query);
        $query->shouldReceive('where')->once()->with('taggable_id', 1)->andReturn($query);
        $query->shouldReceive('where')->once()->with('taggable_type', get_class($relation->getParent()))->andReturn($query);
        $query->shouldReceive('whereIn')->once()->with('tag_id', [1, 2, 3]);
        $query->shouldReceive('delete')->once()->andReturn(true);
        $relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
        $mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
        $relation->expects($this->once())->method('touchIfTouching');

        $this->assertTrue($relation->detach([1, 2, 3]));
    }

    public function testDetachMethodClearsAllPivotRecordsWhenNoIDsAreGiven()
    {
        $relation = $this->getMockBuilder('Globalis\PuppetSkilled\Database\Magic\Relations\MorphToMany')->setMethods(['touchIfTouching'])->setConstructorArgs($this->getRelationArguments())->getMock();
        $query = m::mock('stdClass');
        $query->shouldReceive('from')->once()->with('taggables')->andReturn($query);
        $query->shouldReceive('where')->once()->with('taggable_id', 1)->andReturn($query);
        $query->shouldReceive('where')->once()->with('taggable_type', get_class($relation->getParent()))->andReturn($query);
        $query->shouldReceive('whereIn')->never();
        $query->shouldReceive('delete')->once()->andReturn(true);
        $relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
        $mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
        $relation->expects($this->once())->method('touchIfTouching');

        $this->assertTrue($relation->detach());
    }

    public function getRelation()
    {
        list($builder, $parent) = $this->getRelationArguments();

        return new MorphToMany($builder, $parent, 'taggable', 'taggables', 'taggable_id', 'tag_id');
    }

    public function getRelationArguments()
    {
        $parent = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $parent->shouldReceive('getConnectionName')->andReturn('foo.connection');
        $parent->shouldReceive('getMorphClass')->andReturn(get_class($parent));
        $parent->shouldReceive('getKey')->andReturn(1);
        $parent->shouldReceive('getCreatedAtColumn')->andReturn('created_at');
        $parent->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');
        $parent->shouldReceive('getMorphClass')->andReturn(get_class($parent));

        $builder = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $related = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $builder->shouldReceive('getModel')->andReturn($related);

        $related->shouldReceive('getTable')->andReturn('tags');
        $related->shouldReceive('getKeyName')->andReturn('id');
        $related->shouldReceive('getMorphClass')->andReturn(get_class($related));

        $builder->shouldReceive('join')->once()->with('taggables', 'tags.id', '=', 'taggables.tag_id');
        $builder->shouldReceive('where')->once()->with('taggables.taggable_id', '=', 1);
        $builder->shouldReceive('where')->once()->with('taggables.taggable_type', get_class($parent));

        return [$builder, $parent, 'taggable', 'taggables', 'taggable_id', 'tag_id', 'relation_name', false];
    }
}

class MorphToManyModelStub extends \Globalis\PuppetSkilled\Database\Magic\Model
{
    protected $guarded = [];
}
